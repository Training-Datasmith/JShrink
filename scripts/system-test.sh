#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=scripts/gate-helpers.sh
source "${REPO_ROOT}/scripts/gate-helpers.sh"

USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
IMAGE_TAG="jshrink-system-test:remote-test-2026-11-09"
INNER_OUT="${REPO_ROOT}/.system-test/system-test-inner.out"

docker_cmd() {
  if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
    sudo docker "$@"
  else
    docker "$@"
  fi
}

mkdir -p "${REPO_ROOT}/.system-test"

chmod +x "${REPO_ROOT}/scripts/lint-src-php53.sh"
"${REPO_ROOT}/scripts/lint-src-php53.sh"

docker_cmd build -f "${REPO_ROOT}/docker/system-test/Dockerfile" -t "${IMAGE_TAG}" "${REPO_ROOT}"

echo '=== apt sources in system-test image ==='
docker_cmd run --rm "${IMAGE_TAG}" cat /etc/apt/sources.list

: >"${INNER_OUT}"
docker_cmd run --rm \
  -v "${REPO_ROOT}:/app" \
  -w /app \
  "${IMAGE_TAG}" \
  bash /app/scripts/system-test-inner.sh 2>&1 | tee "${INNER_OUT}"

STRIPPED="$(mktemp)"
gate_strip_ansi_file "${INNER_OUT}" "${STRIPPED}"
gate_reject_php_diagnostics "${STRIPPED}" "system-test-inner" || exit 1

gate_require_line "${STRIPPED}" '^OK \(27 tests, 30 assertions\)$' "system-test" || exit 1
gate_require_line "${STRIPPED}" '^check-phpunit-baseline: OK \(27 tests, 30 assertions\)\.$' "system-test" || exit 1
gate_require_line "${STRIPPED}" '^php-cs-fixer: no files need fixing\.$' "system-test" || exit 1
gate_require_line "${STRIPPED}" '^system-test-inner: all steps passed\.$' "system-test" || exit 1

rm -f "${STRIPPED}"

echo 'system-test: all steps passed.'
