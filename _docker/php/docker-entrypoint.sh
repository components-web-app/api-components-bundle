#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'bin/console' ]; then
	composer install --prefer-dist --no-progress --no-suggest --no-interaction
  tests/app/console enqueue:setup-broker -vvv
	# Permissions hack because setfacl does not work on Mac and Windows
	chown -R www-data tests/app/var
fi

exec docker-php-entrypoint "$@"
