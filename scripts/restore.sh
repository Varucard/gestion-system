#!/usr/bin/env bash
# Restaura un backup generado por scripts/backup.sh.
#
#   scripts/restore.sh backups/db_2026-10-02_220000.sql.gz [backups/archivos_2026-10-02_220000.tar.gz]
#   scripts/restore.sh storage/backups/db_2026-10-02_220000.sql.gz   (backups automáticos)
#
# ATENCIÓN: reemplaza los datos actuales de la base (y los archivos, si se indican).
set -euo pipefail

cd "$(dirname "$0")/.."
DB="${1:?Indicá el archivo db_*.sql.gz a restaurar}"
ARCHIVOS="${2:-}"

read -r -p "Se van a reemplazar los datos actuales con $DB. ¿Continuar? (escribí SI) " respuesta
[ "$respuesta" = "SI" ] || { echo "Cancelado."; exit 1; }

gunzip -c "$DB" | docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$MYSQL_DATABASE"'
echo "Base restaurada."

if [ -n "$ARCHIVOS" ]; then
  tar -xzf "$ARCHIVOS"
  echo "Archivos restaurados."
fi

# Por si el backup es de una versión anterior del sistema.
docker compose exec -T public php bin/migrate.php
