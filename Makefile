SHELL := /bin/bash

.PHONY: setup migrate serve build test-setup test test-parallel lint

setup:
	composer install
	npm ci
	cp -n .env.example .env || true
	php artisan key:generate || true

migrate:
	php artisan migrate

serve:
	php artisan serve

build:
	npm run build

# Testing environment
 test-setup:
	cp -n .env.example .env.testing || true
	php artisan key:generate --env=testing || true
	php artisan migrate:fresh --env=testing

test:
	php artisan test

test-parallel:
	php artisan test --parallel

lint:
	./vendor/bin/pint -v
