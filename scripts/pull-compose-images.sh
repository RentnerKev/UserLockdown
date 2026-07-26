#!/usr/bin/env bash

set -euo pipefail

readonly max_attempts=4
readonly initial_delay_seconds=10

if (( $# == 0 )); then
  printf 'Usage: %s SERVICE [SERVICE...]\n' "$0" >&2
  exit 64
fi

for service in "$@"; do
  for ((attempt = 1; attempt <= max_attempts; attempt++)); do
    if docker compose pull "$service"; then
      break
    fi

    if (( attempt == max_attempts )); then
      printf '::error title=Docker image pull failed::Could not pull %s after %d attempts.\n' \
        "$service" "$max_attempts"
      exit 1
    fi

    delay=$((initial_delay_seconds * 2 ** (attempt - 1)))
    printf '::warning title=Docker image pull retry::Service %s, attempt %d/%d failed; retrying in %ds.\n' \
      "$service" "$attempt" "$max_attempts" "$delay"
    sleep "$delay"
  done
done
