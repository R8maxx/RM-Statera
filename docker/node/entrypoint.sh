#!/bin/sh
set -e

# Vite. El host no tiene Node, así que `node_modules` lo llena este contenedor
# —en la carpeta del proyecto, con tu UID: el servicio declara `user`, porque el
# root del contenedor dejaría ahí decenas de miles de ficheros que tu usuario no
# podría borrar.
#
# Dos cosas dejan `node_modules` inservible, y las dos fallan callando:
#
# 1. **Una instalación a medias.** Se comprueba el BINARIO y no la carpeta del
#    paquete: npm crea las carpetas al principio y enlaza los ejecutables al
#    final, así que un `npm ci` interrumpido deja `node_modules/vite` presente y
#    `vite` sin instalar. Mirar la carpeta daba por buena esa instalación a
#    medias y el contenedor moría con «sh: vite: not found».
#
# 2. **Una instalación de OTRA plataforma.** npm resuelve las dependencias
#    opcionales con binarios nativos —rolldown, el oxide de Tailwind— según el
#    sistema donde corre, así que un `npm install` lanzado desde el host deja
#    aquí `@rolldown/binding-darwin-x64` donde Alpine necesita
#    `@rolldown/binding-linux-x64-musl`. Y no basta con mirar la arquitectura:
#    un Mac Intel y este contenedor son los dos `x64` y aun así no comparten un
#    solo binario. El síntoma no menciona el host: vite muere con «Cannot find
#    native binding», el contenedor sale con código 1 —`docker compose ps` a
#    secas ni siquiera lo lista— y, como sin él no hay `public/hot`, Laravel cae
#    al build estático de `public/build/` y sirve una interfaz vieja sin que
#    nada avise. Eso ya costó una tarde.
#
# De ahí la huella: se sella la plataforma al instalar y se compara al arrancar.
# Se sella, en vez de comprobar el paquete nativo de turno, porque así vale para
# cualquier dependencia con binarios —las de hoy y la que entre mañana— sin
# tener que saberse sus nombres.
HUELLA=node_modules/.statera-plataforma
PLATAFORMA="$(node -p "[process.platform, process.arch, process.report.getReport().header.glibcVersionRuntime ? 'glibc' : 'musl'].join('-')")"

if [ ! -x node_modules/.bin/vite ] || [ "$(cat "$HUELLA" 2>/dev/null)" != "$PLATAFORMA" ]; then
    echo "[statera] instalando dependencias de Node para $PLATAFORMA (la primera vez tarda)…"
    # `npm ci` vacía node_modules antes de instalar, así que repara los dos
    # destrozos sin que haya que borrar nada a mano.
    npm ci
    echo "$PLATAFORMA" > "$HUELLA"
fi

# En desarrollo manda el dev server, y no puede quedar un build estático a su
# espalda. Cuando `@vite` no encuentra `public/hot` cae al manifest de
# `public/build/`, así que un `npm run build` de hace tres semanas convierte la
# muerte de este contenedor en algo que no se ve: la aplicación sigue en pie,
# sirviendo una interfaz vieja, y nada dice que los assets llevan días sin
# recompilarse. Sin manifest, la misma avería es un error en la primera página
# que se abra.
#
# `public/build` está en `.gitignore` y lo rehace `npm run build` en un comando,
# así que aquí no se pierde nada que costara más que eso. Quien construya para
# producción lo hará donde no corre este servicio.
rm -rf public/build

exec npm run dev
