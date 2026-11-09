#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
PHP72_IMAGE='php:7.2-cli@sha256:42ffbc0798e4449bbd1e14fc4dcb87774aa1ad1900a09ef6a965bc0880aa2161'
IMAGE_TAG="jshrink-system-test:remote-test-2026-11-09"

docker_cmd() {
  if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
    sudo docker "$@"
  else
    docker "$@"
  fi
}

chmod +x "${REPO_ROOT}/scripts/lint-src-php53.sh"
"${REPO_ROOT}/scripts/lint-src-php53.sh"

docker_cmd build -f "${REPO_ROOT}/docker/system-test/Dockerfile" -t "${IMAGE_TAG}" "${REPO_ROOT}"

docker_cmd run --rm \
  -v "${REPO_ROOT}:/app" \
  -w /app \
  "${IMAGE_TAG}" \
  bash /app/scripts/system-test-inner.sh

echo 'system-test: all steps passed.'
