#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
IMAGE_PHP71="svg-sanitize-php71-lint:remote-test-2026-11-09"
IMAGE_PHP72="svg-sanitize-php72-test:remote-test-2026-11-09"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

if ! docker_cmd version >/dev/null 2>&1; then
    echo "Docker is required for scripts/system-test.sh" >&2
    exit 1
fi

docker_cmd build -f docker/system-test/Dockerfile.php71 -t "${IMAGE_PHP71}" docker/system-test
docker_cmd build -f docker/system-test/Dockerfile.php72 -t "${IMAGE_PHP72}" docker/system-test

lint_out="$(mktemp)"
set +e
docker_cmd run --rm -v "${ROOT_DIR}:/app" -w /app "${IMAGE_PHP71}" \
    bash -lc 'set -euo pipefail; fail=0; while IFS= read -r -d "" f; do if ! php -d error_reporting=32767 -l "$f"; then fail=1; fi; done < <(find src -name "*.php" -print0); exit "$fail"' >"${lint_out}" 2>&1
lint_run=$?
set -e
cat "${lint_out}"
if [[ ${lint_run} -ne 0 ]]; then
    exit 1
fi
if ! grep -E '^No syntax errors detected in .+$' "${lint_out}" >/dev/null; then
    echo 'php -l: missing per-file success lines' >&2
    exit 1
fi
if grep -E '^(PHP )?(Warning|Deprecated|Notice):' "${lint_out}" >/dev/null; then
    echo 'php -l: output contains PHP warnings' >&2
    exit 1
fi
echo 'php-lint: No syntax errors detected in src/'
rm -f "${lint_out}"

docker_cmd run --rm -v "${ROOT_DIR}:/app" -w /app "${IMAGE_PHP72}" bash /app/scripts/system-test-inner.sh
