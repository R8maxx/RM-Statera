#!/bin/sh
set -e

# Vite. El host no tiene Node, así que `node_modules` lo llena este contenedor
# —en la carpeta del proyecto, con tu UID: el servicio declara `user`, porque el
# root del contenedor dejaría ahí decenas de miles de ficheros que tu usuario no
# podría borrar.
#
# Se comprueba el BINARIO y no la carpeta del paquete: npm crea las carpetas al
# principio y enlaza los ejecutables al final, así que un `npm ci` interrumpido
# deja `node_modules/vite` presente y `vite` sin instalar. Mirar la carpeta daba
# por buena esa instalación a medias y el contenedor moría con
# «sh: vite: not found». `npm ci` vacía node_modules antes de instalar, de modo
# que además repara el destrozo.
if [ ! -x node_modules/.bin/vite ]; then
    echo '[statera] instalando dependencias de Node (la primera vez tarda)…'
    npm ci
fi

exec npm run dev
