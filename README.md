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

## Requisitos

- PHP 8.4 o superior
- Composer
- Docker (PostgreSQL 17, Redis, Gotenberg y MinIO)

## Arranque

```sh
composer install
cp .env.example .env
php artisan key:generate

docker compose up -d          # postgres, redis, gotenberg, minio
php artisan migrate
php artisan catalogo:importar # carga el catálogo normativo desde catalogo/*.yaml

composer dev                  # servidor, cola y logs
```

La aplicación PHP corre en el host. Docker solo levanta los servicios de apoyo; la base de tests `statera_test` y el rol de aplicación `statera_app` se crean solos en el primer arranque del volumen de PostgreSQL.

> La aplicación se conecta como `statera_app`, que **no** es superusuario. PostgreSQL exime a los superusuarios de la seguridad a nivel de fila incluso con `FORCE`, así que conectarse con el rol administrativo dejaría el aislamiento multi-tenant puesto y sin efecto. Si vienes de un volumen anterior a este cambio, recréalo con `docker compose down -v && docker compose up -d`.

Para el almacén de evidencias en local hay que crear el bucket una vez en la consola de MinIO (<http://127.0.0.1:9001>, usuario `statera`).

## Comandos

```sh
composer test      # Pest, sobre PostgreSQL
composer analyse   # Larastan, nivel 6
composer lint      # Pint
```

## El catálogo normativo

El catálogo (marcos, requisitos, refuerzos, matriz de aplicabilidad y mapeos ISO ↔ ENS) es global y compartido, y vive como datos versionados en `catalogo/*.yaml`. No es código: no hay enums ni constantes con los controles dentro.

```sh
php artisan catalogo:importar --dry-run    # muestra el diff sin escribir nada
php artisan catalogo:importar              # aplica los tres ficheros
```

El comando es idempotente y empareja por código, no por id. Los requisitos que desaparecen de un fichero se marcan, nunca se borran: puede haber implantaciones colgando de ellos.

> Los códigos, títulos y la matriz de aplicabilidad del ENS están pendientes de contraste celda a celda con el texto del BOE. Cada fichero lo indica con `revisado: false` en su cabecera.

## Estado

Puntos 1 a 3 de 6 del orden de arranque: catálogo e importador, motor de categorización ENS, y generación de implantaciones con aislamiento multi-tenant, transiciones y recálculo. El detalle está en `CLAUDE.md`.

## Licencia

Software propietario. Todos los derechos reservados.
