#!/usr/bin/env bash
# Backup de la base de datos y de los archivos subidos (fotos, configuración).
#
#   scripts/backup.sh            guarda en backups/ y conserva los últimos 14
#   KEEP=30 scripts/backup.sh    cambia la cantidad de backups que se conservan
#   ABEL_DIR=../abel_mecanica scripts/backup.sh   en la VPS compartida con Mecánica Abel
#
# Para hacerlo todos los días a las 22 h (crontab -e en el servidor):
#   0 22 * * * cd /ruta/al/proyecto && scripts/backup.sh >> backups/backup.log 2>&1
set -euo pipefail

cd "$(dirname "$0")/.."
# La base es siempre la de nuestro .env (DB_DATABASE). En la VPS compartida con Mecánica Abel
# (ver README) vive en su MySQL, que tiene además la base del taller: ahí se corre con
# ABEL_DIR=../abel_mecanica, y nunca se toca la base por defecto de ese contenedor.
BASE="$(sed -n 's/^DB_DATABASE=//p' .env | tr -d "\"'\r")"
[ -n "$BASE" ] || { echo "Falta DB_DATABASE en .env" >&2; exit 1; }
if [ -n "${ABEL_DIR:-}" ]; then
  COMPOSE=(docker compose --project-directory "$ABEL_DIR"); APP=gestion
else
  COMPOSE=(docker compose); APP=public
fi
KEEP="${KEEP:-14}"
DESTINO="backups"
FECHA="$(date +%Y-%m-%d_%H%M%S)"
mkdir -p "$DESTINO"

echo "[$(date '+%F %T')] Backup de la base..."
"${COMPOSE[@]}" exec -T -e BASE="$BASE" database sh -c \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --no-tablespaces "$BASE"' \
  | gzip > "$DESTINO/db_$FECHA.sql.gz"

echo "[$(date '+%F %T')] Backup de archivos..."
tar -czf "$DESTINO/archivos_$FECHA.tar.gz" --ignore-failed-read storage/config storage/uploads 2>/dev/null || true

# Rotación: conserva los últimos $KEEP de cada tipo.
for tipo in db archivos; do
  ls -1t "$DESTINO"/${tipo}_*.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm --
done

echo "[$(date '+%F %T')] Listo: $DESTINO/db_$FECHA.sql.gz y $DESTINO/archivos_$FECHA.tar.gz"
