SAIL=./vendor/bin/sail

.PHONY: up down dev pail test format build deploy

up:
	$(SAIL) up -d

down:
	$(SAIL) down

dev: up
	$(SAIL) npm exec -- concurrently --kill-others -n vite,pail "npm run dev" "php artisan pail --timeout=0"

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

deploy:
	gh workflow run deploy.yml --ref main
	gh run watch --exit-status
