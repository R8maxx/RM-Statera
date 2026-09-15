#!/bin/sh
set -e

# Vite. `node_modules` vive en un volumen de Docker, así que arranca vacío la
# primera vez y hay que llenarlo aquí.
#
# Se comprueba un paquete concreto y no sólo la carpeta: el volumen existe desde
# el primer arranque, y un `npm ci` interrumpido dejaría una carpeta presente e
# inservible.
if [ ! -d node_modules/vite ]; then
    echo '[statera] instalando dependencias de Node (la primera vez tarda)…'
    # El `package-lock.json` se generó en Windows. Si le faltan las variantes de
    # Linux de algún binario opcional —esbuild, rollup—, `npm ci` se planta;
    # `--no-save` resuelve sin tocar el lock, que es un fichero versionado y no
    # debe cambiar por haber arrancado un contenedor.
    npm ci || {
        echo '[statera] npm ci falló; resolviendo sin tocar el lock'
        npm install --no-save
    }
fi

exec npm run dev
