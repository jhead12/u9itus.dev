#!/usr/bin/env bash
set -euo pipefail
year="${ELECTION_YEAR:-2026}"
[[ "$year" =~ ^20[0-9]{2}$ ]] || { echo 'Invalid election year' >&2; exit 2; }
state_arg="${1:-}"
state="${state_arg#--state=}"
file="storage/app/imports/ballotpedia-results-${year}${state:+-$state}.json"
state_args=()
[[ -z "$state" ]] || state_args+=("--state=$state")
node scripts/scrape-ballotpedia.js --office=statewide --strategy=direct --year="$year" --results ${state_args[@]+"${state_args[@]}"} --out="$file"
import_args=()
[[ "${CREATE_MISSING:-false}" != true ]] || import_args+=(--create-missing)
[[ "${DRY_RUN:-false}" != true ]] || import_args+=(--dry-run)
php artisan politicians:import-election-results --file="$file" ${import_args[@]+"${import_args[@]}"}
