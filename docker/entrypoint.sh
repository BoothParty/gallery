#!/bin/sh
set -e

# Les migrations ne tournent que sur un seul rôle (RUN_MIGRATIONS, cf. config/deploy.yml).
if [ "${RUN_MIGRATIONS}" = "true" ]; then
  # Au premier boot MySQL initialise son datadir pendant ~30s.
  echo "→ attente de ${DB_HOST}"
  until php artisan db:monitor >/dev/null 2>&1; do sleep 2; done

  php artisan migrate --force
fi

# Les caches dépendent des variables injectées par Kamal : générés au boot.
php artisan optimize

exec docker-php-entrypoint "$@"
