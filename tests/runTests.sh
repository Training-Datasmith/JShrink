#!/usr/bin/env sh
set -e

echo 'Running full system test (PHP 5.3 src lint + PHP 7.2 suite).'
exec ./scripts/system-test.sh
