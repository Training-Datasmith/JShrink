#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=scripts/gate-helpers.sh
source "${ROOT}/scripts/gate-helpers.sh"

PHP53_IMAGE='docker.io/ktomk/pipelines:docker.io-php-5.3@sha256:d03f3d4bd20f99930c923b9f931dd2dd71ac3e51cce8e8ed989b4994561b991c'
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
DOCKER=(docker)
if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
  DOCKER=(sudo docker)
fi

mkdir -p "${ROOT}/.system-test"
LINT_OUT="${ROOT}/.system-test/lint-src-php53.out"
: >"${LINT_OUT}"

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
    cat /tmp/lint.out
    exit 1
  fi
  if ! grep -Fq 'No syntax errors detected' /tmp/lint.out; then
    cat /tmp/lint.out
    exit 1
  fi
  echo \"lint-src-php53: \${rel} OK\"
done < /tmp/src-php-files.txt
echo 'lint-src-php53: all src PHP files pass php -l on PHP 5.3.29.'
"

"${DOCKER[@]}" pull "${PHP53_IMAGE}" | tee -a "${LINT_OUT}"
"${DOCKER[@]}" run --rm \
  -v "${ROOT}:${ROOT}" \
  -v "${LIST_FILE}:/tmp/src-php-files.txt:ro" \
  -w "${ROOT}" \
  "${PHP53_IMAGE}" \
  bash -lc "${INNER}" 2>&1 | tee -a "${LINT_OUT}"

rm -f "${LIST_FILE}"

STRIPPED="$(mktemp)"
gate_strip_ansi_file "${LINT_OUT}" "${STRIPPED}"
gate_reject_php_diagnostics "${STRIPPED}" "lint-src-php53" || exit 1

for f in "${FILES[@]}"; do
  rel="${f#${ROOT}/}"
  rel_regex="$(printf '%s' "${rel}" | sed 's/[.[\*^$()+?{|]/\\&/g; s|/|\\/|g')"
  gate_require_line "${STRIPPED}" "^lint-src-php53: ${rel_regex} OK\$" "lint-src-php53" || exit 1
done

gate_require_line "${STRIPPED}" '^lint-src-php53: all src PHP files pass php -l on PHP 5\.3\.29\.$' "lint-src-php53" || exit 1

rm -f "${STRIPPED}"
