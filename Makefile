#Makefile
install:
	composer install

start:
	php -S 0.0.0.0:8000 -t public public/index.php

lint:
	composer exec --verbose phpcs -- --standard=PSR12 --colors -v public