#!/bin/sh

set -eu

run_occ() {
	su -s /bin/sh www-data -c "php /var/www/html/occ $*"
}

run_occ app:enable user_lockdown

if ! run_occ user:info restricted >/dev/null 2>&1; then
	su -s /bin/sh www-data -c \
		"OC_PASS='$RESTRICTED_USER_PASSWORD' php /var/www/html/occ user:add --password-from-env --display-name='Restricted Test User' restricted"
fi

if ! run_occ user:info normal >/dev/null 2>&1; then
	su -s /bin/sh www-data -c \
		"OC_PASS='$NORMAL_USER_PASSWORD' php /var/www/html/occ user:add --password-from-env --display-name='Normal Test User' normal"
fi

run_occ user:setting restricted settings email restricted@user-lockdown.test

su -s /bin/sh www-data -c \
	'php /var/www/html/custom_apps/user_lockdown/docker/seed-restriction.php'

printf '%s\n' 'User Lockdown development environment is ready.'
printf '%s\n' 'Nextcloud: http://localhost:8080'
printf '%s\n' 'Admin: admin / admin-dev-password'
printf '%s\n' 'Restricted: restricted / restricted-dev-password'
printf '%s\n' 'Normal: normal / normal-dev-password'
printf '%s\n' 'Mailpit: http://localhost:8025'
