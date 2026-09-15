# Imagen de la aplicación: PHP 8.4 con php-fpm.
#
# 8.4 y no 8.5 porque es lo que declara `composer.json` (`php: ^8.4`) y lo que
# cubre el lock entero: la entrada más exigente es `>=8.4.1`.
#
# No hay `COPY` del código. En desarrollo entra por bind mount desde el host, y
# `vendor/` vive en un volumen de Docker que llena el entrypoint. El contexto de
# build queda en unos pocos kilobytes.
FROM php:8.4-fpm

# Extensiones. Cada una está aquí porque algún paquete la exige como requisito
# duro en `composer.lock`, no por si acaso:
#
#   pdo_pgsql, pgsql  única conexión real; los tests también corren sobre PG
#   gd, zip           phpoffice/phpword (el .docx de copia de trabajo)
#   gmp               laravel/framework
#   pcntl             laravel/horizon — junto con posix, que ya viene en la imagen
#   intl, bcmath      dependencias transitivas y el formato en español
#   opcache           rendimiento del autoloader, que se lee en cada petición
#
# NO se instala `redis`: el cliente es predis, PHP puro, por decisión escrita en
# CLAUDE.md. Tampoco `imagick`: el QR de las etiquetas se renderiza a SVG.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        intl \
        gd \
        zip \
        bcmath \
        gmp \
        pcntl \
        opcache

# `pg_isready`, que el entrypoint usa para esperar a la base, y `git` y `unzip`,
# que Composer necesita para instalar desde fuentes que no traen dist.
RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/statera.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# En Windows los bind mounts no aplican ownership de Linux, así que esto no
# cambia nada hoy. Está para que la imagen siga sirviendo si algún día se
# construye sobre Linux, donde un uid que no coincide deja `storage/` sin
# permisos de escritura y el fallo aparece como un 500 sin explicación.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data

WORKDIR /var/www/html

# Composer corre como www-data y necesita un HOME donde dejar su caché.
ENV COMPOSER_HOME=/tmp/composer
ENV COMPOSER_MEMORY_LIMIT=-1

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
