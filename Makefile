#Makefile
install:
	composer install

PORT ?= 8000
start:
	php -S 0.0.0.0:$(PORT) -t public

lint:
	composer exec --verbose phpcs -- --standard=PSR12 --colors -v public

db-reset:
	dropdb phpproj3 || true
	createdb phpproj3

test-db-reset:
	dropdb phpproj3test || true
	createdb phpproj3test

create_tables:
	psql phpproj3 < database.sql

test-create_tables:
	psql phpproj3test < database.sql