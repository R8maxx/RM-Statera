# Statera

Gestor de cumplimiento **ISO/IEC 27001:2022 + ENS (RD 311/2022)**. Una sola herramienta para el ciclo completo de ambos marcos, con mapeo cruzado entre ellos: cada evidencia, tarea y documento se registra **una vez** y cuenta para todos los marcos donde aplique. Hoy ese trabajo se lleva en hojas de cálculo duplicadas, y ese es el problema que el producto resuelve.

Fase actual: uso interno, objetivo ENS categoría **básica**. Fase futura: producto vendible, con clientes de categoría básica, media o alta y otros marcos.

El producto se llama **Statera** a secas. Nunca "RM Statera" ni "RM - Statera". El respaldo va en letra pequeña: "un producto de RM Technology".

## Documentos de referencia

Tres ficheros en la raíz del repositorio mandan sobre cualquier suposición:

| Fichero | Qué contiene |
|---|---|
| `especificacion-gestor-cumplimiento.md` | El **qué**: modelo de dominio tabla a tabla, motor de categorización, 19 módulos funcionales, contenido del catálogo, fases de entrega. |
| `stack-gestor-cumplimiento.md` | El **con qué** y el **por qué**: marca, decisiones técnicas con su justificación, y lo descartado con el motivo. |
| `DESIGN.md` | El **cómo se ve**: sistema de diseño completo —principios, marca, paleta teal/violeta/neutros, tipografía, retícula, forma, iconografía, componentes, movimiento, accesibilidad, tokens CSS y voz—. |

**Léelos antes de tocar el modelo de datos, antes de proponer tecnología, antes de pintar una pantalla y antes de reabrir una decisión.** El apartado "Descartado y por qué" del stack existe precisamente para que no se rediscuta cada dos semanas.

`DESIGN.md` es la fuente de verdad de todo lo visual y **manda sobre el apartado de marca del stack**: color, tipografía, espaciado, radios, sombras, iconos, copia de interfaz. Nada de hex sueltos ni de valores inventados en plantillas o estilos: si un color, un tamaño o un radio no está en `DESIGN.md`, se añade allí antes de usarlo. Los componentes leen tokens de rol (`--accent`, `--text-primary`, `--border`), no de escala, para que el modo oscuro no obligue a tocar componentes. Antes de dar una pantalla por terminada, pasa la lista de comprobación de su §14.

Y un cuarto sitio, que no es un documento sino un mecanismo: **`.ai/rules/`**. Lo
específico de cada área del código —los módulos uno a uno y los desvíos respecto al
stack— vive ahí, en ficheros con un `paths:` delante, y se carga **sólo cuando la ruta
que se está tocando encaja**. Este fichero se queda con lo que hay que tener delante
siempre. El mapa está abajo, en «Dónde está cada cosa».

## Invariantes

Romper cualquiera de estos obliga a rehacer el modelo más adelante. No son preferencias.

1. **`organizacion_id` en toda tabla de datos propios**, desde la primera migración, aunque solo haya una organización durante meses. Retrofitear multi-tenancy es caro y arriesgado.
2. **El catálogo normativo NO lleva `organizacion_id`.** Es global, compartido entre tenants y versionado. Catálogo y datos de organización jamás en las mismas tablas.
3. **El catálogo es datos, no código.** Nada de enums, constantes o clases con los controles hardcodeados. ISO 27001 ya tiene enmienda de 2024 y el ENS tendrá revisiones.
4. **La aplicabilidad se deriva, no se selecciona.** El usuario valora las cinco dimensiones; el sistema calcula la categoría y de ahí el conjunto exigible. Nunca marcar controles a mano.
5. **Anexo II completo**, aunque solo se use el subconjunto de básica. Cargarlo a medias significa rehacer el modelo cuando llegue un cliente de categoría media.
6. **Evidencia ↔ requisito es N:M.** Una captura puede probar un control ISO y tres medidas ENS.
7. **Los estados llevan histórico.** El auditor no pregunta "¿está implantado?", pregunta "¿desde cuándo?". Toda transición se registra con fecha y autor, en su tabla.
8. **La herramienta entra en el alcance del propio SGSI.** Contiene el inventario, las vulnerabilidades y las evidencias. 2FA, cifrado en reposo, backups verificados y traza inmutable no son aplazables.

   > **Del invariante 8, las vulnerabilidades todavía no están.** No hay registro: `riesgos.vulnerabilidad` es una columna de texto libre del escenario MAGERIT —la condición que hace creíble la amenaza—, que no es lo mismo que un hallazgo técnico con severidad, activo afectado y plazo de remediación (A.8.8 de ISO, `op.exp.4` del ENS). El invariante se queda como está porque es el objetivo declarado, y esta nota existe para que la frase no se lea como una afirmación de estado. Se construye con el módulo de vulnerabilidades; hasta entonces, queda dicho.

Y dos reglas operativas que se derivan de lo anterior:

- **Prohibido `withoutGlobalScopes()`** fuera de comandos de mantenimiento explícitos. Hay tres capas de aislamiento (`organizacion_id`, global scope de Eloquent, Row Level Security en PostgreSQL) y quitar la del medio filtra datos de un cliente a otro. La única puerta que atraviesa las tres es `ContextoOrganizacion::comoMantenimiento()`, y sólo la usa código sin petición ni usuario: los recuentos de afectados del importador del catálogo, que por definición cruzan organizaciones; `documentos:generar` e `implantaciones:generar`, para localizar su fila antes de fijar la organización; y las migraciones que mueven o borran filas de tablas con RLS, casi siempre en su `down()`.
- **Nada de datos reales de Avanza** en seeds, fixtures, demos ni tests. Solo datos sintéticos. El proyecto es personal de César, no de Avanza; la separación se mantiene explícita.

## Convenciones de código

- **El dominio se nombra en español**, porque los marcos normativos están en español y traducir `no_conformidad` o `valoracion_dimensiones` solo añade una capa de traducción mental: `requisitos`, `implantaciones`, `evidencias`, `no_conformidades`, `refuerzos`, `aplicabilidad_ens`. El vocabulario de Laravel (`Controller`, `Request`, `Resource`, `Job`) se queda en inglés.
- **La lógica vive en `app/Domain/<Contexto>/`**, no en controladores ni en componentes de interfaz. Los controladores validan, delegan y devuelven Inertia. Los `FormRequest` son la única fuente de verdad de la validación.
- Razón de esa disciplina: que la capa de presentación sea desechable. En la fase producto se cambia sin tocar el dominio.
- Larastan **nivel 6 mínimo** (`composer analyse`). Pint antes de cerrar (`composer lint`).

## Base de datos

**PostgreSQL 17. No MySQL, no SQLite** — y esto es funcional, no una preferencia:

- **CTEs recursivas** para la jerarquía de requisitos (`op` → `op.acc` → `op.acc.4`) y el grafo de dependencias entre activos. Nada de closure tables ni de recursión en PHP.
- **JSONB con índices GIN** para los cinco atributos de la ISO 27002, las entradas de la revisión por dirección y los valores anteriores del log de auditoría.
- **Row Level Security** como segunda barrera del aislamiento multi-tenant.
- **Restricciones `CHECK`** para los estados, que aquí son muchos y tienen que ser estrictos. Se usan enums PHP respaldados + `CHECK`, no tipos `ENUM` de PostgreSQL: alterar un tipo enum de PG en una migración es doloroso y estos catálogos van a evolucionar.

**Los tests corren sobre PostgreSQL** (base `statera_test`), no sobre SQLite en memoria. Un test verde sobre SQLite no prueba nada de lo anterior.

## Avisos de versión

Por defecto un asistente genera aquí código obsoleto. Estos tres puntos son los que más cuestan de reparar:

- **Inertia v3, no v2.** Plugin `@inertiajs/vite` (sin callbacks `resolve`/`setup` en el punto de entrada), `useHttp` para peticiones que no son navegación, actualizaciones optimistas con rollback, layout props. **Axios ya no es dependencia.** `Inertia::lazy()` desapareció: es `Inertia::optional()`. En `config/inertia.php` las claves son `pages.paths` y `pages.extensions`. Consulta la guía de actualización a v3 antes de escribir el punto de entrada o cualquier petición HTTP.
- **TanStack Table v9, no v8.** `useTable`, no `useReactTable`. El estado va por `table.state` / `table.store` y átomos por slice, no por `getState()`. Casi toda la documentación y los ejemplos que hay por ahí son de v8 y no funcionan. **Versión exacta en `package.json`, sin `^`.** Se usa en modo manual (`manualPagination`, `manualSorting`, `manualFiltering`) porque las consultas son de servidor.
- **Gotenberg 8.9.1 o superior, imagen anclada, nunca el tag `8`** (entre 8.8.0 y 8.9.1 la conversión a PDF/A y PDF/UA estuvo rota). Nunca alcanzable desde fuera de la red interna: descarga las URL que se le pasen, así que exponerlo es un SSRF de manual. Se le envía **el HTML y los assets en el multipart, nunca una URL**. Siempre en cola. PDF/A-3b para todo documento archivable. **El PDF generado se almacena, no se regenera**: la SoA que se entregó al auditor tiene que poder demostrarse tal cual se firmó.

## Stack

| Capa | Elección |
|---|---|
| Lenguaje / framework | PHP 8.4+ / Laravel 13 |
| Base de datos | PostgreSQL 17 |
| Vista | Inertia 3 + Vue 3 + TypeScript |
| Estilos / componentes | Tailwind CSS 4 + shadcn-vue (sobre Reka UI) |
| Tablas | TanStack Table 9.2.4, versión exacta |
| Arrastrar y soltar | `@atlaskit/pragmatic-drag-and-drop` 3.1.0, versión exacta. Sólo el tablero, y siempre con el menú detrás |
| Organigrama | `d3-hierarchy` 3.1.2 (disposición) + `@vue-flow/core` 1.48.2 (lienzo), versión exacta. Sólo el organigrama, y siempre con la lista detrás |
| Grafo de activos | `elkjs` 0.12.0 (disposición de DAG) sobre el mismo lienzo, versión exacta. Sólo la vecindad de un activo, y siempre con las listas de la ficha detrás |
| PDF | Gotenberg 8.9.1 en contenedor |
| Colas | Redis + Horizon (`documentos`, `importadores`, `notificaciones`, `default`) |
| Evidencias | S3 con versionado y Object Lock; disco `evidencias` |
| Auth | Fortify con 2FA. Sin registro self-service |
| Tests | Pest sobre PostgreSQL |

Los tipos de TypeScript se **generan** desde PHP con `spatie/laravel-typescript-transformer`. Nunca se escriben a mano dos veces.

No usar paquetes de multi-tenancy de terceros: es la frontera de seguridad principal, se implementa y se testea a mano. (`spatie/laravel-permission` sí se usa, pero para RBAC, que es otra cosa.)

## Los módulos

Veintiséis puntos, y el orden importa: el catálogo y el motor son la parte más
específica del dominio y la que más se estropea si se improvisa; el resto es CRUD con
reglas de negocio encima. **El porqué de cada uno —qué problema abrió, qué decisión se
tomó y qué dejó declarado que no hace— está en su fichero de reglas**, y la bitácora
completa con el razonamiento del orden, en `.ai/rules/orden-de-arranque.md`.

| # | Módulo | § | Reglas |
|---|---|---|---|
| 1 | Esquema del catálogo + importador idempotente | — | `catalogo.md` |
| 2 | Motor de categorización ENS | — | `catalogo.md` |
| 3 | Generación de implantaciones, transiciones y recálculo | — | `implantaciones.md` |
| 4 | Capa de recursos genérica | — | `recursos.md` |
| 5 | Inventario de activos | 4.2 | `activos.md` |
| 6 | SoA de ISO y DdA del ENS, con Gotenberg | 4.4 | `documentos.md`, `documentos-render.md` |
| 7 | Plan de acción y capa de avisos — **cierra la fase 1** | 4.7 | `tareas.md` |
| 8 | Análisis de riesgos — **abre la fase 2** | 4.3 | `riesgos.md` |
| 9 | Aprobación documental con acuse de lectura | 4.5 | `documentos.md` |
| 10 | Plan de adecuación del ENS — **cierra la fase 2** | — | `documentos.md` |
| 11 | Auditorías — **abre la fase 3** | 4.12 | `auditorias.md` |
| 12 | No conformidades y acciones correctivas | 4.13 | `no-conformidades.md` |
| 13 | Contexto de la organización | 4.1 | `contexto.md` |
| 14 | Indicadores y mediciones | 4.14 | `metricas.md` |
| 15 | Objetivos de seguridad | 6.2 | `objetivos.md` |
| 16 | Oportunidades de mejora | 10.1 | `mejoras.md` |
| 17 | Revisión por la dirección | 4.15 | `revision-direccion.md` |
| 18 | Personas y la cláusula 5.3 | 4.8 | `personas.md` |
| 19 | Incidentes | 4.10 | `incidentes.md` |
| 20 | Puestos, datos de la persona y adjuntos | — | `personas.md`, `adjuntos.md` |
| 21 | Mi cuenta | — | `perfil.md` |
| 22 | La ficha de la organización | — | `organizacion.md` |
| 23 | La marca del cliente | — | `organizacion.md` |
| 24 | El calendario de obligaciones | 4.16 | `obligaciones.md` |
| 25 | Continuidad — **cierra la fase 3** | 4.11 | `continuidad.md` |
| 26 | Conformidad con el ENS, categoría básica | 4.17 | `conformidad.md` |

**La fase 3 está cerrada.** Con continuidad (§ 4.11) dentro —el BIA por servicio,
el plan como documento y las pruebas que lo contrastan— el ciclo vivo se recorre
entero: auditar, tratar, medir, revisar desde la dirección, vigilar los plazos y
comprobar que la organización sabe recuperarse.

Y con el punto 26 el objetivo de la fase actual se recorre de punta a punta: la
conformidad de categoría básica —autoevaluación, Declaración firmada y distintivo— ya
tiene dónde vivir (§ 4.17). La vía de media y alta se modela y no se recorre.

Lo que queda: informes y exportación (§ 4.18); la gestión de cuentas y roles (§ 4.19);
proveedores (§ 4.9), y el registro de vulnerabilidades del invariante 8.

## El catálogo

Vive en `catalogo/*.yaml`, versionado en el repositorio, y se carga con un comando idempotente:

```sh
php artisan catalogo:importar                    # importa los cinco ficheros
php artisan catalogo:importar --dry-run          # muestra el diff sin escribir
php artisan catalogo:importar catalogo/ens-rd311-2022.yaml
```

El importador empareja por clave natural `(marco.codigo, requisito.codigo)`, nunca por id, y clasifica en nuevos / modificados / desaparecidos. **Los desaparecidos no se borran**: se marcan, porque puede haber implantaciones colgando de ellos. Al importar una revisión de un marco, el comando informa de cómo afecta a las implantaciones existentes; no modifica nada en silencio.

## Comandos

Todo corre en contenedores, la aplicación incluida. El host no necesita ni PHP ni
Node.

```sh
docker compose up -d --build        # levanta el producto entero
```

Lo demás va dentro. Con `app` basta para todo lo de PHP; `vite` es el de Node:

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan catalogo:importar       # ISO, ENS, mapeos, amenazas de MAGERIT y obligaciones periódicas
docker compose exec app php artisan db:seed                 # organización, usuarios, sistema, inventario, tareas, riesgos, personas y puestos (sintéticos)
docker compose exec app php artisan avisos:enviar --dry-run # lo que saldría por correo, sin enviarlo
docker compose exec app php artisan indicadores:medir --dry-run # la cifra que se sellaría, sin escribirla
docker compose exec app composer test                       # Pest sobre PostgreSQL
docker compose exec app composer analyse                    # Larastan nivel 6
docker compose exec app composer lint                       # Pint
docker compose exec app composer types                      # regenera resources/js/types/generated.d.ts desde PHP
docker compose exec vite npm run type-check                 # vue-tsc
docker compose exec vite npm run build
docker compose logs -f app queue vite
```

Cinco servicios propios: `nginx` (el 8000), `app` (php-fpm), `queue` (Horizon),
`vite` (el 5173) y `minio-init`, que crea los **tres** buckets —evidencias,
documentos y adjuntos— y se apaga. Detrás siguen
`postgres`, `redis`, `gotenberg` y `minio`.

## Prioridad de cobertura de tests

Por orden, según dónde duele un fallo silencioso:

1. **Motor de categorización ENS** — un fallo aquí deja a un cliente fuera de conformidad sin que nadie se entere. Matriz completa, incluidas las medidas moduladas por nivel de dimensión.
2. **Aislamiento multi-tenant** — que ninguna consulta cruce la frontera de organización.
3. **Transiciones de estado de implantación** y recálculo tras un cambio de valoración.
4. **Importador del catálogo**, incluida la idempotencia y el diff.
5. Resto de módulos: flujos principales.

Y nueve tests **descubren** en vez de enumerar, así que cubren solos lo que traiga el
módulo siguiente. Cuáles son y qué convierte en rojo cada uno, en `.ai/rules/tests.md`.

## Dónde está cada cosa

`.ai/rules/` es el mecanismo que ya declara el bloque de Laravel Boost del final de
este fichero: **antes de planificar o de editar, se abre `.ai/rules/index.md`, se busca
la fila cuyos globs cubran la ruta en cuestión y se lee ese fichero.** Un `grep -rin`
sobre el directorio pilla lo que un encaje de ruta se deja.

> **`index.md` es generado, no escrito.** Lo reconstruye `RuleRepository::writeIndex()`
> entero —título, la línea de instrucción y la tabla— cada vez que alguien llama a
> `record-rule`. Por eso la tabla legible es ésta y no aquélla: lo que se escriba a mano
> en `index.md` se pierde en la primera grabación. Y por eso **nada nuestro va en
> `.ai/rules/boost/`**, que `syncManaged()` borra entero antes de reescribirlo.
>
> El otro fallo silencioso de este mecanismo: **un fichero sin `paths:` en el
> frontmatter no aparece en el índice y no lo lee nadie**. `writeIndex()` lo descarta
> sin decir nada.

### Transversales

| Fichero | Se carga al tocar | Qué lleva |
|---|---|---|
| `aislamiento.md` | `app/Domain/**`, middleware, factories, seeders | Las tres capas; el orden del middleware; la factory que no declara `organizacion_id`; `scoped` y no `singleton`; el job sin `SerializesModels`; `User` fuera de las tres capas; el rol `statera_app` |
| `recursos.md` | `app/Http/Resources/**`, `components/tabla/**`, `components/formulario/**` | La capa de recursos entera: qué describe un `Recurso`, filtros, `MetaTabla`, la vista guardada en el navegador, y por qué la clase se llama `Recurso` y no `Resource` |
| `routing.md` | `routes/**` | `scopeBindings()` pluraliza en inglés: los cuatro `resolveChildRouteBinding()` escritos a mano y el parámetro que se llama `{accion}` |
| `migraciones.md` | `database/migrations/**` | `CREATE OR REPLACE FUNCTION`; el `CHECK` construido desde un enum que `migrate:fresh` no prueba |
| `diseno.md` | `resources/css/**`, `components/ui/**` | La paleta: hue 196, `--acento` frente a `--accent`, los cuatro sitios del violeta, radios, contraste y protanopía |
| `interfaz.md` | `resources/js/**` | `lib/tonos.ts` y `lib/navegacion.ts` como mapas únicos; los tres canales de un estado; qué librería entró, cuál no y por qué |
| `tests.md` | `tests/**` | Los nueve tests que descubren en vez de enumerar |
| `infraestructura.md` | `docker-compose.yml`, `docker/**`, `.env.example` | Los dos endpoints de MinIO, `quay.io`, el `ARG UID`, `predis` |
| `orden-de-arranque.md` | este fichero, `README.md`, `PRODUCT.md` | La bitácora de los 26 puntos, con el razonamiento del orden |

### Por módulo

| Fichero | Se carga al tocar |
|---|---|
| `catalogo.md` | `app/Domain/Catalogo/**`, `app/Domain/Categorizacion/**`, `catalogo/**` |
| `implantaciones.md` | `app/Domain/Implantacion/**` y sus pantallas |
| `evidencias.md` | `app/Domain/Evidencia/**` y sus pantallas |
| `activos.md` | `app/Domain/Activo/**`, sus pantallas, `lib/grafoActivos.ts`, `config/obsolescencia.php` |
| `documentos.md` | `app/Domain/Documento/**`, sus pantallas y las plantillas |
| `documentos-render.md` | `resources/views/documentos/**`, `Documento/Render/**`, `lib/cuerpoDocumento.ts` |
| `tareas.md` | `app/Domain/Tarea/**` y sus dos pantallas |
| `obligaciones.md` | `app/Domain/Obligacion/**`, `app/Domain/Aviso/**`, el calendario y las obligaciones |
| `riesgos.md` | `app/Domain/Riesgo/**` y sus pantallas |
| `auditorias.md` | `app/Domain/Auditoria/**` y sus pantallas |
| `no-conformidades.md` | `app/Domain/NoConformidad/**` y sus pantallas |
| `contexto.md` | `app/Domain/Contexto/**`, contexto y partes interesadas |
| `metricas.md` | `app/Domain/Metrica/**`, indicadores y `components/grafica/**` |
| `objetivos.md` | `app/Domain/Objetivo/**` y sus pantallas |
| `mejoras.md` | `app/Domain/Mejora/**` y sus pantallas |
| `revision-direccion.md` | `app/Domain/RevisionDireccion/**` y sus pantallas |
| `personas.md` | `app/Domain/Persona/**`, personas, puestos, formación y `lib/organigrama.ts` |
| `incidentes.md` | `app/Domain/Incidente/**` y sus pantallas |
| `continuidad.md` | `app/Domain/Continuidad/**`, el BIA, las pruebas y sus pantallas |
| `conformidad.md` | `app/Domain/Conformidad/**`, la conformidad y la Declaración de Conformidad |
| `adjuntos.md` | `app/Domain/Adjunto/**` y `components/adjunto/**` |
| `panel.md` | `app/Domain/Panel/**` y las tres vistas del panel |
| `organizacion.md` | `app/Domain/Organizacion/**` y la ficha del tenant |
| `perfil.md` | `pages/perfil/**`, `app/Domain/Autorizacion/**` |

**Un módulo nuevo entra con su fichero y su `paths:`**, y no tocando este mapa: lo que
hace que se cargue es el glob, no la fila de esta tabla. La tabla es para leerla un
humano.

## Fuera de alcance

Facturación y suscripciones, onboarding self-service, panel de superadministración, white-labeling, integraciones con SIEM o escáneres, aplicación móvil. Los flujos de auditoría formal ENS de categoría media y alta **se modelan pero no se implementan**. NIS2 todavía no se carga, pero el modelo de marcos tiene que permitir añadirla sin cambios estructurales.

---
<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
