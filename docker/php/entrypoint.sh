#!/bin/sh
set -e

# Arranque de los contenedores de PHP. El mismo script sirve a `app` (php-fpm) y
# a `queue` (Horizon); lo que los distingue es STATERA_ARRANQUE:
#
#   completo  espera a la base, instala dependencias, genera APP_KEY, migra e
#             importa el catálogo. Lo hace SÓLO el servicio `app`.
#   ligero    espera a la base y a que `app` haya dejado vendor/ en su sitio.
#
# Dos `composer install` a la vez sobre el mismo volumen se pisarían, y por eso
# `queue` espera a que `app` esté healthy en el compose además de esperar aquí.

ARRANQUE="${STATERA_ARRANQUE:-ligero}"

aviso() {
    echo "[statera] $*"
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
    # El .env va ANTES de `composer install`: el hook post-autoload-dump lanza
    # `artisan package:discover`, que arranca la aplicación y necesita leer la
    # configuración.
    if [ ! -f .env ]; then
        aviso 'no había .env; copiando .env.example'
        cp .env.example .env
    fi

    esperar_a_postgres

    if [ ! -f vendor/autoload.php ]; then
        # Sin `--ignore-platform-req`: aquí sí están pcntl y posix, que es la
        # mitad del motivo de haber metido la aplicación en un contenedor.
        aviso 'instalando dependencias de PHP (la primera vez tarda)…'
        composer install --no-interaction --prefer-dist
        chown -R www-data:www-data vendor
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        aviso 'generando APP_KEY'
        php artisan key:generate --force
    fi

    aviso 'aplicando migraciones'
    php artisan migrate --force

    # Idempotente por diseño: empareja por clave natural `(marco, requisito)` y
    # nunca borra lo que desaparece de un fichero.
    aviso 'importando el catálogo normativo'
    php artisan catalogo:importar

    # En un bind mount de Windows el enlace simbólico puede no poder crearse.
    # No es crítico: las evidencias y los documentos viven en S3, no en el
    # disco público.
    php artisan storage:link 2>/dev/null || aviso 'storage:link omitido'

    aviso 'listo'
else
    esperar_a_postgres
    esperar_a_vendor
fi

exec "$@"
