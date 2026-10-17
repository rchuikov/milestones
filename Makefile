SAIL=./vendor/bin/sail

.PHONY: up down dev pail test format build

up:
	$(SAIL) up -d

down:
	$(SAIL) down

dev: up
	$(SAIL) npm exec -- concurrently --kill-others -n vite,pail "npm run dev" "php artisan pail"

pail:
	$(SAIL) artisan pail

test:
	$(SAIL) artisan config:clear
	$(SAIL) artisan test
	$(SAIL) npm test

format:
	$(SAIL) pint

build:
	$(SAIL) npm run build
