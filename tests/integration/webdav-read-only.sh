#!/bin/sh

set -eu

base_url="${NEXTCLOUD_BASE_URL:-http://localhost:8080}"
credentials="${RESTRICTED_CREDENTIALS:-restricted:restricted-dev-password}"
normal_credentials="${NORMAL_CREDENTIALS:-normal:normal-dev-password}"
admin_credentials="${ADMIN_CREDENTIALS:-admin:admin-dev-password}"
mailpit_api_url="${MAILPIT_API_URL:-http://localhost:8025/api/v1/messages}"
files_url="$base_url/remote.php/dav/files/restricted"
uploads_url="$base_url/remote.php/dav/uploads/restricted"
fixture_url="$files_url/read-only.txt"
scratch_url="$files_url/user-lockdown-write-test.txt"
normal_scratch_url="$base_url/remote.php/dav/files/normal/user-lockdown-normal-write-test.txt"
admin_scratch_url="$base_url/remote.php/dav/files/admin/user-lockdown-admin-write-test.txt"

assert_status() {
	expected="$1"
	description="$2"
	shift 2

	actual="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "$@")"
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

	actual="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "$@")"
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

mail_total() {
	curl --silent --show-error "$mailpit_api_url" \
		| sed -n 's/.*"total":\([0-9][0-9]*\).*/\1/p'
}

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
	--header "Destination: $files_url/renamed.txt" "$fixture_url"
assert_status 403 'Copy is blocked' \
	--user "$credentials" --request COPY \
	--header "Destination: $files_url/copied.txt" "$fixture_url"
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
reset_response="$(mktemp)"
trap 'rm -f "$reset_response"' EXIT
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
rm -f "$reset_response"
trap - EXIT

assert_status_one_of '201 204' 'Normal user upload remains allowed' \
	--user "$normal_credentials" --request PUT --data-binary 'allowed' "$normal_scratch_url"
assert_status 204 'Normal user delete remains allowed' \
	--user "$normal_credentials" --request DELETE "$normal_scratch_url"
assert_status_one_of '201 204' 'Administrator upload remains allowed' \
	--user "$admin_credentials" --request PUT --data-binary 'allowed' "$admin_scratch_url"
assert_status 204 'Administrator delete remains allowed' \
	--user "$admin_credentials" --request DELETE "$admin_scratch_url"
