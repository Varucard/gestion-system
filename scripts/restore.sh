#!/usr/bin/env bash
# Restaura un backup generado por scripts/backup.sh.
#
#   scripts/restore.sh backups/db_2026-10-02_220000.sql.gz [backups/archivos_2026-10-02_220000.tar.gz]
#   scripts/restore.sh storage/backups/db_2026-10-02_220000.sql.gz   (backups automáticos)
#
# ATENCIÓN: reemplaza los datos actuales de la base (y los archivos, si se indican).
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
DB="${1:?Indicá el archivo db_*.sql.gz a restaurar}"
ARCHIVOS="${2:-}"

read -r -p "Se van a reemplazar los datos de la base $BASE con $DB. ¿Continuar? (escribí SI) " respuesta
[ "$respuesta" = "SI" ] || { echo "Cancelado."; exit 1; }

gunzip -c "$DB" | "${COMPOSE[@]}" exec -T -e BASE="$BASE" database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$BASE"'
echo "Base restaurada."

if [ -n "$ARCHIVOS" ]; then
  tar -xzf "$ARCHIVOS"
  echo "Archivos restaurados."
fi

# Por si el backup es de una versión anterior del sistema.
"${COMPOSE[@]}" exec -T "$APP" php bin/migrate.php
