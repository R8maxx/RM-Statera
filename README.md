# Statera

Gestor de cumplimiento **ISO/IEC 27001:2022** y **ENS (RD 311/2022)**.

Una sola herramienta para el ciclo completo de ambos marcos, con mapeo cruzado entre ellos: cada evidencia, tarea y documento se registra una vez y cuenta para todos los marcos donde aplique. La Declaración de Aplicabilidad de ISO y la del ENS son dos consultas sobre la misma tabla, no dos documentos mantenidos a mano.

Un producto de RM Technology.

## Documentación

| Fichero | Contenido |
|---|---|
| [`especificacion-gestor-cumplimiento.md`](especificacion-gestor-cumplimiento.md) | Especificación funcional: modelo de dominio, motor de categorización, módulos, fases de entrega. |
| [`stack-gestor-cumplimiento.md`](stack-gestor-cumplimiento.md) | Decisiones técnicas, con su justificación y lo descartado. |
| [`CLAUDE.md`](CLAUDE.md) | Reglas de proyecto para asistentes de código: invariantes, convenciones y avisos de versión. |
| [`.ai/rules/`](.ai/rules) | Lo específico de cada área —los módulos uno a uno y los desvíos respecto al stack—, en ficheros que se cargan según la ruta que se toca. |

## Requisitos

- Ubuntu con Docker Engine y el plugin `docker compose`

Nada más. PHP, Composer, Node y las extensiones viven dentro de los contenedores; el host no necesita ninguno.

## Arranque

```sh
cp .env.example .env
docker compose up -d --build
```

Y ya está: <http://localhost:8000>. El primer arranque tarda unos minutos —construye la imagen de PHP, instala `vendor/` y `node_modules/`— y por el camino genera `APP_KEY`, aplica las migraciones, importa el catálogo normativo y crea los buckets de MinIO. Los siguientes son cuestión de segundos.

| Servicio | Dónde |
|---|---|
| Aplicación | <http://localhost:8000> |
| Horizon | <http://localhost:8000/horizon> |
| Vite | <http://localhost:5173> |
| MinIO | <http://localhost:9000> (`statera` / `statera-secret`) |

Los contenedores escriben en la carpeta del proyecto —`vendor/`, `node_modules/`, `storage/`— con **tu** UID, no como root. Se da por hecho que es 1000, que es el del primer usuario de un Ubuntu recién instalado; si `id -u` devuelve otra cosa, añádelo al `.env`:

```sh
printf 'UID=%s\nGID=%s\n' "$(id -u)" "$(id -g)" >> .env
```

La base de tests `statera_test` y el rol de aplicación `statera_app` se crean solos, pero **sólo en el primer arranque del volumen de PostgreSQL**: si vienes de un volumen anterior, recréalo con `docker compose down -v && docker compose up -d`.

> La aplicación se conecta como `statera_app`, que **no** es superusuario. PostgreSQL exime a los superusuarios de la seguridad a nivel de fila incluso con `FORCE`, así que conectarse con el rol administrativo dejaría el aislamiento multi-tenant puesto y sin efecto.

## Comandos

Todo se ejecuta dentro del contenedor:

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan catalogo:importar
docker compose exec app composer test       # Pest, sobre PostgreSQL
docker compose exec app composer analyse    # Larastan, nivel 6
docker compose exec app composer lint       # Pint
docker compose exec app composer types      # regenera los tipos de TypeScript desde PHP
docker compose exec vite npm run type-check # vue-tsc
docker compose logs -f app queue vite
```

Para mirar lo que hay en MinIO —las versiones de MinIO desde mayo de 2025 traen la consola web recortada— se usa `mc`, que ya está en el montaje:

```sh
docker compose run --rm --entrypoint sh minio-init -c 'mc alias set s http://minio:9000 statera statera-secret && mc ls --recursive s/statera-documentos'
```

## El catálogo normativo

El catálogo (marcos, requisitos, refuerzos, matriz de aplicabilidad, mapeos ISO ↔ ENS, amenazas de MAGERIT y obligaciones periódicas) es global y compartido, y vive como datos versionados en `catalogo/*.yaml`. No es código: no hay enums ni constantes con los controles dentro.

```sh
docker compose exec app php artisan catalogo:importar --dry-run  # el diff, sin escribir nada
docker compose exec app php artisan catalogo:importar            # aplica los ficheros
```

El comando es idempotente y empareja por código, no por id. Los requisitos que desaparecen de un fichero se marcan, nunca se borran: puede haber implantaciones colgando de ellos.

> Los códigos, títulos y la matriz de aplicabilidad del ENS están pendientes de contraste celda a celda con el texto del BOE. Cada fichero lo indica con `revisado: false` en su cabecera.

## Estado

Veinticinco puntos del orden de arranque, de la fase 1 a la 3: catálogo e importador, motor de categorización ENS, implantaciones, inventario de activos, riesgos, documentos con Gotenberg, plan de acción, auditorías, no conformidades, indicadores, objetivos, mejoras, revisión por la dirección, personas, incidentes, el calendario de obligaciones y continuidad. Las tres fases están cerradas. El detalle, y qué falta después, en [`CLAUDE.md`](CLAUDE.md) y en [`.ai/rules/orden-de-arranque.md`](.ai/rules/orden-de-arranque.md).

## Licencia

Software propietario. Todos los derechos reservados.
