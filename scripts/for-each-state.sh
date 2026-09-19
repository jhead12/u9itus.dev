#!/usr/bin/env bash
# Run a scalar --state command for every requested state; blank means national.
set -euo pipefail
states="${1:-}"
shift
if [[ -z "${states//[[:space:]]/}" ]]; then
  "$@"
  exit
fi
IFS=',' read -ra requested <<< "$states"
normalized=()
for state in "${requested[@]}"; do
  state="$(printf '%s' "$state" | tr '[:lower:]' '[:upper:]' | tr -d '[:space:]')"
  case " AL AK AZ AR CA CO CT DE FL GA HI ID IL IN IA KS KY LA ME MD MA MI MN MS MO MT NE NV NH NJ NM NY NC ND OH OK OR PA RI SC SD TN TX UT VT VA WA WV WI WY DC PR GU VI AS MP " in
    *" $state "*) if [[ -z "$state" ]]; then exit 2; fi ;;
    *) echo "Invalid state: $state" >&2; exit 2 ;;
  esac
  normalized+=("$state")
done
status=0
for state in "${normalized[@]}"; do
  "$@" "--state=$state" || status=1
done
exit "$status"
