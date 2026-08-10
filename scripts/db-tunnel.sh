#!/usr/bin/env bash
# Local SSH tunnel: Mac 127.0.0.1:3307 -> remote 127.0.0.1:3306 via SSH.
# Does not store passwords or private keys. Uses your existing SSH agent/key.

set -euo pipefail

REMOTE_HOST="${REMOTE_HOST:-31.59.184.74}"
SSH_USERNAME="${SSH_USERNAME:-root}"
LOCAL_PORT="${LOCAL_PORT:-3307}"
REMOTE_MYSQL_HOST="${REMOTE_MYSQL_HOST:-127.0.0.1}"
REMOTE_MYSQL_PORT="${REMOTE_MYSQL_PORT:-3306}"
SSH_IDENTITY_FILE="${SSH_IDENTITY_FILE:-}"

SCRIPT_NAME="$(basename "$0")"
PID_FILE="${PID_FILE:-${TMPDIR:-/tmp}/invent-db-tunnel-${LOCAL_PORT}.pid}"
LOG_FILE="${LOG_FILE:-${TMPDIR:-/tmp}/invent-db-tunnel-${LOCAL_PORT}.log}"

usage() {
  cat <<EOF
Usage: ./scripts/db-tunnel.sh [start|stop|status|foreground]

  start       Start tunnel in background (default)
  stop        Stop background tunnel
  status      Show whether local port ${LOCAL_PORT} is listening
  foreground  Run tunnel in foreground (Ctrl+C to stop)

Environment overrides:
  SSH_USERNAME          SSH user (default: root)
  REMOTE_HOST           Server IP/host (default: 31.59.184.74)
  LOCAL_PORT            Local listen port (default: 3307)
  REMOTE_MYSQL_HOST     MySQL host as seen on server (default: 127.0.0.1)
  REMOTE_MYSQL_PORT     MySQL port on server (default: 3306)
  SSH_IDENTITY_FILE     Optional path to private key (not stored in repo)

Examples:
  ./scripts/db-tunnel.sh
  SSH_USERNAME=root ./scripts/db-tunnel.sh start
  ./scripts/db-tunnel.sh stop
EOF
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $1" >&2
    exit 1
  fi
}

port_pids() {
  if command -v lsof >/dev/null 2>&1; then
    lsof -nP -iTCP:"${LOCAL_PORT}" -sTCP:LISTEN -t 2>/dev/null || true
  fi
}

is_listening() {
  local pids
  pids="$(port_pids)"
  [ -n "${pids}" ]
}

ssh_identity_opt() {
  # Prints optional -i args; empty when SSH_IDENTITY_FILE is unset.
  if [ -n "${SSH_IDENTITY_FILE}" ]; then
    if [ ! -f "${SSH_IDENTITY_FILE}" ]; then
      echo "ERROR: SSH_IDENTITY_FILE not found: ${SSH_IDENTITY_FILE}" >&2
      exit 1
    fi
    printf '%s\n' -i "${SSH_IDENTITY_FILE}"
  fi
}

ssh_target=""

preflight_auth() {
  local err_file
  echo "Checking SSH auth to ${ssh_target} (key-based, non-interactive)..."
  err_file="$(mktemp -t invent-db-tunnel-ssh-err.XXXXXX)"
  # shellcheck disable=SC2046
  if ! ssh \
    -o ServerAliveInterval=60 \
    -o ServerAliveCountMax=3 \
    -o StrictHostKeyChecking=yes \
    -o PasswordAuthentication=no \
    -o BatchMode=yes \
    -o ConnectTimeout=15 \
    $(ssh_identity_opt) \
    "${ssh_target}" true 2>"${err_file}"; then
    echo "ERROR: SSH authentication failed for ${ssh_target}." >&2
    echo "Details:" >&2
    cat "${err_file}" >&2 || true
    rm -f "${err_file}"
    cat >&2 <<'EOF'

Fix (on the server, using your existing interactive SSH session):
  1. Copy your local public key:
       cat ~/.ssh/id_ed25519.pub
  2. On the server as the SSH user, append that line to:
       ~/.ssh/authorized_keys
     then:
       chmod 700 ~/.ssh
       chmod 600 ~/.ssh/authorized_keys
  3. Re-test from Mac:
       ssh -o BatchMode=yes root@31.59.184.74 'echo SSH_OK'

Do not put passwords or private keys in this repository.
EOF
    exit 1
  fi
  rm -f "${err_file}"
  echo "SSH auth OK."
}

start_foreground() {
  require_cmd ssh
  ssh_target="${SSH_USERNAME}@${REMOTE_HOST}"
  if is_listening; then
    echo "ERROR: local port ${LOCAL_PORT} is already in use:" >&2
    lsof -nP -iTCP:"${LOCAL_PORT}" -sTCP:LISTEN || true
    exit 1
  fi
  preflight_auth
  echo "Starting SSH tunnel (foreground):"
  echo "  127.0.0.1:${LOCAL_PORT} -> ${ssh_target} -> ${REMOTE_MYSQL_HOST}:${REMOTE_MYSQL_PORT}"
  # shellcheck disable=SC2046
  exec ssh \
    -N \
    -L "${LOCAL_PORT}:${REMOTE_MYSQL_HOST}:${REMOTE_MYSQL_PORT}" \
    -o ExitOnForwardFailure=yes \
    -o ServerAliveInterval=60 \
    -o ServerAliveCountMax=3 \
    -o StrictHostKeyChecking=yes \
    -o PasswordAuthentication=no \
    -o BatchMode=yes \
    -o ConnectTimeout=15 \
    $(ssh_identity_opt) \
    "${ssh_target}"
}

start_background() {
  local pid
  require_cmd ssh
  ssh_target="${SSH_USERNAME}@${REMOTE_HOST}"
  if is_listening; then
    echo "Tunnel already listening on 127.0.0.1:${LOCAL_PORT}"
    lsof -nP -iTCP:"${LOCAL_PORT}" -sTCP:LISTEN || true
    exit 0
  fi
  preflight_auth
  echo "Starting SSH tunnel (background):"
  echo "  127.0.0.1:${LOCAL_PORT} -> ${ssh_target} -> ${REMOTE_MYSQL_HOST}:${REMOTE_MYSQL_PORT}"
  # shellcheck disable=SC2046
  nohup ssh \
    -N \
    -L "${LOCAL_PORT}:${REMOTE_MYSQL_HOST}:${REMOTE_MYSQL_PORT}" \
    -o ExitOnForwardFailure=yes \
    -o ServerAliveInterval=60 \
    -o ServerAliveCountMax=3 \
    -o StrictHostKeyChecking=yes \
    -o PasswordAuthentication=no \
    -o BatchMode=yes \
    -o ConnectTimeout=15 \
    $(ssh_identity_opt) \
    "${ssh_target}" >"${LOG_FILE}" 2>&1 &
  pid=$!
  echo "${pid}" >"${PID_FILE}"
  sleep 1
  if ! kill -0 "${pid}" 2>/dev/null; then
    echo "ERROR: tunnel process exited immediately. Log:" >&2
    cat "${LOG_FILE}" >&2 || true
    rm -f "${PID_FILE}"
    exit 1
  fi
  if ! is_listening; then
    echo "ERROR: tunnel process started (pid ${pid}) but port ${LOCAL_PORT} is not listening." >&2
    echo "Log:" >&2
    cat "${LOG_FILE}" >&2 || true
    kill "${pid}" 2>/dev/null || true
    rm -f "${PID_FILE}"
    exit 1
  fi
  echo "Tunnel running (pid ${pid}). Check with: lsof -i :${LOCAL_PORT}"
  echo "Stop with: ./scripts/db-tunnel.sh stop"
}

stop_tunnel() {
  local pids
  pids="$(port_pids)"
  if [ -z "${pids}" ]; then
    echo "No listener on port ${LOCAL_PORT}."
    rm -f "${PID_FILE}"
    exit 0
  fi
  echo "Stopping tunnel on port ${LOCAL_PORT} (pids: ${pids})..."
  # shellcheck disable=SC2086
  kill ${pids} 2>/dev/null || true
  sleep 1
  pids="$(port_pids)"
  if [ -n "${pids}" ]; then
    echo "Force-stopping pids: ${pids}"
    # shellcheck disable=SC2086
    kill -9 ${pids} 2>/dev/null || true
  fi
  rm -f "${PID_FILE}"
  echo "Stopped."
}

status_tunnel() {
  if is_listening; then
    echo "Tunnel UP on 127.0.0.1:${LOCAL_PORT}"
    lsof -nP -iTCP:"${LOCAL_PORT}" -sTCP:LISTEN || true
    exit 0
  fi
  echo "Tunnel DOWN (nothing listening on 127.0.0.1:${LOCAL_PORT})"
  exit 1
}

cmd="${1:-start}"
case "${cmd}" in
  -h|--help|help)
    usage
    ;;
  start)
    start_background
    ;;
  stop)
    stop_tunnel
    ;;
  status)
    status_tunnel
    ;;
  foreground|fg)
    start_foreground
    ;;
  *)
    echo "Unknown command: ${cmd}" >&2
    usage >&2
    exit 1
    ;;
esac
