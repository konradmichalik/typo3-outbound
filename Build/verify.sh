#!/usr/bin/env bash
#
# Verifies everything that could not be run while developing without TYPO3,
# Docker or network access. Runs all steps, never stops at the first failure,
# and prints a summary at the end.
#
#   Build/verify.sh            all steps
#   Build/verify.sh --no-live  skip the calls to petstore3.swagger.io
#
# Requires DDEV with the ddev-typo3-multi-version-extension add-on (see .ddev/).

set -u

cd "$(dirname "$0")/.." || exit 1

LIVE=1
for argument in "$@"; do
    case "$argument" in
        --no-live) LIVE=0 ;;
        *) echo "Unknown option: $argument" >&2; exit 2 ;;
    esac
done

LOG_DIR=".Build/verify"
mkdir -p "$LOG_DIR"
declare -a RESULTS=()
FAILED=0

step() {
    local name="$1"
    shift
    local log="$LOG_DIR/$(echo "$name" | tr ' /:' '___').log"
    printf '%-44s' "$name"
    if "$@" >"$log" 2>&1; then
        echo "ok"
        RESULTS+=("ok      $name")
    else
        echo "FAILED  (log: $log)"
        RESULTS+=("FAILED  $name  ->  $log")
        FAILED=$((FAILED + 1))
    fi
}

frontend() {
    local version="$1"
    local html
    html="$(curl -fsSk "https://${version}.typo3-outbound.ddev.site/")" || return 1
    grep -q 'outbound-petstore' <<<"$html" || { echo "Petstore plugin not rendered"; return 1; }
    if grep -q 'outbound-error' <<<"$html"; then
        echo "Plugin rendered an API error:"
        grep -A2 'outbound-error' <<<"$html"
        return 1
    fi
}

command -v ddev >/dev/null 2>&1 || { echo "ddev is not installed."; exit 1; }

echo "typo3-outbound verification"
echo

step "ddev start" ddev start
step "composer install" ddev composer install
step "unit tests" ddev composer test
step "cgl install" ddev composer cgl install
step "cgl lint" ddev composer cgl lint
step "cgl sca (PHPStan level max)" ddev composer cgl sca
step "cgl migration (Rector, dry run)" ddev composer cgl migration -- --dry-run
step "cgl dependencies" ddev composer cgl analyze:dependencies
step "functional tests (SQLite)" ddev exec env typo3DatabaseDriver=pdo_sqlite composer test:functional
step "install TYPO3 13 and 14" ddev install all
for version in 13 14; do
    step "TYPO3 $version: outbound:debug" ddev "$version" typo3 outbound:debug
    step "TYPO3 $version: outbound:lint" ddev "$version" typo3 outbound:lint
    step "TYPO3 $version: outbound:call --dry-run" ddev "$version" typo3 outbound:call PetApi::find petId=10 --dry-run
done
if [[ "$LIVE" -eq 1 ]]; then
    step "live tests (petstore3.swagger.io)" ddev composer test:live
    step "TYPO3 14: outbound:health" ddev 14 typo3 outbound:health
    for version in 13 14; do
        step "TYPO3 $version: Petstore plugin" frontend "$version"
    done
fi

echo
echo "Summary"
printf '  %s\n' "${RESULTS[@]}"
echo
if [[ "$FAILED" -eq 0 ]]; then
    echo "All steps passed."
else
    echo "$FAILED step(s) failed. Logs are in $LOG_DIR."
fi
exit "$FAILED"
