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
5. ⬜ Primer módulo completo de punta a punta: inventario de activos.
6. ⬜ Primer documento con Gotenberg: la SoA.

## El catálogo

Vive en `catalogo/*.yaml`, versionado en el repositorio, y se carga con un comando idempotente:

```sh
php artisan catalogo:importar                    # importa los tres ficheros
php artisan catalogo:importar --dry-run          # muestra el diff sin escribir
php artisan catalogo:importar catalogo/ens-rd311-2022.yaml
```

El importador empareja por clave natural `(marco.codigo, requisito.codigo)`, nunca por id, y clasifica en nuevos / modificados / desaparecidos. **Los desaparecidos no se borran**: se marcan, porque puede haber implantaciones colgando de ellos. Al importar una revisión de un marco, el comando informa de cómo afecta a las implantaciones existentes; no modifica nada en silencio.

**Tarea pendiente de verificación:** los códigos, títulos y la matriz de aplicabilidad del ENS se escribieron a partir del RD 311/2022 y **deben contrastarse celda a celda con el texto del BOE** antes de dar el catálogo por bueno. Cada fichero YAML lleva `revisado: false` en la cabecera hasta que eso se haga. El diff del importador hace que la corrección sea barata: se corrige el YAML y se reimporta.

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

- **La escala `--marca-*` no se invierte en oscuro.** 50 es el tono más claro y 950 el más oscuro en los dos temas. Invertirla parecía elegante y era una trampa: `bg-marca-900 text-marca-950` deja de tener sentido en la mitad de los casos y el contraste se rompe sin que se vea en el fichero que lo usa. Lo que cambia de tema son los roles (`--primary`, `--accent`).

- **Un solo sistema de radios.** Superficies (tarjeta, tabla, diálogo, aviso) `rounded-xl`; controles (botón, input, select) `rounded-md`; badges, avatares y chips `rounded-full`. El escalón de superficie lo trae el estilo `reka-vega` de shadcn-vue en `Card` y todo lo que hace de panel lo iguala.

- **`hover:bg-primary/80` del botón primario se cambió por `hover:bg-marca-700`.** El original mezcla con el fondo, y sobre claro **aclara** el botón: con el teal de marca el contraste del texto caía a 3.4:1 y dejaba de pasar AA justo al pasar el puntero. Oscurecer un paso lo sube en lugar de bajarlo.

- **El motion va con `motion-v`, no con GSAP.** Aquí no hay scroll-telling, ni *pinning*, ni *scrub*: lo que se anima son entradas, escalonados y transiciones de estado, y para eso GSAP es peso muerto en una herramienta que entra en el alcance del propio SGSI. Las duraciones y curvas viven en `resources/js/lib/motion.ts`, una sola vez, y la preferencia de movimiento reducido se resuelve en tres capas: el `@media` global de `app.css`, el `<MotionConfig reduced-motion="user">` de los layouts y el composable `useMovimientoReducido`.

- **`lib/navegacion.ts` es el mapa único de la aplicación.** Lo leen el sidebar, el panel lateral de móvil, las migas de pan y la paleta de comandos. Un módulo nuevo se añade ahí y aparece en los cuatro sitios; mantener cuatro listas a mano termina dejando un módulo fuera del buscador sin que nadie lo note.

- **Las páginas de error se pintan con Inertia** (`resources/js/pages/Error.vue`, conectado en `bootstrap/app.php`). No es cosmética: el aislamiento multi-tenant responde **404, no 403**, cuando alguien pide un recurso de otra organización, porque decir «existe pero no es tuyo» ya sería filtrar información. Ese 404 lo ve gente real y con frecuencia, así que tiene que explicar qué ha pasado y llevar a alguna parte. Los 500 sólo se maquillan fuera de depuración: en local se quiere la traza de Laravel. `tests/Feature/ErroresTest.php` fija que la respuesta conserva su código de estado.

- **El formulario de acceso se ancla arriba, no se centra en vertical.** Con centrado, aparecer el aviso de credenciales incorrectas empuja todos los campos hacia abajo y hay que volver a buscar el cursor. El panel de marca de la derecha es de color sólido en los dos temas a propósito: es una superficie de marca, como lo sería una fotografía, no una sección que se haya quedado sin invertir.

- **En el acceso, logotipo, título, campos, ayuda y pie forman una sola pila y comparten borde izquierdo.** El logotipo estaba pegado al borde del navegador y el formulario centrado en una columna de casi mil píxeles: sin ningún eje en común se leían como dos cosas sueltas flotando en el mismo hueco. Por eso el `<footer>` repite el `mx-auto w-full max-w-[26rem]` de la pila en vez de centrarse en la columna. Y por eso **el símbolo no se repite**: el panel llevaba un `Logotipo` de 36 px justo encima de la balanza que gira, la misma figura dos veces en la misma superficie. El respaldo «un producto de RM Technology» vive en la columna del formulario, que es la única que se ve por debajo de `lg`.

- **Las gráficas se pintan a mano, y en el PDF las pintará el servidor.** El stack no decía nada de gráficas, ni a favor ni en contra, así que queda escrito aquí. Dos renderizadores por un motivo concreto: en un documento que va a PDF/A-3b y aspira a PDF/UA no debería ejecutarse JavaScript, porque un canvas entra como mapa de bits y se lleva por delante el texto seleccionable. En pantalla, SVG y CSS sobre los tokens de `app.css` (`AnilloProgreso`, `BarraSegmentada`, `components/grafica/`); en el documento, SVG generado en PHP cuando llegue el módulo de documentos. **Chart.js se descartó** por lo anterior y porque obliga a escribir los colores en JavaScript en vez de leerlos de los tokens. Una librería —`d3-scale` y `d3-shape`, que son funciones puras sin DOM— entra el día que haya una serie histórica con eje de tiempo: escalas y ticks legibles es lo único que no compensa escribir a mano. Hoy no la hay: las transiciones existentes son todas del mismo instante.

- **Ninguna cifra del panel se calcula en el controlador.** Viven en `app/Domain/Implantacion/ResumenCumplimiento.php`, porque son las mismas preguntas que contestará el informe de estado. Se cuenta siempre sobre lo exigible (`aplica = true`): un requisito que no se le exige al sistema no está pendiente, no cuenta. Y una media viaja siempre con su denominador — `madurezMedia` con `madurezEvaluadas`—: una media de madurez sobre cuatro requisitos de doscientos no dice lo mismo que sobre los doscientos.

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
