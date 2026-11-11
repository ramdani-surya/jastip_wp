SHELL := /bin/bash

-include .env
-include .env.prod
export

.PHONY: up down restart ps logs shell db-shell wp-install wp-upgrade wp-verify-core design-system design-system-verify site-components site-components-verify homepage homepage-verify db-backup db-restore prod-up prod-down prod-logs prod-install backup restore

up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker compose restart

ps:
	docker compose ps

logs:
	docker compose logs -f --tail=200

shell:
	docker compose exec wordpress bash

db-shell:
	docker compose exec db sh -c 'MYSQL_PWD="$$MYSQL_PASSWORD" exec mysql --user="$$MYSQL_USER" "$$MYSQL_DATABASE"'

wp-install:
	@if [ ! -f .env ] && [ ! -f .env.prod ] && [ -z "$$WP_ADMIN_USER" ]; then \
		echo "Missing environment configuration. Run: cp .env.example .env or export variables." >&2; exit 1; \
	fi
	@docker compose exec -T \
		-e WP_SITE_TITLE="$(WP_SITE_TITLE)" \
		-e WP_ADMIN_USER="$(WP_ADMIN_USER)" \
		-e WP_ADMIN_PASSWORD="$(WP_ADMIN_PASSWORD)" \
		-e WP_ADMIN_EMAIL="$(WP_ADMIN_EMAIL)" \
		wordpress php < docker/php/install-wordpress.php
	@docker compose exec -T wordpress php < docker/php/sync-domain.php

wp-upgrade:
	@docker compose exec -T wordpress php < docker/php/upgrade-wordpress.php

wp-verify-core:
	@docker compose exec -T wordpress php -r 'require "/var/www/html/wp-includes/version.php"; if ($$wp_version !== "7.1.4") { fwrite(STDERR, "Unexpected WordPress version: $$wp_version\n"); exit(1); } echo "Verified WordPress $$wp_version core files.\n";'

design-system:
	@docker compose exec -T -e DESIGN_SYSTEM_MODE=apply wordpress php < docker/php/setup-design-system.php

design-system-verify:
	@docker compose exec -T -e DESIGN_SYSTEM_MODE=verify wordpress php < docker/php/setup-design-system.php

site-components:
	@docker compose exec -T -e SITE_COMPONENTS_MODE=apply wordpress php < docker/php/setup-site-components.php

site-components-verify:
	@docker compose exec -T -e SITE_COMPONENTS_MODE=verify wordpress php < docker/php/setup-site-components.php

homepage:
	@docker compose exec -T -e HOMEPAGE_MODE=apply wordpress php < docker/php/setup-homepage.php
	@$(MAKE) --no-print-directory site-components
	@$(MAKE) --no-print-directory homepage-verify

homepage-verify:
	@docker compose exec -T -e HOMEPAGE_MODE=verify wordpress php < docker/php/setup-homepage.php

db-backup:
	@set -eu; \
	mkdir -p backups; \
	file="backups/db-$$(date +%Y-%m-%d-%H%M%S).sql"; \
	docker compose exec -T db sh -c 'MYSQL_PWD="$$MYSQL_PASSWORD" exec mysqldump --user="$$MYSQL_USER" --single-transaction --quick --lock-tables=false --no-tablespaces --default-character-set=utf8mb4 "$$MYSQL_DATABASE"' > "$$file"; \
	test -s "$$file"; \
	echo "Database backup created: $$file"

db-restore:
	@set -eu; \
	test -n "$(FILE)" || { echo "Usage: make db-restore FILE=backups/example.sql" >&2; exit 1; }; \
	test -f "$(FILE)" || { echo "Backup file not found: $(FILE)" >&2; exit 1; }; \
	database="$$(docker compose exec -T db printenv MYSQL_DATABASE | tr -d '\r')"; \
	confirmation="$(CONFIRM)"; \
	if [ -z "$$confirmation" ]; then \
		printf "Restore will overwrite database '%s'. Type the database name to continue: " "$$database"; \
		read -r confirmation < /dev/tty; \
	fi; \
	test "$$confirmation" = "$$database" || { echo "Restore cancelled: confirmation did not match '$$database'." >&2; exit 1; }; \
	docker compose exec -T db sh -c 'MYSQL_PWD="$$MYSQL_PASSWORD" exec mysql --user="$$MYSQL_USER" "$$MYSQL_DATABASE"' < "$(FILE)"; \
	echo "Database restored from $(FILE) into $$database."

# =============================================================================
# Production Commands
# =============================================================================
# Gunakan perintah ini di server production dengan file .env.prod yang sudah
# dikonfigurasi. Pastikan docker/nginx/certs/ sudah berisi sertifikat SSL.

prod-up:
	@if [ -f .env.prod ]; then \
		docker compose -f compose.yaml -f docker-compose.prod.yml --env-file .env.prod up -d --build; \
	else \
		docker compose -f compose.yaml -f docker-compose.prod.yml up -d --build; \
	fi

prod-down:
	@if [ -f .env.prod ]; then \
		docker compose -f compose.yaml -f docker-compose.prod.yml --env-file .env.prod down; \
	else \
		docker compose -f compose.yaml -f docker-compose.prod.yml down; \
	fi

prod-logs:
	@if [ -f .env.prod ]; then \
		docker compose -f compose.yaml -f docker-compose.prod.yml --env-file .env.prod logs -f --tail=200; \
	else \
		docker compose -f compose.yaml -f docker-compose.prod.yml logs -f --tail=200; \
	fi

# Inisialisasi awal database (Day-0 seed) di production
prod-install:
	@set -e; \
	echo "Mengimpor baseline database seed..."; \
	gunzip -c database/seed.sql.gz | docker compose exec -T db sh -c 'MYSQL_PWD="$$MYSQL_PASSWORD" exec mysql --user="$$MYSQL_USER" "$$MYSQL_DATABASE"'; \
	if [ -f database/uploads-seed.tar.gz ]; then \
		echo "Mengekstrak baseline media uploads..."; \
		docker compose exec -T wordpress tar --extract --gzip --directory /var/www/html/wp-content < database/uploads-seed.tar.gz; \
		docker compose exec -T wordpress chown -R www-data:www-data /var/www/html/wp-content/uploads; \
	fi; \
	echo "Menyelaraskan URL target..."; \
	docker compose exec -T wordpress php < docker/php/sync-domain.php

# Backup database + uploads ke folder backups/
backup:
	@bash scripts/backup.sh

# Restore database dan/atau uploads dari file backup.
# Contoh: make restore ARGS="--db backups/db-2026-10-17-020000.sql"
restore:
	@bash scripts/restore.sh $(ARGS)

