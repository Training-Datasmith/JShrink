#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1
ROOT="/app"
cd "${ROOT}"

fail_deprecation_output() {
  local label="$1"
  local file="$2"
  local stripped
  stripped="$(mktemp)"
  sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
  if grep -E '^(PHP )?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
    echo "${label}: output contains Warning, Deprecated, or Notice" >&2
    exit 1
  fi
  rm -f "${stripped}"
}

php -v | tee /tmp/php-version.txt
php -r '
$v = PHP_VERSION;
if (version_compare($v, "7.2.0", "<") || version_compare($v, "7.3.0", ">=")) {
    fwrite(STDERR, "PHP {$v} is not the expected 7.2.x runtime\n");
    exit(1);
}
'

for ext in dom json mbstring tokenizer xml; do
  if ! php -m | grep -qi "^${ext}$"; then
    echo "Missing required PHP extension: ${ext}" >&2
    exit 1
  fi
done

COMPOSER_URL="https://getcomposer.org/download/2.11.1/composer.phar"
COMPOSER_SHA256="6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

git config --global --add safe.directory "${ROOT}"

composer_out="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
  exit "${composer_status}"
fi
rm -f "${composer_out}"

php scripts/check-cluster-pins.php

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit --no-interaction --abandoned=report >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
  exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
  echo 'composer audit: missing exact success line' >&2
  exit 1
fi
rm -f "${audit_out}"

php scripts/check-rename-complete.php

phpunit_out="$(mktemp)"
set +e
vendor/bin/phpunit --colors=never >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
cat "${phpunit_out}"
fail_deprecation_output "phpunit" "${phpunit_out}"
if [[ ${phpunit_status} -ne 0 ]]; then
  exit "${phpunit_status}"
fi
php scripts/check-phpunit-baseline.php "${phpunit_out}"
rm -f "${phpunit_out}"

csfixer_dir="$(mktemp -d)"
cat >"${csfixer_dir}/composer.json" <<'EOF'
{
    "require": {
        "fabpot/php-cs-fixer": "0.4.0"
    }
}
EOF
(
  cd "${csfixer_dir}"
  php /tmp/composer.phar update --no-interaction --no-progress --no-ansi >/dev/null
)

phpcs_out="$(mktemp)"
set +e
"${csfixer_dir}/vendor/bin/php-cs-fixer" fix "${ROOT}" --level="all" -vv --dry-run >"${phpcs_out}" 2>&1
phpcs_status=$?
rm -rf "${csfixer_dir}"
set -e
cat "${phpcs_out}"
fail_deprecation_output "php-cs-fixer" "${phpcs_out}"
if [[ ${phpcs_status} -ne 0 ]]; then
  exit "${phpcs_status}"
fi
if grep -E '^[ ]*[0-9]+\) ' "${phpcs_out}" >/dev/null; then
  echo 'php-cs-fixer: files would be modified' >&2
  exit 1
fi
if grep -E '^! ' "${phpcs_out}" >/dev/null; then
  echo 'php-cs-fixer: PSR-0 diagnostic' >&2
  exit 1
fi
echo 'php-cs-fixer: no files need fixing.'
rm -f "${phpcs_out}"

echo 'system-test-inner: all steps passed.'
