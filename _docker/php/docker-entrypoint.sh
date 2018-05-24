#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'bin/console' ]; then
  # Add any other folders that your web application needs to create here
  mkdir -p var/cache var/sessions var/log

	if [ "$APP_ENV" != 'prod' ]; then
	  # local filesystem mounts after install in Dockerfile so run again here
	  composer install --prefer-dist --no-progress --no-suggest --no-interaction

	  # Check bin/console is executable now because the file should definitely exist
    chmod +x bin/console

		# Uncomment the following line if you are using Symfony Encore
		#yarn run watch
  else
    composer run-script --no-dev post-install-cmd

		# Uncomment the following line if you are using Symfony Encore
		#yarn run build
	fi

	# Permissions hack because setfacl does not work on Mac and Windows
	# Add any other paths that your web application may need to write to
	chown -R www-data var
fi

exec docker-php-entrypoint "$@"
