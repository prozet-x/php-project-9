#Makefile
install:
	composer install

start:
	php -S 0.0.0.0:8000 -t public

lint:
	composer exec --verbose phpcs -- --standard=PSR12 --colors -v public