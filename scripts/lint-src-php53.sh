#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# Official library/php:5.3 uses schema v1 (unpullable on current Docker). This digest is the
# manifest-v2 republication of the same PHP 5.3.29 image (ktomk/pipelines:docker.io-php-5.3).
PHP53_IMAGE='docker.io/ktomk/pipelines:docker.io-php-5.3@sha256:d03f3d4bd20f99930c923b9f931dd2dd71ac3e51cce8e8ed989b4994561b991c'
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
DOCKER=(docker)
if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
  DOCKER=(sudo docker)
fi

mapfile -t FILES < <(find "${ROOT}/src" -name '*.php' -type f | sort)
if [[ "${#FILES[@]}" -eq 0 ]]; then
  echo 'lint-src-php53: no PHP files under src/' >&2
  exit 1
fi

LIST_FILE="$(mktemp)"
printf '%s\n' "${FILES[@]}" >"${LIST_FILE}"

INNER="
set -e
while IFS= read -r f; do
  rel=\"\${f#${ROOT}/}\"
  if ! php -l \"\$f\" >/tmp/lint.out 2>&1; then
    cat /tmp/lint.out >&2
    exit 1
  fi
  if ! grep -Fq 'No syntax errors detected' /tmp/lint.out; then
    cat /tmp/lint.out >&2
    exit 1
  fi
  echo \"lint-src-php53: \${rel} OK\"
done < /tmp/src-php-files.txt
echo 'lint-src-php53: all src PHP files pass php -l on PHP 5.3.29.'
"

"${DOCKER[@]}" pull "${PHP53_IMAGE}"
"${DOCKER[@]}" run --rm \
  -v "${ROOT}:${ROOT}" \
  -v "${LIST_FILE}:/tmp/src-php-files.txt:ro" \
  -w "${ROOT}" \
  "${PHP53_IMAGE}" \
  bash -lc "${INNER}"

rm -f "${LIST_FILE}"
