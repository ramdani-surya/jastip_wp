SHELL := /bin/bash

-include .env
export

.PHONY: up down restart ps logs shell db-shell wp-install wp-upgrade wp-verify-core db-backup db-restore

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
	@test -f .env || { echo "Missing .env. Run: cp .env.example .env" >&2; exit 1; }
	@docker compose exec -T \
		-e WP_SITE_TITLE="$(WP_SITE_TITLE)" \
		-e WP_ADMIN_USER="$(WP_ADMIN_USER)" \
		-e WP_ADMIN_PASSWORD="$(WP_ADMIN_PASSWORD)" \
		-e WP_ADMIN_EMAIL="$(WP_ADMIN_EMAIL)" \
		wordpress php < docker/php/install-wordpress.php

wp-upgrade:
	@docker compose exec -T wordpress php < docker/php/upgrade-wordpress.php

wp-verify-core:
	@docker compose exec -T wordpress sh -c 'cd /var/www/html && sha256sum --check --strict --quiet /usr/local/share/wordpress-core.sha256'
	@docker compose exec -T wordpress php -r 'require "/var/www/html/wp-includes/version.php"; if ($$wp_version !== "7.0.3") { fwrite(STDERR, "Unexpected WordPress version: $$wp_version\n"); exit(1); } echo "Verified WordPress $$wp_version core files.\n";'

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
