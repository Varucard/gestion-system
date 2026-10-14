#!/bin/sh
set -e

# El servicio "tareas" comparte el código con "public": la preparación la hace solo "public".
if [ "${SKIP_SETUP:-0}" = "1" ]; then
  exec docker-php-entrypoint "$@"
fi

# Instala dependencias de Composer si todavía no están (el código se monta como volumen) o si
# composer.lock cambió con el último "git pull" (se agregó o actualizó una librería).
# Se compara el contenido de composer.lock (no las fechas: git y Windows las cambian solas)
# con el que se instaló la última vez. Si falla (sin red), avisa y arranca con lo que hay.
if [ ! -f vendor/autoload.php ] || [ "$(sha1sum composer.lock | cut -d' ' -f1)" != "$(cat vendor/.composer-lock.sha1 2>/dev/null)" ]; then
  if composer install --no-interaction --prefer-dist; then
    sha1sum composer.lock | cut -d' ' -f1 > vendor/.composer-lock.sha1
  else
    echo "AVISO: no se pudieron instalar las dependencias de Composer." >&2
  fi
fi

# Apache necesita poder escribir la configuración del negocio y los logs.
mkdir -p storage/config storage/logs storage/uploads/clientes storage/uploads/equipos storage/uploads/firmas
chown -R www-data:www-data storage

# Aplica las migraciones pendientes (reintenta mientras la base termina de iniciar).
php bin/migrate.php 15 || echo "AVISO: no se pudieron aplicar las migraciones." >&2

exec docker-php-entrypoint "$@"
