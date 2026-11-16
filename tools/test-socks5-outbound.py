#!/usr/bin/env python3
"""Isolated local regression suite for server SOCKS5 outbound (option 35)."""

from __future__ import annotations

import http.server
import json
import os
import re
import select
import socket
import socketserver
import stat
import subprocess
import sys
import tempfile
import threading
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SERVER_SCRIPT = ROOT / "server"

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


def extract_function(source: str, name: str) -> str:
    m = re.search(rf"^{re.escape(name)}\(\)\{{.*?(?=\n+[A-Za-z0-9_]+\(\)\{{|\Z)", source, flags=re.MULTILINE | re.DOTALL)
    if not m:
        raise RuntimeError(f"Missing function {name} in {SERVER_SCRIPT}")
    block = m.group(0)
    end = block.rfind("\n}")
    if end < 0:
        raise RuntimeError(f"Unterminated function {name} in {SERVER_SCRIPT}")
    return block[: end + 2]


def build_bash_harness(server_text: str, opt_dir: Path, tmp_dir: Path, extra_env: dict[str, str] | None = None) -> tuple[str, dict[str, str]]:
    fn_names = [
        "normalizeSha256",
        "checkSum",
        "isElfBinary",
        "XrayInbound",
        "XrayOutboundDirect",
        "socks5Trim",
        "socks5WriteFile",
        "socks5UrlDecode",
        "socks5OutboundIsValidIpv4",
        "socks5OutboundIsValidIpv6",
        "socks5OutboundIsValidAddress",
        "socks5OutboundIsValidIpRule",
        "socks5OutboundParseEndpoint",
        "socks5OutboundExpandRules",
        "socks5OutboundSettingsOrDefault",
        "socks5OutboundProfiles",
        "socks5OutboundProfile",
        "socks5OutboundCheckSelfLoop",
        "socks5OutboundValidateProfile",
        "socks5OutboundValidate",
        "socks5ExtractGeodataZip",
        "socks5OutboundInstallGeodata",
        "socks5OutboundEnsureGeodata",
        "socks5OutboundConfig",
        "socks5OutboundSync",
        "socks5OutboundApply",
        "socks5FormatEndpoint",
        "socks5FormatRulesPreview",
        "socks5OutboundStatus",
        "socks5OutboundConfigure",
        "socks5OutboundToggle",
        "socks5OutboundDisable",
        "socks5OutboundDelete",
        "socks5OutboundReorder",
        "socks5CurlEscape",
        "socks5OutboundTestProfile",
        "socks5OutboundTestMenu",
        "socks5OutboundMenu",
    ]
    extracted = "\n\n".join(extract_function(server_text, name) for name in fn_names)

    config_path = opt_dir / "vtrui" / "config.json"
    vtrui_dir = opt_dir / "vtrui"
    settings_path = opt_dir / "socks5-outbound.json"
    repo_dir = opt_dir / ".repo"

    # Rewrite hardcoded paths inside XrayOutboundDirect / XrayInbound / self-loop checks
    extracted = extracted.replace("/opt/de_GWD/vtrui/config.json", str(config_path))
    extracted = extracted.replace("/opt/de_GWD/socks5-outbound.json", str(settings_path))
    extracted = extracted.replace("/opt/de_GWD/extra_domains.json", str(opt_dir / "extra_domains.json"))
    extracted = extracted.replace("/etc/nginx/conf.d/default.conf", str(opt_dir / "nginx_default.conf"))
    extracted = extracted.replace("/tmp/de_GWD", str(tmp_dir / "de_GWD"))

    prelude = f"""
set -eo pipefail
RED=''
GREEN=''
YELLOW=''
BLUE=''
PURPLE=''
CYAN=''
WHITE=''
cRES=''
branch="v2"
socks5OutboundSettings={json.dumps(str(settings_path))}
vtruiConfig={json.dumps(str(config_path))}
vtruiDir={json.dumps(str(vtrui_dir))}
DE_GWD_REPO_DIR={json.dumps(str(repo_dir))}
sponge() {{
  local data
  data=$(cat)
  printf '%s\\n' "$data" > "$1"
}}
systemctl() {{
  if [[ $1 = is-active ]]; then
    if [[ $2 = --quiet ]]; then return 1; fi
    printf '%s\\n' inactive
    return 0
  fi
  return 0
}}
"""
    env = os.environ.copy()
    if extra_env:
        env.update(extra_env)
    return prelude + "\n" + extracted, env


def run_bash(harness: str, env: dict[str, str], body: str, stdin_text: str | None = None, cwd: Path | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["/bin/bash", "-c", f"{harness}\n{body}"],
        input=stdin_text,
        text=True,
        capture_output=True,
        env=env,
        cwd=str(cwd or ROOT),
        timeout=20,
    )


class MiniTraceHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self) -> None:
        payload = b"fl=1f1\nh=example.test\nip=203.0.113.88\nts=1700000000\nloc=SG\n"
        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def log_message(self, format: str, *args: object) -> None:
        return


class ThreadedTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True
    daemon_threads = True


def start_loopback_socks5_server(expected_user: str, expected_pass: str) -> tuple[ThreadedTCPServer, int]:
    class Socks5Handler(socketserver.BaseRequestHandler):
        def recv_exact(self, n: int) -> bytes:
            buf = bytearray()
            while len(buf) < n:
                chunk = self.request.recv(n - len(buf))
                if not chunk:
                    raise EOFError("unexpected EOF")
                buf.extend(chunk)
            return bytes(buf)

        def handle(self) -> None:
            try:
                ver, nmethods = self.recv_exact(2)
                if ver != 5:
                    return
                methods = self.recv_exact(nmethods)
                if 2 not in methods:
                    self.request.sendall(b"\x05\xff")
                    return
                self.request.sendall(b"\x05\x02")

                auth_ver, ulen = self.recv_exact(2)
                if auth_ver != 1:
                    self.request.sendall(b"\x01\x01")
                    return
                user = self.recv_exact(ulen).decode("utf-8", errors="replace")
                plen = self.recv_exact(1)[0]
                pw = self.recv_exact(plen).decode("utf-8", errors="replace")
                if user != expected_user or pw != expected_pass:
                    self.request.sendall(b"\x01\x01")
                    return
                self.request.sendall(b"\x01\x00")

                ver, cmd, _rsv, atyp = self.recv_exact(4)
                if ver != 5 or cmd != 1:
                    self.request.sendall(b"\x05\x07\x00\x01\x00\x00\x00\x00\x00\x00")
                    return
                if atyp == 1:
                    host = socket.inet_ntop(socket.AF_INET, self.recv_exact(4))
                elif atyp == 3:
                    dlen = self.recv_exact(1)[0]
                    host = self.recv_exact(dlen).decode("utf-8", errors="replace")
                elif atyp == 4:
                    host = socket.inet_ntop(socket.AF_INET6, self.recv_exact(16))
                else:
                    self.request.sendall(b"\x05\x08\x00\x01\x00\x00\x00\x00\x00\x00")
                    return
                port = int.from_bytes(self.recv_exact(2), "big")

                remote = socket.create_connection((host, port), timeout=5)
                self.request.sendall(b"\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00")
                with remote:
                    sockets = [self.request, remote]
                    while True:
                        readable, _, _ = select.select(sockets, [], [], 5.0)
                        if not readable:
                            break
                        for s in readable:
                            data = s.recv(4096)
                            if not data:
                                return
                            (remote if s is self.request else self.request).sendall(data)
            except Exception:
                return

    srv = ThreadedTCPServer(("127.0.0.1", 0), Socks5Handler)
    port = srv.server_address[1]
    t = threading.Thread(target=srv.serve_forever, daemon=True)
    t.start()
    return srv, port


def main() -> int:
    server_text = SERVER_SCRIPT.read_text(encoding="utf-8")

    with tempfile.TemporaryDirectory(prefix="dex-socks5-v2-") as tmp_root_str:
        tmp_root = Path(tmp_root_str)
        opt_dir = tmp_root / "opt" / "de_GWD"
        tmp_dir = tmp_root / "tmp"
        vtrui_dir = opt_dir / "vtrui"
        config_path = vtrui_dir / "config.json"
        settings_path = opt_dir / "socks5-outbound.json"
        vtrui_dir.mkdir(parents=True, exist_ok=True)
        tmp_dir.mkdir(parents=True, exist_ok=True)

        harness, env = build_bash_harness(server_text, opt_dir, tmp_dir)

        base_config = {
            "inbounds": [
                {"tag": "main", "listen": "127.0.0.1", "port": 9890, "protocol": "vless"},
                {"tag": "inbound-socks5", "listen": "0.0.0.0", "port": 1080, "protocol": "socks"},
            ],
            "outbounds": [
                {"tag": "direct", "protocol": "freedom"},
                {"tag": "custom", "protocol": "freedom"},
            ],
            "routing": {
                "rules": [
                    {"type": "field", "domain": ["domain:example.org"], "outboundTag": "custom"},
                ]
            },
        }

        def reset(cfg: dict | None = None, settings: dict | None = None) -> None:
            config_path.write_text(json.dumps(cfg if cfg is not None else base_config, indent=2), encoding="utf-8")
            os.chmod(config_path, 0o644)
            settings_path.unlink(missing_ok=True)
            if settings is not None:
                settings_path.write_text(json.dumps(settings, indent=2), encoding="utf-8")
                os.chmod(settings_path, 0o600)
            for dat in ("geoip.dat", "geosite.dat"):
                (vtrui_dir / dat).unlink(missing_ok=True)

        def read_cfg() -> dict:
            return json.loads(config_path.read_text(encoding="utf-8"))

        def read_settings() -> dict:
            return json.loads(settings_path.read_text(encoding="utf-8"))

        # 1. Absent settings leaves XrayOutboundDirect behavior intact
        reset()
        res = run_bash(harness, env, "XrayOutboundDirect")
        cfg = read_cfg()
        record(
            res.returncode == 0
            and [o["tag"] for o in cfg["outbounds"]] == ["direct", "blocked", "custom"]
            and cfg["routing"]["rules"] == base_config["routing"]["rules"],
            "Absent socks5-outbound.json leaves existing inbounds, custom outbounds, and rules unchanged",
        )

        # 2. Global default mode vs default-with-rules routing fix
        reset()
        res = run_bash(
            harness,
            env,
            "socks5OutboundConfigure",
            stdin_text="default\nproxy.example.test\n1080\nu1\np1\n\n\n",
        )
        cfg = read_cfg()
        st = read_settings()
        cfg_mode = stat.S_IMODE(config_path.stat().st_mode)
        st_mode = stat.S_IMODE(settings_path.stat().st_mode)
        priv_rule = cfg["routing"]["rules"][-1]
        socks_ob = cfg["outbounds"][0]
        record(
            res.returncode == 0
            and socks_ob["tag"] == "socks5-exit"
            and socks_ob["streamSettings"]["sockopt"] == {"tcpFastOpen": True, "tcpNoDelay": True}
            and priv_rule.get("ruleTag") == "socks5-exit-private-direct"
            and priv_rule.get("outboundTag") == "direct"
            and "127.0.0.0/8" in priv_rule.get("ip", [])
            and "192.168.0.0/16" in priv_rule.get("ip", [])
            and st_mode == 0o600
            and cfg_mode == 0o644,
            "Global default mode puts socks5-exit at outbounds[0], adds private CIDR bypass, and sets 0600/0644 perms",
        )

        # Critical bug fix check: legacy single-object OR named 'default' WITH domains must NOT take outbounds[0]
        legacy_with_domains = {
            "enabled": True,
            "server": {"address": "proxy.example.test", "port": 1080, "username": "u", "password": "p"},
            "domains": ["domain:example.com"],
        }
        reset(settings=legacy_with_domains)
        res = run_bash(harness, env, "socks5OutboundSync")
        cfg = read_cfg()
        record(
            res.returncode == 0
            and [o["tag"] for o in cfg["outbounds"]] == ["direct", "custom", "socks5-exit"]
            and cfg["routing"]["rules"][0] == {
                "type": "field",
                "ruleTag": "socks5-domain-default",
                "domain": ["domain:example.com"],
                "outboundTag": "socks5-exit",
            }
            and not any(r.get("ruleTag") == "socks5-exit-private-direct" for r in cfg["routing"]["rules"]),
            "Default profile WITH domains keeps direct at outbounds[0] and routes only matched domains",
        )

        # 3. Multi-profile Domain + IP split routing & domainStrategy management
        multi_settings = {
            "profiles": [
                {
                    "name": "sg",
                    "enabled": True,
                    "server": {"address": "sg.example.test", "port": 1080, "username": "sg-u", "password": "sg-secret"},
                    "domains": ["geosite:netflix"],
                    "ips": ["1.1.1.1/32", "geoip:telegram"],
                },
                {
                    "name": "us",
                    "enabled": True,
                    "server": {"address": "us.example.test", "port": 1081, "username": "", "password": ""},
                    "domains": [],
                    "ips": ["geoip:us"],
                },
            ]
        }
        reset(settings=multi_settings)
        res = run_bash(harness, env, "socks5OutboundApply_cand(){ local c=$(mktemp); cat > \"$c\"; socks5OutboundApply \"$c\"; }; socks5OutboundApply_cand", stdin_text=json.dumps(multi_settings))
        cfg = read_cfg()
        rule_tags = [(r.get("ruleTag"), r.get("outboundTag")) for r in cfg["routing"]["rules"]]
        record(
            res.returncode == 0
            and [o["tag"] for o in cfg["outbounds"]] == ["direct", "custom", "socks5-exit-sg", "socks5-exit-us"]
            and rule_tags[:3] == [
                ("socks5-domain-sg", "socks5-exit-sg"),
                ("socks5-ip-sg", "socks5-exit-sg"),
                ("socks5-ip-us", "socks5-exit-us"),
            ]
            and rule_tags[3] == (None, "custom")
            and cfg["routing"].get("domainStrategy") == "IPIfNonMatch"
            and (vtrui_dir / "geosite.dat").is_file()
            and (vtrui_dir / "geoip.dat").is_file()
            and stat.S_IMODE((vtrui_dir / "geosite.dat").stat().st_mode) == 0o644
            and stat.S_IMODE((vtrui_dir / "geoip.dat").stat().st_mode) == 0o644,
            "Multi-profile domain+IP & IP-only rules set IPIfNonMatch and auto-provision 0644 geoip.dat + geosite.dat",
        )

        # Disabling IP rules removes managed IPIfNonMatch strategy
        res = run_bash(harness, env, "socks5OutboundDisable")
        cfg = read_cfg()
        record(
            res.returncode == 0
            and [o["tag"] for o in cfg["outbounds"]] == ["direct", "custom"]
            and "domainStrategy" not in cfg["routing"],
            "Disabling all profiles removes SOCKS5 outbounds/rules and cleans up managed IPIfNonMatch",
        )

        # Preserve pre-existing custom IPOnDemand strategy
        custom_strat_cfg = json.loads(json.dumps(base_config))
        custom_strat_cfg["routing"]["domainStrategy"] = "IPOnDemand"
        reset(cfg=custom_strat_cfg, settings=multi_settings)
        run_bash(harness, env, "socks5OutboundSync")
        cfg_on = read_cfg()
        run_bash(harness, env, "socks5OutboundDisable")
        cfg_off = read_cfg()
        record(
            cfg_on["routing"].get("domainStrategy") == "IPOnDemand"
            and cfg_off["routing"].get("domainStrategy") == "IPOnDemand",
            "User-configured IPOnDemand domainStrategy is preserved across enable and disable",
        )

        # 4. Endpoint parser, IPv6 validator, presets, '-' clear & in-place edit
        parse_checks = [
            ("socks5://us%40er:p%3Ass%25w@proxy.example.test:1080#my-node", ("uri", "proxy.example.test", "1080", "us@er", "p:ss%w", True)),
            ("socks://dXNlcjpwYXNz@1.2.3.4:1080#b64", ("uri", "1.2.3.4", "1080", "user", "pass", True)),
            ("1.2.3.4:1080:u:p:extra", ("tuple4", "1.2.3.4", "1080", "u", "p:extra", True)),
            ("[2001:db8::1]:1080", ("hostport", "2001:db8::1", "1080", "", "", False)),
            ("2001:db8::1", ("host", "2001:db8::1", "", "", "", False)),
            ("::192.0.2.1", ("host", "::192.0.2.1", "", "", "", False)),
        ]
        parse_ok = True
        for raw_ep, exp in parse_checks:
            r = subprocess.run(
                ["/bin/bash", "-c", f'{harness}\nsocks5OutboundParseEndpoint "$1"', "_", raw_ep],
                text=True, capture_output=True, env=env, cwd=str(ROOT), timeout=10
            )
            if r.returncode != 0:
                parse_ok = False
                break
            got = json.loads(r.stdout)
            if (got["kind"], got["address"], got["port"], got["username"], got["password"], got["hasAuth"]) != exp:
                parse_ok = False
                break
        bad_eps = ["http://1.2.3.4:1080", "socks5://useronly@1.2.3.4:1080", "socks5://1.2.3.4:99999", "12345::1", "1:2:3::4:"]
        for bad_ep in bad_eps:
            r = subprocess.run(
                ["/bin/bash", "-c", f'{harness}\nsocks5OutboundParseEndpoint "$1"', "_", bad_ep],
                text=True, capture_output=True, env=env, cwd=str(ROOT), timeout=10
            )
            if r.returncode == 0:
                parse_ok = False
                break
        record(parse_ok, "socks5OutboundParseEndpoint handles URI/%HH/base64/tuple4/IPv6 and rejects malformed inputs")

        # Presets & order-preserving deduplication
        r_ai = run_bash(harness, env, 'socks5OutboundExpandRules domain "@ai,domain:claude.ai,geosite:openai,@tg"')
        r_ip = run_bash(harness, env, 'socks5OutboundExpandRules ip "@tg,1.1.1.1/32,geoip:telegram"')
        r_bad = run_bash(harness, env, 'socks5OutboundExpandRules domain "@nonexistent"')
        ai_list = json.loads(r_ai.stdout) if r_ai.returncode == 0 else []
        ip_list = json.loads(r_ip.stdout) if r_ip.returncode == 0 else []
        record(
            r_ai.returncode == 0
            and ai_list[0] == "geosite:openai"
            and ai_list.count("domain:claude.ai") == 1
            and ai_list[-1] == "geosite:telegram"
            and r_ip.returncode == 0
            and ip_list == ["geoip:telegram", "1.1.1.1/32"]
            and r_bad.returncode != 0,
            "Presets @ai/@stream/@tg expand, deduplicate preserving order, and reject unknown presets",
        )

        # In-place edit + '-' clearing auth, domains, and IP rules
        reset()
        run_bash(harness, env, "socks5OutboundConfigure", stdin_text="first\n1.2.3.4:1080:u1:p1\n@tg\n1.1.1.1/32\n")
        run_bash(harness, env, "socks5OutboundConfigure", stdin_text="second\nsocks5://[2001:db8::1]:1081#tag\ndomain:example.org\n\n")
        # Edit 'first' keeping host:port, clearing auth with '-', keeping domain, clearing ip with '-'
        res = run_bash(harness, env, "socks5OutboundConfigure", stdin_text="first\n1.2.3.4:1080\n-\n\n-\n")
        st = read_settings()
        cfg = read_cfg()
        r_v6_status = run_bash(harness, env, "socks5OutboundStatus")
        record(
            res.returncode == 0
            and [p["name"] for p in st["profiles"]] == ["first", "second"]
            and st["profiles"][0]["server"]["username"] == ""
            and st["profiles"][0]["server"]["password"] == ""
            and st["profiles"][0]["domains"] == ["geosite:telegram"]
            and st["profiles"][0]["ips"] == []
            and [r.get("ruleTag") for r in cfg["routing"]["rules"][:2]] == ["socks5-domain-first", "socks5-domain-second"]
            and "[2001:db8::1]:1081" in r_v6_status.stdout,
            "In-place edit preserves profile priority order, '-' clears auth/IPs, and status formats IPv6",
        )

        # 5. Toggle single profile, Reorder profiles, Delete single/all profiles
        res_t1 = run_bash(harness, env, "socks5OutboundToggle", stdin_text="1\n")
        cfg_t1 = read_cfg()
        res_te = run_bash(harness, env, "socks5OutboundToggle", stdin_text="e\n")
        cfg_te = read_cfg()
        res_ro = run_bash(harness, env, "socks5OutboundReorder", stdin_text="2\n1\n")
        st_ro = read_settings()
        cfg_ro = read_cfg()
        res_del1 = run_bash(harness, env, "socks5OutboundDelete", stdin_text="1\n")
        st_del1 = read_settings()
        res_dela = run_bash(harness, env, "socks5OutboundDelete", stdin_text="a\n")
        st_dela = read_settings()
        record(
            res_t1.returncode == 0
            and [o["tag"] for o in cfg_t1["outbounds"]] == ["direct", "custom", "socks5-exit-second"]
            and res_te.returncode == 0
            and [o["tag"] for o in cfg_te["outbounds"]] == ["direct", "custom", "socks5-exit-first", "socks5-exit-second"]
            and res_ro.returncode == 0
            and [p["name"] for p in st_ro["profiles"]] == ["second", "first"]
            and [r.get("ruleTag") for r in cfg_ro["routing"]["rules"][:2]] == ["socks5-domain-second", "socks5-domain-first"]
            and res_del1.returncode == 0
            and [p["name"] for p in st_del1["profiles"]] == ["first"]
            and res_dela.returncode == 0
            and st_dela["profiles"] == [],
            "Single-profile toggle, priority reorder, and single/all delete work cleanly",
        )

        # 6. Validation guards (half creds, bad IP/CIDR, duplicate rules, self-loop) & Rollback
        reset()
        invalid_payloads = [
            ("half credentials", {"profiles": [{"name": "a", "enabled": True, "server": {"address": "1.2.3.4", "port": 1080, "username": "only", "password": ""}, "domains": ["domain:a.com"], "ips": []}]}),
            ("bad IPv4", {"profiles": [{"name": "a", "enabled": True, "server": {"address": "999.1.1.1", "port": 1080, "username": "", "password": ""}, "domains": ["domain:a.com"], "ips": []}]}),
            ("bad CIDR", {"profiles": [{"name": "a", "enabled": True, "server": {"address": "1.2.3.4", "port": 1080, "username": "", "password": ""}, "domains": [], "ips": ["1.2.3.4/33"]}]}),
            ("duplicate rules", {"profiles": [
                {"name": "a", "enabled": True, "server": {"address": "1.2.3.4", "port": 1080, "username": "", "password": ""}, "domains": ["domain:dup.test"], "ips": []},
                {"name": "b", "enabled": True, "server": {"address": "2.3.4.5", "port": 1080, "username": "", "password": ""}, "domains": ["domain:dup.test"], "ips": []},
            ]}),
            ("self-loop loopback", {"profiles": [{"name": "a", "enabled": True, "server": {"address": "127.0.0.1", "port": 1080, "username": "", "password": ""}, "domains": ["domain:a.com"], "ips": []}]}),
            ("self-loop ::1", {"profiles": [{"name": "a", "enabled": True, "server": {"address": "::1", "port": 1080, "username": "", "password": ""}, "domains": ["domain:a.com"], "ips": []}]}),
        ]
        val_ok = True
        for label, payload in invalid_payloads:
            r = run_bash(harness, env, 'c=$(mktemp); cat > "$c"; socks5OutboundApply "$c"', stdin_text=json.dumps(payload))
            if r.returncode == 0:
                val_ok = False
                print(f"  Expected validation failure for {label}", file=sys.stderr)
                break
        # Remote host on port 1080 must NOT trigger self-loop
        r_remote = run_bash(
            harness,
            env,
            'c=$(mktemp); cat > "$c"; socks5OutboundApply "$c"',
            stdin_text=json.dumps({"profiles": [{"name": "a", "enabled": True, "server": {"address": "remote.example.test", "port": 1080, "username": "", "password": ""}, "domains": ["domain:a.com"], "ips": []}]}),
        )
        record(val_ok and r_remote.returncode == 0, "Validation blocks half creds, bad IP/CIDR, duplicates, and self-loop while allowing remote same-port server")

        # Rollback on vtrui run -test failure
        prev_settings = read_settings()
        prev_cfg = read_cfg()
        fake_vtrui = vtrui_dir / "vtrui"
        fake_vtrui.write_text("#!/bin/bash\necho 'simulated xray parse error' >&2\nexit 1\n", encoding="utf-8")
        fake_vtrui.chmod(0o755)
        failing_payload = {"profiles": [{"name": "b", "enabled": True, "server": {"address": "8.8.8.8", "port": 1080, "username": "", "password": ""}, "domains": ["domain:b.com"], "ips": []}]}
        r_fail = run_bash(harness, env, 'c=$(mktemp); cat > "$c"; socks5OutboundApply "$c"', stdin_text=json.dumps(failing_payload))
        fake_vtrui.unlink(missing_ok=True)
        record(
            r_fail.returncode != 0
            and "simulated xray parse error" in r_fail.stderr
            and read_settings() == prev_settings
            and read_cfg() == prev_cfg
            and stat.S_IMODE(settings_path.stat().st_mode) == 0o600
            and stat.S_IMODE(config_path.stat().st_mode) == 0o644,
            "vtrui test failure prints diagnostic stderr and atomically rolls back settings (0600) and config (0644)",
        )

        # 7. Domain-only geosite extracts ONLY geosite.dat
        reset()
        r_geo = run_bash(harness, env, "socks5OutboundConfigure", stdin_text="stream\nproxy.example.test\n1080\n\ngeosite:netflix\n\n")
        cfg_geo = read_cfg()
        record(
            r_geo.returncode == 0
            and (vtrui_dir / "geosite.dat").is_file()
            and not (vtrui_dir / "geoip.dat").exists()
            and stat.S_IMODE((vtrui_dir / "geosite.dat").stat().st_mode) == 0o644
            and "domainStrategy" not in cfg_geo["routing"],
            "geosite-only rule auto-provisions 0644 geosite.dat without unnecessary geoip.dat or IPIfNonMatch",
        )

        # 8. Live loopback SOCKS5 handshake & masked status check
        http_srv = ThreadedTCPServer(("127.0.0.1", 0), MiniTraceHandler)
        http_port = http_srv.server_address[1]
        threading.Thread(target=http_srv.serve_forever, daemon=True).start()
        socks_srv, socks_port = start_loopback_socks5_server("live-user", "live-s3cr3t-pw")
        try:
            live_env = env.copy()
            live_env["SOCKS5_TEST_URL"] = f"http://127.0.0.1:{http_port}/cdn-cgi/trace"
            # Note: test using localhost/127.0.0.1 on socks_port (different from base_config inbound-socks5 port 1080)
            live_settings = {
                "profiles": [
                    {
                        "name": "live",
                        "enabled": True,
                        "server": {"address": "127.0.0.1", "port": socks_port, "username": "live-user", "password": "live-s3cr3t-pw"},
                        "domains": ["domain:example.com"],
                        "ips": [],
                    }
                ]
            }
            reset(settings=live_settings)
            r_status = run_bash(harness, live_env, "socks5OutboundStatus")
            r_good = run_bash(harness, live_env, 'socks5OutboundTestProfile "$(socks5OutboundProfile live)"')
            bad_profile = json.dumps({
                "name": "live",
                "enabled": True,
                "server": {"address": "127.0.0.1", "port": socks_port, "username": "live-user", "password": "wrong-password"},
                "domains": ["domain:example.com"],
                "ips": [],
            })
            r_bad = subprocess.run(
                ["/bin/bash", "-c", f'{harness}\nsocks5OutboundTestProfile "$1"', "_", bad_profile],
                text=True, capture_output=True, env=live_env, cwd=str(ROOT), timeout=15
            )
            record(
                r_status.returncode == 0
                and "live-user" in r_status.stdout
                and "live-s3cr3t-pw" not in r_status.stdout
                and r_good.returncode == 0
                and "203.0.113.88" in r_good.stdout
                and "SG" in r_good.stdout
                and "RTT=" in r_good.stdout
                and "live-s3cr3t-pw" not in r_good.stdout
                and r_bad.returncode != 0
                and "wrong-password" not in r_bad.stdout,
                "Live loopback SOCKS5 probe verifies auth, exit IP/country/RTT, rejects bad auth, and never leaks password",
            )
        finally:
            socks_srv.shutdown()
            socks_srv.server_close()
            http_srv.shutdown()
            http_srv.server_close()

    print(f"Summary: passed={PASSED} failed={FAILED}")
    return 0 if FAILED == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
