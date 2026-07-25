#!/usr/bin/env bash

set -euo pipefail

app_id='user_lockdown'
script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_root="$(CDPATH= cd -- "$script_dir/.." && pwd)"
build_dir="$project_root/build"
stage_root="$build_dir/appstore"
stage_dir="$stage_root/$app_id"
archive="$build_dir/$app_id.tar.gz"
checksum="$archive.sha256"
mode="${1:-all}"

cd "$project_root"

case "$mode" in
	all|--stage-only|--archive-staged)
		;;
	*)
		printf '%s\n' 'Usage: scripts/package.sh [--stage-only|--archive-staged]' >&2
		exit 2
		;;
esac

stage_release() {
	bun install --frozen-lockfile
	bun run build

	mkdir -p "$build_dir"
	temporary_root="$(mktemp -d "$build_dir/package.XXXXXX")"
	trap 'if [[ -n "${temporary_root:-}" && -d "$temporary_root" ]]; then rm -rf -- "$temporary_root"; fi' EXIT
	temporary_app="$temporary_root/$app_id"
	mkdir -p "$temporary_app"

	for path in appinfo css img js l10n lib templates LICENSE README.md SECURITY.md; do
		if [ ! -e "$project_root/$path" ]; then
			printf '%s\n' "Missing release input: $path" >&2
			exit 1
		fi
		cp -R "$project_root/$path" "$temporary_app/"
	done
	rm -f -- "$temporary_app/appinfo/signature.json"

	rm -rf -- "$stage_root"
	mv "$temporary_root" "$stage_root"
	temporary_root=''
	trap - EXIT
}

archive_release() {
	if [ ! -d "$stage_dir" ]; then
		printf '%s\n' 'No staged app found. Run with --stage-only first.' >&2
		exit 1
	fi

	if [ "${REQUIRE_SIGNATURE:-0}" = '1' ] \
		&& [ ! -f "$stage_dir/appinfo/signature.json" ]; then
		printf '%s\n' 'A signed appinfo/signature.json is required.' >&2
		exit 1
	fi

	source_date_epoch="${SOURCE_DATE_EPOCH:-0}"
	tar --sort=name \
		--mtime="@$source_date_epoch" \
		--owner=0 \
		--group=0 \
		--numeric-owner \
		-cf - \
		-C "$stage_root" \
		"$app_id" \
		| gzip -n > "$archive"

	(
		cd "$build_dir"
		sha256sum "$app_id.tar.gz" > "$app_id.tar.gz.sha256"
	)

	first_entry="$(tar -tzf "$archive" | sed -n '1p')"
	case "$first_entry" in
		"$app_id/"*)
			;;
		*)
			printf '%s\n' "Unexpected archive root: $first_entry" >&2
			exit 1
			;;
	esac

	printf '%s\n' "Created $archive"
	printf '%s\n' "Created $checksum"
}

if [ "$mode" != '--archive-staged' ]; then
	stage_release
fi

if [ "$mode" != '--stage-only' ]; then
	archive_release
fi
