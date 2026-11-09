#!/usr/bin/env bash
# Shared gate helpers: ANSI strip, PHP diagnostic rejection, anchored line checks.

gate_strip_ansi_file() {
  local infile="$1"
  local outfile="$2"
  sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${infile}" >"${outfile}"
}

gate_reject_php_diagnostics() {
  local stripped_file="$1"
  local label="${2:-output}"
  if grep -E '^(PHP )?(Warning|Deprecated|Notice):' "${stripped_file}" >/dev/null; then
  echo "${label}: contains Warning, Deprecated, or Notice line" >&2
    grep -E '^(PHP )?(Warning|Deprecated|Notice):' "${stripped_file}" >&2 || true
    return 1
  fi
  return 0
}

gate_require_line() {
  local stripped_file="$1"
  local regex="$2"
  local label="$3"
  if ! grep -E "${regex}" "${stripped_file}" >/dev/null; then
    echo "${label}: missing anchored line matching /${regex}/" >&2
    return 1
  fi
  return 0
}
