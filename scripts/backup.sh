#!/usr/bin/env bash
# Backup de la base de datos y de los archivos subidos (fotos, configuración).
#
#   scripts/backup.sh            guarda en backups/ y conserva los últimos 14
#   KEEP=30 scripts/backup.sh    cambia la cantidad de backups que se conservan
#   ABEL_DIR=../abel_mecanica scripts/backup.sh   en la VPS compartida con Mecánica Abel
#
# Con BACKUP_CLAVE_PUBLICA en .env, los backups quedan cifrados (.cif), igual que los automáticos.
#
# Para hacerlo todos los días a las 22 h (crontab -e en el servidor):
#   0 22 * * * cd /ruta/al/proyecto && scripts/backup.sh >> backups/backup.log 2>&1
set -euo pipefail
# Los backups tienen todos los datos de clientes y los hashes de las claves: solo para el dueño.
umask 077

cd "$(dirname "$0")/.."
# La base es siempre la de nuestro .env (DB_DATABASE), y se entra con el usuario de la aplicación
# (DB_USERNAME / DB_PASSWORD), no con root. En la VPS compartida con Mecánica Abel (ver README) la
# base vive en su MySQL, que tiene además la base del taller: ahí se corre con
# ABEL_DIR=../abel_mecanica, y nuestro usuario no puede ni ver esa otra base.
leer() { sed -n "s/^$1=//p" .env | tail -n 1 | tr -d "\"'\r"; }
BASE="$(leer DB_DATABASE)"
USUARIO="$(leer DB_USERNAME)"
[[ "$BASE" =~ ^[A-Za-z0-9_]+$ && "$USUARIO" =~ ^[A-Za-z0-9_]+$ ]] \
  || { echo "DB_DATABASE y DB_USERNAME del .env tienen que ser letras, números o _" >&2; exit 1; }
# La clave viaja por el entorno (exec -e MYSQL_PWD, sin valor): no aparece en la lista de procesos.
MYSQL_PWD="$(leer DB_PASSWORD)"
export MYSQL_PWD
if [ -n "${ABEL_DIR:-}" ]; then
  COMPOSE=(docker compose --project-directory "$ABEL_DIR"); APP=gestion
else
  COMPOSE=(docker compose); APP=public
fi
KEEP="${KEEP:-14}"
DESTINO="backups"
FECHA="$(date +%Y-%m-%d_%H%M%S)"
mkdir -p "$DESTINO"

# Cifra lo que entra por la entrada estándar si hay clave pública (lo hace PHP en el contenedor).
EXT=""
if "${COMPOSE[@]}" exec -T "$APP" sh -c 'test -n "$BACKUP_CLAVE_PUBLICA"'; then EXT=".cif"; fi
cifrar() { if [ -n "$EXT" ]; then "${COMPOSE[@]}" exec -T "$APP" php bin/backup.php cifrar - -; else cat; fi; }

echo "[$(date '+%F %T')] Backup de la base..."
"${COMPOSE[@]}" exec -T -e MYSQL_PWD -e BASE="$BASE" -e USUARIO="$USUARIO" database sh -c \
  'exec mysqldump -u"$USUARIO" --single-transaction --routines --no-tablespaces -- "$BASE"' \
  | gzip | cifrar > "$DESTINO/db_$FECHA.sql.gz$EXT"

echo "[$(date '+%F %T')] Backup de archivos..."
{ tar -cz --ignore-failed-read storage/config storage/uploads 2>/dev/null || true; } | cifrar > "$DESTINO/archivos_$FECHA.tar.gz$EXT"

# Rotación: conserva los últimos $KEEP de cada tipo.
shopt -s nullglob
for tipo in db archivos; do
  lista=("$DESTINO"/${tipo}_*.gz "$DESTINO"/${tipo}_*.gz.cif)
  if [ "${#lista[@]}" -gt 0 ]; then
    ls -1t "${lista[@]}" | tail -n +"$((KEEP + 1))" | xargs -r rm --
  fi
done

echo "[$(date '+%F %T')] Listo: $DESTINO/db_$FECHA.sql.gz$EXT y $DESTINO/archivos_$FECHA.tar.gz$EXT"
