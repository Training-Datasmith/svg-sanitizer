#!/usr/bin/env bash

validate_phpunit_output() {
    local output_file="$1"
    local expected_line="$2"
    if grep -Eq '^(PHP )?Warning:' "${output_file}"; then
        printf '%s\n' "PHPUnit output contains Warning:"
        grep -E '^(PHP )?Warning:' "${output_file}" || true
        return 1
    fi
    if grep -Eq '^(PHP )?Deprecated:' "${output_file}"; then
        printf '%s\n' "PHPUnit output contains Deprecated:"
        grep -E '^(PHP )?Deprecated:' "${output_file}" || true
        return 1
    fi
    if grep -Eq '^(PHP )?Notice:' "${output_file}"; then
        printf '%s\n' "PHPUnit output contains Notice:"
        grep -E '^(PHP )?Notice:' "${output_file}" || true
        return 1
    fi
    if ! grep -Fxq "${expected_line}" "${output_file}"; then
        printf '%s\n' "PHPUnit output is missing strict success line: ${expected_line}"
        return 1
    fi
    return 0
}
