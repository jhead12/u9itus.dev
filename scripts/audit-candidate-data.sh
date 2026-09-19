#!/usr/bin/env bash
set -euo pipefail
state_arg="${1:-}"
label="${state_arg#--state=}"
label="${label:-all}"
mkdir -p storage/app/qa/candidate-data
args=(--limit=0 --fix --deactivate --report="storage/app/qa/candidate-data/$label.json")
[[ -z "$state_arg" ]] || args+=("$state_arg")
[[ "${DRY_RUN:-false}" != true ]] || args+=(--dry-run)
php artisan politicians:audit-data-integrity "${args[@]}" 2>&1 | tee "storage/app/qa/candidate-data/$label.log"
