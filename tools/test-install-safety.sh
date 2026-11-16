#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
CLIENT_FILE="$ROOT_DIR/client"
SERVER_FILE="$ROOT_DIR/server"
KERNEL_DIR="$ROOT_DIR/resource/kernel"
UISCRIPT_DIR="$ROOT_DIR/resource/client/ui-script"
UIWEB_DIR="$ROOT_DIR/resource/client/ui-web"
ARCHIVE_ZIP="$ROOT_DIR/resource/client/Archive.zip"
ARCHIVE_SHA="$ROOT_DIR/resource/client/Archive.zip.sha256sum"

TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

pass_count=0
fail_count=0

pass() {
  echo "[PASS] $1"
  pass_count=$((pass_count + 1))
}

fail() {
  echo "[FAIL] $1" >&2
  fail_count=$((fail_count + 1))
}

assert_eq() {
  local expected="$1"
  local actual="$2"
  local desc="$3"
  if [[ "$expected" == "$actual" ]]; then
    pass "$desc"
  else
    fail "$desc (expected='$expected', actual='$actual')"
  fi
}

extract_fn() {
  local file="$1"
  local fn="$2"
  awk -v fn="$fn" '
    $0 ~ "^" fn "\\(\\)\\{" { in_fn=1 }
    in_fn { print }
    in_fn && /^\}$/ { exit }
  ' "$file"
}

sha256_file() {
  local file="$1"
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$file" | awk '{print $1}'
  else
    shasum -a 256 "$file" | awk '{print $1}'
  fi
}

test_helper_set() {
  local label="$1"
  local src_file="$2"
  local include_cidr="$3"
  local helper_lib="$TMP_DIR/${label}_helpers.sh"

  {
    extract_fn "$src_file" "supportsSystem"
    extract_fn "$src_file" "normalizeSha256"
    extract_fn "$src_file" "checkSum"
    extract_fn "$src_file" "isElfBinary"
    if [[ "$include_cidr" == "1" ]]; then
      extract_fn "$src_file" "cidr_network"
    fi
  } > "$helper_lib"

  local os_deb12="$TMP_DIR/os_deb12_${label}"
  local os_deb13="$TMP_DIR/os_deb13_${label}"
  local os_deb11="$TMP_DIR/os_deb11_${label}"
  local os_ubuntu="$TMP_DIR/os_ubuntu_${label}"
  printf 'ID=debian\nVERSION_ID="12"\n' > "$os_deb12"
  printf 'ID=debian\nVERSION_ID="13"\n' > "$os_deb13"
  printf 'ID=debian\nVERSION_ID="11"\n' > "$os_deb11"
  printf 'ID=ubuntu\nVERSION_ID="22.04"\n' > "$os_ubuntu"

  local ok_out
  ok_out=$(
    bash -c '
      set -euo pipefail
      source "$1"
      supportsSystem "$2" amd64
      supportsSystem "$2" arm64
      supportsSystem "$3" amd64
      supportsSystem "$3" arm64
      echo "SUPP_OK"
    ' _ "$helper_lib" "$os_deb12" "$os_deb13"
  )
  assert_eq "SUPP_OK" "$ok_out" "$label: supportsSystem accepts Debian 12/13 amd64/arm64"

  for bad_case in "$os_deb11:amd64" "$os_ubuntu:amd64" "$os_deb13:i386" "$TMP_DIR/missing_os:amd64" "$os_deb12:"; do
    local b_os="${bad_case%%:*}"
    local b_arch="${bad_case#*:}"
    if bash -c 'source "$1"; supportsSystem "$2" "$3"' _ "$helper_lib" "$b_os" "$b_arch"; then
      fail "$label: supportsSystem should reject '$(basename "$b_os")/$b_arch'"
    else
      pass "$label: supportsSystem rejects '$(basename "$b_os")/$b_arch'"
    fi
  done

  local sample_file="$TMP_DIR/sample_${label}.bin"
  printf 'de_GWD verified payload\n' > "$sample_file"
  local actual_sha upper_sha
  actual_sha=$(sha256_file "$sample_file")
  upper_sha=$(printf '%s' "$actual_sha" | tr 'a-f' 'A-F')

  local norm_actual
  norm_actual=$(bash -c 'source "$1"; normalizeSha256 "$2  ignored_name.bin"' _ "$helper_lib" "$upper_sha")
  assert_eq "$actual_sha" "$norm_actual" "$label: normalizeSha256 normalizes uppercase hash + filename"

  local norm_bad
  norm_bad=$(bash -c 'source "$1"; normalizeSha256 "<html>not-a-hash</html>" || true' _ "$helper_lib")
  assert_eq "" "$norm_bad" "$label: normalizeSha256 rejects non-hex HTML string"

  local c_res
  c_res=$(bash -c 'source "$1"; checkSum "$2" "$3"' _ "$helper_lib" "$sample_file" "$upper_sha")
  assert_eq "true" "$c_res" "$label: checkSum returns true on matching hash"

  for bad_hash in "" "0000000000000000000000000000000000000000000000000000000000000000" "<html>404</html>" "abc123"; do
    c_res=$(bash -c 'source "$1"; checkSum "$2" "$3"' _ "$helper_lib" "$sample_file" "$bad_hash")
    assert_eq "false" "$c_res" "$label: checkSum rejects invalid/mismatched hash '${bad_hash:-<empty>}'"
  done

  c_res=$(bash -c 'source "$1"; checkSum "$2" "$3"' _ "$helper_lib" "$TMP_DIR/does_not_exist" "$actual_sha")
  assert_eq "false" "$c_res" "$label: checkSum rejects missing file"

  local elf_file="$TMP_DIR/fake_${label}.elf"
  printf '\x7fELF\x02\x01\x01\x00testbin' > "$elf_file"
  if bash -c 'source "$1"; isElfBinary "$2"' _ "$helper_lib" "$elf_file"; then
    pass "$label: isElfBinary accepts ELF magic header"
  else
    fail "$label: isElfBinary failed on valid ELF header"
  fi

  local html_file="$TMP_DIR/fake_${label}.html"
  printf '<!DOCTYPE html><html><body>502 Bad Gateway</body></html>\n' > "$html_file"
  for non_elf in "$html_file" "$sample_file" "$TMP_DIR/no_such_elf"; do
    if bash -c 'source "$1"; isElfBinary "$2"' _ "$helper_lib" "$non_elf"; then
      fail "$label: isElfBinary should reject $non_elf"
    else
      pass "$label: isElfBinary rejects non-ELF $(basename "$non_elf")"
    fi
  done

  if [[ "$include_cidr" == "1" ]]; then
    local cidr_out
    cidr_out=$(bash -c 'source "$1"; cidr_network "192.168.10.55/24"' _ "$helper_lib")
    assert_eq "192.168.10.0/24" "$cidr_out" "$label: cidr_network 192.168.10.55/24 -> 192.168.10.0/24"

    cidr_out=$(bash -c 'source "$1"; cidr_network "10.20.30.40/16"' _ "$helper_lib")
    assert_eq "10.20.0.0/16" "$cidr_out" "$label: cidr_network 10.20.30.40/16 -> 10.20.0.0/16"

    cidr_out=$(bash -c 'source "$1"; cidr_network "172.16.130.9/20"' _ "$helper_lib")
    assert_eq "172.16.128.0/20" "$cidr_out" "$label: cidr_network 172.16.130.9/20 -> 172.16.128.0/20"

    cidr_out=$(bash -c 'source "$1"; cidr_network "192.168.1.9"' _ "$helper_lib")
    assert_eq "192.168.1.0/24" "$cidr_out" "$label: cidr_network bare IP defaults cleanly"
  fi
}

test_helper_set "client" "$CLIENT_FILE" "1"
test_helper_set "server" "$SERVER_FILE" "0"

# Static safety assertions
if grep -rnE 'rm[[:space:]]+(-rf|-f)[[:space:]]+(/var/lib/(apt|dpkg)/.*lock|/var/cache/apt/archives/lock)' "$CLIENT_FILE" "$SERVER_FILE" "$KERNEL_DIR" "$UISCRIPT_DIR" >/dev/null 2>&1; then
  fail "Static check: found unsafe rm on apt/dpkg lock"
else
  pass "Static check: zero unsafe rm on apt/dpkg lock"
fi

if grep -rn -- '--no-check-certificate' "$CLIENT_FILE" "$SERVER_FILE" "$KERNEL_DIR" "$UISCRIPT_DIR" >/dev/null 2>&1; then
  fail "Static check: found wget --no-check-certificate"
else
  pass "Static check: zero wget --no-check-certificate"
fi

if grep -rnE 'chmod[[:space:]]+(0?644|0?777)[[:space:]]+.*\.key\b' "$CLIENT_FILE" "$SERVER_FILE" "$UISCRIPT_DIR" >/dev/null 2>&1; then
  fail "Static check: found overly permissive chmod on .key"
else
  pass "Static check: zero chmod 644/777 on .key files"
fi

if grep -rn '/var/log/auth\.log' "$SERVER_FILE" >/dev/null 2>&1; then
  fail "Static check: server references /var/log/auth.log deletion"
else
  pass "Static check: server does not delete /var/log/auth.log"
fi

check_kernel_fn=$(extract_fn "$SERVER_FILE" "checkKernel")
if printf '%s\n' "$check_kernel_fn" | grep -qE '(^|[;&|])[[:space:]]*(/sbin/)?reboot([[:space:]]|$|;)'; then
  fail "Static check: server checkKernel still triggers reboot"
else
  pass "Static check: server checkKernel does not trigger forced reboot"
fi

if grep -qE 'auth_request[[:space:]]+/act/authCheck\.php;' "$CLIENT_FILE" && grep -qF 'location ^~ /restore/' "$CLIENT_FILE"; then
  pass "Static check: nginx protects /ttyd with auth_request and denies /restore/"
else
  fail "Static check: nginx missing /ttyd auth_request or /restore/ deny rule"
fi

if [[ -e "$UIWEB_DIR/restore" ]]; then
  fail "Static check: deprecated ui-web/restore directory should not exist"
else
  pass "Static check: ui-web/restore directory does not exist"
fi

# Archive.zip integrity and sync check
if unzip -tq "$ARCHIVE_ZIP" >/dev/null 2>&1; then
  pass "Archive check: Archive.zip passes unzip -tq"
else
  fail "Archive check: Archive.zip failed unzip -tq"
fi

expected_archive_sha=$(awk '{print $1; exit}' "$ARCHIVE_SHA" | tr 'A-F' 'a-f')
actual_archive_sha=$(sha256_file "$ARCHIVE_ZIP")
assert_eq "$expected_archive_sha" "$actual_archive_sha" "Archive check: Archive.zip matches Archive.zip.sha256sum"

unzip -q "$ARCHIVE_ZIP" -d "$TMP_DIR/unzipped"
hash_tree() {
  local base_dir="$1"
  (
    cd "$base_dir"
    if command -v sha256sum >/dev/null 2>&1; then
      find ui-script ui-web -type f ! -name '.DS_Store' | sort | xargs sha256sum
    else
      find ui-script ui-web -type f ! -name '.DS_Store' | sort | xargs shasum -a 256
    fi
  )
}
hash_tree "$ROOT_DIR/resource/client" > "$TMP_DIR/src_hashes.txt"
hash_tree "$TMP_DIR/unzipped" > "$TMP_DIR/zip_hashes.txt"
if diff -u "$TMP_DIR/src_hashes.txt" "$TMP_DIR/zip_hashes.txt" >/dev/null 2>&1; then
  pass "Archive sync: all ui-script and ui-web files in Archive.zip match source SHA-256"
else
  fail "Archive sync: Archive.zip differs from ui-script/ui-web source files"
  diff -u "$TMP_DIR/src_hashes.txt" "$TMP_DIR/zip_hashes.txt" >&2 || true
fi

echo "Summary: passed=$pass_count failed=$fail_count"
[[ "$fail_count" -eq 0 ]]
