#!/usr/bin/env bash
# Backup de la base de datos y de los archivos subidos (fotos, configuración).
#
#   scripts/backup.sh            guarda en backups/ y conserva los últimos 14
#   KEEP=30 scripts/backup.sh    cambia la cantidad de backups que se conservan
#
# Para hacerlo todos los días a las 22 h (crontab -e en el servidor):
#   0 22 * * * cd /ruta/al/proyecto && scripts/backup.sh >> backups/backup.log 2>&1
set -euo pipefail

cd "$(dirname "$0")/.."
KEEP="${KEEP:-14}"
DESTINO="backups"
FECHA="$(date +%Y-%m-%d_%H%M%S)"
mkdir -p "$DESTINO"

echo "[$(date '+%F %T')] Backup de la base..."
docker compose exec -T database sh -c \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --no-tablespaces "$MYSQL_DATABASE"' \
  | gzip > "$DESTINO/db_$FECHA.sql.gz"

echo "[$(date '+%F %T')] Backup de archivos..."
tar -czf "$DESTINO/archivos_$FECHA.tar.gz" --ignore-failed-read storage/config storage/uploads 2>/dev/null || true

# Rotación: conserva los últimos $KEEP de cada tipo.
for tipo in db archivos; do
  ls -1t "$DESTINO"/${tipo}_*.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm --
done

echo "[$(date '+%F %T')] Listo: $DESTINO/db_$FECHA.sql.gz y $DESTINO/archivos_$FECHA.tar.gz"
