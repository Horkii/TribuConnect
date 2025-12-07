#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Ensure needed dirs (cache, logs, mails, uploads, doctrine proxies)
mkdir -p \
  var/cache \
  var/log \
  var/mails \
  public/uploads/photos \
  var/cache/dev/doctrine/orm/Proxies \
  var/cache/prod/doctrine/orm/Proxies
mkdir -p var/doctrine/proxies
chmod -R u+rwX,g+rwX var public/uploads || true


# Install vendors if missing (handle mounted empty volume)
export COMPOSER_ALLOW_SUPERUSER=1
if [ ! -f vendor/autoload.php ]; then
  set +e
  composer install --no-interaction --prefer-dist --optimize-autoloader
  if [ $? -ne 0 ]; then
    echo "composer install failed; running composer update to refresh lock file"
    composer update --no-interaction --prefer-dist --optimize-autoloader
  fi
  set -e
fi
# Always refresh optimized autoload to ensure new classes are discovered
composer dump-autoload -o || true

# Ensure web user owns writable dirs (cache, logs, mails, uploads, doctrine proxies)
chown -R www-data:www-data var public/uploads || true
chmod -R u+rwX,g+rwX var public/uploads || true
# Extra permissive fallback for Windows-mounted volumes (cache + doctrine proxies)
chmod -R a+rwX var/cache || true
chmod -R a+rwX var/doctrine/proxies || true

# Clear cache to ensure fresh metadata/config (run as www-data to avoid root-owned cache)
if command -v su >/dev/null 2>&1; then
  su -s /bin/sh -c "php bin/console cache:clear --no-warmup" www-data || true
else
  php bin/console cache:clear --no-warmup || true
fi

# Wait for Postgres
if [ -n "${DATABASE_URL:-}" ]; then
  echo "Waiting for Postgres to be ready..."
  ATTEMPTS=0
  until php -r '
    $url = getenv("DATABASE_URL");
    $p = @parse_url($url);
    if (!$p || !isset($p["scheme"])) { exit(1); }
    if ($p["scheme"] === "postgres" || $p["scheme"] === "postgresql") {
      $db = isset($p["path"]) ? ltrim($p["path"], "/") : "postgres";
      $host = $p["host"] ?? "127.0.0.1";
      $port = (int)($p["port"] ?? 5432);
      $user = $p["user"] ?? null;
      $pass = $p["pass"] ?? null;
      $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
      try { new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 1]); exit(0); }
      catch (Throwable $e) { exit(1); }
    }
    exit(1);
  '; do
    ATTEMPTS=$((ATTEMPTS+1))
    if [ $ATTEMPTS -gt 60 ]; then echo "Postgres not ready"; exit 1; fi
    sleep 1
  done
fi

# Run migrations (idempotent) as www-data so cache/warmed files are writable later
if command -v su >/dev/null 2>&1; then
  su -s /bin/sh -c "php bin/console doctrine:migrations:migrate --no-interaction" www-data || true
else
  php bin/console doctrine:migrations:migrate --no-interaction || true
fi

# Log mapped entities to help troubleshooting
if command -v su >/dev/null 2>&1; then
  su -s /bin/sh -c "php bin/console doctrine:mapping:info || true" www-data
else
  php bin/console doctrine:mapping:info || true
fi

# Final permissions pass
chown -R www-data:www-data var public/uploads || true
chmod -R u+rwX,g+rwX var public/uploads || true

exec apache2-foreground
