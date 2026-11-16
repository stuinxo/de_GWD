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

        # 9. Test updateSave.php validation, IPv6 brackets, default updateCMD, and 32-hex updateToken
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

        # 9b. updateSave.php accepts real default installCMD, formats IPv6 bracketed, and rejects invalid inputs
        st_default_cmd, _, _, _ = cgi(
            "act/updateSave.php",
            method="POST",
            post_data={
                "updateAddr": "::1",
                "updatePort": "3000",
                "updateCMD": "bash <(wget -qO- https://raw.githubusercontent.com/stuinxo/de_GWD/v2/client)",
            },
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        # Re-read body cleanly for IPv6 assertion
        st_v6, _, _, v6_body = cgi(
            "act/updateSave.php",
            method="POST",
            post_data={"updateAddr": "::1", "updatePort": "3000", "updateCMD": "de_GWD"},
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        v6_text = v6_body.decode("utf-8", errors="replace").strip()
        record(
            st_default_cmd == 200 and st_v6 == 200 and v6_text == f"[::1]:3000/u_{saved_token}/",
            f"updateSave.php accepts default updateCMD and formats IPv6 bracketed (v6={v6_text})",
        )

        st_bad_port, _, _, _ = cgi(
            "act/updateSave.php",
            method="POST",
            post_data={"updateAddr": "127.0.0.1", "updatePort": "99999", "updateCMD": "de_GWD"},
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        st_bad_cmd, _, _, _ = cgi(
            "act/updateSave.php",
            method="POST",
            post_data={"updateAddr": "127.0.0.1", "updatePort": "3000", "updateCMD": "rm -rf /; id"},
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        record(
            st_bad_port == 400 and st_bad_cmd == 400,
            "updateSave.php rejects invalid port (99999) and shell-chaining CMD with 400",
        )

        # 9c. act/*.php input validation checks
        st_nfs_loop, _, _, _ = cgi(
            "act/getNFSlist.php",
            method="POST",
            post_data={"NFSserver": "127.0.0.1"},
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        st_nfs_flag, _, _, _ = cgi(
            "act/getNFSlist.php",
            method="POST",
            post_data={"NFSserver": "-bad"},
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        st_bad_reboot, _, _, _ = cgi(
            "act/reboot.php",
            method="POST",
            post_data={"localip": "999.1.1.1", "upstreamip": "192.168.1.1"},
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        st_bad_wg, _, _, _ = cgi(
            "act/onWG.php",
            method="POST",
            post_data={"WGaddress": "wg.example.com", "WGport": "70000", "sprivatekey": "bad", "spublickey": "bad"},
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        st_bad_onnfs, _, _, _ = cgi(
            "act/onNFS.php",
            method="POST",
            post_data={
                "NFSlist[0][NFSpoint]": "/etc/passwd",
                "NFSlist[0][NFSserver]": "192.168.1.10",
                "NFSlist[0][NFSaddress]": "/share",
            },
            headers={"X-CSRF-Token": csrf_token},
            cookies=cookies,
        )
        record(
            st_nfs_loop == 400
            and st_nfs_flag == 400
            and st_bad_reboot == 400
            and st_bad_wg == 400
            and st_bad_onnfs == 400,
            "act/*.php endpoints reject invalid NFS/reboot/WireGuard inputs with 400",
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


def test_auth_fail_closed_rate_limit_and_malformed_conf() -> None:
    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)
        sess_dir = tmp / "sessions"
        sess_dir.mkdir(mode=0o700)
        bin_dir = tmp / "bin"
        bin_dir.mkdir()
        sudo_shim = bin_dir / "sudo"
        sudo_shim.write_text("#!/usr/bin/env bash\nexit 0\n", encoding="utf-8")
        sudo_shim.chmod(0o755)

        # 1. Fail-closed when both 0conf and 0conf_bak lack valid 64-hex PWD
        empty_conf = tmp / "empty_0conf"
        empty_conf.write_text(json.dumps({"address": {"PWD": ""}}), encoding="utf-8")
        bak_conf = Path(f"{empty_conf}_bak")
        bak_conf.write_text(json.dumps({"address": {"PWD": "short"}}), encoding="utf-8")

        st_old_default, _, _, _ = call_php_cgi(
            "act/authCheck.php",
            method="POST",
            post_data={"gwdpw": "de_GWD"},
            conf_path=empty_conf,
            sess_dir=sess_dir,
            extra_bin_dir=bin_dir,
        )
        st_empty_pw, _, _, _ = call_php_cgi(
            "act/authCheck.php",
            method="POST",
            post_data={"gwdpw": ""},
            conf_path=empty_conf,
            sess_dir=sess_dir,
            extra_bin_dir=bin_dir,
        )
        record(
            st_old_default == 401 and st_empty_pw == 401,
            "auth.php fails closed when 0conf and 0conf_bak lack valid 64-hex PWD",
        )

        # 2. Per-IP login rate limit (>=10 wrong attempts -> 429)
        pw_plain = "rate-limit-secret"
        pw_hash = hashlib.sha256(hashlib.sha256(pw_plain.encode("utf-8")).hexdigest().encode("utf-8")).hexdigest()
        rl_conf = tmp / "rl_0conf"
        rl_conf.write_text(json.dumps({"address": {"PWD": pw_hash}}), encoding="utf-8")
        rl_key = hashlib.md5(f"{rl_conf}|127.0.0.1".encode("utf-8")).hexdigest()
        rl_state = Path(tempfile.gettempdir()) / f"de_GWD_login_rl_{rl_key}.json"
        try:
            statuses = []
            for _ in range(11):
                st_rl, _, _, _ = call_php_cgi(
                    "act/authCheck.php",
                    method="POST",
                    post_data={"gwdpw": "wrong-pw"},
                    conf_path=rl_conf,
                    sess_dir=sess_dir,
                    extra_bin_dir=bin_dir,
                )
                statuses.append(st_rl)
            record(
                all(s == 401 for s in statuses[:10]) and statuses[10] == 429,
                f"Login rate limiter enforces 429 after 10 failures (statuses={statuses})",
            )
        finally:
            if rl_state.exists():
                rl_state.unlink()

        # 3. Malformed non-array/non-object 0conf rendering on index.php & forward.php
        bad_conf = tmp / "malformed_0conf"
        bad_conf.write_text(
            json.dumps(
                {
                    "address": {"PWD": pw_hash, "alias": ["bad"], "localIP": {"x": 1}},
                    "v2node": "not-an-array",
                    "v2nodeDIV": {
                        "nodeDT": {"CHNlistProxyIP": "bad", "globalProxyIP": 1, "directProxyIP": {}},
                        "nodeCU": {"rulesDomain": "bad", "rulesIP": 99},
                        "ss": "bad",
                    },
                    "dns": {"doh": "not-array", "china": ["bad"], "listB": "bad", "listW": 123},
                    "FORWARD": {
                        "domain": ["bad"],
                        "FWD0": {"port": [], "uuid": "bad"},
                        "FWD1": "not-obj",
                        "Rproxy": {"server": {"mapping": "bad"}, "client": {"mapping": "bad"}},
                    },
                }
            ),
            encoding="utf-8",
        )
        st_login, _, cookies_list, _ = call_php_cgi(
            "act/authCheck.php",
            method="POST",
            post_data={"gwdpw": pw_plain},
            conf_path=bad_conf,
            sess_dir=sess_dir,
            extra_bin_dir=bin_dir,
        )
        bad_cookies = dict(cookies_list)
        bad_render_err: list[str] = []
        if st_login != 204:
            bad_render_err.append(f"login status={st_login}")
        else:
            for page in ("index.php", "forward.php"):
                st_p, _, _, p_body = call_php_cgi(
                    page,
                    cookies=bad_cookies,
                    conf_path=bad_conf,
                    sess_dir=sess_dir,
                    extra_bin_dir=bin_dir,
                )
                txt = p_body.decode("utf-8", errors="replace")
                if st_p != 200:
                    bad_render_err.append(f"{page}: status={st_p}")
                for marker in ("Fatal error", "Uncaught TypeError", "Uncaught Error", "Warning:"):
                    if marker in txt:
                        bad_render_err.append(f"{page}: found '{marker}'")
        record(
            not bad_render_err,
            "index.php and forward.php render malformed non-list 0conf without PHP TypeError/warnings",
        )
        for err in bad_render_err:
            print(f"  {err}", file=sys.stderr)


def test_review_round2_fixes() -> None:
    """Regressions for the code-review follow-up fixes."""
    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)
        sess_dir = tmp / "sessions"
        sess_dir.mkdir(mode=0o700)
        bin_dir = tmp / "bin"
        bin_dir.mkdir()
        sudo_shim = bin_dir / "sudo"
        sudo_shim.write_text("#!/usr/bin/env bash\nexit 0\n", encoding="utf-8")
        sudo_shim.chmod(0o755)

        pw_plain = "round2-secret"
        pw_hash = hashlib.sha256(hashlib.sha256(pw_plain.encode("utf-8")).hexdigest().encode("utf-8")).hexdigest()
        conf_path = tmp / "0conf"
        conf_path.write_text(
            json.dumps(
                {
                    "address": {"PWD": pw_hash},
                    "v2node": [{"domain": "n1.example.com", "name": "N1", "uuid": "aaaa-1111", "tls": "", "path": ""}],
                    "FORWARD": {
                        "domain": "fwd.example.com",
                        "FWD0": {"port": "8443", "uuid": [
                            {"FWD0uuid": "uuid-one", "FWD0mark": "m1"},
                            {"FWD0uuid": "uuid-two", "FWD0mark": "m2"},
                        ]},
                        "FWD1": {"port": "9443", "uuid": [
                            {"FWD1uuid": "uuid-a", "FWD1mark": "a"},
                            {"FWD1uuid": "uuid-b", "FWD1mark": "b"},
                        ]},
                    },
                }
            ),
            encoding="utf-8",
        )

        def cgi(script_rel: str, **kwargs):
            return call_php_cgi(script_rel, conf_path=conf_path, sess_dir=sess_dir, extra_bin_dir=bin_dir, **kwargs)

        # 1. Empty password is rejected even when 0conf holds the hash of the empty string
        empty_hash = hashlib.sha256(hashlib.sha256(b"").hexdigest().encode("utf-8")).hexdigest()
        empty_conf = tmp / "empty_pw_0conf"
        empty_conf.write_text(json.dumps({"address": {"PWD": empty_hash}}), encoding="utf-8")
        st_empty, _, _, _ = call_php_cgi(
            "act/authCheck.php", method="POST", post_data={"gwdpw": ""},
            conf_path=empty_conf, sess_dir=sess_dir, extra_bin_dir=bin_dir,
        )
        record(st_empty == 401, "auth.php rejects empty password even if 0conf stores the empty-string hash")

        # Login + CSRF token
        st, _, set_cookies, _ = cgi("act/authCheck.php", method="POST", post_data={"gwdpw": pw_plain})
        cookies = dict(set_cookies)
        _, _, _, upd_body = cgi("update.php", cookies=cookies)
        m = re.search(r'name="csrf-token"\s+content="([0-9a-f]{64})"', upd_body.decode("utf-8", errors="replace"))
        csrf = m.group(1) if m else ""
        if st != 204 or not csrf:
            record(False, f"round2 login/CSRF setup (status={st}, csrf={'yes' if csrf else 'no'})")
            return
        hdr = {"X-CSRF-Token": csrf}

        # 2. forward.php: QR row lookup counts only real UUID rows, never popup input-groups
        _, _, _, fwd_body = cgi("forward.php", cookies=cookies)
        fwd_html = fwd_body.decode("utf-8", errors="replace")
        fwd0_rows = fwd_html.count('class="input-group my-2 fwd0-row"')
        fwd1_rows = fwd_html.count('class="input-group my-2 fwd1-row"')
        record(
            fwd0_rows == 3 and fwd1_rows == 3  # 2 rendered rows + 1 JS add-row template each
            and "#FWD0List .input-group" not in fwd_html
            and "#FWD1List .input-group" not in fwd_html
            and "fwdSavedIndex('fwd0-row'" in fwd_html
            and "fwdSavedIndex('fwd1-row'" in fwd_html,
            f"forward.php QR/save row selection uses dedicated row classes (fwd0={fwd0_rows}, fwd1={fwd1_rows})",
        )

        # 3. Inline JavaScript of all 6 pages parses (catches syntax slips in hand-edited handlers)
        node_bin = shutil.which("node")
        if node_bin:
            js_errors: list[str] = []
            for page in ("index.php", "nodes.php", "app.php", "ddns.php", "forward.php", "update.php"):
                _, _, _, body = cgi(page, cookies=cookies)
                html = body.decode("utf-8", errors="replace")
                scripts = re.findall(r"<script>(.*?)</script>", html, flags=re.DOTALL)
                js_file = tmp / f"{page}.js"
                js_file.write_text("\n;\n".join(scripts), encoding="utf-8")
                res = run_cmd([node_bin, "--check", str(js_file)])
                if res.returncode != 0:
                    js_errors.append(f"{page}: {res.stderr.decode(errors='replace').strip()[:300]}")
            record(not js_errors, "Inline <script> blocks of all 6 pages pass node --check")
            for e in js_errors:
                print(f"  {e}", file=sys.stderr)
        else:
            print("[SKIP] node not found; inline JS syntax check skipped")

        # 4. NodeSave skips invalid rows (reported) but refuses to wipe the list when all are invalid
        st_mix, _, _, mix_body = cgi(
            "act/NodeSave.php", method="POST", headers=hdr, cookies=cookies,
            post_data={
                "nodeList[0][domain]": "ok.example.com", "nodeList[0][name]": "OK", "nodeList[0][uuid]": "uuid-ok",
                "nodeList[1][domain]": "bad host", "nodeList[1][name]": "BAD", "nodeList[1][uuid]": "uuid-bad",
            },
        )
        nodes_after = json.loads(conf_path.read_text(encoding="utf-8")).get("v2node", [])
        st_allbad, _, _, _ = cgi(
            "act/NodeSave.php", method="POST", headers=hdr, cookies=cookies,
            post_data={"nodeList[0][domain]": "bad host", "nodeList[0][name]": "B", "nodeList[0][uuid]": "u"},
        )
        nodes_after2 = json.loads(conf_path.read_text(encoding="utf-8")).get("v2node", [])
        record(
            st_mix == 200 and mix_body.decode().strip() == "skipped 1"
            and [n.get("domain") for n in nodes_after] == ["ok.example.com"]
            and st_allbad == 400 and nodes_after2 == nodes_after,
            "NodeSave.php skips invalid rows with notice and never wipes the list when all rows are invalid",
        )

        # 5. onNFS skips invalid entries (reported)
        st_nfs, _, _, nfs_body = cgi(
            "act/onNFS.php", method="POST", headers=hdr, cookies=cookies,
            post_data={
                "NFSlist[0][NFSpoint]": "/mnt/share", "NFSlist[0][NFSserver]": "192.168.1.10", "NFSlist[0][NFSaddress]": "/srv/share",
                "NFSlist[1][NFSpoint]": "/mnt/", "NFSlist[1][NFSserver]": "192.168.1.10", "NFSlist[1][NFSaddress]": "/srv/",
            },
        )
        nfs_after = json.loads(conf_path.read_text(encoding="utf-8")).get("app", {}).get("NFS", [])
        record(
            st_nfs == 200 and nfs_body.decode().strip() == "skipped 1"
            and [e.get("NFSpoint") for e in nfs_after] == ["/mnt/share"],
            "onNFS.php keeps valid mounts and skips invalid ones with a notice",
        )

        # 6. DNSsave accepts the tab-separated hosts format printed by ui-hostsCustomize, skips junk
        st_dns, _, _, dns_body = cgi(
            "act/DNSsave.php", method="POST", headers=hdr, cookies=cookies,
            post_data={
                "DoGc": "", "DoH1": "doh.example.com/dq", "DoH2": "",
                "dnsChina": "223.5.5.5\nnot-an-ip\n119.29.29.29",
                "hostsCustomize": "192.168.1.2\tnas.lan\n# comment\n10.0.0.1 *.corp.lan\nbogus-line",
            },
        )
        dns_conf = json.loads(conf_path.read_text(encoding="utf-8")).get("dns", {})
        record(
            st_dns == 200 and dns_body.decode().strip() == "skipped 2"
            and dns_conf.get("china") == "223.5.5.5 119.29.29.29"
            and dns_conf.get("hosts") == {"nas.lan": "192.168.1.2", "*.corp.lan": "10.0.0.1"},
            f"DNSsave.php accepts TAB-separated hosts and skips invalid DNS/hosts entries (body={dns_body.decode().strip()!r})",
        )

    # 7. ttyd restart uses an anchored pkill pattern, and the sudoers alias allows exactly that
    auth_txt = (UIWEB_DIR / "auth.php").read_text(encoding="utf-8")
    client_txt = CLIENT_SCRIPT.read_text(encoding="utf-8")
    m_alias = re.search(r"cat << 'EOF' > \"\$sudoersTmp\"\n(.*?)\nEOF\n", client_txt, flags=re.DOTALL)
    anchored = (
        "pkill -f '^/usr/bin/ttyd -i lo -p 3000'" in auth_txt
        and 'pkill -f "/usr/bin/ttyd -i lo -p 3000"' not in auth_txt
        and m_alias is not None
        and r"/usr/bin/pkill -f ^/usr/bin/ttyd\ -i\ lo\ -p\ 3000" in m_alias.group(1)
    )
    visudo_ok = True
    visudo_bin = shutil.which("visudo")
    if anchored and visudo_bin:
        with tempfile.NamedTemporaryFile("w", suffix=".sudoers", delete=False) as sf:
            sf.write(m_alias.group(1) + "\n")
            sudoers_tmp = sf.name
        try:
            visudo_ok = run_cmd([visudo_bin, "-cf", sudoers_tmp]).returncode == 0
        finally:
            os.unlink(sudoers_tmp)
    record(anchored and visudo_ok, "gwd_start_ttyd pkill pattern is anchored and client sudoers alias passes visudo -c")

    # 8. client changePWD: hidden input, confirmation, rejects empty/mismatch, stores 64-hex hash
    m_fn = re.search(r"(^changePWD\(\)\{.*?^\})", client_txt, flags=re.MULTILINE | re.DOTALL)
    if not m_fn:
        record(False, "client changePWD() function found")
        return
    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)
        opt = tmp / "opt"
        opt.mkdir()
        shutil.copy2(UISCRIPT_DIR / "ui-confCommon", opt / "ui-confCommon")
        conf = opt / "0conf"
        conf.write_text(json.dumps({"address": {"PWD": "x"}}), encoding="utf-8")
        fbin = tmp / "bin"
        fbin.mkdir()
        (fbin / "docker").write_text("#!/usr/bin/env bash\nexit 1\n", encoding="utf-8")
        (fbin / "docker").chmod(0o755)
        runner = tmp / "run.sh"
        runner.write_text(
            "#!/usr/bin/env bash\nset -u\nRED='' GREEN='' WHITE='' cRES=''\n"
            + m_fn.group(1).replace("/opt/de_GWD", str(opt))
            + "\nchangePWD\n",
            encoding="utf-8",
        )
        runner.chmod(0o755)
        env = os.environ.copy()
        env["PATH"] = f"{fbin}:{env.get('PATH', '')}"
        env["DEGWD_CONF_PATH"] = str(conf)
        # empty -> mismatch -> good pair
        res = run_cmd([str(runner)], env=env, input_bytes=b"\nnew-pw\nother\nnew-pw\nnew-pw\n")
        stored = json.loads(conf.read_text(encoding="utf-8")).get("address", {}).get("PWD", "")
        expect = hashlib.sha256(hashlib.sha256(b"new-pw").hexdigest().encode("utf-8")).hexdigest()
        out = res.stdout.decode(errors="replace")
        record(
            res.returncode == 0 and stored == expect
            and "must not be empty" in out and "do not match" in out and "new-pw" not in out,
            "client changePWD rejects empty/mismatched input, hides the password, and stores the double-SHA256 hash",
        )


def test_initial_random_password() -> None:
    """client installGWD generates a random initial password instead of the well-known default hash."""
    client_txt = CLIENT_SCRIPT.read_text(encoding="utf-8")
    m_gen = re.search(r"(^gen_initial_password\(\)\{.*?^\})", client_txt, flags=re.MULTILINE | re.DOTALL)
    m_hash = re.search(r"(^gwd_pw_hash\(\)\{.*?^\})", client_txt, flags=re.MULTILINE | re.DOTALL)
    m_install = re.search(r"(^installGWD\(\)\{.*?^\})", client_txt, flags=re.MULTILINE | re.DOTALL)
    if not (m_gen and m_hash and m_install):
        record(False, "client defines gen_initial_password, gwd_pw_hash and installGWD")
        return
    install_fn = m_install.group(1)
    static_ok = (
        "f69ad6fd10ecc116889c9985d4b45ffaf51875af3198d4e6b6eceb1080ca1e99" not in client_txt
        and "gen_initial_password" in install_fn
        and "--arg pwd \"$initPWhash\" '.address.PWD=$pwd'" in install_fn
        and "pihole setpassword \"$initPW\"" in install_fn
        and "/opt/de_GWD/.initial_password" in install_fn
        and "rm -f /opt/de_GWD/.initial_password" in client_txt
    )

    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)
        fbin = tmp / "bin"
        fbin.mkdir()
        if not shutil.which("sha256sum"):
            shim = fbin / "sha256sum"
            shim.write_text("#!/usr/bin/env bash\nexec shasum -a 256 \"$@\"\n", encoding="utf-8")
            shim.chmod(0o755)
        runner = tmp / "pw.sh"
        runner.write_text(
            "#!/usr/bin/env bash\nset -euo pipefail\n"
            + m_gen.group(1) + "\n" + m_hash.group(1) + "\n"
            + 'a=$(gen_initial_password); b=$(gen_initial_password)\n'
            + 'printf "%s\\n%s\\n%s\\n" "$a" "$b" "$(gwd_pw_hash "$a")"\n',
            encoding="utf-8",
        )
        runner.chmod(0o755)
        env = os.environ.copy()
        env["PATH"] = f"{fbin}:{env.get('PATH', '')}"
        res = run_cmd([str(runner)], env=env)
        lines = res.stdout.decode(errors="replace").split()
        dyn_ok = False
        if res.returncode == 0 and len(lines) == 3:
            a, b, h = lines
            expect = hashlib.sha256(hashlib.sha256(a.encode()).hexdigest().encode()).hexdigest()
            dyn_ok = (
                re.fullmatch(r"[A-Za-z0-9]{16}", a) is not None
                and re.fullmatch(r"[A-Za-z0-9]{16}", b) is not None
                and a != b
                and h == expect
            )
    record(
        static_ok and dyn_ok,
        "installGWD writes a random 16-char initial password (auth.php-compatible hash), syncs Pi-hole, and drops the default hash",
    )


def test_ui_script_xray_and_split_routing() -> None:
    xray_common = UISCRIPT_DIR / "xray-common"
    conf_common = UISCRIPT_DIR / "ui-confCommon"
    node_cu = UISCRIPT_DIR / "ui-NodeCU"
    node_dt = UISCRIPT_DIR / "ui-NodeDT"

    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)
        # 1. Test xray-common special character escaping + freedom finalRules
        out_runner = tmp / "run_xray_common.sh"
        out_runner.write_text(
            f"""#!/usr/bin/env bash
set -euo pipefail
source {json.dumps(str(xray_common))}
vless=$(build_vless_xhttp_outbound 'tag"1' 'edge.example.com' '8443' '11111111-1111-1111-1111-111111111111' 'sni"host' '/pa$th\\1' '255')
free=$(build_freedom_direct_outbound 'direct' '255')
jq -nc --argjson v "$vless" --argjson f "$free" '{{vless: $v, freedom: $f}}'
""",
            encoding="utf-8",
        )
        out_runner.chmod(0o755)
        res = run_cmd([str(out_runner)])
        ok_xray = False
        if res.returncode == 0:
            parsed = json.loads(res.stdout.decode("utf-8"))
            v = parsed.get("vless", {})
            f = parsed.get("freedom", {})
            ok_xray = (
                v.get("tag") == 'tag"1'
                and v.get("streamSettings", {}).get("tlsSettings", {}).get("serverName") == 'sni"host'
                and v.get("streamSettings", {}).get("xhttpSettings", {}).get("path") == '/pa$th\\1'
                and f.get("protocol") == "freedom"
                and isinstance(f.get("settings", {}).get("finalRules"), list)
                and len(f.get("settings", {}).get("finalRules", [])) > 0
            )
        record(
            ok_xray,
            "xray-common safely JSON-escapes special characters and emits finalRules on freedom outbound",
        )

        # 2. Test ui-NodeCU and ui-NodeDT invocation without $2
        opt_dir = tmp / "opt_de_GWD"
        nft_dir = opt_dir / "nftables"
        vtrui_dir = opt_dir / "vtrui"
        opt_dir.mkdir(parents=True)
        nft_dir.mkdir(parents=True)
        vtrui_dir.mkdir(parents=True)
        bin_dir = tmp / "bin"
        bin_dir.mkdir()

        for tool in ("nft", "systemctl", "chown", "chmod"):
            shim = bin_dir / tool
            shim.write_text("#!/usr/bin/env bash\nexit 0\n", encoding="utf-8")
            shim.chmod(0o755)
        # Portable GNU grep -Po shim & sed shim for BSD macOS
        grep_shim = bin_dir / "grep"
        grep_shim.write_text(
            """#!/usr/bin/env python3
import re, sys
args = sys.argv[1:]
if "-Po" in args:
    args = [a for a in args if a != "-Po"]
    pat = args[0] if args else ""
    files = args[1:] if len(args) > 1 else []
    text = sys.stdin.read() if not files else "".join(open(f, encoding="utf-8", errors="replace").read() for f in files)
    matches = re.findall(pat, text)
    if matches:
        sys.stdout.write("\\n".join(matches) + "\\n")
        sys.exit(0)
    sys.exit(1)
import os, subprocess
real_grep = next((os.path.join(d, "grep") for d in os.environ.get("PATH", "").split(os.pathsep) if os.path.abspath(d) != os.path.abspath(os.path.dirname(__file__)) and os.path.exists(os.path.join(d, "grep"))), "/usr/bin/grep")
sys.exit(subprocess.call([real_grep] + sys.argv[1:]))
""",
            encoding="utf-8",
        )
        grep_shim.chmod(0o755)

        calls_log = tmp / "v2calls.log"
        for stub_name in ("ui-V2outbound", "ui-V2routingDomain", "ui-V2routingIP", "ui-V2routingSip"):
            st_file = opt_dir / stub_name
            st_file.write_text(
                f"#!/usr/bin/env bash\nprintf '%s:%s\\n' {json.dumps(stub_name)} \"$*\" >> {json.dumps(str(calls_log))}\n",
                encoding="utf-8",
            )
            st_file.chmod(0o755)

        shutil.copy2(conf_common, opt_dir / "ui-confCommon")
        shutil.copy2(xray_common, opt_dir / "xray-common")

        gwd_conf = opt_dir / "0conf"
        gwd_conf.write_text(
            json.dumps(
                {
                    "v2node": [
                        {"domain": "n1.example.com:443", "tls": "n1.example.com", "name": "N1", "uuid": "11111111-1111-1111-1111-111111111111", "path": "/p1"}
                    ],
                    "v2nodeDIV": {
                        "nodeCU": {"address": "n1.example.com:443", "rulesDomain": ["example.org"], "rulesIP": ["1.1.1.1"]},
                        "nodeDT": {"address": "n1.example.com:443", "CHNlistProxyIP": ["192.168.1.50"], "globalProxyIP": [], "directProxyIP": []},
                    },
                }
            ),
            encoding="utf-8",
        )
        xray_cfg = vtrui_dir / "config.json"
        xray_cfg.write_text(json.dumps({"routing": {"rules": []}, "outbounds": []}), encoding="utf-8")

        patched_cu = opt_dir / "ui-NodeCU"
        patched_cu.write_text(node_cu.read_text(encoding="utf-8").replace("/opt/de_GWD", str(opt_dir)), encoding="utf-8")
        patched_cu.chmod(0o755)
        patched_dt = opt_dir / "ui-NodeDT"
        dt_txt = node_dt.read_text(encoding="utf-8").replace("/opt/de_GWD", str(opt_dir)).replace("sed -i '/^\\s*$/d'", "sed -i.bak '/^[[:space:]]*$/d'").replace("sed -i 's/$/,/g'", "sed -i.bak 's/$/,/g'")
        patched_dt.write_text(dt_txt, encoding="utf-8")
        patched_dt.chmod(0o755)

        env = os.environ.copy()
        env["PATH"] = f"{bin_dir}:{env.get('PATH', '')}"
        env["DEGWD_CONF_PATH"] = str(gwd_conf)
        env["XRAY_CONF_PATH"] = str(xray_cfg)

        res_cu = run_cmd([str(patched_cu)], env=env)
        res_dt = run_cmd([str(patched_dt)], env=env)
        conf_res = json.loads(gwd_conf.read_text(encoding="utf-8"))
        logged = calls_log.read_text(encoding="utf-8") if calls_log.exists() else ""
        record(
            res_cu.returncode == 0
            and res_dt.returncode == 0
            and conf_res.get("v2nodeDIV", {}).get("nodeCU", {}).get("status") == "on"
            and conf_res.get("v2nodeDIV", {}).get("nodeDT", {}).get("status") == "on"
            and "ui-V2outbound:custom " in logged
            and "ui-V2outbound:divert " in logged,
            "ui-NodeCU and ui-NodeDT execute cleanly without $2 and mark status=on",
        )


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
    test_auth_fail_closed_rate_limit_and_malformed_conf()
    test_review_round2_fixes()
    test_initial_random_password()
    test_ui_script_xray_and_split_routing()
    test_xray_merge_and_reality_preservation()

    print(f"Summary: passed={PASSED} failed={FAILED}")
    return 0 if FAILED == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
