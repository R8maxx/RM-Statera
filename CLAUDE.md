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

Y dos reglas operativas que se derivan de lo anterior:

- **Prohibido `withoutGlobalScopes()`** fuera de comandos de mantenimiento explícitos. Hay tres capas de aislamiento (`organizacion_id`, global scope de Eloquent, Row Level Security en PostgreSQL) y quitar la del medio filtra datos de un cliente a otro. La única puerta que atraviesa las tres es `ContextoOrganizacion::comoMantenimiento()`, y hoy la usa un solo sitio: el recuento de implantaciones afectadas del importador del catálogo, que por definición cruza organizaciones.
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
| PDF | Gotenberg 8.9.1 en contenedor |
| Colas | Redis + Horizon (`documentos`, `importadores`, `notificaciones`, `default`) |
| Evidencias | S3 con versionado y Object Lock; disco `evidencias` |
| Auth | Fortify con 2FA. Sin registro self-service |
| Tests | Pest sobre PostgreSQL |

Los tipos de TypeScript se **generan** desde PHP con `spatie/laravel-typescript-transformer`. Nunca se escriben a mano dos veces.

No usar paquetes de multi-tenancy de terceros: es la frontera de seguridad principal, se implementa y se testea a mano. (`spatie/laravel-permission` sí se usa, pero para RBAC, que es otra cosa.)

## Orden de arranque

El orden importa: el catálogo y el motor son la parte más específica del dominio y la que más se estropea si se improvisa; el resto es CRUD con reglas de negocio encima.

1. ✅ Esquema del catálogo + comando de importación idempotente desde YAML.
2. ✅ Motor de categorización ENS, con tests exhaustivos de la matriz completa.
3. ✅ Generación de implantaciones desde el motor, con transiciones y recálculo.
4. ✅ Capa de recursos genérica (`Recurso` + `DataTable` + formularios), validada con Sistemas (CRUD) e Implantaciones (lectura y acción masiva).
5. ✅ Primer módulo completo de punta a punta: inventario de activos.
6. ✅ Primeros documentos con Gotenberg: la SoA de ISO y la DdA del ENS.
7. ✅ Plan de acción (§ 4.7) y la capa de avisos. Con esto la **fase 1** de la
   especificación —«sustituir las hojas de cálculo»— queda completa: catálogo,
   motor, inventario, implantaciones, evidencias y tareas.

## El catálogo

Vive en `catalogo/*.yaml`, versionado en el repositorio, y se carga con un comando idempotente:

```sh
php artisan catalogo:importar                    # importa los tres ficheros
php artisan catalogo:importar --dry-run          # muestra el diff sin escribir
php artisan catalogo:importar catalogo/ens-rd311-2022.yaml
```

El importador empareja por clave natural `(marco.codigo, requisito.codigo)`, nunca por id, y clasifica en nuevos / modificados / desaparecidos. **Los desaparecidos no se borran**: se marcan, porque puede haber implantaciones colgando de ellos. Al importar una revisión de un marco, el comando informa de cómo afecta a las implantaciones existentes; no modifica nada en silencio.

**Contrastado con el BOE, y lo que falta.** El Anexo II se contrastó celda a celda contra el texto consolidado de BOE-A-2022-7191 y salieron **73 celdas mal de 273**, cinco títulos parafraseados y una medida de sobra —`op.exp.11`, numeración del RD 3/2010—. Lo más grave: nueve medidas que el ENS exige en categoría **básica** estaban como `no_aplica`, entre ellas `op.exp.7` (gestión de incidentes), `op.exp.8` (registro de la actividad) y `op.mon.1` (detección de intrusión). Es exactamente el fallo silencioso que encabeza la lista de prioridades de cobertura: dejaba a un sistema básico fuera de conformidad sin que nadie se enterara. Todo eso está corregido, y `tests/Feature/Catalogo/AnexoIIVerificadoTest.php` clava las celdas para que no vuelvan a torcerse.

`revisado` sigue en `false` en los tres YAML porque quedan **dos cosas que son modelo, no datos**:

1. **Nueve medidas están moduladas por varias dimensiones a la vez** (`op.acc.1` por T y A; `op.acc.2`–`op.acc.6` por C, I, T y A; `mp.com.3` y `mp.info.3` por I y A; `mp.si.2` por C e I) y `aplicabilidad_ens` guarda una sola `dimension_moduladora`. Mientras tanto se leen por categoría —el máximo de las cinco—, que exige de más pero **nunca de menos**.
2. **Diez celdas de cuatro medidas** (`op.acc.5`, `op.acc.6`, `mp.com.4`, `mp.s.2`) exigen un refuerzo **a elegir** entre varios: «+ [R1 o R2]». `Exigencia` guarda un único `Rn` y no sabe expresar una alternativa; queda registrado lo que se exige con seguridad.

Y sobre los refuerzos: en el Anexo II **se acumulan** —«+ R1 + R2» exige los dos—, así que el catálogo guarda el mayor y `R2` se lee como «hasta R2», no como «sólo R2».

**Sobre el texto normativo:** en los YAML van código, título corto y atributos. La redacción íntegra de los controles de ISO 27001/27002 tiene derechos de autor y no se vuelca al repositorio. El ENS es texto del BOE y no tiene esa restricción.

## Comandos

```sh
docker compose up -d                # postgres 17, redis, gotenberg, minio
composer dev                        # servidor, cola y logs
php artisan migrate
php artisan catalogo:importar
php artisan db:seed                 # organización, usuario y sistema de desarrollo (sintéticos)
php artisan avisos:enviar --dry-run # lo que saldría por correo, sin enviarlo
composer test                       # Pest sobre PostgreSQL
composer analyse                    # Larastan nivel 6
composer lint                       # Pint
composer types                      # regenera resources/js/types/generated.d.ts desde PHP
npm run type-check                  # vue-tsc
npm run build
```

La aplicación PHP corre en el host; Docker solo levanta los servicios de apoyo.

## Prioridad de cobertura de tests

Por orden, según dónde duele un fallo silencioso:

1. **Motor de categorización ENS** — un fallo aquí deja a un cliente fuera de conformidad sin que nadie se entere. Matriz completa, incluidas las medidas moduladas por nivel de dimensión.
2. **Aislamiento multi-tenant** — que ninguna consulta cruce la frontera de organización.
3. **Transiciones de estado de implantación** y recálculo tras un cambio de valoración.
4. **Importador del catálogo**, incluida la idempotencia y el diff.
5. Resto de módulos: flujos principales.

## La capa de recursos

Vive en `app/Http/Resources/` y existe para que los diecinueve módulos hablen el mismo dialecto.

| Pieza | Dónde |
|---|---|
| `Recurso` (abstracta) + `ConsultaRecurso` | `app/Http/Resources/` |
| `Columna`, `Filtro`, `Accion`, `Etiquetas`, `MetaTabla`, `ValorEtiquetado` | `app/Http/Resources/Definicion/` |
| `RespondeConRecurso` (trait de controlador) | `app/Http/Resources/Concerns/` |
| `DataTable`, filtros, paginación, celdas | `resources/js/components/tabla/` |
| Lectura de filtros, formato de celda y CSV | `resources/js/lib/{filtros,celdas,csv}.ts` |
| `FormularioRecurso` y campos | `resources/js/components/formulario/` |

Un `Recurso` **describe**: columnas, filtros, acciones, orden y tamaños de página. No consulta —de eso se encarga `ConsultaRecurso` con `spatie/laravel-query-builder`— ni autoriza —de eso, la ruta y la política—. Lo que no está declarado no filtra ni ordena, por mucho que llegue en la query string; y lo que se aplicó de verdad vuelve en `MetaTabla`, no lo que se pidió.

Los props se reparten en dos mitades porque cambian a ritmos distintos: `recurso` viaja como `Inertia::once()` con la clave del recurso, y `filas` + `meta` se recargan con `router.reload({ only: ['filas', 'meta'] })`.

**TanStack Table se usa en modo servidor**: aporta el modelo de columnas, su visibilidad, orden y anclado, y la selección de filas. Paginar, ordenar y filtrar son consultas de servidor y no se duplican en cliente.

**Un filtro sobre una columna de otra tabla necesita `->campo('tabla.columna')`, y sólo funciona si `consulta()` ya trae ese join.** «Requisito» se pinta desde `requisitos.titulo` y se filtra con `Filtro::texto('requisito', 'Requisito')->campo('requisitos.titulo')` porque `ImplantacionRecurso::consulta()` une `requisitos`. Cuando el join no está, se declara un `select` sobre la clave foránea —así van Sistema y Responsable—: **el filtro no inventa joins**, porque una tabla de cincuenta mil implantaciones no puede ganarse un join por escribir tres letras en un cuadro.

**Cada filtro dice bajo qué columna se pinta.** `Filtro::$columna` vale por defecto la propia clave, y `->enColumna('marco')` la cambia cuando no coinciden (`marco_id` sobre la columna `marco`). Si esa columna está a la vista, el control baja a la fila de filtros de la cabecera; si no, se agrupa en el desplegable «Filtros» de la barra. `Filtro::busqueda()` es la excepción: cruza varios campos declarados —admite campos de una tabla unida, como `requisitos.codigo`— y vive siempre en la barra. Escapa los comodines del término, porque un `%` suelto sin escapar devuelve la tabla entera. Si se le pasa un mapa `campo => clave de columna` en lugar de una lista, declara además dónde se resalta la coincidencia: el cliente no puede deducir que `requisitos.titulo` es la columna `requisito`.

**Un valor que llega por la query string no puede reventar la consulta.** Los extremos de un rango de fechas se comprueban antes de usarse: PostgreSQL responde a `whereDate(..., '>=', '2026')` con un error de sintaxis, y eso era un 500 en una URL que la gente guarda y comparte. Mismo criterio que el escapado de comodines: lo que no se entiende se ignora, no se aplica a ciegas ni se convierte en un error.

**La vista de cada tabla se guarda en el navegador** (`statera.tabla.<clave>.vista`): visibilidad, orden y anclado de columnas, densidad y fila de filtros. Es preferencia de un puesto, no un dato de la organización, y por eso no viaja al servidor ni cruza la frontera del tenant. Una vista guardada nunca resucita una columna que el recurso ya no declara, y una columna nueva del recurso no se queda fuera por una vista vieja: se coloca al final. La puerta de vuelta es «Restablecer la vista», en el desplegable de columnas.

## Los documentos

Viven en `app/Domain/Documento/`. Una clase por tipo de documento —`DeclaracionAplicabilidadIso`,
`DeclaracionAplicabilidadEns`— sobre una tubería común que renderiza, llama a Gotenberg, calcula el
hash, almacena y registra la versión. El sexto documento cuesta una clase y una plantilla.

```sh
php artisan documentos:generar SOA-SGSI-01 --html   # vuelca el HTML, sin Gotenberg
php artisan documentos:generar SOA-SGSI-01 --sync   # genera el PDF en este proceso
php artisan documentos:generar SOA-SGSI-01          # lo encola en «documentos»
```

**`--html` es la opción que más se usa**: el noventa por ciento del trabajo de plantilla se hace
mirando el HTML en un navegador, no abriendo PDFs.

`documentos` es la **serie** —«la SoA del SGSI»— y `documento_versiones` es **cada entrega**. La
especificación (§2.2) describe una sola tabla con `version`, `estado` y `fichero` dentro; una fila
no sostiene un histórico, y la propia especificación pide versionado.

**`numero IS NULL` es el borrador** —hay uno como mucho por documento, lo garantiza un índice único
parcial— y se regenera cuantas veces haga falta. **Con número, la fila es inmutable**, y eso lo
impone un trigger de PostgreSQL, no la buena voluntad: si el documento entregado se pudiera cambiar
desde PHP, no habría forma de demostrar qué se firmó. Emitir mueve además el PDF de `borradores/` a
`emitidas/`, que es el prefijo que en producción lleva Object Lock.

La `instantanea` en JSONB **no es redundante con el PDF**: un PDF no se puede consultar, y sin ella
no se contesta «¿qué cambió entre la v3 y la v4?». Por eso el contrato del generador es que la
plantilla recibe arrays y value objects y **nunca modelos de Eloquent**: lo que se pinta y lo que se
congela son literalmente lo mismo.

**Los dos documentos no son el mismo con otras columnas.** En ISO la aplicabilidad es una *decisión*
que hay que justificar —de ahí las dos columnas de justificación, la de inclusión y la de
exclusión—; en el ENS es un *cálculo* del motor que hay que poder rastrear, y de ahí la exigencia, el
refuerzo, el origen y la dimensión moduladora, más la derivación de la categoría impresa en portada.

**Los dos declaran por escrito lo que no pueden afirmar.** Que no hay análisis de riesgos, que no hay
aprobación formal, que los roles ENS están pendientes de designación, que el texto normativo de ISO
no se reproduce por derechos de autor, y las dos brechas conocidas del Anexo II. Un auditor respeta
una limitación declarada y suspende una inventada.

**La SoA justifica la inclusión sin inventarse un riesgo.** Mientras no exista el módulo de riesgos
(§4.3), cada control aplicable dice «Anexo A» y, cuando el mapeo cruzado lo encuentra, «exigido por
el ENS (op.acc.2)» —que es un requisito **legal** y una justificación de inclusión legítima para
ISO—. Es el mapeo cruzado tapando parte del hueco, y de paso el argumento del producto impreso en el
entregable.

---

## Los textos de un documento

Un documento tiene dos mitades y sólo una se edita. **Las tablas, las cifras y la derivación de la
categoría se calculan desde `implantaciones` y no se tocan a mano** —el invariante sigue intacto—;
**el envoltorio narrativo lo redacta la organización**. Si hay que corregir una justificación, se
corrige en su requisito, que es donde vive.

Es el § 4.5 de la especificación, «plantillas base personalizables por organización», que hasta ahora
no estaba implementado: todo el aparato narrativo era literal en Blade o en PHP y **el usuario no podía
escribir ni un carácter que saliera en el PDF**.

**Once huecos, catálogo cerrado** (`SeccionNarrativa`). De ese enum salen a la vez el formulario, las
reglas del `FormRequest`, el `CHECK` de las dos tablas y las claves que acepta el resolutor: **no hay
forma de nombrar un hueco que no exista**, ni por la interfaz ni llamando a la ruta a mano. Ésa es la
primera de las tres capas que impiden tocar las limitaciones del sistema; las otras dos son el `CHECK`
y que el Blade de esos bloques no llama al parcial de narrativa.

**La cadena de lectura tiene tres eslabones y gana el primero que EXISTA:**

```
documento_secciones  →  documento_plantilla_secciones  →  TextosDeFabrica
```

**Incluida la cadena vacía.** Una fila vacía dice «aquí no va nada, lo he decidido yo» y una fila
ausente dice «vale lo que venga de más atrás». Sin esa distinción, borrar un texto lo resucitaría en
la siguiente generación. Y es lo que hace que **no haya hecho falta ninguna migración de datos**: los
documentos que ya existían no tienen filas, resuelven hasta fábrica y su PDF sale idéntico.

**Un documento materializado no se entera si la plantilla cambia después**, y es deliberado: lo que
dice un documento es un hecho del documento, no el resultado de un join que cambie bajo los pies. Es
el mismo razonamiento que hay detrás de `instantanea`. La pantalla de plantillas lo avisa, porque sin
ese aviso cualquiera daría por hecho que acaba de cambiar su SoA.

```sh
php artisan documentos:generar SOA-SGSI-01 --html    # sigue siendo el bucle rápido
```

---

## Desvíos vigentes respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **La clase base se llama `Recurso`, no `Resource`.** El directorio sí es `app/Http/Resources/`, como dice §2.1 del stack, pero `Resource` colisiona con el pseudo-tipo `resource` de PHP: Pint lo pasa a minúsculas en los docblocks (`@extends resource<Sistema>`) y a partir de ahí Larastan no resuelve el genérico. En español encaja además con `ConsultaRecurso`, `DefinicionRecurso` y `RespondeConRecurso`.

- **`EstablecerContextoOrganizacion` va antes de `SubstituteBindings`**, y por eso `bootstrap/app.php` saca este último de su sitio por defecto y lo vuelve a poner detrás. El *route model binding* resuelve los modelos con una consulta de Eloquent que pasa por el scope de organización: sin contexto fijado, el scope no devuelve nada y **cualquier** ruta con `{sistema}` responde 404, también las propias. Hay un test que lo fija (`tests/Feature/Sistemas/CrudTest.php`), y falla si se revierte el orden.

- **Un parámetro de filtro u ordenación no declarado se ignora, no rompe la petición** (`config/query-builder.php`). El 400 por defecto de spatie convierte cualquier URL guardada en un error en cuanto se renombra un filtro. Silencioso no es: `MetaTabla` devuelve el orden y los filtros aplicados de verdad.

- **SSR desactivado** (`INERTIA_SSR_ENABLED=false`). La aplicación vive tras un login: no hay SEO ni primer pintado crítico que lo justifique. Con `@inertiajs/vite` volver a activarlo es cambiar la variable.

- **`DatabaseSeeder` no usa `WithoutModelEvents`.** `PerteneceAOrganizacion` rellena `organizacion_id` en el evento `creating`; silenciar los eventos deja la columna a nulo y RLS rechaza la inserción con un error de privilegios que no dice nada de la causa.

- **Los avisos van por el canal de flash de Inertia v3** (`Inertia::flash()` + `router.on('flash')`), no como prop compartido. Un prop se reenvía en cada recarga parcial y el aviso volvía a saltar al filtrar o paginar.

- **Cliente de Redis: `predis`, no `phpredis`.** La máquina de desarrollo no tiene la extensión `phpredis` compilada y el stack no elige cliente. `predis` es PHP puro y no requiere extensión. Si en producción se instala `phpredis`, basta cambiar `REDIS_CLIENT` en el entorno.
- **La aplicación se conecta a PostgreSQL como `statera_app`, no como `statera`.** El rol que crea `POSTGRES_USER` es superusuario, y PostgreSQL exime a los superusuarios y a los roles con `BYPASSRLS` de la seguridad a nivel de fila **incluso con `FORCE ROW LEVEL SECURITY`**. Conectarse con él dejaría la tercera capa del aislamiento presente en el esquema y sin ningún efecto, que es peor que no tenerla porque parece puesta. `statera_app` es `NOSUPERUSER NOBYPASSRLS` y propietario del esquema `public`; `statera` queda como rol administrativo. Lo crea `docker/postgres/init/02-crear-rol-de-aplicacion.sql` en el primer arranque del volumen.

- **`DESIGN.md` se corrigió al código, no al revés.** El documento venía describiendo otra marca: un símbolo en cinta con degradado teal→violeta, teal en hue 212, neutros `ink-*` y Montserrat. Nada de eso estaba implementado y las tres decisiones del código tenían motivo escrito, así que ganaron ellas: **la balanza** (§2), **hue 196** (§3) e **Instrument Sans** (§4). Lo único que se tomó del documento tal cual fue el violeta de acento. Los hex y los contrastes de §3 son conversión calculada de los `oklch` de `app.css`: si se retoca la paleta, se recalculan, no se estiman.

- **El color de marca es teal petróleo, hue 196** (`oklch(0.52 0.10 196)` en claro, `oklch(0.78 0.11 196)` en oscuro). No es preferencia estética: la paleta de estados del dominio ocupa 245 (`planificado`), 155 (`implantado`), 70 (`en_progreso`) y 27 (`destructive`), y el teal es el hue libre más alejado de todos ellos. Un botón primario en verde o en ámbar se confundiría con un badge de estado. Los tokens `--estado-*` son semántica del dominio y **no se retocan** al cambiar la marca.

- **`--acento` (violeta de marca) y `--accent` (superficie de hover de shadcn) son cosas distintas y tienen nombres distintos a propósito.** `--accent` es el teal pálido que pintan el ítem activo del sidebar, el menú, el desplegable y el select; unificarlo con el acento de marca los rompe todos a la vez. El violeta vive en `--acento`, `--acento-suave`, `--acento-borde` y la escala `--violeta-*`.

- **El violeta se queda en cuatro sitios y sólo cuatro:** el filete de `CabeceraPagina` (uno por pantalla), la variante `acento` del botón —reservada a flujos de revisión y auditoría, todavía sin usar—, el token `--estado-en-revision` (declarado, sin flujo detrás) y la balanza del acceso. **No** en enlaces, **no** en el anillo de foco y **no** en el resto de badges de estado. El reparto es 60/30/10 y el violeta que se ve en todas partes deja de ser acento.

- **La balanza del panel de acceso es la única animación decorativa del producto**, y contradice a propósito el «lo decorativo no entra» de `lib/motion.ts`. El motivo: el panel se mira quince segundos antes de entrar, no ocho horas, y está fuera del chrome de trabajo. A cambio se apaga en tres condiciones —`prefers-reduced-motion`, pestaña en segundo plano y por debajo de `lg`— y en las tres se pinta un solo fotograma quieto. Es canvas 2D a mano (`lib/balanza.ts` + `components/BalanzaPixeles.vue`), sin librería: para setecientos puntos no hace falta un motor 3D, y aquí cada dependencia hay que justificarla en una revisión. La geometría sale del `viewBox` de `Logotipo.vue`, así que lo que gira **es** el logotipo; si alguien redibuja el símbolo, hay que redibujar la nube. **El tamaño lo decide la caja, nunca una medida escrita a mano:** `extension()` mide cuánto ocupa la figura en el fotograma más ancho de toda la vuelta y de ahí sale el `tam` que cabe, así que basta con meter el componente en un `flex-1` para que se adapte a la ventana y ningún platillo se sale en ningún ángulo. Volver a poner anchos en `rem` por punto de ruptura es el error que ya se cometió una vez.

- **Una celda pegada en horizontal no puede llevar alfa en el fondo.** En `DataTable`, las columnas ancladas heredan el fondo de su fila con `bg-inherit`. El hover era `bg-muted/50` y la fila seleccionada `bg-accent/40`: en cuanto el puntero entraba en la fila, la columna fija se volvía medio transparente y dejaba ver lo que se desplazaba por debajo. El comentario que exigía «un fondo opaco» estaba escrito justo encima de la línea que lo incumplía. Ahora son `--fila-hover` y `--fila-seleccionada`, la misma mezcla ya compuesta con `color-mix` contra `--card`. **Volver a poner un `/50` porque el hover parece fuerte reintroduce el fallo**, y el síntoma aparece a dos columnas de distancia de la causa.

- **La escala `--marca-*` no se invierte en oscuro.** 50 es el tono más claro y 950 el más oscuro en los dos temas. Invertirla parecía elegante y era una trampa: `bg-marca-900 text-marca-950` deja de tener sentido en la mitad de los casos y el contraste se rompe sin que se vea en el fichero que lo usa. Lo que cambia de tema son los roles (`--primary`, `--accent`).

- **Un solo sistema de radios.** Superficies (tarjeta, tabla, diálogo, aviso) `rounded-xl`; controles (botón, input, select) `rounded-md`; badges, avatares y chips `rounded-full`. El escalón de superficie lo trae el estilo `reka-vega` de shadcn-vue en `Card` y todo lo que hace de panel lo iguala.

- **`hover:bg-primary/80` del botón primario se cambió por `hover:bg-marca-700`.** El original mezcla con el fondo, y sobre claro **aclara** el botón: con el teal de marca el contraste del texto caía a 3.4:1 y dejaba de pasar AA justo al pasar el puntero. Oscurecer un paso lo sube en lugar de bajarlo.

- **Las cifras de resumen llegan contando, con `@number-flow/vue`, y la tabla no.** Es la única librería de animación que entra además de `motion-v`, y la frontera es la que importa: `components/Cifra.vue` se usa en el panel, en la tarjeta de inventario y en la tira de alertas —números que **resumen**— y **nunca en las celdas de una tabla**. Trescientas cifras contando a la vez cada vez que alguien filtra no es énfasis, es ruido, y contradice la regla de que las recargas parciales no se animan. El argumento a favor es el mismo que ya justificaba el contador de `AnilloProgreso`: una cifra que ya está puesta se da por leída y la vista pasa por encima.

  Tres detalles que costaron y que no se ven en el código de quien la usa: NumberFlow anima **al cambiar** el valor, no al montarse, así que la cuenta de entrada arranca en cero y salta al valor real en el siguiente fotograma; **no** arranca en cero si la pestaña está en segundo plano o hay movimiento reducido, porque `requestAnimationFrame` no corre ahí y el panel se quedaría enseñando ceros; y un `watch` sigue los cambios posteriores, porque sin él la cifra se quedaba con la del primer montaje y el panel mentía en silencio. Las duraciones salen de `lib/motion.ts`, no de la librería. Pesa 20 kB en su propio chunk, que sólo cargan las pantallas que la usan.

  `AnilloProgreso` conserva su propio contador con `useTransition`: ahí el número y el anillo tienen que moverse juntos, y separarlos en dos motores los desincronizaría.

- **El motion va con `motion-v`, no con GSAP.** Aquí no hay scroll-telling, ni *pinning*, ni *scrub*: lo que se anima son entradas, escalonados y transiciones de estado, y para eso GSAP es peso muerto en una herramienta que entra en el alcance del propio SGSI. Las duraciones y curvas viven en `resources/js/lib/motion.ts`, una sola vez, y la preferencia de movimiento reducido se resuelve en tres capas: el `@media` global de `app.css`, el `<MotionConfig reduced-motion="user">` de los layouts y el composable `useMovimientoReducido`.

- **`lib/navegacion.ts` es el mapa único de la aplicación.** Lo leen el sidebar, el panel lateral de móvil, las migas de pan y la paleta de comandos. Un módulo nuevo se añade ahí y aparece en los cuatro sitios; mantener cuatro listas a mano termina dejando un módulo fuera del buscador sin que nadie lo note.

- **Las páginas de error se pintan con Inertia** (`resources/js/pages/Error.vue`, conectado en `bootstrap/app.php`). No es cosmética: el aislamiento multi-tenant responde **404, no 403**, cuando alguien pide un recurso de otra organización, porque decir «existe pero no es tuyo» ya sería filtrar información. Ese 404 lo ve gente real y con frecuencia, así que tiene que explicar qué ha pasado y llevar a alguna parte. Los 500 sólo se maquillan fuera de depuración: en local se quiere la traza de Laravel. `tests/Feature/ErroresTest.php` fija que la respuesta conserva su código de estado.

- **El formulario de acceso se ancla arriba, no se centra en vertical.** Con centrado, aparecer el aviso de credenciales incorrectas empuja todos los campos hacia abajo y hay que volver a buscar el cursor. El panel de marca de la derecha es de color sólido en los dos temas a propósito: es una superficie de marca, como lo sería una fotografía, no una sección que se haya quedado sin invertir.

- **En el acceso, logotipo, título, campos, ayuda y pie forman una sola pila y comparten borde izquierdo.** El logotipo estaba pegado al borde del navegador y el formulario centrado en una columna de casi mil píxeles: sin ningún eje en común se leían como dos cosas sueltas flotando en el mismo hueco. Por eso el `<footer>` repite el `mx-auto w-full max-w-[26rem]` de la pila en vez de centrarse en la columna. Y por eso **el símbolo no se repite**: el panel llevaba un `Logotipo` de 36 px justo encima de la balanza que gira, la misma figura dos veces en la misma superficie. El respaldo «un producto de RM Technology» vive en la columna del formulario, que es la única que se ve por debajo de `lg`.

- **Las gráficas se pintan a mano, y en el PDF las pintará el servidor.** El stack no decía nada de gráficas, ni a favor ni en contra, así que queda escrito aquí. Dos renderizadores por un motivo concreto: en un documento que va a PDF/A-3b y aspira a PDF/UA no debería ejecutarse JavaScript, porque un canvas entra como mapa de bits y se lleva por delante el texto seleccionable. En pantalla, SVG y CSS sobre los tokens de `app.css` (`AnilloProgreso`, `BarraSegmentada`, `components/grafica/`); en el documento, SVG generado en PHP cuando llegue el módulo de documentos. **Chart.js se descartó** por lo anterior y porque obliga a escribir los colores en JavaScript en vez de leerlos de los tokens. Una librería —`d3-scale` y `d3-shape`, que son funciones puras sin DOM— entra el día que haya una serie histórica con eje de tiempo: escalas y ticks legibles es lo único que no compensa escribir a mano. Hoy no la hay: las transiciones existentes son todas del mismo instante.

- **El resumen del inventario está repartido a propósito entre el panel y la tabla.** Los repartos —por tipo, por ciclo de vida, cobertura de cifrado y copia— viven en `/panel`, que es donde se pregunta cómo va la cosa; en `/activos` sólo queda lo que pide acción hoy. Antes eran nueve recuentos del mismo tamaño encima de la tabla, varios a cero, mezclando tres cosas distintas: incumplimiento real, dato que falta y perfil. Había que leerse los nueve para saber si algo iba mal. **Un indicador a cero ya no ocupa una tarjeta**: si no hay nada abierto se pinta una línea diciéndolo, que es un estado vacío de verdad y no una fila de ceros. Y toda cifra va con su denominador — «2 sin cifrar» sobre 4 es una urgencia y sobre 307 es un martes.

- **`ResumenInventario::controlesResueltos()` cuenta `no_aplica` como resuelto.** Un router no cifra en reposo porque no almacena nada; contarlo como pendiente pondría un techo que la organización no puede alcanzar por mucho que trabaje, y un indicador que nunca llega al cien por cien se deja de mirar a las dos semanas.

- **No entró ninguna librería de gráficas, y hubo permiso para meterla.** Sigue valiendo lo que ya decía este documento: en un documento que va a PDF/A-3b no debe ejecutarse JavaScript, y una librería obliga a escribir los colores en JS en vez de leerlos de los tokens. La puerta abierta —`d3-scale` y `d3-shape`— es para cuando haya una **serie histórica con eje de tiempo**; un inventario es una foto de hoy. `AnilloProgreso`, `BarraSegmentada` y `grafica/GraficaBarras` ya cubren el caso y ya llevan dentro lo que cuesta acertar: porcentaje con denominador, `role="img"` con su descripción, colores de token y movimiento reducido.

- **`BarraSegmentada` y `GraficaBarras` traducen el TONO, no la clave.** Lo que llega del servidor es el tono del dominio —`caducada`, `implantado`, `tipo:datos`—, no el valor del enum. Costó un rato: el tramo «No» de la cobertura salía gris porque el mapa buscaba `no` y el servidor mandaba `caducada`. Las clases van escritas enteras y nunca compuestas en ejecución, como en `CeldaBadge`.

- **Ninguna cifra del panel se calcula en el controlador.** Viven en `app/Domain/Implantacion/ResumenCumplimiento.php`, porque son las mismas preguntas que contestará el informe de estado. Se cuenta siempre sobre lo exigible (`aplica = true`): un requisito que no se le exige al sistema no está pendiente, no cuenta. Y una media viaja siempre con su denominador — `madurezMedia` con `madurezEvaluadas`—: una media de madurez sobre cuatro requisitos de doscientos no dice lo mismo que sobre los doscientos.

- **La valoración de un activo va en cinco columnas de `activos`, no en filas de `valoracion_dimensiones`.** Aquella tabla es la entrada del motor de categorización: lleva justificación por dimensión y su cambio recalcula las implantaciones. La del activo no hace nada de eso, y además la propagación por el grafo es un `GREATEST` sobre columnas dentro de una CTE recursiva — con filas habría que pivotar dentro de la recursiva. El value object `ValoracionDimensiones` se reutiliza igual, y `elevadaCon()` es la operación con la que la valoración sube por el grafo.

- **La valoración efectiva de un activo se calcula, no se almacena.** Es el máximo entre la suya y la de todo lo que depende de él: una base de datos valorada «bajo» que sostiene un servicio esencial vale «alto», y ese es justo el activo que una hoja de cálculo deja desprotegido. Guardar una copia sería abrir la puerta a que se desincronice del grafo que la justifica. **La propia nunca se sobrescribe** y las dos se enseñan juntas: el auditor pregunta qué valoró la organización, no qué dedujo la herramienta. `ValoracionEfectiva` tiene dos entradas —`de()` para una ficha y `paraLaOrganizacion()` para la tabla, en una sola consulta— y hay un test que fija que coinciden; si divergen, la tabla enseñaría una cifra y la ficha otra.

- **Los ciclos del grafo de activos los rechaza `RegistrarDependencia`, no la base.** El `CHECK` de `activo_dependencias` sólo cubre el bucle de un salto; uno de tres se cuela igual, y contra un grafo con un ciclo una CTE recursiva no devuelve un resultado raro: no termina. Se comprueba en el dominio y no en el `FormRequest` porque la prohibición vale también para un importador o para el seeder. Aun así, los recorridos de `GrafoActivos` arrastran la ruta en un array y se niegan a reentrar en un nodo visitado: la red de seguridad se paga una vez y evita colgar el proceso.

- **El registro de revisiones es una tabla, no una fecha suelta.** `A.5.9` de ISO y `op.exp.1` del ENS no piden un inventario, piden un inventario **mantenido**, y la diferencia entre las dos cosas es `revisiones_inventario`. `altas` y `bajas` se guardan como los contó quien revisó y no se calculan desde la traza: son la cifra que esa persona firmó ese día, y si mañana alguien da de alta un activo con fecha anterior, no cambia. La fecha por activo (`ultima_revision`) se pone con la acción masiva de la tabla, que es lo que conecta las dos cosas sin una pivote más.

- **`activo_sistema` es N:M.** El mismo servidor está en el alcance del SGSI de ISO y del sistema del ENS a la vez, y duplicarlo para que quepa en los dos sería volver a las hojas de cálculo duplicadas. De ahí sale `Filtro::porRelacion()`: filtrar por alcance con un `join` multiplicaría las filas —el activo de dos sistemas saldría dos veces y la paginación contaría mal—, así que va por `whereHas`. No contradice la regla de que el filtro no inventa joins: aquí no hay join, hay subconsulta, y `consulta()` se queda como estaba.

- **`retirado` y `dado_de_baja` no son lo mismo, y por eso son dos estados.** Retirado es que ya no presta servicio; dado de baja es que además hay constancia de que se borró o destruyó lo que contenía, que es lo que exige `mp.si.5`. Un disco retirado que sigue en un cajón con los datos dentro es un hallazgo, no un activo cerrado, y `Activo::esperaBorradoSeguro()` es lo que lo señala. El `FormRequest` no deja dar de baja sin esa fecha.

- **`proveedor_id` no está en `activos`, a propósito.** El módulo de proveedores (§ 4.9) no existe y no se declara una clave foránea contra una tabla que no está. Se añade con ese módulo, igual que la importación desde CSV y los importadores automáticos de § 4.2.

- **Los tipos de activo tienen paleta propia, `--tipo-*`, y no reutilizan los `--estado-*`.** Un estado dice *cómo va* algo y un tipo dice *qué es*; con la misma saturación, un badge de tipo en verde se leería como «implantado». Se separan por croma —0.10 frente a 0.13— y las cifras están medidas en `DESIGN.md` §3: contraste 5.58 en el peor caso y ΔE 6.2 frente al estado más cercano. **La peor pareja tipo↔tipo queda en ΔE 5.2, por debajo del suelo de 6**, y es aceptable sólo porque estos badges nunca se tocan y **siempre llevan icono**: el color agrupa, el icono identifica. Quitar el icono de `CeldaBadge` deja la distinción por debajo del umbral, así que no es decoración.

- **«Por confirmar» no es «No», y por eso `EstadoControl` tiene cuatro casos.** Un export de AWS informa del cifrado de los volúmenes pero no dice nada de las copias de los EC2; con tres valores, esas instancias figuran como incumplimiento y alguien se pasa una semana «arreglando» copias que ya existían. La ausencia de dato es una pregunta abierta y se cuenta aparte. `NoAplica` tampoco es `No`: un router no cifra en reposo porque no almacena nada.

- **La clasificación de la información no sustituye al nivel del Anexo I.** La clasificación se decide y se estampa (`mp.info.2`); el nivel se deriva de valorar el perjuicio. Un mismo activo puede ser de uso interno y valer «alto» en disponibilidad. Conviven, y ninguna se calcula desde la otra.

- **Cada indicador de `ResumenInventario` cuenta con el mismo scope que usa su filtro de la tabla.** No es comodidad: es lo que garantiza que pulsar una cifra enseñe exactamente esa cifra. Con la condición escrita dos veces, el día que cambie una el panel dirá 12 y la lista enseñará 9, y a partir de ahí nadie se fía del panel. `Filtro::porScope()` existe para eso, y `ResumenInventarioTest` recorre los nueve comparando indicador con filtro.

- **Los indicadores se cuentan sobre activos vigentes**, como el cumplimiento se cuenta sobre lo exigible. Un portátil dado de baja sin copia de seguridad no está pendiente: está cerrado. Lo que un activo retirado sí puede deber es el borrado seguro, y eso lo señala `Activo::esperaBorradoSeguro()` en su ficha. El noveno indicador del Excel original —«instancias detenidas», coste de AWS sin uso— se sustituyó por **soporte o garantía vencidos**: misma pregunta, y sin importador no hay de dónde sacar el otro.

- **El QR de la etiqueta codifica la URL de la ficha, no el código en texto plano.** Escanear la pegatina abre la ficha en el móvil; con el código suelto hay que memorizarlo, abrir la aplicación y buscarlo, y a la tercera vez nadie escanea. La base sale de `organizaciones.url_base_etiquetas` y sólo cae a `config('app.url')` si está vacía: una etiqueta impresa dura años y apuntar a la URL equivocada obliga a reimprimir el parque entero. Sólo se etiqueta lo **físico y vigente** —`TipoActivo::esFisico()`—: en AWS y en SaaS no hay carcasa donde pegar nada.

- **`bacon/bacon-qr-code` se declaró como dependencia directa.** Ya estaba instalado como transitiva de Fortify, que lo usa para el QR del segundo factor. Apoyarse en la transitiva de otro paquete es depender de que Fortify no la cambie. No se descargó nada: sólo cambió el hash del `composer.lock`.

- **`retirado`, `en_stock`, `en_reparacion` y `prestado` cuentan como vigentes.** Un portátil en el armario o en el taller sigue teniendo los datos dentro y sigue siendo responsabilidad de alguien; sacarlo del inventario activo es exactamente cómo se pierde el rastro de un equipo. Sólo `retirado` y `dado_de_baja` salen del recuento.

- **El fin de soporte del software base vive en `config/obsolescencia.php`, no en una tabla.** Son hechos del mundo, iguales para todos los clientes: meterlos en una tabla con `organizacion_id` sería duplicarlos por tenant y dejar que se desincronicen. Y no es catálogo normativo (invariante 3): son quince filas que se actualizan cuando sale una LTS. Las fechas son de soporte **estándar**, no extendido de pago: si el inventario contara ya el soporte extendido, la fecha nunca vencería y el aviso no saltaría nunca. Un sistema que no está en la lista **no** cuenta como obsoleto — eso convertiría cada macOS del parque en un falso positivo.

- **`spatie/laravel-medialibrary` se retiró.** Venía instalado en el esqueleto con su migración `media` y no lo usaba nadie: ni un `HasMedia`, ni `config/media-library.php` publicado. Y su tabla `media` no lleva `organizacion_id`, así que meter ahí los ficheros de las evidencias las habría dejado fuera de las tres capas de aislamiento; incluirla exigía modelo propio, migración, RLS y global scope sobre una tabla que no controlamos. Las evidencias guardan `disco`, `ruta`, `mime`, `tamano` y `hash_sha256` en columnas propias sobre el disco `evidencias`, que es exactamente lo que ya describía el comentario de `config/filesystems.php`. Si algún día hacen falta conversiones de imagen o adjuntos de documentos, se vuelve a valorar entonces.

- **`laravel/passport` se retiró.** Venía en el esqueleto inicial junto con sus seis migraciones OAuth. No hay API pública que autenticar (§12 del stack descarta la SPA con API separada) y la autenticación va por Fortify. Si algún día hace falta OAuth para integraciones, se vuelve a valorar entonces.

- **`ContextoOrganizacion` se registra como `scoped`, no como `singleton`.** El worker de la cola es
  un proceso largo que atiende jobs de organizaciones distintas: con `singleton`, la organización del
  job A sigue puesta al empezar el job B. Y no basta con eso — `set_config(..., false)` es de SESIÓN
  y vive en la conexión que el worker reutiliza; quien la devuelve a «denegar por defecto» es el
  `finally` de `paraOrganizacion()`. **Hacen falta las dos cosas.**

- **Un job en cola fija su organización con `ConContextoDeOrganizacion`**, el middleware de job que
  hace en la cola lo que `EstablecerContextoOrganizacion` hace en HTTP. Sin él no hay petición, no
  hay usuario y por tanto no hay contexto: el scope no devuelve nada y RLS deniega por defecto, así
  que **el job no revienta, sencillamente no ve nada**. Tres consecuencias que no se ven leyendo el
  job: se pasan **escalares y nunca `SerializesModels`** —ese trait reconsulta el modelo al
  deserializar, *antes* de cualquier middleware, y muere con un `ModelNotFoundException` que no
  menciona la palabra «organización»—; **`failed()` NO pasa por el middleware** y necesita su propio
  envoltorio o la versión se queda «generando» para siempre; y la traza de auditoría registra sin
  autor, que es el motivo de que `documento_versiones.generada_por_id` exista.
  `tests/Feature/Documentos/ContextoEnColaTest.php` fija las cuatro cosas.

- **Disco `documentos` aparte del de `evidencias`.** No es simetría: en producción el Object Lock se
  aplica **sólo al prefijo `emitidas/`**, porque un borrador tiene que poder reescribirse y una
  versión entregada no debe poder hacerlo nunca. Bloqueado el bucket entero, regenerar un borrador
  falla. Y regenerar escribe **una clave nueva** (ULID en el nombre), nunca sobre la anterior.

- **`pdfua()` está apagado a propósito.** Gotenberg rechaza la petición **entera** si el documento no
  es conforme, así que encenderlo antes de que las plantillas lleven `lang`, `<th scope>`, jerarquía
  de encabezados y `<title>` en cada SVG convierte la generación en algo que falla por sorpresa.
  `generateTaggedPdf()` sí va: es su prerrequisito y no rompe nada. PDF/A-3b sí está activo.

- **La cadena de tiempos de Gotenberg es `--api-timeout=120s` < Guzzle 180 s < timeout del job 300 s,
  en ese orden.** Por eso `GotenbergHttp` construye un `GuzzleHttp\Client` explícito en vez de dejar
  que `Psr18ClientDiscovery` encuentre uno: el de por defecto trae 30 s y una SoA grande falla de
  forma intermitente con un error que apunta a Gotenberg, que no tiene ninguna culpa.

- **Las fuentes del documento van incrustadas en el CSS como `data:`, no como ficheros del
  multipart.** Chromium trata una fuente como recurso sujeto a CORS y el documento se renderiza desde
  un `file://`, que es un origen opaco: la petición se bloquea **en silencio**, sin error de carga que
  `failOnResourceLoadingFailed()` pueda cazar. Un `data:` no se descarga, así que no hay origen que
  comparar. Cuesta unos 150 kB en el CSS, que no sale del contenedor.

- **Y por eso la allow-list de Gotenberg es `^(file:///tmp/|data:).*` y no sólo `file:///tmp/`.** La
  allow-list **deniega todo lo que no case**, `data:` incluido. Sigue sin entrar ningún `http(s)://`,
  que es de lo que protege: Gotenberg descarga las URL que se le pasen.

- **La conversión a PDF/A **resustituye** las fuentes, y eso no se puede evitar desde aquí.** Chromium
  embebe Instrument Sans y JetBrains Mono correctamente —comprobado generando sin `pdfa()`—, y el paso
  a PDF/A-3b las reemplaza por Noto Sans y Arial. El PDF resultante es **conforme y con texto
  seleccionable**, que es lo que exige el archivado; lo que se pierde es la tipografía de marca. Se
  acepta a conciencia: el stack §4 pide PDF/A-3b «para todo documento de cumplimiento archivable», y
  la conservación a largo plazo manda sobre la tipografía.

  **Cómo se comprueba, porque a simple vista no se ve**: los `/BaseFont` del PDF. Un documento con
  Arial dentro tiene exactamente la misma pinta que uno con la fuente correcta.

- **Las caras itálicas se envían desde que la narrativa es editable.** Antes no: ninguna plantilla
  usaba cursiva, precisamente porque sin cara propia un `<em>` cae a la itálica de otra familia y mete
  una fuente de más en el PDF. El editor de textos ofrece cursiva, así que ahora viajan
  `instrument-sans-400-italic` y `-600-italic`. **En el texto fijo de las plantillas se sigue sin usar
  cursiva**: donde hace falta énfasis van las comillas latinas o la negrita, que es lo que ya está
  escrito y no hay motivo para cambiar.

- **`AssetsDocumento` genera el `@font-face` desde lo que hay en `resources/fonts/`**, que es el mismo
  juego de ficheros que carga la interfaz: **dejar los `.woff2` ahí basta para que el documento pase a
  usarlos, sin tocar una línea**. Lo que el PDF no puede hacer es apuntar a la copia de
  `public/build/assets/`, donde el nombre lleva hash de contenido y cambia en cada `npm run build`.

- **La cabecera y el pie del PDF son documentos HTML autónomos.** Chromium los renderiza en un
  contexto aparte que **no carga recursos externos, no hereda el CSS de la página y no hereda las
  fuentes**; además, lo que no lleve `font-size` explícito sale minúsculo, y el ancho útil es la hoja
  entera, así que el padding lateral replica a mano los márgenes. No es preferencia: es la limitación
  que se lleva una tarde por delante.

- **El documento aplana el lenguaje de forma de DESIGN.md §6, y conserva el de color.** Sin sombras
  —en papel imprimen como manchas grises—, radio 0 en las superficies —un `rounded-xl` en una tabla
  de noventa y tres filas partida en cinco páginas sólo deja esquinas sueltas a mitad de tabla— y
  siempre tema claro. Lo único que conserva forma son los badges, porque el color tiene que
  sobrevivir. `documento.css` usa **hex sRGB**, nunca `oklch`: un color que dependa de la gestión de
  color del navegador no es archivable. Los hex de los neutros se añadieron a DESIGN.md §3 antes de
  usarlos, y salen de convertir los `oklch` de `app.css`, no de estimarlos.

- **La variante `acento` del botón ya tiene su primer uso: «Emitir versión».** Era la que DESIGN.md
  reservaba a los flujos de revisión y auditoría, y entregar un documento al auditor es exactamente
  eso. Con ella en pantalla, «Generar borrador» baja a `outline`: **dos botones de color lleno a la
  vez y no manda ninguno**.

- **`Documento::resolveChildRouteBinding()` está escrito a mano.** `scopeBindings()` deduce la
  relación pluralizando el nombre del parámetro **en inglés** —`version` → `versions`— y aquí el
  dominio se nombra en español. Sin eso, `/documentos/{documento}/versiones/{version}/descargar`
  responde 500 con un «Call to undefined method» que no dice nada de la causa. Lo cazó
  `tests/Feature/Documentos/AislamientoTest.php`, y es la pieza que impide descargar la versión de
  otro documento desde una URL que no le corresponde.

- **La migración del trigger usa `CREATE OR REPLACE FUNCTION`.** `migrate:fresh` tira las TABLAS, no
  las funciones, así que la función sobrevive a un refresco de la base y la segunda pasada chocaría.
  Lo descubrió la suite, no una revisión.

- **Las cifras de versión del `DocumentoRecurso` llegan por subconsulta, no por `join`.** Un `join`
  contra `documento_versiones` multiplicaría las filas —un documento con cuatro entregas saldría
  cuatro veces— y la paginación contaría mal. Es el mismo razonamiento que llevó a
  `Filtro::porRelacion()` en activos. Esas subconsultas van en SQL crudo y **no pasan por el scope de
  Eloquent**: ahí quien filtra es RLS, que es justo el caso para el que existe la tercera capa.

- **El aviso de «generando…» va con `usePoll` de Inertia v3**, acotado a dos minutos y con
  `keepAlive: false`. `Inertia::defer()` no sirve —resuelve en **una** petición de seguimiento y no
  reintenta: responde a «carga lo lento después de pintar», no a «espera a un trabajo en segundo
  plano»—, y el flash tampoco, porque pertenece a la petición que lo provoca y el worker corre en
  otro proceso sin sesión. El servidor anuncia lo que hizo («generación encolada») y el cliente
  anuncia lo que vio («el borrador está listo»). Un poll infinito contra una cola atascada es un
  bucle caliente, y por eso se rinde y ofrece «Comprobar».

- **Las cifras de un documento se cuentan sobre las filas que ese documento lista**, no sobre las
  implantaciones del sistema. Acotar por sistema —que es lo que hacía `ResumenCumplimiento::deSistema()`,
  ya retirado— seguía sin bastar: un sistema de ISO lleva, además de los 93 controles del Anexo A, las
  cláusulas 4 a 10, que son el sistema de gestión y que el documento no enseña. **La barra decía 122 y
  la tabla que tenía debajo decía 93.** Una gráfica que contradice a su propia tabla no es un detalle
  de maquetación: es el documento desmintiéndose solo delante del auditor. Mismo criterio que ya regía
  en el panel de inventario — cada cifra se cuenta con el alcance de lo que enseña al lado.

- **La DdA imprime cuántas medidas tiene el Anexo II, no sólo cuántas se exigen.** A un sistema de
  categoría básica se le exigen 52 de 73, y una tabla que enseñe 52 sin denominador se lee como si el
  Anexo II tuviera 52. Que falten veintiuna es una consecuencia correcta de la categorización, pero el
  auditor tiene que poder **verla**, no deducirla. Mismo criterio que «toda cifra con su denominador».

- **`CorrespondenciasCruzadas::paraRequisitos()` existe por la Declaración de Aplicabilidad.** Llamar
  a `paraRequisito()` en un bucle sobre los noventa y tres controles del Anexo A son ciento ochenta y
  seis consultas. La versión en bloque resuelve todo en dos.

- **La fecha de extracción del documento lleva la zona horaria escrita.** La aplicación trabaja en
  UTC y quien lee el documento no tiene por qué: sin la marca, un documento generado a las 00:30 en
  España aparece fechado el día anterior, y una fecha que no cuadra con su registro es un hallazgo
  barato de encontrar.

- **Guardar la plantilla sin tocarla no deja fila.** `GuardarPlantilla` borra la fila cuando el texto
  coincide con el de fábrica, y no es una optimización: sin eso bastaría con abrir la pantalla y darle
  a guardar para que las once secciones quedaran congeladas y esa organización dejara de recibir
  cualquier mejora futura del texto de Statera, sin haberlo decidido y sin enterarse. **«No lo he
  tocado» y «no hay fila» tienen que ser lo mismo.** En el documento es al revés: ahí las filas se
  materializan todas a propósito, porque son una copia congelada.

- **El texto se guarda en Markdown, nunca en HTML.** Es lo diffeable —«¿qué frase cambió entre la v3 y
  la v4?» se contesta con un diff de texto plano—, lo que cabe en la instantánea sin inflarla y lo que
  no tiene superficie de inyección. `paraInstantanea()` congela el Markdown; el HTML es una función
  determinista de él y el PDF entregado ya está almacenado.

- **`MarkdownDocumento` NO usa `Str::markdown()`.** Ese helper monta un `GithubFlavoredMarkdownConverter`
  y trae tablas —y aquí los datos se calculan, no se escriben—, autoenlaces y listas de tareas. Se monta
  el convertidor a mano con `CommonMarkCoreExtension`, `html_input => 'escape'`, `allow_unsafe_links =>
  false` y un tope de anidamiento, más `NormalizarNarrativa`, que poda el árbol ya parseado.

- **Hay DOS renderizadores de Markdown, y el que alimenta al editor no baja los encabezados.** El
  desplazamiento `#`/`##` → `h3` y `###` → `h4` es maquetación del PDF: mantiene la jerarquía que exige
  PDF/UA y evita competir con el `<h2>` que pone la plantilla. Si el editor cargara el HTML del
  documento, un «Título» bajaría un nivel **en cada guardado** hasta tocar fondo. Lo que se almacena es
  `##`; lo que se imprime es `<h3>`.

- **`NormalizarNarrativa` elimina las imágenes, y eso no es cosmética.** `failOnResourceLoadingFailed()`
  está encendido y la allow-list de Gotenberg es `^(file:///tmp/|data:).*`: un `![](https://…)` escrito
  por cualquiera tumbaría la generación del PDF entero con un error que apunta a Gotenberg, que no
  tiene ninguna culpa. Un `<a href>` sí pasa: un enlace no se descarga al imprimir.

- **La regla `SinHtml` RECHAZA en vez de escapar en silencio.** Escapar dejaría un `<b>hola</b>`
  impreso tal cual en el PDF del auditor y quien lo escribió no sabría de dónde ha salido. El patrón
  exige que parezca una etiqueta entera —`<`, nombre y su `>`—: con uno más laxo caía prosa legítima
  como «el riesgo residual < bajo».

- **`realce()` y el Markdown conviven, y la frontera es quién escribió el texto.** Literal de PHP →
  `realce()`, que es un patrón y no un parser; texto de la organización → CommonMark restringido. Las
  cadenas de las limitaciones están llenas de códigos entre acentos graves que un parser convertiría en
  `<code>` y de guiones que leería como listas. De paso, `realce()` ahora convierte esos acentos graves
  en `<span class="cifra">`: **salían impresos en el PDF**, y el documento que se le entregaba al
  auditor decía «(`op.acc.1`)» con las comillas dentro.

- **El editor es TipTap 3 + `prosemirror-markdown`, y el argumento no es que sea popular.** En
  ProseMirror **el esquema del documento ES la lista blanca**: lo que no está declarado no se puede
  crear, ni escribiendo, ni pegando, ni arrastrando. No hay saneado de HTML pegado en cliente, que es
  justo la clase de código que no se quiere en una herramienta que entra en el alcance de su propio
  SGSI. Y el serializador se declara a mano —`lib/markdownEditor.ts`— porque ese mapa **es la lista
  blanca escrita otra vez en el camino de escritura**; un paquete que «detecta» el Markdown hace lo
  contrario. Pesa **170 kB gzip** y va en su propio chunk con `defineAsyncComponent`: el bundle
  principal no se movió ni un kilobyte. Mismo criterio que `@number-flow/vue`.

- **`CampoBase` gana `etiquetaOculta`, y `FormularioRecurso` deja de anunciar asteriscos que no hay.**
  Lo primero, porque el título ya lo pone la `SeccionFormulario` y repetirlo justo debajo es ruido —el
  `<label>` sigue existiendo y asociado: quitarlo dejaría el control sin nombre accesible—. Lo segundo,
  porque la pantalla de textos no tiene ni un campo obligatorio y la leyenda seguía apareciendo.

- **El `.docx` es una copia de trabajo, no la entrega, y se construye desde `instantanea`.** El
  entregable archivable es el PDF/A-3b con su huella. **Construirlo desde una consulta nueva sería el
  fallo más caro del módulo**: el Word de una versión emitida en marzo enseñaría los datos de octubre y
  contradiría al PDF que lo acompaña, con la huella de ese PDF impresa dentro. De ahí
  `ContenidoDocumento::desdeInstantanea()`, que además deja la instantánea **rehidratable** y habilita
  mañana una pantalla de diff entre versiones.

  No se almacena ni se versiona: `documento_versiones` existe para demostrar qué se entregó, sus
  `CHECK` acoplan la fila a **un** fichero con su hash, y el trigger de inmutabilidad no dejaría
  adjuntarlo a una versión emitida. Va marcado en tres sitios que sobreviven a un reenvío —el pie de
  cada página, las propiedades del fichero con la huella del PDF, y el nombre—.

- **`phpoffice/phpword` es LGPL-3.0 en un repositorio MIT.** Compatible como dependencia de Composer sin
  modificar y cargada en ejecución, pero queda escrito para que no lo descubra nadie más adelante. Y
  emite avisos de obsolescencia con PHP 8.4 que sólo se ven con `E_ALL` —`tinker`—: no afectan al
  fichero, que sale válido.

- **Los anchos del `.docx` van en twips ENTEROS.** `Converter::cmToTwip()` devuelve decimales y salían
  al XML como `w:w="1583.3333333333333"`. Lo cazó Larastan, no una revisión.

- **El cuerpo del documento está exento de `TrimStrings` y de `ConvertEmptyStringsToNull`**
  (`bootstrap/app.php`). Los dos son globales y recortan **toda** cadena de la petición; el cuerpo
  viaja como un árbol de ProseMirror donde cada trozo de texto es una cadena suelta, y el espacio que
  separa un trozo del anterior **no es relleno, es la separación**. Sin la exención, el PDF que se le
  entrega al auditor decía «no es una entrega.Este PDF se regenera», y además guardar sin tocar nada
  cambiaba el documento. `Str::is` entiende el comodín, así que `cuerpo.*` cubre el árbol a cualquier
  profundidad; el de cadenas vacías va por camino (`documentos/*/cuerpo`) porque **corre antes de
  resolver la ruta** y ahí no hay `routeIs()` que valga. Lo cazó un test, no una lectura: el síntoma
  aparece a tres capas de distancia de la causa.

- **`editado_en` significa «alguien guardó desde el editor», no «el contenido cambió».** Es
  deliberado y hay un test que lo fija (`DocumentoEditadoDeclaraTest`): guardar sin cambiar nada
  declara el documento mantenido a mano, y a la vez **no** marca ningún bloque calculado como
  modificado, porque la procedencia se comprueba contra la línea base y no se deduce de que alguien
  haya pulsado Guardar. De ahí que el editor tenga **dos eventos y no uno**: Tiptap normaliza el árbol
  al cargarlo y eso llega por `normalizado`; sólo `onUpdate` emite `cambio`. Emitir los dos como
  `cambio` dejaba el documento sucio nada más abrirse, «Ver el PDF» guardaba solo y el documento
  acababa declarando en portada que se había editado a mano por el hecho de abrirlo.

- **Se puede redactar antes de generar nada.** Una versión nace en `GenerarDocumento::encolar()`, así
  que un documento recién creado no tiene ninguna y el editor abortaba con 404 en el camino más corto
  que hay entre crear un documento y escribir en él. `DocumentoCuerpoController::versionVigente()` cae
  a una `DocumentoVersion` **en memoria y sin guardar** —`etiqueta()` ya dice «Borrador» con `numero`
  nulo—. No se crea la fila: `documento_versiones` es el registro de lo que se ha **entregado**, y
  meter ahí un documento que alguien abrió una vez le quita el único significado que tiene.

- **El `.docx` recorre el cuerpo de la instantánea; `CuerpoAWord` es el hermano de
  `RenderizadorCuerpo`.** Mismo árbol, mismo vocabulario cerrado de `EsquemaCuerpo`, otro destino.
  `EscritorWord` se queda con el continente —hoja, estilos, pie de copia de trabajo y propiedades del
  fichero—. Lo que **no** se traduce es el lenguaje de color y forma: los badges llegan como texto y la
  barra por tramos como sus cifras. Sin cuerpo en la instantánea se responde 404, igual que sin
  instantánea. Y ojo con PHPWord: **`TextRun` no tiene estilo de fuente propio y no protesta si se le
  pide** —un `__call` se traga `setFontStyle()` en silencio—, así que el estilo base baja hasta cada
  `addText`. Lo cazó Larastan.

- **Dos cabos sueltos anotados del módulo de documentos, que siguen abiertos:**
  1. `CuerpoRenderizadoTest` monta todo sobre ISO, así que los bloques exclusivos del ENS
     —`tabla_derivacion`, `notas_anexo_ii` y `tabla_madurez`— no tienen ninguna aserción sobre su HTML
     materializado. `CuerpoSeguroTest` recorre los dos tipos, pero sobre el **esqueleto** de fábrica,
     con los huecos sin rellenar.
  2. `MaterializarCuerpo` cierra su `match` con `default => []`. Una fuente nueva añadida a
     `EsquemaCuerpo::FUENTES` y olvidada ahí produce un bloque **vacío** en el PDF sin ningún error.
     `EsquemaEnDosIdiomasTest` compara PHP con TypeScript, pero nadie compara `FUENTES` contra las
     ramas del `match`.

- **Los avisos son un resumen diario por organización, y de momento sólo por correo.** `avisos:enviar`
  recorre las organizaciones con `ContextoOrganizacion::paraOrganizacion()`, una cada vez: un comando
  programado no tiene petición ni usuario, así que sin contexto el scope no devuelve nada y RLS
  deniega por defecto — **no falla, no ve nada**, y un aviso que no salta es indistinguible de no
  tener nada que avisar. Nada de `withoutGlobalScopes()` ni de `comoMantenimiento()`: esto no cruza
  organizaciones. La notificación lleva **escalares y ningún modelo**, por lo mismo que los jobs.

  **Un resumen, no una alerta por evidencia**: dice cómo está la cosa hoy, así que repetirlo mañana no
  es spam y no hace falta una tabla de «ya avisado» para evitar duplicados. Si no hay nada que decir no
  se envía: un correo diario que casi siempre dice «todo en orden» se filtra a una carpeta en dos
  semanas y deja de verse el día que importa.

  El resumen lleva **evidencias y tareas en listas separadas**: una evidencia caducada es una prueba que
  ya no prueba y una tarea vencida es trabajo que no se hizo; se arreglan de formas distintas y las lleva
  gente distinta.

  **No hay tabla de avisos y es a propósito.** Una bandeja en la interfaz necesitaría tabla propia con
  `organizacion_id` y RLS; la tabla `notifications` de Laravel no lleva organización, que es
  exactamente el motivo por el que se retiró `spatie/laravel-medialibrary`. Y `ResumenVencimientos` usa
  **los mismos scopes que cuenta el panel** (`Evidencia::caducadas()`, `porCaducar()`): con la
  condición escrita dos veces, el día que cambie una el correo dirá 12 y la pantalla enseñará 9.

- **`lang/es.json` existe por el correo.** Las cadenas de la plantilla de notificaciones de Laravel
  —«If you're having trouble clicking…», «All rights reserved.»— van por `__()` y salían en inglés en
  el primer correo que manda el producto, con todo lo demás en español.

- **`OrigenTarea::Propia` no está en la especificación y se añadió a conciencia.** § 4.7 enumera cinco
  orígenes —hallazgo, riesgo, brecha de implantación, incidente, revisión por la dirección— y los cinco
  dan por supuesto que toda tarea nace de otro registro. Muchas no: «pedir presupuesto del antivirus» no
  es ninguna de las cinco cosas. Sin un valor para eso, quien apunta una tarea a mano elige el que menos
  mal le suena y el campo deja de significar nada, que es lo contrario de por qué existe. Los cuatro
  orígenes cuyo módulo no existe **se declaran pero no se ofrecen** (`OrigenTarea::disponible()`, y el
  `FormRequest` los rechaza): una tarea marcada como «hallazgo de auditoría» sin auditoría detrás no es
  trazable, es una etiqueta.

- **`retirado`/`dado_de_baja` tiene su equivalente en tareas: `hecha` y `descartada` no son lo mismo.**
  Descartar es decidir que no se hará, y **exige motivo** —lo comprueban `CambiarEstadoTarea` y el
  `FormRequest`, porque la regla vale también para un importador—. Por eso la **acción masiva no
  descarta**: un motivo escrito una vez para cincuenta tareas no es un motivo, es un trámite. Y por eso
  no se borran las tareas que no se van a hacer: borrarlas deja el hallazgo sin rastro de qué se decidió.

- **La fecha de cierre la pone el dominio, no el formulario.** Un `CHECK` acopla `estado` y
  `fecha_cierre` en las dos direcciones, así que dejar que la escriba quien llame significa que el día
  que se cierre una tarea desde un job la inserción falle con un error de restricción que no menciona la
  palabra «cierre». `CrearTarea` hace `->refresh()` tras insertar por lo mismo: los valores por defecto
  de `estado`, `origen` y `prioridad` los pone la base, y repetirlos en el modelo sería el mismo dato en
  dos sitios que pueden desincronizarse.

- **En la tabla de tareas el rojo es sólo de la columna «Plazo».** Una tarea vencida es de las pocas
  cosas del dominio que van mal de verdad, y es el mismo uso que ya tenía `caducada` en evidencias. Los
  estados **no** lo gastan —`bloqueada` va en el azul de `planificado`: está aparcada, no incumplida— y
  hay un test que lo fija recorriendo el enum. Si los estados llevaran rojo, el plazo dejaría de saltar a
  la vista, que es la única razón por la que se pinta de rojo.

- **Los tres filtros de estado de la tabla van por `Filtro::porScope()`**, apuntando a los mismos scopes
  que cuenta el aviso diario (`abiertas`, `vencidas`, `sinResponsable`). Misma regla que en el inventario:
  con la condición escrita dos veces, el día que cambie una el correo dirá 12 y la tabla enseñará 9.

- **El bloque «Qué se está haciendo» vive en la ficha de la implantación, no sólo en `/tareas`.** Es
  donde alguien se pregunta qué falta para cumplir un requisito, igual que las evidencias están donde se
  pregunta cómo se prueba. Y de ahí sale el único camino que hoy produce tareas con origen trazable:
  `/tareas/crear?implantacion={id}`, que preselecciona el origen y **no lo deja cambiar** —preguntarlo
  invita a cambiarlo—.

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
