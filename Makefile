.PHONY: init up down build logs ps sh-backend test lint

init:      ## First run: copy missing .env files then start the dev stack
	@for d in .; do \
		if [ -f "$$d/.env.example" ] && [ ! -f "$$d/.env" ]; then \
			cp "$$d/.env.example" "$$d/.env"; echo "created $$d/.env"; \
		fi; \
	done
	docker compose up --build -d
	@echo "Dev stack up — api http://localhost:8080  rabbit http://localhost:15672"

up:        ## Start the dev stack
	docker compose up --build -d

down:      ## Stop and remove containers
	docker compose down

build:     ## Rebuild images
	docker compose build

logs:      ## Tail all logs
	docker compose logs -f

ps:        ## List running services
	docker compose ps

sh-backend: ## Shell into the backend container
	docker compose exec backend bash

test:      ## Run backend tests
	cd backend && vendor/bin/phpunit

lint:      ## Run backend lint suite
	cd backend && composer lint
