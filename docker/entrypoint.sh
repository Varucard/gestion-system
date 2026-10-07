#!/bin/sh
set -e

# El servicio "tareas" comparte el código con "public": la preparación la hace solo "public".
if [ "${SKIP_SETUP:-0}" = "1" ]; then
  exec docker-php-entrypoint "$@"
fi

# Instala dependencias de Composer si todavía no están (el código se monta como volumen).
if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

# Apache necesita poder escribir la configuración del negocio y los logs.
mkdir -p storage/config storage/logs storage/uploads/clientes storage/uploads/equipos
chown -R www-data:www-data storage

# Aplica las migraciones pendientes (reintenta mientras la base termina de iniciar).
php bin/migrate.php 15 || echo "AVISO: no se pudieron aplicar las migraciones." >&2

exec docker-php-entrypoint "$@"
