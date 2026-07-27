#!/bin/sh

set -eu

base_url="${NEXTCLOUD_BASE_URL:-http://localhost:8080}"
credentials="${RESTRICTED_CREDENTIALS:-restricted:restricted-dev-password}"
normal_credentials="${NORMAL_CREDENTIALS:-normal:normal-dev-password}"
admin_credentials="${ADMIN_CREDENTIALS:-admin:admin-dev-password}"
mailpit_api_url="${MAILPIT_API_URL:-http://localhost:8025/api/v1/messages}"
null_device="${NULL_DEVICE:-/dev/null}"
files_url="$base_url/remote.php/dav/files/restricted"
uploads_url="$base_url/remote.php/dav/uploads/restricted"
fixture_url="$files_url/read-only.txt"
scratch_url="$files_url/user-lockdown-write-test.txt"
copy_url="$files_url/user-lockdown-copy-test.txt"
moved_url="$files_url/user-lockdown-moved-test.txt"
overwrite_url="$files_url/user-lockdown-overwrite-test.txt"
full_access_url="$files_url/user-lockdown-full-access-test.txt"
normal_scratch_url="$base_url/remote.php/dav/files/normal/user-lockdown-normal-write-test.txt"
admin_scratch_url="$base_url/remote.php/dav/files/admin/user-lockdown-admin-write-test.txt"
temporary_dir="$(mktemp -d)"

set_permissions() {
	docker compose exec -T --user www-data nextcloud php \
		/var/www/html/custom_apps/user_lockdown/docker/set-permissions.php \
		restricted "$1"
}

delete_quietly() {
	curl --silent --output "$null_device" --user "$credentials" --request DELETE "$1" || true
}

cleanup() {
	set +e
	set_permissions 63 >/dev/null
	delete_quietly "$scratch_url"
	delete_quietly "$copy_url"
	delete_quietly "$moved_url"
	delete_quietly "$overwrite_url"
	delete_quietly "$full_access_url"
	set_permissions 1 >/dev/null
	rm -rf -- "$temporary_dir"
}

trap cleanup EXIT

assert_status() {
	expected="$1"
	description="$2"
	shift 2

	actual="$(curl --silent --show-error --output "$null_device" --write-out '%{http_code}' "$@")"
	if [ "$actual" != "$expected" ]; then
		printf '%s\n' "$description: expected HTTP $expected, got $actual" >&2
		exit 1
	fi

	printf '%s\n' "$description: HTTP $actual"
}

assert_status_one_of() {
	expected_statuses="$1"
	description="$2"
	shift 2

	actual="$(curl --silent --show-error --output "$null_device" --write-out '%{http_code}' "$@")"
	case " $expected_statuses " in
		*" $actual "*)
			;;
		*)
			printf '%s\n' "$description: expected one of [$expected_statuses], got $actual" >&2
			exit 1
			;;
	esac

	printf '%s\n' "$description: HTTP $actual"
}

assert_redirect() {
	expected_path="$1"
	description="$2"
	shift 2

	headers="$temporary_dir/redirect-headers"
	actual="$(curl --silent --show-error --output "$null_device" --dump-header "$headers" --write-out '%{http_code}' "$@")"
	location="$(sed -n 's/^[Ll]ocation:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$headers" | tr -d '\r' | tail -n 1)"
	case "$location" in
		"$expected_path"|"$base_url$expected_path")
			location_matches=true
			;;
		*)
			location_matches=false
			;;
	esac
	if [ "$actual" != '303' ] || [ "$location_matches" != 'true' ]; then
		printf '%s\n' "$description: expected HTTP 303 to $expected_path, got HTTP $actual to $location" >&2
		exit 1
	fi

	printf '%s\n' "$description: HTTP $actual to $location"
}

mail_total() {
	curl --silent --show-error "$mailpit_api_url" \
		| sed -n 's/.*"total":\([0-9][0-9]*\).*/\1/p'
}

set_permissions 63 >/dev/null
delete_quietly "$scratch_url"
delete_quietly "$copy_url"
delete_quietly "$moved_url"
delete_quietly "$overwrite_url"
delete_quietly "$full_access_url"
set_permissions 1 >/dev/null

printf '%s\n' 'Testing read-only permissions (mask 1).'
assert_status 200 'File download is allowed' \
	--user "$credentials" "$fixture_url"
assert_status 200 'HEAD is allowed' \
	--user "$credentials" --head "$fixture_url"
assert_status 207 'PROPFIND is allowed' \
	--user "$credentials" --request PROPFIND --header 'Depth: 1' "$files_url/"
assert_status 403 'Upload is blocked' \
	--user "$credentials" --request PUT --data-binary 'blocked' "$scratch_url"
assert_status 403 'Chunked upload folder creation is blocked' \
	--user "$credentials" --request MKCOL "$uploads_url/user-lockdown-chunk"
assert_status 403 'Folder creation is blocked' \
	--user "$credentials" --request MKCOL "$files_url/user-lockdown-folder"
assert_status 403 'Delete is blocked' \
	--user "$credentials" --request DELETE "$fixture_url"
assert_status 403 'Rename is blocked' \
	--user "$credentials" --request MOVE \
	--header "Destination: $moved_url" "$fixture_url"
assert_status 403 'Copy is blocked' \
	--user "$credentials" --request COPY \
	--header "Destination: $copy_url" "$fixture_url"
assert_status 403 'Partial update is blocked' \
	--user "$credentials" --request PATCH --data-binary 'blocked' "$fixture_url"
assert_status 403 'POST mutation is blocked' \
	--user "$credentials" --request POST --data-binary 'blocked' "$files_url/"
assert_status 303 'Personal security settings are blocked' \
	--user "$credentials" "$base_url/settings/user/security"
assert_status 303 'Other applications are blocked' \
	--user "$credentials" "$base_url/apps/dashboard/"
assert_status 403 'Share creation is blocked' \
	--user "$credentials" \
	--header 'OCS-APIRequest: true' \
	--request POST \
	--data-urlencode 'path=/read-only.txt' \
	--data-urlencode 'shareType=3' \
	"$base_url/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json"

mail_before="$(mail_total)"
reset_response="$temporary_dir/reset-response"
reset_status="$(curl \
	--silent \
	--show-error \
	--output "$reset_response" \
	--write-out '%{http_code}' \
	--header 'OCS-APIRequest: true' \
	--request POST \
	--data-urlencode 'user=restricted' \
	"$base_url/lostpassword/email")"
if [ "$reset_status" != '200' ] || ! grep -q '"status":"success"' "$reset_response"; then
	printf '%s\n' "Lost-password privacy response failed: HTTP $reset_status" >&2
	exit 1
fi
mail_after="$(mail_total)"
if [ -z "$mail_before" ] || [ "$mail_before" != "$mail_after" ]; then
	printf '%s\n' "Restricted lost-password request sent email: before=$mail_before after=$mail_after" >&2
	exit 1
fi
printf '%s\n' 'Restricted lost-password request is privacy-safe and sends no email: HTTP 200'

printf '%s\n' 'Testing file editor permissions (mask 3).'
set_permissions 3 >/dev/null
assert_status_one_of '201 204' 'File editor can upload' \
	--user "$credentials" --request PUT --data-binary 'editable' "$scratch_url"
assert_status_one_of '201 204' 'File editor can prepare an overwrite target' \
	--user "$credentials" --request PUT --data-binary 'target' "$overwrite_url"
assert_status 403 'File editor cannot overwrite without delete permission' \
	--user "$credentials" --request MOVE \
	--header "Destination: $overwrite_url" "$scratch_url"
assert_status 201 'File editor can copy to a new path' \
	--user "$credentials" --request COPY \
	--header "Destination: $copy_url" "$scratch_url"
assert_status 201 'File editor can move to a new path' \
	--user "$credentials" --request MOVE \
	--header "Destination: $moved_url" "$copy_url"
assert_status 403 'File editor cannot delete' \
	--user "$credentials" --request DELETE "$moved_url"
assert_status 403 'File editor cannot share without share permission' \
	--user "$credentials" \
	--header 'OCS-APIRequest: true' \
	--request POST \
	--data-urlencode 'path=/user-lockdown-moved-test.txt' \
	--data-urlencode 'shareType=3' \
	"$base_url/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json"

printf '%s\n' 'Testing deletion-only permissions (mask 5).'
set_permissions 5 >/dev/null
assert_status 403 'Deletion-only user cannot upload' \
	--user "$credentials" --request PUT --data-binary 'blocked' "$copy_url"
assert_status 204 'Deletion-only user can delete an existing file' \
	--user "$credentials" --request DELETE "$moved_url"
assert_status 204 'Deletion-only user can remove the overwrite target' \
	--user "$credentials" --request DELETE "$overwrite_url"
assert_status 204 'Deletion-only user can remove the editor source' \
	--user "$credentials" --request DELETE "$scratch_url"

printf '%s\n' 'Testing sharing permissions (mask 9).'
set_permissions 9 >/dev/null
share_response="$temporary_dir/share-response"
share_status="$(curl \
	--silent \
	--show-error \
	--output "$share_response" \
	--write-out '%{http_code}' \
	--user "$credentials" \
	--header 'OCS-APIRequest: true' \
	--request POST \
	--data-urlencode 'path=/read-only.txt' \
	--data-urlencode 'shareType=3' \
	"$base_url/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json")"
if [ "$share_status" != '200' ]; then
	printf '%s\n' "Share-enabled user could not create a share: HTTP $share_status" >&2
	exit 1
fi
share_id="$(sed -n 's/.*"id":"\([0-9][0-9]*\)".*/\1/p' "$share_response")"
if [ -z "$share_id" ]; then
	printf '%s\n' 'Share-enabled response did not contain a share ID.' >&2
	exit 1
fi
printf '%s\n' 'Share-enabled user can create a share: HTTP 200'
assert_status 200 'Share-enabled user can remove a share' \
	--user "$credentials" \
	--header 'OCS-APIRequest: true' \
	--request DELETE \
	"$base_url/ocs/v2.php/apps/files_sharing/api/v1/shares/$share_id?format=json"

printf '%s\n' 'Testing hidden Files navigation (mask 65).'
set_permissions 65 >/dev/null
assert_status 200 'File viewing remains available with hidden navigation' \
	--user "$credentials" "$fixture_url"
assert_redirect '/apps/files/files' \
	'Files root redirects to All files' \
	--user "$credentials" "$base_url/apps/files/"
assert_redirect '/apps/files/files' \
	'Alternative Files view redirects to All files' \
	--user "$credentials" "$base_url/apps/files/recent"
assert_status 200 'Canonical All files view remains available' \
	--user "$credentials" "$base_url/apps/files/files"

printf '%s\n' 'Testing password-only permissions (mask 16).'
set_permissions 16 >/dev/null
assert_status 403 'Password-only user cannot read files' \
	--user "$credentials" "$fixture_url"
assert_status 200 'Password-only user can open personal security settings' \
	--user "$credentials" "$base_url/settings/user/security"
assert_redirect '/settings/user/security' \
	'Password-only Files entry redirects to security settings' \
	--user "$credentials" "$base_url/apps/files/"

printf '%s\n' 'Testing fully blocked permissions (mask 0).'
set_permissions 0 >/dev/null
assert_status 403 'Blocked user cannot read files' \
	--user "$credentials" "$fixture_url"
assert_status 200 'Blocked user retains the safe Files shell for logout' \
	--user "$credentials" "$base_url/apps/files/"
assert_status 303 'Blocked user cannot open security settings' \
	--user "$credentials" "$base_url/settings/user/security"

printf '%s\n' 'Testing managed full access (mask 63).'
set_permissions 63 >/dev/null
assert_status 200 'Managed full-access user can read files' \
	--user "$credentials" "$fixture_url"
assert_status_one_of '201 204' 'Managed full-access user can upload' \
	--user "$credentials" --request PUT --data-binary 'allowed' "$full_access_url"
assert_status 204 'Managed full-access user can delete' \
	--user "$credentials" --request DELETE "$full_access_url"

assert_status_one_of '201 204' 'Normal user upload remains allowed' \
	--user "$normal_credentials" --request PUT --data-binary 'allowed' "$normal_scratch_url"
assert_status 204 'Normal user delete remains allowed' \
	--user "$normal_credentials" --request DELETE "$normal_scratch_url"
assert_status_one_of '201 204' 'Administrator upload remains allowed' \
	--user "$admin_credentials" --request PUT --data-binary 'allowed' "$admin_scratch_url"
assert_status 204 'Administrator delete remains allowed' \
	--user "$admin_credentials" --request DELETE "$admin_scratch_url"

set_permissions 1 >/dev/null
printf '%s\n' 'All permission-profile integration checks passed.'
