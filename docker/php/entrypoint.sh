#!/bin/sh
set -e

# Arranque de los contenedores de PHP. El mismo script sirve a `app` (php-fpm) y
# a `queue` (Horizon); lo que los distingue es STATERA_ARRANQUE:
#
#   completo  espera a la base, instala dependencias, genera APP_KEY, migra e
#             importa el catálogo. Lo hace SÓLO el servicio `app`.
#   ligero    espera a la base y a que `app` haya dejado vendor/ en su sitio.
#
# Dos `composer install` a la vez sobre la misma carpeta se pisarían, y por eso
# `queue` espera a que `app` esté healthy en el compose además de esperar aquí.
#
# El script arranca como root —php-fpm necesita serlo para crear sus workers—
# pero todo lo que ESCRIBE en la carpeta del proyecto va con `gosu www-data`,
# que lleva el uid del host: si no, vendor/ y storage/ acabarían llenos de
# ficheros de root en el repositorio de alguien.

ARRANQUE="${STATERA_ARRANQUE:-ligero}"

aviso() {
    echo "[statera] $*"
}

como_usuario() {
    gosu www-data "$@"
}

esperar_a_postgres() {
    aviso "esperando a PostgreSQL en ${DB_HOST:-postgres}:${DB_PORT:-5432}…"
    until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -q; do
        sleep 1
    done
}

esperar_a_vendor() {
    aviso 'esperando a que las dependencias de PHP estén instaladas…'
    until [ -f vendor/autoload.php ]; do
        sleep 1
    done
}

if [ "$ARRANQUE" = 'completo' ]; then
    # Lo que el framework necesita poder escribir siempre. Se hace en cada
    # arranque y no sólo en la instalación: `git clone` deja estas carpetas con
    # el uid de quien clonó, que no tiene por qué ser el mismo.
    chown -R www-data:www-data storage bootstrap/cache

    # El .env va ANTES de `composer install`: el hook post-autoload-dump lanza
    # `artisan package:discover`, que arranca la aplicación y necesita leer la
    # configuración.
    if [ ! -f .env ]; then
        aviso 'no había .env; copiando .env.example'
        como_usuario cp .env.example .env
    fi

    esperar_a_postgres

    if [ ! -f vendor/autoload.php ]; then
        # Sin `--ignore-platform-req`: aquí sí están pcntl y posix, que es la
        # mitad del motivo de haber metido la aplicación en un contenedor.
        aviso 'instalando dependencias de PHP (la primera vez tarda)…'
        como_usuario composer install --no-interaction --prefer-dist
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        aviso 'generando APP_KEY'
        como_usuario php artisan key:generate --force
    fi

    aviso 'aplicando migraciones'
    como_usuario php artisan migrate --force

    # Idempotente por diseño: empareja por clave natural `(marco, requisito)` y
    # nunca borra lo que desaparece de un fichero.
    aviso 'importando el catálogo normativo'
    como_usuario php artisan catalogo:importar

    como_usuario php artisan storage:link

    aviso 'listo'
else
    esperar_a_postgres
    esperar_a_vendor
fi

# php-fpm tiene que arrancar como root: el maestro lee la configuración y crea
# los workers, que sí bajan a www-data. Cualquier otro comando —Horizon, un
# artisan suelto— baja aquí, porque escribe en la carpeta del proyecto.
case "$1" in
    php-fpm) exec "$@" ;;
    *)       exec gosu www-data "$@" ;;
esac
