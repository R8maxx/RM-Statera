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
6. ⬜ Primer documento con Gotenberg: la SoA.

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
