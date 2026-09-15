#!/bin/sh
set -e

# Vite. El host no tiene Node, así que `node_modules` lo llena este contenedor
# —en la carpeta del proyecto, con tu UID: el servicio declara `user`, porque el
# root del contenedor dejaría ahí decenas de miles de ficheros que tu usuario no
# podría borrar.
#
# Se comprueba un paquete concreto y no sólo la carpeta: un `npm ci`
# interrumpido deja una carpeta presente e inservible.
if [ ! -d node_modules/vite ]; then
    echo '[statera] instalando dependencias de Node (la primera vez tarda)…'
    npm ci
fi

exec npm run dev
