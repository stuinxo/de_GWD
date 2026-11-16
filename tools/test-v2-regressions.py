#!/usr/bin/env python3
"""Isolated local regression suite for stuinxo-de_GWD (v2)."""

from __future__ import annotations

import concurrent.futures
import hashlib
import http.cookies
import json
import os
import py_compile
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import urllib.parse
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CLIENT_SCRIPT = ROOT / "client"
SERVER_SCRIPT = ROOT / "server"
KERNEL_DIR = ROOT / "resource" / "kernel"
UISCRIPT_DIR = ROOT / "resource" / "client" / "ui-script"
UIWEB_DIR = ROOT / "resource" / "client" / "ui-web"

PASSED = 0
FAILED = 0


def record(ok: bool, msg: str) -> None:
    global PASSED, FAILED
    if ok:
        print(f"[PASS] {msg}")
        PASSED += 1
    else:
        print(f"[FAIL] {msg}", file=sys.stderr)
        FAILED += 1


def run_cmd(
    args: list[str],
    *,
    env: dict[str, str] | None = None,
    input_bytes: bytes | None = None,
    cwd: Path | None = None,
    timeout: int = 30,
) -> subprocess.CompletedProcess[bytes]:
    return subprocess.run(
        args,
        input=input_bytes,
        env=env,
        cwd=str(cwd) if cwd else None,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        timeout=timeout,
    )


def test_syntax() -> None:
    # PHP syntax
    php_files = sorted(UIWEB_DIR.rglob("*.php"))
    php_errors: list[str] = []
    for pf in php_files:
        res = run_cmd(["php", "-l", str(pf)])
        if res.returncode != 0:
            php_errors.append(f"{pf}: {res.stderr.decode(errors='replace').strip()}")
    record(not php_errors and len(php_files) > 0, f"PHP syntax check ({len(php_files)} files)")
    for err in php_errors:
        print(f"  {err}", file=sys.stderr)

    # Core shell scripts
    shell_targets = [
        CLIENT_SCRIPT,
        SERVER_SCRIPT,
        KERNEL_DIR / "installkernel",
        KERNEL_DIR / "zabbly",
    ]
    ui_scripts = sorted(p for p in UISCRIPT_DIR.iterdir() if p.is_file() and not p.name.startswith("."))

    sh_errors: list[str] = []
    py_errors: list[str] = []
    sh_count = 0
    py_count = 0

    with tempfile.TemporaryDirectory() as tmpdir:
        for path in shell_targets + ui_scripts:
            first_line = path.read_text(encoding="utf-8", errors="replace").splitlines()[0] if path.stat().st_size else ""
            if "python" in first_line:
                py_count += 1
                try:
                    py_compile.compile(
                        str(path),
                        cfile=str(Path(tmpdir) / f"{path.name}.pyc"),
                        doraise=True,
                    )
                except py_compile.PyCompileError as exc:
                    py_errors.append(f"{path.name}: {exc}")
            else:
                sh_count += 1
                res = run_cmd(["bash", "-n", str(path)])
                if res.returncode != 0:
                    sh_errors.append(f"{path}: {res.stderr.decode(errors='replace').strip()}")

    record(not sh_errors and sh_count > 0, f"Bash syntax check ({sh_count} files)")
    for err in sh_errors:
        print(f"  {err}", file=sys.stderr)
    record(not py_errors and py_count > 0, f"Python syntax check ({py_count} ui-script files)")
    for err in py_errors:
        print(f"  {err}", file=sys.stderr)


def test_concurrent_config_locking() -> None:
    auth_php = UIWEB_DIR / "auth.php"
    conf_common = UISCRIPT_DIR / "ui-confCommon"

    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)
        conf_path = tmp / "0conf"
        conf_path.write_text(json.dumps({"address": {"PWD": "x"}, "race": {}}), encoding="utf-8")

        php_runner = tmp / "php_mutate.php"
        php_runner.write_text(
            f"""<?php
require_once({json.dumps(str(auth_php))});
$key = $argv[1];
$val = $argv[2];
$ok = gwd_update_conf(function (array $conf) use ($key, $val): array {{
    if (!isset($conf['race']) || !is_array($conf['race'])) {{
        $conf['race'] = [];
    }}
    usleep(10000);
    $conf['race'][$key] = $val;
    return $conf;
}});
exit($ok ? 0 : 1);
""",
            encoding="utf-8",
        )

        bash_runner = tmp / "bash_mutate.sh"
        bash_runner.write_text(
            f"""#!/usr/bin/env bash
set -euo pipefail
source {json.dumps(str(conf_common))}
key="$1"
val="$2"
conf_jq --arg k "$key" --arg v "$val" '.race[$k] = $v' >/dev/null
""",
            encoding="utf-8",
        )
        bash_runner.chmod(0o755)

        env = os.environ.copy()
        env["DEGWD_CONF_PATH"] = str(conf_path)

        tasks: list[tuple[list[str], str, str]] = []
        for i in range(12):
            k = f"php_{i}"
            tasks.append((["php", str(php_runner), k, f"pv_{i}"], k, f"pv_{i}"))
        for i in range(12):
            k = f"sh_{i}"
            tasks.append(([str(bash_runner), k, f"sv_{i}"], k, f"sv_{i}"))

        failures: list[str] = []
        with concurrent.futures.ThreadPoolExecutor(max_workers=12) as pool:
            fut_map = {pool.submit(run_cmd, cmd, env=env): (k, v) for cmd, k, v in tasks}
            for fut in concurrent.futures.as_completed(fut_map):
                k, v = fut_map[fut]
                res = fut.result()
                if res.returncode != 0:
                    failures.append(f"{k}: rc={res.returncode} stderr={res.stderr.decode(errors='replace').strip()}")

        record(not failures, f"Concurrent PHP/Bash config updates completed without lock failure ({len(tasks)} workers)")
        for f in failures:
            print(f"  {f}", file=sys.stderr)

        try:
            data = json.loads(conf_path.read_text(encoding="utf-8"))
            race = data.get("race", {})
            missing = [k for _, k, v in tasks if race.get(k) != v]
            record(
                not missing and data.get("address", {}).get("PWD") == "x",
                f"Concurrent PHP gwd_update_conf + Bash conf_jq preserved all {len(tasks)} updates",
            )
            if missing:
                print(f"  Missing/overwritten keys: {missing}", file=sys.stderr)
        except Exception as exc:
            record(False, f"Concurrent config file remained valid JSON: {exc}")


def parse_cgi_response(raw: bytes) -> tuple[int, dict[str, str], list[tuple[str, str]], bytes]:
    sep = b"\r\n\r\n" if b"\r\n\r\n" in raw else b"\n\n"
    if sep not in raw:
        return 500, {}, [], raw
    hdr_bytes, body = raw.split(sep, 1)
    status = 200
    headers: dict[str, str] = {}
    set_cookies: list[tuple[str, str]] = []
    for line in hdr_bytes.decode("latin1").splitlines():
        if ":" not in line:
            continue
        name, val = line.split(":", 1)
        lname = name.strip().lower()
        sval = val.strip()
        if lname == "status":
            try:
                status = int(sval.split()[0])
            except ValueError:
                pass
        elif lname == "set-cookie":
            cookie_pair = sval.split(";", 1)[0]
            if "=" in cookie_pair:
                ck, cv = cookie_pair.split("=", 1)
                set_cookies.append((ck.strip(), cv.strip()))
        headers[lname] = sval
    return status, headers, set_cookies, body


def call_php_cgi(
    script_rel: str,
    *,
    method: str = "GET",
    post_data: dict[str, str] | None = None,
    query: str = "",
    cookies: dict[str, str] | None = None,
    headers: dict[str, str] | None = None,
    conf_path: Path,
    sess_dir: Path,
    extra_bin_dir: Path | None = None,
) -> tuple[int, dict[str, str], list[tuple[str, str]], bytes]:
    script_path = UIWEB_DIR / script_rel
    env = os.environ.copy()
    if extra_bin_dir is not None:
        env["PATH"] = f"{extra_bin_dir}:{env.get('PATH', '')}"
    env["DEGWD_CONF_PATH"] = str(conf_path)
    env["GATEWAY_INTERFACE"] = "CGI/1.1"
    env["REDIRECT_STATUS"] = "200"
    env["REQUEST_METHOD"] = method
    env["SCRIPT_FILENAME"] = str(script_path)
    env["SCRIPT_NAME"] = "/" + script_rel
    env["REQUEST_URI"] = "/" + script_rel + (f"?{query}" if query else "")
    env["QUERY_STRING"] = query
    env["DOCUMENT_ROOT"] = str(UIWEB_DIR)
    env["SERVER_NAME"] = "localhost"
    env["SERVER_PORT"] = "80"
    env["REMOTE_ADDR"] = "127.0.0.1"
    env["PHP_VALUE"] = f"session.save_path={sess_dir}"

    if cookies:
        env["HTTP_COOKIE"] = "; ".join(f"{k}={v}" for k, v in cookies.items())
    if headers:
        for hk, hv in headers.items():
            env[f"HTTP_{hk.upper().replace('-', '_')}"] = hv

    body_bytes = b""
    if post_data is not None:
        body_bytes = urllib.parse.urlencode(post_data).encode("utf-8")
        env["CONTENT_TYPE"] = "application/x-www-form-urlencoded"
        env["CONTENT_LENGTH"] = str(len(body_bytes))
    else:
        env["CONTENT_LENGTH"] = "0"

    res = run_cmd(["php-cgi"], env=env, input_bytes=body_bytes, cwd=script_path.parent)
    if res.returncode != 0 and not res.stdout:
        return 500, {}, [], res.stderr
    return parse_cgi_response(res.stdout)


def test_web_auth_csrf_xss() -> None:
    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)
        conf_path = tmp / "0conf"
        sess_dir = tmp / "sessions"
        sess_dir.mkdir(mode=0o700)
        bin_dir = tmp / "bin"
        bin_dir.mkdir()

        # Dummy sudo shim for PHP exec('sudo ...') during updateSave.php test
        sudo_shim = bin_dir / "sudo"
        sudo_shim.write_text("#!/usr/bin/env bash\nexit 0\n", encoding="utf-8")
        sudo_shim.chmod(0o755)

        pw_plain = "test-v2-secret-pw"
        pw_hash = hashlib.sha256(hashlib.sha256(pw_plain.encode("utf-8")).hexdigest().encode("utf-8")).hexdigest()
        xss_probe = '"><script>alert(1)</script>'

        conf_path.write_text(
            json.dumps(
                {
                    "address": {
                        "PWD": pw_hash,
                        "localIP": xss_probe,
                        "upstream": "127.0.0.1",
                    },
                    "v2nodeDIV": {
                        "nodeCU": {"rulesDomain": [xss_probe], "rulesIP": []},
                        "ss": {"status": "on", "ssAddress": xss_probe},
                    },
                }
            ),
            encoding="utf-8",
        )

        def cgi(script_rel: str, **kwargs):
            return call_php_cgi(
                script_rel,
                conf_path=conf_path,
                sess_dir=sess_dir,
                extra_bin_dir=bin_dir,
                **kwargs,
            )

        # 1. Unauthenticated authCheck -> 401
        st, _, _, _ = cgi("act/authCheck.php")
        record(st == 401, "authCheck.php returns 401 when unauthenticated")

        # 2. Unauthenticated backupDownload -> 403
        st, _, _, _ = cgi("act/backupDownload.php", query="type=gwd")
        record(st == 403, "backupDownload.php rejects unauthenticated request with 403")

        # 3. Wrong password does not authenticate
        st, _, set_cookies, _ = cgi(
            "act/authCheck.php",
            method="POST",
            post_data={"gwdpw": "wrong-password"},
        )
        record(st == 401, "Wrong gwdpw password keeps authCheck.php at 401")

        # 4. Login via gwdpw populates valid session cookie -> authCheck 204
        st, _, set_cookies, _ = cgi(
            "act/authCheck.php",
            method="POST",
            post_data={"gwdpw": pw_plain},
        )
        cookies = dict(set_cookies)
        record(st == 204 and "PHPSESSID" in cookies, "Valid gwdpw login yields PHPSESSID and authCheck.php 204")

        # 5. Authenticated authCheck with cookie -> 204
        st, _, _, _ = cgi("act/authCheck.php", cookies=cookies)
        record(st == 204, "Authenticated authCheck.php GET with session cookie returns 204")

        # 6. Authenticated backupDownload rejects traversal/invalid type -> 400
        st, _, _, _ = cgi(
            "act/backupDownload.php",
            query="type=../../etc/passwd",
            cookies=cookies,
        )
        record(st == 400, "backupDownload.php rejects invalid type '../../etc/passwd' with 400")

        # 7. Check all pages render CSRF meta + gwd-csrf.js, escape XSS, and have no direct /restore/ links
        pages = ["index.php", "nodes.php", "app.php", "ddns.php", "forward.php", "update.php"]
        csrf_token = ""
        page_failures: list[str] = []
        for page in pages:
            st, _, _, body_bytes = cgi(page, cookies=cookies)
            html = body_bytes.decode("utf-8", errors="replace")
            if st != 200:
                page_failures.append(f"{page}: status={st}")
                continue
            m = re.search(r'<meta\s+name="csrf-token"\s+content="([0-9a-f]{64})"', html)
            if not m:
                page_failures.append(f"{page}: missing 64-hex csrf-token meta")
            elif not csrf_token:
                csrf_token = m.group(1)
            if "js/gwd-csrf.js" not in html:
                page_failures.append(f"{page}: missing js/gwd-csrf.js script tag")
            if "./restore/" in html or '"/restore/' in html:
                page_failures.append(f"{page}: still contains direct /restore/ link")

        record(not page_failures and bool(csrf_token), f"Authenticated web pages ({len(pages)}) include CSRF token & gwd-csrf.js")
        for f in page_failures:
            print(f"  {f}", file=sys.stderr)

        # Verify XSS payload escaped on index.php
        _, _, _, idx_body = cgi("index.php", cookies=cookies)
        idx_html = idx_body.decode("utf-8", errors="replace")
        record(
            "<script>alert(1)</script>" not in idx_html and "&lt;script&gt;alert(1)&lt;/script&gt;" in idx_html,
            "XSS payload in 0conf is HTML-escaped on index.php",
        )

        # 8. Every mutating act/*.php calling gwd_require_post_csrf rejects GET and missing/wrong CSRF with 403
        csrf_endpoints = sorted(
            p for p in (UIWEB_DIR / "act").glob("*.php")
            if "gwd_require_post_csrf" in p.read_text(encoding="utf-8", errors="replace")
        )
        csrf_failures: list[str] = []
        for ep in csrf_endpoints:
            rel = f"act/{ep.name}"
            st_get, _, _, _ = cgi(rel, method="GET", cookies=cookies)
            if st_get != 403:
                csrf_failures.append(f"{rel}: GET returned {st_get}, expected 403")
            st_bad_post, _, _, _ = cgi(
                rel,
                method="POST",
                post_data={"csrf_token": "0" * 64},
                cookies=cookies,
            )
            if st_bad_post != 403:
                csrf_failures.append(f"{rel}: POST with invalid CSRF returned {st_bad_post}, expected 403")
        record(
            not csrf_failures and len(csrf_endpoints) >= 50,
            f"All {len(csrf_endpoints)} mutating act/*.php endpoints enforce POST + CSRF token (403 on violation)",
        )
        for f in csrf_failures[:10]:
            print(f"  {f}", file=sys.stderr)

        # 9. Test updateSave.php creates 32-hex updateToken and returns /u_<token>/ when CSRF is valid
        st, _, _, up_body = cgi(
            "act/updateSave.php",
            method="POST",
            post_data={"updateAddr": "127.0.0.1", "updatePort": "3000", "updateCMD": "de_GWD"},
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        up_text = up_body.decode("utf-8", errors="replace").strip()
        conf_after = json.loads(conf_path.read_text(encoding="utf-8"))
        saved_token = conf_after.get("update", {}).get("updateToken", "")
        record(
            st == 200
            and bool(re.fullmatch(r"[0-9a-f]{32}", saved_token))
            and up_text == f"127.0.0.1:3000/u_{saved_token}/",
            f"updateSave.php generates random updateToken and outputs 127.0.0.1:3000/u_<token>/ (status={st})",
        )

        # 10. Logout invalidates session -> authCheck returns 401
        st, _, _, _ = cgi(
            "act/authCheck.php",
            method="POST",
            post_data={"logout": "1"},
            cookies=cookies,
        )
        st_after, _, _, _ = cgi("act/authCheck.php", cookies=cookies)
        record(st_after == 401, "POST logout invalidates session and authCheck.php returns 401")


def extract_server_xray_functions(server_text: str) -> tuple[str, str]:
    m_in = re.search(
        r"(^XrayInbound\(\)\{.*?^\})\s*\n(?=^XrayOutboundDirect\(\)\{)",
        server_text,
        flags=re.MULTILINE | re.DOTALL,
    )
    m_out = re.search(
        r"(^XrayOutboundDirect\(\)\{.*?^\})",
        server_text,
        flags=re.MULTILINE | re.DOTALL,
    )
    if not m_in or not m_out:
        raise RuntimeError("Failed to extract XrayInbound / XrayOutboundDirect from server")
    return m_in.group(1), m_out.group(1)


def test_xray_merge_and_reality_preservation() -> None:
    server_text = SERVER_SCRIPT.read_text(encoding="utf-8")
    in_fn, out_fn = extract_server_xray_functions(server_text)

    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)
        cfg_path = tmp / "config.json"
        bin_dir = tmp / "bin"
        bin_dir.mkdir()

        # Portable sponge & no-op chmod shims
        sponge_shim = bin_dir / "sponge"
        sponge_shim.write_text(
            "#!/usr/bin/env bash\nset -euo pipefail\ntmp=$(mktemp \"${1}.tmp.XXXXXX\")\ncat > \"$tmp\"\nmv -f \"$tmp\" \"$1\"\n",
            encoding="utf-8",
        )
        sponge_shim.chmod(0o755)
        chmod_shim = bin_dir / "chmod"
        chmod_shim.write_text("#!/usr/bin/env bash\nexit 0\n", encoding="utf-8")
        chmod_shim.chmod(0o755)

        initial_cfg = {
            "log": {"loglevel": "warning"},
            "routing": {
                "rules": [
                    {"type": "field", "inboundTag": ["inbound-vless-reality"], "outboundTag": "warp"}
                ]
            },
            "inbounds": [
                {"listen": "127.0.0.1", "port": 9890, "protocol": "vless", "tag": "main"},
                {"listen": "0.0.0.0", "port": 2443, "protocol": "vless", "tag": "inbound-vless-reality"},
            ],
            "outbounds": [
                {"tag": "direct", "protocol": "freedom"},
                {"tag": "blocked", "protocol": "blackhole"},
                {"tag": "warp", "protocol": "wireguard", "settings": {"secretKey": "dummy"}},
            ],
        }
        cfg_path.write_text(json.dumps(initial_cfg, indent=2), encoding="utf-8")

        patched_in = in_fn.replace("/opt/de_GWD/vtrui/config.json", str(cfg_path))
        patched_out = out_fn.replace("/opt/de_GWD/vtrui/config.json", str(cfg_path))

        runner = tmp / "run_xray_merge.sh"
        runner.write_text(
            f"""#!/usr/bin/env bash
set -euo pipefail
export PATH="{bin_dir}:$PATH"
uuids="11111111-1111-1111-1111-111111111111"
path="/testpath"
{patched_in}
{patched_out}
XrayInbound
XrayOutboundDirect
""",
            encoding="utf-8",
        )
        runner.chmod(0o755)

        res = run_cmd([str(runner)])
        if res.returncode != 0:
            record(False, f"server XrayInbound + XrayOutboundDirect merge script exited {res.returncode}: {res.stderr.decode(errors='replace')}")
            return

        merged = json.loads(cfg_path.read_text(encoding="utf-8"))
        inbound_tags = [ib.get("tag") for ib in merged.get("inbounds", [])]
        rule_tags = [r.get("outboundTag") for r in merged.get("routing", {}).get("rules", [])]
        outbound_tags = [ob.get("tag") for ob in merged.get("outbounds", [])]
        main_path = merged.get("inbounds", [{}])[0].get("streamSettings", {}).get("xhttpSettings", {}).get("path")

        record(
            "inbound-vless-reality" in inbound_tags
            and "warp" in rule_tags
            and outbound_tags == ["direct", "blocked", "warp"]
            and main_path == "/testpath",
            "server XrayInbound/XrayOutboundDirect preserve inbound-* extra inbounds, custom routing rules, and warp outbound",
        )

    # Verify uncommitted Reality (v2nodeDIV.ss) & ui-failover hooks remain intact
    change_ss_php = (UIWEB_DIR / "act" / "changeNodeSS.php").read_text(encoding="utf-8")
    node_change_sh = (UISCRIPT_DIR / "ui-NodeChange").read_text(encoding="utf-8")
    node_one_sh = (UISCRIPT_DIR / "ui-NodeOne").read_text(encoding="utf-8")
    node_save_sh = (UISCRIPT_DIR / "ui-NodeSave").read_text(encoding="utf-8")
    failover_py = (UISCRIPT_DIR / "ui-failover").read_text(encoding="utf-8")

    reality_ok = (
        "['v2nodeDIV']['ss']" in change_ss_php
        and ".v2nodeDIV.ss" in node_change_sh
        and ".v2nodeDIV.ss" in node_one_sh
        and ".v2nodeDIV.ss.ssAddress" in node_save_sh
        and len(failover_py) > 100
    )
    record(reality_ok, "Pre-existing Reality (v2nodeDIV.ss) and ui-failover integration remain intact")


def main() -> int:
    if not shutil.which("php") or not shutil.which("php-cgi") or not shutil.which("jq"):
        print("Missing required test tool (php, php-cgi, or jq)", file=sys.stderr)
        return 1

    test_syntax()
    test_concurrent_config_locking()
    test_web_auth_csrf_xss()
    test_xray_merge_and_reality_preservation()

    print(f"Summary: passed={PASSED} failed={FAILED}")
    return 0 if FAILED == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
