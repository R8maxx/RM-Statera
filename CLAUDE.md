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

   > **Del invariante 8, las vulnerabilidades todavía no están.** No hay registro: `riesgos.vulnerabilidad` es una columna de texto libre del escenario MAGERIT —la condición que hace creíble la amenaza—, que no es lo mismo que un hallazgo técnico con severidad, activo afectado y plazo de remediación (A.8.8 de ISO, `op.exp.4` del ENS). El invariante se queda como está porque es el objetivo declarado, y esta nota existe para que la frase no se lea como una afirmación de estado. Se construye con el módulo de vulnerabilidades; hasta entonces, queda dicho.

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
| Arrastrar y soltar | `@atlaskit/pragmatic-drag-and-drop` 3.1.0, versión exacta. Sólo el tablero, y siempre con el menú detrás |
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
8. ✅ Análisis de riesgos (§ 4.3), que abre la **fase 2** —«el papel formal»—.
   Catálogo de amenazas de MAGERIT, metodología por organización, valoración con
   histórico comparable y salvaguardas sobre implantaciones.
9. ✅ Flujo de aprobación documental con acuse de lectura (§ 4.5): estado del
   documento, firma de la dirección, obsolescencia de la versión anterior,
   periodicidad de revisión con su aviso, y la segunda familia de documentos —los
   **redactados**: política, norma y procedimiento—.
10. ✅ Plan de adecuación del ENS, el tercer documento calculado. **No es el
    § 4.18**, que es «Informes y exportación» y sigue pendiente: el plan no es un
    módulo numerado de los diecinueve —aparece dentro de la lista de exportables
    del 4.18 y en la fase 2—, y llamarlo así hacía creer que ese módulo estaba
    hecho.

    Con él la **fase 2** —«el papel formal»— queda completa: riesgos con metodología,
    documentos con flujo de aprobación, y SoA, DdA y plan de adecuación.
11. ✅ Auditorías (§ 4.12), que abre la **fase 3** —«el ciclo vivo»—: los tres
    tipos, checklist generada desde el catálogo, hallazgos, y el cierre que
    congela e inmoviliza lo auditado.
12. ✅ No conformidades y acciones correctivas (§ 4.13), la otra mitad del módulo
    anterior: un hallazgo sin tratamiento detrás no cierra ningún ciclo. Causa
    raíz, acciones correctivas —que son **tareas**— y la **verificación de
    eficacia**, que es el paso que la cláusula 10.2 pide y el que más se olvida.
    Con esto el ciclo se recorre entero: auditar, encontrar, tratar y comprobar.
13. ✅ Contexto de la organización (§ 4.1): el DAFO, las partes interesadas con
    sus requisitos y el alcance declarado, en revisiones **versionadas** que se
    aprueban y se congelan. Era el único de los diecinueve módulos que no estaba
    asignado a ninguna fase, y es lo que le faltaba al § 4.15: la cláusula 9.3
    pide «cambios de contexto» como entrada obligatoria y hasta aquí no había de
    dónde sacarla.

14. ✅ Indicadores y mediciones (§ 4.14, cláusula 9.1). El panel llevaba desde el
    principio contando cosas, y todas esas cifras eran de **hoy**: la 9.1 no pide
    una cifra, pide un seguimiento con cadencia, objetivo y responsable. Con la
    serie sellada, «¿ha mejorado esto desde la última revisión?» —que es
    literalmente lo que pregunta la 9.3— tiene contra qué compararse.

    Va **antes** que los objetivos de seguridad (6.2) porque el «cómo se
    evaluarán los resultados» que esa cláusula exige **es** un indicador: al
    revés, el objetivo nacería con el campo que el auditor más mira y nada
    detrás. Es el mismo orden que llevó a hacer el § 4.1 antes que el § 4.15.

    La **fase 3 sigue abierta**, y el § 4.15 sigue bloqueado por lo mismo que lo
    bloqueaba el § 4.1: de las siete entradas obligatorias de la 9.3, ya salen
    cinco —acciones previas, cambios de contexto, partes interesadas, no
    conformidades, auditorías y riesgos— y **faltan dos**: el cumplimiento de los
    **objetivos de seguridad** (6.2, sin módulo) y las **oportunidades de mejora**
    (10.1, que hoy sólo existen como `TipoHallazgo::OportunidadMejora` dentro de
    una auditoría). Queda además el calendario de obligaciones completo (§ 4.16) y
    los otros dos tercios del flujo de conformidad (§ 4.17).

15. ✅ Objetivos de seguridad (cláusula 6.2). La primera de las dos entradas que
    le faltaban a la 9.3, y la que el punto anterior dejó preparada: el «cómo se
    evaluarán los resultados» que la 6.2 exige **es** un indicador, así que el
    § 4.14 fue antes a propósito. Hasta aquí el producto **medía** y no había
    dónde comprometerse a una cifra; son dos cosas distintas y la norma las pide
    las dos.

    Es además una de las cinco cláusulas que tenían requisito en el catálogo,
    implantación esperando y **ningún sitio donde escribirse** — exactamente lo
    que le pasaba al § 4.1 hasta que se construyó.

    **A la 9.3 le falta ya una sola entrada**: las oportunidades de mejora (10.1),
    que siguen existiendo únicamente como `TipoHallazgo::OportunidadMejora` dentro
    de una auditoría. Ése es el punto 16.

16. ✅ Oportunidades de mejora (cláusula 10.1). La segunda mitad del capítulo 10 y
    **la última entrada que le faltaba a la 9.3**: con esto, las siete entradas
    obligatorias de la revisión por la dirección salen todas del producto y el
    § 4.15 deja de estar bloqueado.

    Hasta aquí una oportunidad de mejora **sólo existía dentro de una auditoría**,
    como `TipoHallazgo::OportunidadMejora`: la que se le ocurría a alguien un
    martes, o la que salía de un indicador que no llegaba a su objetivo, no tenía
    dónde apuntarse.

    Es además el módulo que **cierra la bifurcación del capítulo 10**: la 10.2
    trata lo que incumple y la 10.1 lo que se puede mejorar sin que nada incumpla,
    y desde aquí un hallazgo va al registro que le toca — con las dos puertas
    cerradas en el dominio, no sólo en el formulario.

17. ✅ Revisión por la dirección (§ 4.15, cláusula 9.3). **El módulo que llevaba
    bloqueado desde el principio**, y no por su complejidad: la 9.3 cierra la
    lista de entradas obligatorias y dos de las siete no salían de ninguna parte.
    Con los dos puntos anteriores dentro, las siete existen y esto las recoge.

    Con él **la fase 3 —«el ciclo vivo»— llega a su pieza central**: auditar,
    encontrar, tratar, comprobar, medir, comprometerse, mejorar y **revisarlo todo
    desde arriba**. El quinto documento calculado, el quinto trigger de
    inmutabilidad y el octavo verbo de supervisión.

    Lo que **sigue abierto de la fase 3**: el calendario de obligaciones completo
    (§ 4.16), del que hoy existen tres `Fuente` de las once que enumera la
    especificación, y los otros dos tercios del flujo de conformidad (§ 4.17).

18. ✅ Personas (§ 4.8) y la cláusula 5.3. **El primero de los dos módulos que
    muerden hoy**: en categoría básica ya son exigibles `mp.per.2`, `mp.per.3` y
    `mp.per.4` —deberes por escrito, concienciación y formación— y no tenían dónde
    registrarse. El otro es incidentes (§ 4.10), que sigue sin construirse.

    Es además el módulo que llevaba **cuatro enganches puestos esperando**: el
    IND-03 del seeder, que era manual con un comentario que decía «mientras el
    § 4.8 no exista»; las dos limitaciones impresas —la de los roles ENS de la DdA
    y la del acuse de lectura—, que pasaron a ser falsas en el PDF entregado y se
    reescribieron; y la cláusula 5.3, que era un hueco declarado en `PRODUCT.md`.

    Lo que cierra la 5.3 no es la tabla de nombramientos: es que la
    incompatibilidad se **impide** y no se avisa, que es lo que la especificación
    pide con esas palabras.

19. ✅ Incidentes (§ 4.10, `op.exp.7`). **El segundo de los dos módulos que
    muerden hoy**, y con él los dos quedan cubiertos: en categoría básica ya no
    hay ninguna medida exigible sin dónde registrarse.

    Es además el módulo que cierra **tres enganches** puestos hace meses:
    `OrigenTarea::Incidente` y `OrigenNoConformidad::Incidente` pasan a ofrecerse
    sin migración —el valor estaba en el `CHECK` desde la primera—, y de paso
    `OrigenNoConformidad::RevisionDireccion`, que se quedó en `false` y era falso
    desde el § 4.15.

    La decisión del módulo no es el ciclo: es **dónde hay reloj y dónde no**. Las
    72 h de la AEPD salen del artículo 33.1 del RGPD y van citadas; el CCN-CERT
    no tiene cuenta atrás porque el RD 311/2022 dice «sin dilación», y
    inventarle un número sería una opinión de la herramienta disfrazada de plazo
    legal — lo mismo que el producto se niega a hacer con el riesgo residual.

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

Todo corre en contenedores, la aplicación incluida. El host no necesita ni PHP ni
Node.

```sh
docker compose up -d --build        # levanta el producto entero
```

Lo demás va dentro. Con `app` basta para todo lo de PHP; `vite` es el de Node:

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan catalogo:importar       # ISO, ENS, mapeos y las amenazas de MAGERIT
docker compose exec app php artisan db:seed                 # organización, usuarios, sistema, inventario, tareas, riesgos y personas (sintéticos)
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
`vite` (el 5173) y `minio-init`, que crea los buckets y se apaga. Detrás siguen
`postgres`, `redis`, `gotenberg` y `minio`.

## Prioridad de cobertura de tests

Por orden, según dónde duele un fallo silencioso:

1. **Motor de categorización ENS** — un fallo aquí deja a un cliente fuera de conformidad sin que nadie se entere. Matriz completa, incluidas las medidas moduladas por nivel de dimensión.
2. **Aislamiento multi-tenant** — que ninguna consulta cruce la frontera de organización.
3. **Transiciones de estado de implantación** y recálculo tras un cambio de valoración.
4. **Importador del catálogo**, incluida la idempotencia y el diff.
5. Resto de módulos: flujos principales.

### Los tests que no hay que acordarse de ampliar

Seis tests **descubren** en vez de enumerar, así que cubren solos lo que traiga el módulo siguiente.
Nacieron de fallos que ya habían mordido o estaban a punto:

| Test | Qué convierte en rojo |
|---|---|
| `Organizacion/RlsDeclaradaTest` | Una tabla con `organizacion_id` **sin RLS activa, forzada y con política**. Pregunta a `pg_policies` en vez de enumerar modelos. Lleva la lista de las cuatro excepciones declaradas —`users` y las tres de spatie— y exige que quien deje de serlo salga de la lista. |
| `Organizacion/FactoriesSinOrganizacionTest` | Una factory que declare `organizacion_id` en su `definition()`. **Recorre los subdirectorios desde el § 4.1**: su `glob` miraba sólo el primer nivel, así que `NoConformidad/`, `Auditoria/`, `Riesgo/`, `Catalogo/` y `Contexto/` quedaban fuera — cinco de diecinueve, y justo las de los módulos más recientes. Un test que existe para impedir una recaída y que no mira donde se escribe el código nuevo da por cubierto lo que no cubre. |
| `Autorizacion/RolesTest` | Que al rol `Auditor` le falte un permiso `.ver`, o que le sobre uno de escritura. `Rol::permisos()` es lista literal para `Tecnico` y `Auditor`, y olvidarla no rompía nada. |
| `Diseno/TonosTest` | Un tono que el servidor emite y que no está en `lib/tonos.ts`. **`tono()` acaba en `?? neutro`: el badge sale gris y no falla nadie** — el mismo fallo que `IconoTipo` tenía y que `IconosTest` ya cubría. |
| `Metricas/CalculosTest` | Un caso de `CalculoIndicador` cuya rama revienta, o que devuelve una cifra que la tabla rechaza —numerador sin denominador, denominador a cero—. Recorre `cases()` y **sella cada uno de verdad**, así que lo que prueba no es que el `match` tenga la rama: es que el `CHECK` de PostgreSQL la acepta. Sin él, ese rechazo aparecería meses después dentro del comando de las 07:30 y sin nadie mirando. |
| `Panel/AlertasTest` | Un registro con `alertas()` que no esté en `AlertasDelPanel::FUENTES`. Recorre `app/Domain/` en vez de enumerar módulos, porque olvidar uno **no rompe nada**: su pestaña del panel deja de marcarse, que es el fallo que el punto existe para cerrar. Y si su `glob` deja de encontrar nada se pone rojo, que es la lección de `FactoriesSinOrganizacionTest`. |

Los dos de diseño y el de roles se apoyan en `enumsDelDominioCon()` (en `tests/Pest.php`), que recorre
`app/Domain/<Contexto>/Enums/` **y el propio contexto**, porque algunos enums están sueltos —`Aviso\Fuente`
lo está— y limitarse al subdirectorio dejaba fuera justo al que rompía la suite. `IconosTest` tenía dos
listas literales y ya no tiene ninguna.

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

**La SoA justifica la inclusión sin inventarse un riesgo.** Cada control aplicable dice «Anexo A» y,
cuando los hay, «tratamiento del riesgo R-014» —el vínculo de salvaguarda del §4.3, que es la
justificación que ISO 6.1.3 d) espera de verdad— y «exigido por el ENS (op.acc.2)» —que es un
requisito **legal** y una justificación de inclusión igualmente legítima para ISO, y de paso el
argumento del producto impreso en el entregable—. Lo que no se hace es escribir una referencia a un
riesgo que no está vinculado.

**Y por eso las limitaciones de los dos documentos se reescribieron cuando llegó el §4.3.** Decían
que el módulo de riesgos no estaba implantado, y eso pasó a ser **falso en el PDF que se le entrega
al auditor**, que es peor que una limitación ausente. No se borraron: se precisó qué es lo que la
herramienta sigue sin hacer —no exige que todo control aplicable tenga un riesgo detrás, ni comprueba
que el análisis cubra el alcance entero—, mismo tratamiento que ya se le había dado a la limitación
del flujo de aprobación. En la DdA se separaron las dos mitades: el análisis de riesgos existe y no
figura ahí **por diseño** —una Declaración de Aplicabilidad declara medidas, no riesgos—.

**Y se volvió a reescribir al llegar el plan de adecuación**, por tercera vez y por lo mismo: la DdA
decía que el plan «sigue pendiente: su módulo no está implantado» y eso pasó a ser falso en el PDF
entregado. Ahora dice que el plan existe, en documento aparte, y **por qué no figura ahí** — que es
una decisión y no una carencia. `ContenidoDdaTest` clava que la frase vieja no vuelva.

**Y por cuarta vez con las auditorías (§ 4.12 y § 4.13).** Decía que «el módulo de auditorías no está
implantado», y con el ciclo entero dentro eso era falso en el PDF entregado. Ahora dice que las
auditorías se registran, que su resultado no figura ahí **por diseño** —una Declaración de
Aplicabilidad declara la situación de cada medida, no el resultado de quien la revisó— y **qué sigue
sin hacer la herramienta**: el informe de auditoría como documento, el programa anual, y comprobar que
el alcance auditado cubra lo exigible. Ese último punto es el que vale: sin esa comprobación, «esta
medida no tiene hallazgos» se lee como «esta medida se auditó y estaba conforme», que es el mismo
argumento por el que un punto de la checklist distingue `pendiente` de `conforme`. El test comprueba
las dos mitades —que la frase vieja no vuelve **y que la nueva sigue declarando lo que falta**—, porque
si sólo mirara la primera daría por bueno borrar la limitación entera.

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

## La aprobación de un documento

Es el § 4.5, y el hueco estaba reservado por escrito en tres sitios: `estado_generacion` se llama así
para dejar libre el nombre `estado`, `Permiso` anunciaba que `documentos.aprobar` «llegará con el
flujo de aprobación», y `DESIGN.md` tenía el token `--estado-en-revision` declarado **sin flujo
detrás**. Éste es ese flujo.

**Aprobar es lo que emite, y no es una preferencia.** La portada se congela en `instantanea` al
generar y el trigger vuelve la fila inmutable en cuanto tiene número: una firma posterior **no podría
salir impresa en el PDF que se entrega**, que es justamente donde el auditor la busca. Así que firmar
hace dos cosas —escribe la aprobación y **manda regenerar**— y `EmitirVersion` pasa a ser el último
paso de ese trabajo, sin ruta ni botón propios. Entre las dos cosas hay un hueco en el que la fila
está **firmada y sin numerar**; el `CHECK` lo admite a propósito —sólo exige firma para el estado
`aprobado`, no al revés— y si la generación falla, la versión se queda en revisión con su error.

De ahí salen dos detalles que no se ven leyendo el job:

1. **`etiquetaPrevista()` existe por el pie de página.** Esa generación corre con `numero` todavía
   nulo, así que `etiqueta()` diría «Borrador» en las noventa páginas del documento entregado y el
   fichero se llamaría `soa-sgsi-01-borrador.pdf`.
2. **«Borrador» dejó de ser «sin número» y pasó a ser «sin firma».** La limitación que se antepone en
   el PDF y el `esBorrador` de la portada miran `tieneFirma()`, no `numero`. Mirando el número, el
   documento entregado se declararía borrador a sí mismo.

**Cinco estados y no los cuatro de la § 2.2.** Falta uno para «la dirección lo ha mirado y ha dicho
que no», y sin él una versión tumbada se queda en «pendiente de firma» para siempre. `rechazado`
**exige motivo**, igual que `descartada` en tareas, y no gasta número: un hueco en la numeración es
una pregunta del auditor. Tampoco gasta rojo — que la dirección tumbe una versión es una decisión
legítima, mismo criterio que `DecisionRiesgo`.

**`obsoleto` es el hermano de `EstadoImplantacion::NoAplica`: lo pone el sistema**, al aprobarse la
siguiente, y nunca una persona. Es además **la única puerta del trigger**, tallada igual que la de
`riesgo_valoraciones` con `vigente`: sin ella un documento aprobado no podría revisarse nunca. Se
compara el registro entero con `estado` y `obsoleta_en` neutralizados, y **`updated_at` se neutraliza
sólo cuando el estado cambia** —neutralizarlo siempre dejaría pasar un «toque» suelto sobre una fila
entregada—. Quién es la vigente lo marca un índice único parcial, como el borrador.

**Las versiones que ya estaban emitidas se archivaron como obsoletas, sin firmante.** Bajo el modelo
nuevo una fila con número está aprobada, y aquéllas no lo están: rellenarles `aprobada_por_id` con
quien pulsó «Generar» sería **fabricar una firma**, que es lo que estas tablas existen para hacer
imposible. Por eso el `CHECK` de la firma no alcanza a `obsoleto`.

**Los destinatarios del acuse son todos los usuarios de la organización**, sin tabla de destinatarios:
los pendientes salen de restar. Es una simplificación **declarada en las limitaciones del PDF**, no un
descuido —quien tiene que conocer la política son las personas, y § 4.8 no existe— y `User` no lleva
el scope de organización, así que `CoberturaAcuse` lo acota a mano. El acuse cuelga de la **versión**:
quien leyó la v3 no ha leído la v4, y heredarlo convertiría el registro en un trámite que se pasa solo.

**La ruta del acuse va sin permiso propio y sin segundo factor**, y es la única escritura del producto
que va así: se escribe sobre uno mismo, como en `/perfil`. Un acuse que cuesta dos pasos se deja de
firmar. En cambio **mandar a revisión va con `documentos.redactar`** y no con `generar`: es el final
de escribir, no el principio de entregar, y quien lo redacta tiene que poder soltarlo sin depender de
nadie.

**Y la limitación impresa se reescribió, no se borró.** Decir que la herramienta «no implementa un
flujo de aprobación» pasó a ser **falso en el PDF que se le entrega al auditor**, que es peor que una
limitación ausente — el mismo tratamiento que ya se les dio a las dos de riesgos con el § 4.3. Lo que
sigue sin hacer: comprobar que quien firma tenga potestad para hacerlo, y guardar firma electrónica
cualificada. `LimitacionesBlindadasTest` lo clava.

---

## Los documentos redactados

La segunda familia, y no se parece a la primera. Una Declaración de Aplicabilidad es una consulta
sobre `implantaciones` congelada en un PDF; **una política no sale de ninguna consulta**. Son los tres
niveles de la jerarquía del § 4.5 —`politica`, `norma`, `procedimiento`— y son los que dan sentido al
acuse: nadie acusa recibo de una SoA.

El cuarto nivel de esa jerarquía, el **registro**, no entra: un registro es la salida de un
procedimiento, no un documento que Statera redacte y versione.

**Una sola clase para los tres** (`DocumentoRedactado`), que implementa `GeneradorDocumento`
directamente en vez de heredar de `DeclaracionAplicabilidad`: no tiene filas, ni tabla, ni
correspondencias cruzadas. Lo único que comparte con las declaraciones —portada, historial y
limitaciones— se extrajo al trait `ArmaContenidoComun`, y **las limitaciones son el motivo de fondo**:
dos copias de esa lista es cómo se acaba con una política declarando algo que la SoA ya no declara.

**No cuelgan de un sistema** —el `CHECK` en negativo ya lo admitía— y `marcoEsperado()` devuelve nulo,
que significa «no hay nada que casar» y nunca «no se ha rellenado». De los once huecos narrativos les
quedan cinco: ofrecerle «cómo leer la tabla» a un documento sin tabla es ofrecerle explicar algo que
no existe.

**El texto de fábrica del alcance se queda vacío a propósito.** A quién obliga y sobre qué se aplica
es lo más específico que tiene un documento así, y cualquier frase de relleno acabaría impresa en el
PDF que alguien aprueba. Un hueco vacío no se pinta —ni él ni su título—, así que se nota; una frase
genérica, no. Lo que sí trae es la introducción, porque `org.1` pide literalmente que la política
declare objetivos, compromiso de la dirección y a quién obliga: eso es lo que la § 4.5 llama
«plantilla base».

---

## Las auditorías

§ 4.12, la cláusula 9.2 de ISO, y la primera pieza de la **fase 3**. Tres tablas —`auditorias`,
`auditoria_puntos`, `hallazgos`— y cuatro desvíos de la § 2.2, cada uno con su motivo en la cabecera
de la migración.

**Cerrarla es lo que la vuelve un hecho**, y lo garantiza el **tercer trigger de inmutabilidad** del
producto, hermano de los de `documento_versiones` y `riesgo_valoraciones`. A partir del cierre, ni la
checklist ni los hallazgos admiten cambios: si se pudieran reescribir desde PHP, bastaría con pasar un
`no_conforme` a `conforme` y borrar el hallazgo para que la auditoría del año pasado dijera otra cosa.
Con su puerta, como los otros dos: de `cerrada` se vuelve a `en_curso` —reabrir— y **nunca a
`planificada`**, que sería decir que nunca se hizo.

**Y al cerrar se congela lo derivado.** Cada punto guarda la exigencia y el estado que la implantación
tenía ese día. Sin eso, revalorar el sistema en octubre cambiaría bajo los pies el denominador de la
auditoría de marzo y la fila **mentiría** — el mismo motivo por el que `riesgo_valoraciones` congela su
escala y sus salvaguardas. De ahí que `CerrarAuditoria` congele **antes** de marcar el estado: al revés,
el trigger bloquea el propio congelado con un error que habla de la checklist y no del orden.

**`sistema_id` es obligatorio y `marco_id` no existe.** § 2.2 dibuja lo contrario, pero ella misma
define `sistemas` como «la unidad de alcance y de certificación»: el SGSI **es** un sistema. Sin él no
hay checklist, que es la mitad del módulo — el mismo argumento que ya se escribió para el plan de
adecuación. Y con el sistema puesto, `marco_id` sería el mismo dato en dos sitios que pueden
desincronizarse.

**Un punto no se puede marcar «no aplica».** La checklist se precarga desde lo aplicable, así que todo
punto lo es **por construcción**: un auditor marcando «no aplica» estaría contradiciendo una derivación
legal desde un desplegable, que es lo que prohíbe el invariante 4. Lo que sí necesita decir es
`fuera_de_muestra`, que es una decisión suya sobre el alcance y no sobre la aplicabilidad. Y
`pendiente` no es `conforme`, que es el argumento de `EstadoControl::PorConfirmar`: sin la checklist,
la ausencia de hallazgo se lee como conformidad y una auditoría por muestreo miente.

**Los hallazgos cuelgan del punto, no de la pareja (auditoría, requisito)** —con la pareja, nada
impediría un punto «conforme» con una no conformidad encima del mismo requisito—, y su
`auditoria_punto_id` es **nullable** contra la letra de § 2.2: una auditoría ISO produce hallazgos que
no cuelgan de ninguna medida —«el programa de auditoría interna no está definido»— y con la columna
obligatoria acabarían colgados de un requisito arbitrario.

### La checklist: el primer `Recurso` acotado a un padre

Es **pantalla propia** (`/auditorias/{auditoria}/checklist`) y no un bloque de la ficha: son 52 medidas
en categoría básica y unas **122** en un sistema de ISO —los 93 controles del Anexo A más las cláusulas
4 a 10, el mismo 122-contra-93 que ya mordió a la SoA—. A ese tamaño hacen falta filtros, orden y
marcado en bloque. Precedente de forma: `/tareas/tablero` y `/activos/etiquetas`.

**La auditoría entra por el constructor.** `Recurso::consulta()` no recibe argumentos y sólo lo llama
`ConsultaRecurso`; cambiar esa firma contaminaría las once implementaciones para que la use una.

**Y el aislamiento tiene aquí un eje que no existía.** Las tres capas tapan el cruce entre
organizaciones; entre dos auditorías de la **misma** organización no hay nada. Así que el `where` de la
consulta es la frontera y no un filtro, la acción masiva acota por `auditoria_id` además de por los ids,
y todo lo que cuelga de `{auditoria}` va con `scopeBindings()`.

**`Inertia::once()` colisionaba, y hay test.** La definición viaja con la clave `recurso:{clave}` y el
cliente la reclama por esa clave **copiando el valor viejo**: dos checklists con la misma clave harían
que la segunda se pintara con la definición de la primera, incluida la URL de su acción masiva. Por eso
`RespondeConRecurso::tabla()` acepta un sufijo de caché y `clave()` **se queda estable**: `clave()` es
además el nombre con el que la vista de columnas se guarda en el navegador y con el que se nombra el
CSV, así que hacerla dinámica guardaría una vista por auditoría —y quien ordena sus columnas las
perdería en la siguiente— y metería dos puntos en el nombre del fichero. Las columnas son idénticas
auditoría a auditoría: compartir la vista es lo que se quiere.

**La checklist entera cabe en una página** (`porPagina` 100, con escalón de 200 que no existe en el
resto del producto). `DataTable` limpia la selección cada vez que cambia `meta`, así que paginar la
borra: sin eso, «marcar veinte conformes de golpe» obliga a empezar de nuevo en cada página.

**La acción masiva marca `conforme` y sólo `conforme`**, por `update` masivo y no por bucle tolerante.
Lo primero, porque «no conforme» y «observación» piden un hallazgo detrás y marcar cuarenta de golpe
fabricaría cuarenta huecos —el argumento que dejó `descartada` fuera de la masiva de tareas—. Lo
segundo, porque el bucle de implantaciones existe para rechazar filas según su máquina de estados, y un
punto no tiene: el recuento de rechazadas sería siempre cero, un mensaje que miente sobre su propio
esfuerzo.

**La guarda del cierre está en el dominio aunque el trigger también lo impida.** Un `update` sobre una
auditoría cerrada levanta el `RAISE EXCEPTION` y sube como `QueryException` sin capturar: el usuario ve
el 500 genérico y el mensaje de la base —sin tildes, porque es SQL— no lo lee nadie.

**Los puntos no llevan `RegistraTraza`, y es deliberado.** Un `update` masivo por Query Builder no
dispara eventos, así que la traza aparecería en el camino de uno en uno y no en el masivo: media traza
es peor que ninguna, que es el razonamiento que ya está escrito para `marcarRevisados`. Y cerrar una
auditoría ISO escribiría 122 eventos que no dicen nada que el cierre no diga. **La limitación que eso
deja**: «¿quién marcó conforme esta línea?» se contesta con `auditorias.auditor` y nada más fino. Para
una auditoría basta —el acta la firma el auditor, no cada casilla—, pero es una limitación y no una
ausencia.

**`RegistrarAuditoria` existe por una línea**, el `refresh()`: `estado` lo pone la base y la instancia
recién creada llega sin él, así que lo primero que lo lea revienta con un «call to a member function on
null» que no menciona la palabra «estado». Es lo mismo que ya le pasó a `CrearTarea` y a
`GenerarDocumento::encolar()`. Aquí mordió en el seeder.

**`Auditoria::puntos()` no lleva joins ni orden**, y tuvo los dos durante un rato. El *route model
binding* acotado resuelve `{punto}` a través de la relación con un `where` **sin cualificar**: con
`implantaciones` y `requisitos` unidas, la consulta muere con «column reference "id" is ambiguous», un
error que no menciona ni la ruta ni la relación. Una relación dice de quién cuelga qué; cómo se ordena
es de quien consulta.

**El rol `Auditor` lee y no escribe**, y conviene decirlo porque el nombre del rol y el del módulo
coinciden y parece un olvido: este rol es el auditor **externo** que § 4.19 describe como de sólo
lectura, y quien registra la auditoría interna es el responsable de seguridad. Dejarle escribir sería
que quien audita redactara el acta de su propia auditoría.

---

## Las no conformidades

§ 4.13, la cláusula 10.2 de ISO y la otra mitad del módulo anterior. Un hallazgo dice qué se encontró;
esto dice por qué pasó, qué se hizo, quién responde y **si funcionó**. Tres tablas —`no_conformidades`,
`no_conformidad_tarea` y `no_conformidad_transiciones`— y cuatro desvíos de la § 2.2, con el primero
dando forma al resto.

**`accion_correctiva` no es una columna de texto: es una tarea.** Una acción correctiva tiene
responsable, plazo, estado y coste, que es literalmente `tareas`. Con una columna de texto, el trabajo
correctivo quedaría fuera del tablero, del calendario, del aviso diario y del presupuesto del plan de
adecuación — cinco sitios donde hay que verlo. Y el vínculo es **N:M**, como `implantacion_tarea`:
«implantar MFA» cierra a la vez una no conformidad de la auditoría ISO y otra de la autoevaluación del
ENS.

**Dos columnas de fecha y dos `CHECK`, no una.** `fecha_cierre` es cuándo se dio por tratada y
`fecha_verificacion` cuándo se comprobó que la corrección sirvió. Son dos momentos distintos —la
eficacia se mira semanas después, cuando hay con qué mirarla— y con una sola columna la verificación
que llega en noviembre no tiene dónde fecharse. `anulada` entra en el acoplamiento del cierre por el
mismo argumento que metió `descartada` en el de tareas: la pregunta del auditor es «¿desde cuándo dejó
de estar abierta?».

**`eficacia_verificada` no es un booleano, es un estado.** Como bandera sería el mismo dato que
`estado = 'verificada'` en dos sitios que pueden desincronizarse. Lo que sí merece columna es
`resultado_verificacion`: **qué** se comprobó. Mismo reparto que `nota_aceptacion` en riesgos.

**La verificación fallida no es un estado, es la vuelta a `en_tratamiento`.** Un `no_eficaz` se
quedaría puesto sobre una no conformidad que sigue viva y volvería a contarse como cerrada en cuanto
alguien lo mirara por encima. Precedente exacto: `EstadoAuditoria::Cerrada → EnCurso`. Y esa vuelta
**suelta las tres columnas de la verificación**, no sólo la fecha: dejar el resultado puesto sin su
fecha sería enseñar una comprobación que ya no consta. No se pierde nada, porque al verificar el texto
se copia además a la nota de la transición.

**Tres transiciones exigen motivo escrito**, y la regla vive en el dominio y no en el `FormRequest`
porque vale también para un importador: anular —«esto no era una no conformidad»—, verificar —donde la
nota *es* el resultado— y reabrir el tratamiento —donde «qué falló» es lo único que explica el ir y
venir—. El motivo de `anulada` va en la nota y no en columna propia, como `descartada` en tareas.

**`no_conformidades.verificar` es el quinto verbo de supervisión**, junto a `sistemas.valorar`,
`riesgos.aceptar` y `documentos.aprobar`, y es el que mejor explica la familia: comprobar que una
acción correctiva funcionó no puede hacerlo quien la ejecutó. El técnico trata la no conformidad
entera y no la firma. Y sí, `no_conformidades.*` rompe el patrón de una palabra de los otros nueve
módulos: se queda así porque casa con la tabla y con la ruta, y tres nombres para la misma cosa
cuestan más que un guion bajo.

### El doble vínculo, que es el fallo caro

`Implantacion::sinTrabajo()` mira `implantacion_tarea`. Una acción correctiva colgada **sólo** de la no
conformidad no está ahí, así que el plan de adecuación imprimiría «sin trabajo planificado» sobre una
medida que sí lo tiene — en la tabla que la dirección mira seguro. Por eso
`VincularAccionCorrectiva::vincular()` ata los dos vínculos, y cuatro precisiones:

1. **Vive en la acción de dominio y no en el controlador.** Si sólo lo hiciera el formulario de alta,
   la tarea que alguien vincule más tarde desde la ficha no lo tendría y el falso positivo volvería por
   la otra puerta.
2. **Cubre una parte de los casos.** Hace falta hallazgo **con punto de checklist**: ahí hay medida
   detrás. Una no conformidad suelta, o de un hallazgo sobre el sistema de gestión, no tiene a qué
   apuntar, y forzarla contra una implantación arbitraria es el vicio que `OrigenTarea::Propia` existe
   para evitar.
3. **No depende del estado de la auditoría.** Escribir en la pivote no pasa por el trigger de
   inmutabilidad —que blinda la checklist y los hallazgos, no lo que cuelga de ellos—, y es lo
   correcto: las no conformidades se tratan **después** de cerrar. Pero es donde uno espera un error,
   así que hay test.
4. **Desvincular no suelta el vínculo con la medida.** No hay forma de saber si lo puso esto o una
   persona desde la ficha de la implantación, y quitarlo a ciegas borraría trabajo planificado a mano.

Ninguna de las trece cifras que cuentan tareas se mueve al vincular: todas cuentan filas de `tareas` y
esto no crea ninguna. Lo que sube es el total del plan de adecuación, porque trabajo que era invisible
pasa a estar presupuestado. `Coste::total()` sigue contando cada tarea una vez.

**`OrigenTarea::NoConformidad` no está en § 4.7 y es el que de verdad usa una auditoría.** La
especificación enumera «hallazgo», y una tarea no cuelga nunca de un hallazgo: cuelga de la no
conformidad que lo trata. `Hallazgo` sigue declarado y sin ofrecerse, y desde el § 4.13 **por otro
motivo** —no es que falte su módulo, es que hay un eslabón por medio—; esa frase estaba escrita en el
enum y pasó a ser falsa en cuanto llegó el § 4.12.

**El origen de una acción correctiva se pone, no se pregunta**, como en `/tareas/crear?implantacion=`:
preguntarlo invita a cambiarlo. `AbrirAccionCorrectiva` es además el único camino que produce tareas
con ese origen, y vive en `Domain\NoConformidad` y no en `Domain\Tarea` por la dirección de la
dependencia: este módulo sabe de tareas, y el plan de acción no tiene por qué saber de no
conformidades.

**Un hallazgo se trata una vez**, y lo impone un índice único sobre `hallazgo_id`. Sin él, «hallazgos
sin tratar» dependería de cuál de las dos filas se mirase. En PostgreSQL los nulos son distintos entre
sí, así que el mismo índice deja pasar todas las no conformidades sueltas que hagan falta. Y el
hallazgo va con `nullOnDelete` y no con cascada, a diferencia de casi todo el módulo: borrar el
hallazgo de una auditoría abierta no puede llevarse por delante la prueba de que se trató.

**`Tarea\Plazo` tiene desde aquí un segundo cliente**, y por eso la regla se extrajo a `Plazo::para()`:
una no conformidad también es «algo abierto con una fecha para cuándo», y una segunda copia de
«vencida en rojo, sin plazo en gris» es cómo se acaba con dos pantallas que discrepan. Se queda en
`Domain\Tarea` porque es donde nació; si llega un tercer contexto, se mueve al lado de `Indicador`.

**El rojo de este registro es de «Fuera de plazo» y de «Sin verificar»**, y ninguno de los estados.
La gravedad la lleva el tipo del hallazgo —`TipoHallazgo::NcMayor` sí es rojo— y pintar de rojo el
estado dejaría el registro entero en rojo por estar haciendo su trabajo. «Sin verificar» lo gasta
porque es la cláusula 10.2 e) sin hacer, y es el paso que el auditor comprueba **precisamente porque es
el que todo el mundo se salta**.

### En el panel (§ 4.14)

**Dos bloques nuevos, y los dos contestan a la misma pregunta por separado.** La tarjeta de no
conformidades dice qué se rompió y si se arregló; el reparto por origen del plan de acción dice de
dónde sale el trabajo que hay abierto. Van detrás del cumplimiento y del plan por el mismo orden de
siempre: qué falta → quién lo está haciendo → qué se rompió por el camino.

**«Sin verificar» sube al panel, y es la única cifra del módulo que está por la norma y no por la
pantalla.** Una no conformidad cerrada y sin verificar se lee como resuelta y no lo está, y la cláusula
10.2 e) es el paso que el auditor comprueba **precisamente porque es el que todo el mundo se salta**.
Va en rojo, en la misma línea que lo vencido.

**Sin porcentaje de cerradas**, como el plan de acción no lleva porcentaje de tareas hechas: esa cifra
sube al cerrar y baja al registrar una nueva, así que castigaría por auditar bien.

**El reparto por estado de este módulo sí incluye los estados cerrados**, a diferencia del de tareas,
que sólo cuenta lo abierto. La pregunta es otra: en tareas es «en qué punto está lo que queda» y aquí
es «cuántas de las que hemos encontrado hemos llegado a verificar». Sin `verificada` en la barra, la
única cifra que pide la norma no se vería.

**`OrigenTarea::tono()` devuelve tres tonos para siete orígenes, y no hay familia `origen:*`.** Era lo
previsto y no sale: siete colores distinguibles no existen en la paleta —los únicos siete medidos son
los `--tipo-*`, y un origen no es un tipo de activo— y el reparto se pinta con `GraficaBarras`, donde
**cada barra lleva su etiqueta escrita**. Con el nombre al lado el color no tiene que identificar, así
que dice lo que de verdad se mira: **ámbar lo reactivo** —hallazgo, no conformidad, incidente—, **azul
lo planificado** —brecha, riesgo, revisión por la dirección— y **gris la iniciativa propia**. Un plan
que es casi todo ámbar es una organización apagando fuegos; un arcoíris de siete colores no contesta
eso. Y ninguno gasta rojo: una acción correctiva no es un incumplimiento, es lo que hay que hacer con
uno.

**La tarjeta no se manda a quien no tiene `no_conformidades.ver`.** Conectar dos módulos abre una
puerta lateral al registro del otro sin que nadie la decida — misma regla que el bloque de riesgos de
la ficha de un activo. Hoy los tres roles del § 4.19 lo tienen, así que la guarda no la ejerce nadie;
el test la comprueba quitándole el permiso **al rol** y no al usuario, porque `revokePermissionTo`
sobre la persona no quita lo que hereda y el test pasaría por el motivo equivocado.

**Lo que este módulo declara que no hace todavía**, y está escrito además en la limitación de la DdA:
el informe de auditoría interna como documento generado, el programa anual de auditoría, comprobar que
el alcance auditado cubra lo exigible, y la cuarta `Fuente` del calendario de obligaciones —la
`fecha_prevista` de una no conformidad vence el mismo día que sus acciones correctivas, y el calendario
pintaría tres chips para un solo compromiso—.

**Y el § 4.17 se queda a un tercio.** El flujo de conformidad de categoría básica son tres pasos
—autoevaluación → Declaración de Conformidad → publicación del distintivo— y de esos existe el primero:
`TipoAuditoria::Autoevaluacion` con su checklist y sus hallazgos. La Declaración de Conformidad es un
cuarto documento calculado y el distintivo es un trámite ante el CCN; ninguno de los dos entra aquí. Se
declara por escrito, que es lo que este proyecto hace con lo que aún no puede afirmar.

---

## El contexto de la organización

§ 4.1, y las cláusulas 4.1 a 4.3 de ISO. Era el único de los diecinueve módulos **sin fase asignada**
y el que bloqueaba al § 4.15: la cláusula 9.3 pide «cambios de contexto» como entrada obligatoria de
la revisión por la dirección, y hasta aquí no había de dónde sacarla. Vive en `app/Domain/Contexto/`,
con siete tablas —`analisis_contexto`, `cuestiones_contexto`, `partes_interesadas`,
`requisitos_interesados` y tres pivotes—.

**La 4.3 ya estaba hecha y no se ha tocado.** `sistemas.alcance_declarado` y
`sistemas.exclusiones_justificadas` existen desde la primera migración, están en el formulario y ya se
imprimen en la portada de los cuatro documentos. Lo que este módulo les añade no es una tabla: es
**histórico**, congelándolas en la instantánea del análisis. Y por eso la pantalla las **enseña y no
las edita**: repetir el campo sería el mismo dato en dos sitios que pueden discrepar.

**Choca de nombre con `ContextoOrganizacion`**, que es la pieza de multi-tenancy, y es el mismo caso
que obligó a separar `Domain\Traza` de `Domain\Auditoria`. Aquí no se renombra nada: aquella clase
nunca se nombra `Contexto` a secas, vive en `Domain\Organizacion`, y mover la pieza más sensible del
aislamiento por una colisión conceptual sale mucho más caro que anotarla.

### El análisis es lo versionado; las cuestiones viven

Es la decisión que da forma al resto. Copiar el DAFO entero en cada revisión —el patrón literal de
`riesgo_valoraciones`— **rompería los tres vínculos** que dan sentido al módulo: un riesgo apuntaría a
la cuestión de marzo y en octubre apuntaría a una fila muerta. Así que las cuestiones y las partes
**viven**, con `analisis_alta_id` y `analisis_baja_id`, y lo que se congela es la **instantánea** del
análisis al aprobarse.

Sin ella la fila mentiría en cuanto alguien editara una cuestión, que es el mismo razonamiento ya
escrito para `riesgo_valoraciones.salvaguardas` y para `documento_versiones.instantanea`. Y es lo que
le da histórico al alcance sin migrar nada.

**`vigente` no es columna: es `estado = 'aprobado'`**, con índice único parcial, como el borrador de
un documento. Una bandera aparte sería el mismo dato dos veces.

**El borrador se estrena solo** al registrar la primera cuestión. Obligar a «abrir un análisis» antes
de poder escribir una debilidad pone un trámite delante del primer minuto de uso, y lo que la gente
hace entonces es apuntar el DAFO en otro sitio. Aprobar sí es explícito: es lo que congela.

**Y el borrador no se descarta**, a diferencia del de un documento. No es una propuesta que se pueda
tumbar: es donde se trabaja, y lo que ya está aprobado sigue vigente mientras tanto.

**El cuarto trigger de inmutabilidad** del producto, hermano de los de `documento_versiones`,
`riesgo_valoraciones` y `auditorias`. Una sola puerta —`aprobado → obsoleto`—, que pone el sistema al
aprobar el siguiente y nunca una persona; sin ella un contexto aprobado no podría revisarse nunca.
Se compara el registro entero con `estado` neutralizado, y `updated_at` **sólo** cuando el estado
cambia, por lo mismo que en `documento_versiones`.

### El cambio climático es una pregunta, no una casilla

La enmienda 1:2024 no pide apuntar cuestiones climáticas: pide **determinar si** el cambio climático
es pertinente. Con una casilla suelta, «no lo hemos mirado» y «lo hemos mirado y no aplica» serían
indistinguibles — que es justo lo que el auditor pregunta. De ahí `clima_pertinente` +
`clima_justificacion` y **un `CHECK` que impide aprobar sin contestar**, en la misma línea que
`rechazado` exige motivo. Las cuestiones llevan además `es_climatica`, que es lo que permite enseñar
cuáles son en vez de sólo afirmarlo.

### Lo derivado y lo guardado

**El ámbito y el signo de una cuestión NO son columnas**: una fortaleza es interna y favorable por
definición del DAFO, y guardarlo sería la misma información en tres sitios que pueden discrepar.
Reclasificar una cuestión es cambiar un campo y no acordarse de tres.

**En una parte interesada el ámbito SÍ se guarda**, y esa asimetría es real: un empleado es interno y
un regulador externo, pero un socio o un accionista son lo que cada organización decida. Deducirlo
acertaría en seis casos de ocho, que es la peor cifra posible — suficiente para que parezca que
funciona. `TipoParteInteresada::ambitoSugerido()` lo propone y el formulario lo rellena; no lo impone.

**`Ambito` es un solo enum para las dos cláusulas.** La 4.1 habla de cuestiones internas y externas y
la 4.2 de partes que también lo son: es literalmente el mismo eje, y con dos enums nadie podría
preguntar «¿qué tenemos de fuera?» sin cruzar dos vocabularios que dicen lo mismo.

**La otra clasificación de una cuestión se llama `materia` y no `ambito`**, precisamente porque
`ambito` ya es el interno/externo. Dos columnas con el mismo nombre y dos ejes distintos es cómo se
acaba filtrando por lo que no era.

### Un tono por cuadrante, en familia propia: `--dafo-*`

**Tercera familia semántica**, junto a `--estado-*` y `--tipo-*`, declarada en `DESIGN.md` §3. Un
estado dice *cómo va* algo, un tipo dice *qué es* y un cuadrante dice *dónde cae*; las tres preguntas
conviven en el panel y compartir paleta haría que una fortaleza se leyera como «implantado».

**Lo que la separa no es el hue, es la profundidad**: L 0.40 y croma 0.16, frente a L 0.52 / 0.13 de
los estados y L 0.47 / 0.10 de los tipos. No es estética: **los nueve `--tipo-*` ocupan ya la rueda de
hue entera**, y una familia que sólo se moviera de tono no se leería como familia. Se midió antes de
elegir: con los tipos dentro del suelo, ningún cuádruple de hues llegaba a ΔE 6 — bajar la
luminosidad es lo que abre el hueco.

**Los hues van por pares y no sueltos**, que es lo que hace legible un 2×2: verde 175 y azul 255 lo
favorable —dentro y fuera—, ocre 55 y magenta 340 lo adverso. El color dice las dos cosas a la vez:
de qué mitad es, por la temperatura, y qué cuadrante exacto, por el tono.

**Ninguno entra en el rojo**, que conserva sus tres dueños. Una debilidad apuntada en un análisis no
va mal: es algo que la organización ha sabido ver y escribir, y pintarla de alarma enseña a no
escribirla.

**Y el icono sigue sin ser opcional.** ΔE 10.7 en el peor par de la familia —el mejor de las tres—,
pero contra `destructive` la peor pareja queda en **2.7** con protanopía: un verde oscuro y un rojo
colapsan sobre el mismo eje. Es la convivencia que la paleta ya tenía —`destructive ↔ tipo-soportes`
está en 3.6— y la cargan el icono y el texto. Lo mide `PaletaTest`, que lee `app.css` y no la tabla
del documento.

`NaturalezaRequisito` usa la familia **ordinal** (`alta`/`media`/`basica`), que es lo que es: un
requisito legal obliga más que uno contractual y ése más que una expectativa. Es la solución que
`DESIGN.md` ya documentó para la categoría del ENS, donde el rojo mentía.

**`MatrizDafo.vue` es rejilla CSS y no SVG**, calcada de `MatrizRiesgo.vue` y por lo mismo: tiene que
caber a 375 px sin scroll horizontal. Los rótulos de los ejes viven **dentro** de la misma rejilla, y
cada cuadrante los repite escritos para que al apilarse por debajo de `sm` no se pierdan.

### Los tres vínculos, que son lo que paga el módulo

- **Cuestión ↔ riesgo**, N:M. Es lo que la cláusula 6.1.1 pide cuando dice que la apreciación de
  riesgos se hace **considerando** las cuestiones del 4.1. **Se vincula, no se crea**: deducir un
  riesgo de una amenaza produciría riesgos sin probabilidad, sin impacto y sin propietario, que es lo
  que ISO 6.1.3 f) no admite.
- **Cuestión ↔ tarea**, con `OrigenTarea::Contexto` —séptimo origen, y el segundo que no está en
  § 4.7—. El origen **se pone y no se pregunta**. **Sin doble vínculo**, a diferencia de la acción
  correctiva: allí hacía falta porque el plan de adecuación imprimía «sin trabajo planificado» sobre
  una medida que sí lo tenía, y aquí no hay medida detrás **por construcción**. La consecuencia,
  declarada: el coste de esa tarea no entra en el presupuesto del plan, porque ese plan presupuesta
  medidas.
- **Requisito de una parte ↔ implantación**, que apunta a `implantaciones` y no a `requisitos` por lo
  mismo que las salvaguardas. Es el que paga: a partir de él la SoA imprime **«exigido por el
  regulador X»** como justificación de inclusión, al lado de «Anexo A» y de «tratamiento del riesgo
  R-014». ISO 6.1.3 d) admite las tres. **Sólo lo que obliga** —legal y contractual—: una expectativa
  es razón para tener en cuenta un control, no para declararlo aplicable.

### El documento, y la frontera que rompe

`TipoDocumento::AnalisisContexto` es el **cuarto calculado** y el primero de **ámbito organizativo**.
Hasta él, «calculado» y «exige sistema» eran lo mismo por accidente, y ese accidente era el motor de
`documentos_sistema_check`. Ahora lo es **`TipoDocumento::exigeSistema()`**, y `esRedactado()` se
queda diciendo lo único que dice: quién escribe el contenido. Es la **tercera** reescritura de ese
`CHECK`, y la migración anterior pedía por escrito que no se tocara sin motivo — éste es el motivo.

`AnalisisDelContexto` implementa `GeneradorDocumento` **directamente**, como `DocumentoRedactado`: no
tiene tabla larga de requisitos, ni agrupado por nodo padre, ni correspondencias cruzadas. Lo común
—portada, historial y limitaciones— sale de `ArmaContenidoComun`.

**Se construye desde la instantánea y nunca de una consulta nueva.** Es el fallo más caro que este
módulo podía tener, el mismo que ya está documentado para el `.docx`: las cuestiones se siguen
editando entre revisiones, así que consultar las tablas enseñaría el DAFO de hoy bajo la fecha de la
aprobación de hace un año. Sin análisis aprobado no hay documento, y se dice.

Cuatro fuentes nuevas de cuerpo —`declaracion_climatica`, `dafo_cuadrantes`,
`tabla_partes_interesadas` y `alcance_sistemas`—, y **el clima va primero**: es una respuesta de una
línea, es lo que la enmienda añadió y es de lo primero que un auditor busca desde 2024; enterrada
detrás de dos tablas se lee como que no está. En papel el DAFO son **cuatro tablas y no una rejilla**:
un cuadrante con doce cuestiones partiría el 2×2 a mitad de página.

**Lo que el módulo declara que no hace**: no comprueba que el análisis esté completo —ni que toda
parte interesada relevante esté registrada, ni que las cuestiones cubran todos los ámbitos, ni que
cada amenaza acabe en un riesgo—; no comprueba que un requisito de una parte exista de verdad ni que
la medida vinculada lo satisfaga; no contrasta los alcances de los sistemas entre sí; y **no hay
pantalla de diff** entre dos instantáneas —la comparativa dice qué entró y qué salió, no qué cambió
por dentro de una cuestión que sigue en las dos—.

**Y no entra una cuarta `Fuente` en el calendario.** Lo que vence no es el análisis sino la revisión
de su documento aprobado, que `Fuente::Documento` ya recoge desde el § 4.5; una `Fuente` propia
pintaría dos chips el mismo día para un solo compromiso. Es el argumento exacto por el que la
`fecha_objetivo` del plan de adecuación se quedó fuera.

**Ninguna limitación existente pasó a ser falsa con este módulo dentro** —comprobado: ni «contexto»,
ni «partes interesadas», ni «4.1» aparecían en ninguna—. Es la primera vez en cinco módulos. La única
que sí se reescribió es la de la justificación de inclusión de la SoA, porque enumeraba los orígenes
posibles y se quedó corta en cuanto la columna empezó a imprimir uno más.

---

## Los indicadores y las mediciones

§ 4.14 y la cláusula 9.1. El panel lleva desde el principio contando cosas —cumplimiento,
inventario, plan de acción, no conformidades, contexto— y **todas esas cifras son de hoy**. La 9.1 no
pide una cifra: pide qué se mide, con qué método, **cada cuánto**, quién lo mira y **contra qué
objetivo**. Sin la serie, «¿ha mejorado esto desde la última revisión?» —que es literalmente lo que
pregunta la 9.3— se contesta con un encogimiento de hombros.

Vive en `app/Domain/Metrica/`, con dos tablas: `indicadores` y `mediciones`.

**El contexto se llama `Metrica` y el modelo `Indicador`, y eso choca con
`App\Http\Resources\Panel\Indicador`**, que es otra cosa: aquél es «una cifra que pide acción, con el
camino para ir a verla», la baldosa que nació en el inventario y se generalizó a cualquier módulo con
tabla. Es el caso de `Contexto` frente a `ContextoOrganizacion` y se resuelve igual: **no se renombra
nada**, se anota, y el único fichero donde conviven —`RegistroIndicadores`— importa uno con alias.
Renombrar el VO tocaría seis resúmenes de panel, los tipos generados y `TiraIndicadores.vue` para
ganar cero. El **espacio de nombres** sí se eligió para no tartamudear: `Domain\Indicador\Models\Indicador`
era peor que `Domain\Metrica\Models\Indicador`, y § 4.14 se titula «Métricas».

### La medición se sella, y con su objetivo al lado

Es la decisión que da forma al resto. **«Calculado» no quiere decir «se consulta al mirarlo»**:
quiere decir que el sistema **propone** la cifra al cerrar el periodo y la guarda con su fecha. Una
serie que se recalcula reescribiría marzo en octubre — el argumento literal de
`riesgo_valoraciones.salvaguardas`, `documento_versiones.instantanea`, la exigencia congelada al
cerrar una auditoría y la instantánea del análisis del contexto. Cuatro precedentes, y éste es el
quinto sitio donde una consulta en vivo mentiría sobre el pasado.

**Y se sella con su objetivo dentro** (`mediciones.objetivo`). Sin él, subir el listón en marzo
reescribiría el veredicto de enero: lo que estuvo en objetivo pasaría a figurar como fallado y nadie
sabría por qué. El objetivo vigente vive en el indicador; el que se aplicó, en la fila. **Corregir una
cifra no mueve el objetivo**, y eso lo garantiza `RegistrarMedicion`: arreglar un dígito mal tecleado
en abril no puede cambiar contra qué se juzgó marzo.

**`mediciones.origen` no es una copia de `indicadores.origen`.** El del indicador es la política de
hoy; el de la fila es el hecho de cómo se obtuvo **aquélla**. Pasar un indicador de calculado a manual
no puede reescribir cómo se tomó la medición de marzo. Mismo reparto que la exigencia congelada en
`auditoria_puntos`.

**Y aun así no lleva trigger de inmutabilidad**, a diferencia de los cuatro registros que sí lo
llevan. Una medición no la firma nadie y no se entrega sola a un auditor; lo que se congela es el acta
de la revisión por la dirección que la cita. Blindarla aquí haría imposible corregir un dedazo en una
medición manual —el caso ordinario— sin proteger nada que no esté ya protegido. La frontera del módulo
es otra: **derivar en silencio, prohibido; corregir con autor y traza, permitido**.

### El cálculo es un catálogo cerrado, no una fórmula

§ 2.2 dice «formula_o_fuente» y la tentación es una columna de texto con `(implantadas / aplicables)
* 100` dentro y un intérprete detrás. No entra, por dos motivos y el segundo pesa más: un evaluador de
expresiones en una herramienta que está en el alcance de su propio SGSI es superficie de ataque a
cambio de nada, y **cada caso de `CalculoIndicador` llama al scope o al resumen que ya existe**
—`Implantacion::pendientes()`, `Evidencia::caducadas()`, `NoConformidad::pendientesDeVerificar()`—,
nunca reescribe la condición. Es lo mismo que hace `Filtro::porScope()`, y es lo que garantiza que el
indicador, la cifra del panel y la lista que sale al pulsarla digan el mismo número.

`CalculosTest` **se parametriza solo**: recorre `CalculoIndicador::cases()` y sella cada uno, así que
un cálculo nuevo entra sin que nadie toque el fichero. Y lo que comprueba no es que no lance, es que
la cifra **entra en la tabla**: un `Medida` con numerador y sin denominador lo rechaza un `CHECK`, y
ese rechazo aparecería meses después en el comando de las siete y media de la mañana.

**Las dos mitades de «formula_o_fuente» son dos columnas**, `calculo` y `formula_o_fuente`, cada una
obligatoria exactamente cuando la otra sobra. El indicador **manual** existe igual y no es una
concesión: «porcentaje de personal formado» no sale de esta base de datos mientras el § 4.8 no exista,
y un módulo de métricas que sólo admitiera lo que ya sabe contar dejaría fuera justo lo que cuesta
medir.

### Lo derivado y lo declarado

**El cumplimiento no es una columna**: sale de (`valor`, `objetivo`, `sentido`) cada vez que hace
falta. Guardarlo sería el mismo dato en dos sitios, como `vigente` en el análisis del contexto.

**`sentido` es obligatorio aunque `objetivo` sea opcional.** «Tareas vencidas ≤ 5» y «cobertura de
cifrado ≥ 90 %» se juzgan al revés, y sin la columna el veredicto sale invertido en la mitad de los
indicadores: un cuadro de mando que felicita por subir las no conformidades vencidas se deja de mirar
el mismo día.

**Cuatro veredictos y no dos.** «Sin objetivo» y «sin medir» no son «fuera de objetivo», que es el
argumento de `EstadoControl::PorConfirmar`: vigilar algo sin comprometerse a una cifra sigue siendo
seguimiento, y un periodo sin medir es una pregunta abierta. Colapsarlos daría un panel en rojo el día
que se crea el primer indicador.

**La regla está escrita dos veces y hay test.** `Indicador::scopeFueraDeObjetivo()` la aplica
PostgreSQL sobre miles de filas y `SentidoIndicador::alcanza()` decide el badge de una; no hay forma
de tener una sola. Es el caso de `ValoracionEfectiva`, que tiene dos entradas y un test que fija que
coinciden. Aquí es `Metricas/CumplimientoCoincideTest`, que recorre la matriz de los dos sentidos por
encima, por debajo y justo en el umbral.

### El rojo es de no medir, no de quedarse corto

**Ningún veredicto gasta rojo**, y conviene decir por qué, porque `NivelRiesgo::MuyAlto` sí lo gasta
con un argumento que parece el mismo. Estar por debajo de un objetivo **es la distancia que queda**:
pintarlo de alarma castiga por ponerse objetivos ambiciosos, que es exactamente lo que el quinto
principio del producto existe para impedir —«un indicador que castiga por apuntar lo que falta enseña
a no apuntarlo»—. Un riesgo por encima del umbral crítico no es una distancia: es una exposición que
la organización ya declaró inaceptable.

Lo que sí va mal de verdad es **un periodo que cerró sin medición** habiéndose comprometido a medirlo:
eso es la cláusula 9.1 sin hacer, y es lo primero que un auditor comprueba. Es el único rojo del
módulo y lo lleva la columna «Periodo», no el estado — mismo reparto que en tareas, donde el rojo es
del plazo y no del estado.

### Un periodo, no una fecha

`Periodicidad` **parte el calendario en cubos**, y por eso no se reutiliza `Evidencia\PeriodicidadRenovacion`
aunque la palabra sea la misma y cuatro casos coincidan: aquélla **suma meses a una fecha** —«esta
captura caduca el 3 de junio»— y ésta contesta «el 3 de marzo cae en el primer trimestre». Falta
`Bienal` a propósito: existe allí por la conformidad del ENS, y a esa cadencia no hay serie, hay dos
puntos. El motivo de fondo es la dirección de la dependencia: compartirla haría que `Domain\Metrica`
supiera de `Domain\Evidencia` y ofrecería «bienal» en un cuadro de mando.

De ahí que `mediciones` tenga **`periodo_inicio`, `periodo_fin` y `medida_en`**: a qué pertenece el
dato y cuándo se tomó no son lo mismo, y una medición de marzo apuntada en abril desordenaría la serie
si se ordenara por `created_at`. El par `(indicador, periodo_inicio)` es único: medir dos veces el
mismo periodo es **corregir, no acumular**.

**El formulario pide una fecha cualquiera, no dos extremos.** Dejar escribir los dos permitiría sellar
«del 3 de marzo al 7 de abril», que no es ningún trimestre, y la serie tendría puntos que no encajan
con los demás. Y **no se sella el periodo en curso**: una cifra a medias habría que corregirla al día
siguiente.

### El marco, y lo que no se deja acotar

`indicadores.marco_id` es opcional y sólo lo admiten los cuatro cálculos que cuelgan de
`implantaciones`. § 4.14 pide «porcentaje de implantación **por marco**» con esas palabras, y es la
pregunta del producto: ISO y el ENS avanzan a ritmos distintos y una sola cifra los promedia hasta que
no dice nada. Las evidencias, las tareas y los activos **no se acotan**: son de la organización entera
y sirven a los dos marcos a la vez (invariante 6), así que repartirlos los contaría dos veces o los
dejaría fuera de uno. Lo rechaza el `FormRequest` y lo declara `CalculoIndicador::admiteMarco()`.

### El comando, y dónde se rompe

```sh
php artisan indicadores:medir              # sella el periodo cerrado de cada indicador calculado
php artisan indicadores:medir --dry-run    # enseña la cifra que saldría, sin escribir
php artisan indicadores:medir --fecha=…    # toma otro día como «hoy», para cerrar antes
```

**Mismo cuidado que `avisos:enviar` y por lo mismo**: un comando programado no tiene petición ni
usuario, así que sin contexto el scope no devuelve nada y RLS deniega por defecto. **No falla, no ve
nada**, y una serie sin puntos es indistinguible de una organización que no mide. De ahí
`ContextoOrganizacion::paraOrganizacion()`, una organización cada vez. Nada de `withoutGlobalScopes()`.

Va **diario y no mensual**, aunque el periodo más corto sea el mes: el comando mira qué periodo ha
cerrado y sella sólo lo que falte, así que correrlo todos los días es idempotente y correrlo una vez
al mes deja la serie con un agujero en cuanto el planificador se pierda un día. Misma disciplina que
el importador del catálogo. Y **no toca los manuales ni los retirados**: sellar un cero en su nombre
sería inventarse la medición.

### La gráfica, y la librería que no entró

`CLAUDE.md` dejó la puerta abierta a `d3-scale` y `d3-shape` «el día que haya una serie histórica con
eje de tiempo». **Ese día llegó y la puerta sigue cerrada**, con motivo: el eje de esta serie no es
tiempo continuo, son **cubos etiquetados y equiespaciados** —«T1 2026», «T2 2026»— que impone
`Periodicidad`. Lo que `d3-scale` compra es elegir ticks legibles sobre un eje continuo, y aquí los
ticks vienen escritos de casa. La puerta se queda abierta para el día que haya una serie con fechas
irregulares.

`GraficaSerie.vue` es **SVG y no rejilla CSS**, al revés que `MatrizRiesgo` y `BarraSegmentada`: aquí
sí hay una línea que dibujar entre puntos, que es el caso de `AnilloProgreso`. Las etiquetas viven
**fuera** del SVG, en HTML, porque con `preserveAspectRatio="none"` el texto se deformaría con la caja
y a 375 px tienen que poder envolver. **La línea de objetivo va de puntos y no de color**: si sólo la
distinguiera el tono, con protanopía sería otra serie más.

**La escala arranca en cero salvo que la serie no lo toque nunca.** Una serie de madurez que va de 3,1
a 3,4 dibujada desde cero es una recta plana que no dice nada; una de porcentajes que empieza en su
mínimo exagera dos puntos hasta que parecen un despegue. Se recorta el eje sólo cuando lo primero no
distingue nada.

### `resolveChildRouteBinding()`, por segunda vez en el producto

`scopeBindings()` deduce la relación pluralizando el nombre del parámetro **en inglés** —`medicion` →
`medicions`— y aquí el dominio se nombra en español. Sin escribirlo a mano, `/indicadores/{indicador}/mediciones/{medicion}`
responde 500 con un «Call to undefined method» que no menciona ni la ruta ni la relación, y de paso
deja de acotar: la medición de otro indicador se borraría desde éste. El precedente exacto es
`Documento::resolveChildRouteBinding()`, y **lo cazó un test de aislamiento, no una revisión**, igual
que allí.

### Dos verbos y no tres

`indicadores.ver` e `indicadores.gestionar`. **En este módulo no hay nada que firmar**: una medición
es un dato que se toma, no una decisión que alguien aprueba, y quien está en el día a día es quien
sabe de dónde sale la cifra. Por eso el técnico define indicadores y los mide. El verbo de supervisión
de este ciclo es `objetivos.aprobar`, y llegó con la 6.2: comprometerse a una cifra sí se firma.

### Lo que este módulo declara que no hace todavía

- **No entra en el calendario de obligaciones.** El periodo que cierra sin medir es el rojo del
  módulo y hoy sólo se ve en la tabla, en el panel y en la ficha: `Aviso\Fuente` sigue con sus tres
  casos, así que ni el aviso diario ni la vista de mes lo recogen. Es trabajo aparte, y va declarado.
- **No comprueba que lo que se mide cubra lo que hay que medir.** La 9.1 a) pide determinar qué
  necesita seguimiento; Statera registra lo que se declare y no dice si falta algo.
- ~~**No vincula indicadores con objetivos de seguridad**~~. Lo hace desde la 6.2, y la pivote es la
  N:M que aquí se dejó anunciada: `indicador_objetivo`.
- **Una media se registra sin numerador**, con sólo el denominador al lado. Es correcto —«3,2 sobre 48
  requisitos valorados»— y por eso el `CHECK` es asimétrico: un numerador exige denominador, pero no
  al revés.
---

## Los objetivos de seguridad

Cláusula 6.2, y la primera de las dos entradas que le faltaban a la 9.3. El § 4.14 dejó el producto
**midiendo**; esto es a lo que la organización **se compromete**. Son dos cosas distintas y la norma
las pide las dos: un cuadro de indicadores sin objetivos contesta «¿cómo va?» y no contesta «¿va
bien?».

Vive en `app/Domain/Objetivo/`, con cuatro tablas: `objetivos_seguridad`, `indicador_objetivo`,
`objetivo_tarea` y `objetivo_transiciones`.

**Va después del § 4.14 y eso da forma a la tabla.** De las cinco cosas que la 6.2 pide de la
planificación de un objetivo, dos ya existían en el producto y no se escriben a mano:

| 6.2 | Dónde |
|---|---|
| Qué se hará (a) | `objetivo_tarea`, N:M — son **tareas** |
| Qué recursos (b) | `recursos`, texto libre |
| Quién responde (c) | `responsable_id` |
| Para cuándo (d) | `fecha_objetivo`, **exigida al aprobar** |
| Cómo se evalúan los resultados (e) | `indicador_objetivo`, N:M — son **indicadores** |

**«Cómo se evaluarán los resultados» ES un indicador**, y por eso el § 4.14 fue antes: al revés, el
objetivo nacería con el campo que el auditor más mira y nada detrás. La N:M estaba **anunciada por
escrito** al cerrar el § 4.14 —«un indicador evalúa varios objetivos y un objetivo necesita varios»—
y es real: «porcentaje de implantación del ENS» evalúa a la vez el objetivo de adecuación y el de
madurez.

**Lo que sí es columna es «qué recursos»**, y es texto libre y no una cifra: no se deduce del coste
de sus tareas, porque hay objetivos que se cumplen con horas de gente que ya está y una cifra a cero
se leería como «no hace falta nada» en vez de como «no cuesta dinero».

### Un borrador se escribe como se pueda; un compromiso no

Es la regla del módulo, y son **dos `CHECK`**: el plazo y la firma son obligatorios exactamente en
los tres estados comprometidos —`aprobado`, `alcanzado`, `no_alcanzado`— y no en `propuesto` ni en
`retirado`. Obligar la fecha en el formulario impediría apuntar la idea el día que se tiene, que es
cuando la gente la apunta; no exigirla nunca dejaría pasar un compromiso sin plazo, que es una
consigna. Por eso está en los dos sitios que corresponden: **opcional al escribir, obligatoria al
firmar**, y la comprobación vive en `CambiarEstadoObjetivo` y no sólo en el `FormRequest`, porque la
regla vale también para un importador.

**`retirado` no exige firma a propósito**: se puede retirar un objetivo que nunca llegó a aprobarse,
y rellenarle el firmante sería fabricar una aprobación que nadie dio. Es el mismo argumento por el
que el `CHECK` de la firma de `documento_versiones` no alcanza a `obsoleto`.

**Aprobar no reescribe quién firmó.** Reabrir un objetivo cerrado conserva el firmante y la fecha
originales —`$objetivo->aprobado_por_id ?? $usuario?->id`—, igual que corregir una medición no mueve
el objetivo sellado contra el que se juzgó su periodo. Lo contrario: **volver a `propuesto` suelta la
firma entera**, porque un objetivo que vuelve al borrador ya no está aprobado y dejar puestos el
firmante y la fecha sería enseñar una aprobación que ya no consta. No se pierde nada: el histórico la
conserva.

**`alcanzado` y `no_alcanzado` son dos estados y no un `resultado` al lado de un `cerrado`**, por lo
mismo que `Verificada` en una no conformidad: «cuántos de los objetivos del año se alcanzaron» es
literalmente una de las siete entradas de la 9.3, y con el resultado en otra columna esa cifra
dependería de cruzar dos campos que pueden desincronizarse.

**Tres transiciones exigen motivo escrito**, y la del medio es la que paga el módulo: retirar —«esto
ya no lo perseguimos»—, **dar por no alcanzado** —«por qué» es lo que la revisión por la dirección va
a preguntar del año que termina, y sin texto el acta diría «tres de cinco» sin poder explicar ni
uno— y reabrir desde algo cerrado.

### El séptimo verbo de supervisión

`objetivos.aprobar`, junto a `sistemas.valorar`, `riesgos.aceptar`, `documentos.aprobar`,
`contexto.aprobar` y `no_conformidades.verificar`. El § 4.14 se quedó a propósito con dos verbos
—una medición es un dato que se toma, no una decisión que se firma— y **lo anunciaba por escrito**;
éste es ese verbo. Cubre aprobar, declarar el resultado y **retirar**, porque retirar es renunciar a
un compromiso adquirido. El técnico propone y planifica; no firma.

### Lo derivado y lo declarado

**El veredicto lo declara una persona; lo derivado se enseña al lado y no lo sobrescribe nunca.** Al
cierre, quien firma decide si el objetivo se alcanzó; `Avance` dice lo que las cifras cuentan
mientras tanto. Es el precedente exacto de `ValoracionEfectiva` y del riesgo residual, y **el
invariante 4 no aplica**: aquél es una derivación legal con una respuesta correcta en el BOE, y la
6.2 no publica ninguna función de indicadores a veredicto.

Lo que sí hace la herramienta es **señalar la contradicción**: un objetivo dado por alcanzado con
indicadores medidos por debajo de su objetivo se pone delante en su ficha, y el dato no se toca. Es
el mismo papel que hacen `Riesgo::residualSinRespaldo()` y `Activo::esperaBorradoSeguro()`.

**`Avance` cuenta los que llegan sobre los MEDIDOS, no sobre el total.** «Sin objetivo» y «sin medir»
no cuentan como medidos, por el argumento de `EstadoControl::PorConfirmar`: la ausencia de dato es
una pregunta abierta. Y los dos casos vacíos se nombran aparte porque no son lo mismo —sin ningún
indicador vinculado, lo que falta es la 6.2 e); con indicadores y sin medición, la 9.1—.

### El rojo es el plazo, y no quedarse corto

**Ningún estado gasta rojo, ni siquiera `no_alcanzado`**, y es el mismo argumento que dejó sin rojo
los cuatro veredictos del § 4.14: quedarse corto respecto a una cifra que la propia organización se
puso es la distancia que queda, y pintarlo de alarma castiga por ponerse objetivos ambiciosos — el
quinto principio del producto. Lo que sí va en rojo es **un objetivo aprobado cuyo plazo pasó y que
nadie ha cerrado**: eso es la 6.2 sin terminar. Mismo reparto que en tareas y en no conformidades,
donde el rojo es de la columna «Plazo» y nunca del estado.

**`propuesto` gasta el violeta de `en_revision`, y es el tercer badge que lo hace.** Los otros dos
son la versión de un documento esperando firma y la no conformidad tratada y pendiente de verificar,
y los tres significan lo mismo: hecho y a la espera de que alguien con potestad lo confirme. **No
abre un quinto sitio para el violeta**: el token ya era uno de los cuatro.

### Sin doble vínculo, a diferencia de la acción correctiva

`VincularActuacion` ata **un solo** extremo. En el § 4.13 hacía falta el segundo porque
`Implantacion::sinTrabajo()` mira `implantacion_tarea` y el plan de adecuación imprimiría «sin trabajo
planificado» sobre una medida que sí lo tiene; aquí **no hay medida detrás por construcción** —un
objetivo de seguridad no cuelga de ningún requisito—, y atarlo a una arbitraria sería el vicio que
`OrigenTarea::Propia` existe para evitar. Mismo reparto que `cuestion_tarea` en el § 4.1.

**La consecuencia, declarada:** el coste de una actuación de objetivo **no entra en el presupuesto
del plan de adecuación**, porque ese plan presupuesta medidas del Anexo II.

**`OrigenTarea::Objetivo` es el octavo origen y el tercero que no está en § 4.7.** No se apunta a
`brecha_implantacion`, que es el que más se le parece: una brecha es una medida exigible sin
implantar, con su requisito detrás.

### `resolveChildRouteBinding()`, por tercera vez en el producto

`scopeBindings()` deduce la relación pluralizando el nombre del parámetro **en inglés** —`indicador`
→ `indicadors`— y aquí el dominio se nombra en español. Sin escribirlo a mano,
`/objetivos/{objetivo}/indicadores/{indicador}` responde 500 con un «Call to undefined method» que no
menciona ni la ruta ni la relación, y de paso deja de acotar. Los precedentes son
`Documento::resolveChildRouteBinding()` e `Indicador::resolveChildRouteBinding()`, y **lo cazó un
test de aislamiento y no una revisión**, igual que las dos veces anteriores. `tarea` no hace falta
declararla porque su plural inglés coincide con el español, que es justo lo que hace que este fallo
sea difícil de ver leyendo las rutas.

### El fallo que este módulo destapó en otro

`CodigoNoConformidad` pasaba el desplazamiento de `substring` como binding, PDO lo mandaba **como
texto** y PostgreSQL leía `substring(x from '10')` como la forma SQL estándar con expresión regular:
devolvía NULL, el máximo salía nulo y **todas las no conformidades del año se proponían como `-01`**,
chocando con el índice único a partir de la segunda. No lo cazaba nada porque el test que había sólo
comprobaba el **primer** código del año, que sale bien incluso con el contador roto. Arreglado con
`?::int` en los dos generadores y con un test de regresión que siembra dos códigos y pide el tercero.

### Lo que este módulo declara que no hace todavía

- **No entra en el calendario de obligaciones.** El objetivo vencido es el rojo del módulo y hoy sólo
  se ve en la tabla, en el panel y en su ficha. No entra una `Fuente` nueva a propósito: un objetivo
  tiene tareas detrás y sus plazos ya pintan chip, y una `Fuente` propia pintaría dos el mismo día
  para un solo compromiso. Es el argumento exacto que dejó fuera la `fecha_prevista` de una no
  conformidad y la `fecha_objetivo` del plan de adecuación.
- **No comprueba que los objetivos cubran la política de seguridad.** La 6.2 a) pide que sean
  coherentes con ella; Statera registra lo que se declare y no dice si falta algo.
- **No exige que todo objetivo tenga indicador**, lo señala. Exigirlo impediría apuntar la idea el
  día que se tiene, que es el mismo motivo por el que la fecha es opcional en el borrador.
- **No entra en ningún documento.** El acta de la revisión por la dirección es el sitio donde estos
  objetivos se leen, y ese documento llega con el § 4.15.

---

## Las oportunidades de mejora

Cláusula 10.1, «mejora continua», y la segunda mitad del capítulo 10. Es la última
entrada que le faltaba a la 9.3: con este módulo dentro, las siete entradas
obligatorias de la revisión por la dirección salen del producto.

Vive en `app/Domain/Mejora/`, con tres tablas: `mejoras`, `mejora_tarea` y
`mejora_transiciones`.

### Tabla propia, y el motivo es aritmético antes que conceptual

**No es una ampliación de `no_conformidades`.** «No conformidades abiertas» es a
la vez cifra del panel, cálculo de `CalculoIndicador` y entrada obligatoria de la
9.3; con las mejoras dentro, una idea apuntada contaría como un incumplimiento en
los tres sitios. Contar de más es el fallo caro y aquí se evita no dando la
ocasión — el mismo argumento que dejó las subtareas fuera de `tareas`.

Y la diferencia de fondo es la que hace la norma: **la 10.2 trata lo que incumple
y la 10.1 lo que se puede mejorar sin que nada incumpla**. Una tiene causa raíz y
verificación de eficacia porque algo falló; la otra no tiene nada que verificar.

Por eso la tabla es **mucho más corta**: sin `correccion_inmediata`, sin
`analisis_causa_raiz`, sin `fecha_verificacion` y sin `resultado_verificacion`.
Copiar esas cuatro columnas «por simetría» sería pedirle a quien apunta una idea
que declare la causa raíz de algo que no ha pasado.

### La bifurcación del hallazgo, cerrada por los dos lados

`TipoHallazgo::abreMejora()` y `admiteNoConformidad()` deciden a qué registro va
cada hallazgo, y **las dos puertas están en el dominio**: `RegistrarNoConformidad`
lanza `HallazgoNoTratable` si alguien intenta tratar una oportunidad de mejora
como no conformidad. Está en el dominio y no sólo en el `FormRequest` porque la
regla vale también para un importador — mismo criterio que el motivo de
`descartada` en tareas.

Y en la interfaz no se rechaza, **se redirige**: `/no-conformidades/crear?hallazgo=`
lleva a `/mejoras/crear?hallazgo=` cuando el tipo no corresponde, y al revés.
Dejar rellenar un formulario que el dominio va a rechazar al final es la forma más
cara de decir que no.

**Un hallazgo se trata una vez** en cada registro, y lo impone el índice único
sobre `mejoras.hallazgo_id`. En PostgreSQL los nulos son distintos entre sí, así
que deja pasar todas las mejoras sueltas que hagan falta — que son la mayoría: casi
ninguna mejora sale de una auditoría.

### El módulo sin rojo

**Es el único registro del producto sin `alertas()`**, y es la decisión que lo
define. Ninguna cifra de aquí va mal de verdad: una idea sin hacer no incumple
nada —la 10.1 pide mejorar de forma continua, no tener cero ideas pendientes— y
una mejora descartada es una decisión legítima. Pintar de rojo lo que alguien
apuntó voluntariamente es la forma más rápida de que deje de apuntarlo, que es el
quinto principio del producto.

Ni siquiera el plazo. `Mejora::sePasoDeFecha()` se llama así y no `haVencido()` a
propósito: nadie se comprometió a esa fecha —eso es un objetivo de la 6.2, que sí
lleva su rojo—, y el tono de la columna **rebaja `caducada` a `no_iniciado`**. Hay
un test que recorre el enum comprobando que ningún estado gasta rojo.

Lo que sí hay es `sinEmpezar`, que es la cifra honesta del registro: un buzón de
ideas al que nadie vuelve no es mejora continua.

### Dos verbos, y ninguno de supervisión

`mejoras.ver` y `mejoras.gestionar`. **Aquí no hay nada que firmar**, y es lo que
lo separa del registro de al lado: no hay eficacia que verificar porque no había
nada roto, y no hay compromiso que aprobar porque nadie se obligó. Cuando una
mejora se convierte en un compromiso, lo que nace es un **objetivo de la 6.2**, que
sí tiene su verbo. El técnico la gestiona entera, descartarla incluida.

**Una sola transición exige motivo: descartar.** Implantar no lo pide —lo que se
hizo lo cuentan sus tareas— y pedir un texto para cerrar lo que sí se hizo
convierte en trámite el único gesto del registro que da alegrías.

### Sin doble vínculo, y aquí el argumento es distinto

`VincularActuacionDeMejora` ata **un solo** extremo, como en objetivos y en el
contexto. Pero el motivo no es el mismo que allí: en un objetivo **no hay medida
detrás por construcción**, y aquí sí puede haberla —cuando la mejora viene de un
hallazgo con punto de checklist— y aun así no se ata.

El motivo: **una oportunidad de mejora no incumple la medida**. El plan de
adecuación lista lo que falta por implantar, y una medida que ya está implantada y
que además se puede hacer mejor no está pendiente de nada. Atar el vínculo la
metería en un plan que presupuesta brechas, que es la clase de cifra inflada que el
§ 4.13 tuvo que arreglar por el otro lado.

**La consecuencia, declarada:** el coste de una actuación de mejora no entra en el
presupuesto del plan de adecuación.

**`OrigenTarea::Mejora` es el noveno origen y el cuarto que no está en § 4.7.** No
se apunta a `NoConformidad`: el reparto por origen del plan de acción existe para
distinguir lo reactivo de lo voluntario, y colapsarlos haría que un plan lleno de
mejoras se leyera como una organización apagando fuegos.

### Lo que este módulo declara que no hace todavía

- **No entra en el calendario de obligaciones**, y aquí ni siquiera se plantea: lo
  que vence no vence, porque nadie se comprometió. Sus tareas sí tienen plazo y
  ésas ya pintan chip.
- **No comprueba que la mejora continua exista de verdad.** La 10.1 pide mejorar
  de forma continua; Statera registra lo que se declare y no dice si el registro
  lleva seis meses sin moverse.
- **No convierte una mejora en objetivo.** Cuando una mejora se asume como
  compromiso, el objetivo de la 6.2 se registra aparte y a mano. Automatizarlo
  crearía objetivos sin plazo, sin recursos y sin firma, que es lo que la 6.2 no
  admite.
- **No entra en ningún documento.** El acta de la revisión por la dirección es
  donde estas mejoras se leen, y llega con el § 4.15.

---

## La revisión por la dirección

§ 4.15 y la cláusula 9.3. **Es el módulo que llevaba bloqueado desde el
principio**, y no por su complejidad: la 9.3 cierra la lista de entradas
obligatorias —son siete, no «las que se tengan»— y dos de ellas no salían de
ninguna parte. El § 6.2 y el § 10.1 existen para desbloquear esto.

Vive en `app/Domain/RevisionDireccion/`, con dos tablas: `revisiones_direccion` y
`revision_tarea`.

> **Ojo con el nombre, y está comprobado:** `/revisiones` ya estaba ocupada por las
> revisiones del **inventario de activos** (`RevisionInventario`), que son el
> «inventario mantenido» de A.5.9 y `op.exp.1`. Esta ruta es `/revision-direccion`.
> Mismo caso que `Contexto` frente a `ContextoOrganizacion` y que `Domain\Traza`
> frente a `Domain\Auditoria`: se anota, no se renombra lo que ya está. En la
> paleta de comandos, **el alias «revisión» a secas no se le da a ninguno de los
> dos**, porque sería empatar dos módulos con la palabra que más se teclea.

### La instantánea es lo que da forma al módulo

Las siete entradas se **congelan al aprobar** y nunca se consultan en vivo. Es el
fallo más caro que este módulo podía tener y el repositorio ya lo ha evitado
cuatro veces —`documento_versiones.instantanea`, `analisis_contexto`, la exigencia
congelada al cerrar una auditoría y `mediciones.objetivo`—; aquí sería el peor de
todos, porque el acta de marzo enseñaría las no conformidades y los riesgos de
octubre **bajo la fecha y la firma de marzo**.

De ahí la única decisión de interfaz que importa: **la ficha enseña las entradas
en vivo mientras la revisión está abierta y congeladas cuando el acta está
firmada**. Antes de firmar, lo que se mira es cómo está la cosa hoy —que es para
lo que se convoca la reunión—; después, lo que se revisó aquel día.

**Y no hay vigente**, a diferencia del análisis del contexto: allí el contexto es
un estado de cosas que se sustituye, y aquí cada revisión es un **acto** con su
fecha. La del año pasado no deja de haber ocurrido porque se celebre la de este
año, así que no hay índice único parcial ni estado `obsoleta`.

### Las siete entradas, y de dónde sale cada una

| 9.3.2 | De dónde |
|---|---|
| a) Acciones de revisiones previas | `revision_tarea` de la revisión anterior |
| b) Cambios en cuestiones internas y externas | § 4.1, desde el análisis **aprobado** |
| c) Necesidades de las partes interesadas | § 4.1 |
| d) Desempeño: NC, medición, auditorías y **objetivos** | § 4.13, § 4.14, § 4.12 y la **6.2** |
| e) Retroalimentación de las partes interesadas | § 4.1, **con limitación declarada** |
| f) Riesgos y estado del tratamiento | § 4.3 |
| g) **Oportunidades de mejora** | **10.1** |

**`EntradasRevision` no lee los resúmenes del panel**, y podría: `RegistroNoConformidades::paraElPanel()`
cuenta casi lo mismo. Sería acoplar un acta que se entrega a un auditor a la forma
que hoy tiene una tarjeta. Lo que sí comparte son los **scopes**, que es donde vive
la regla: `NoConformidad::pendientesDeVerificar()` cuenta aquí lo mismo que en el
panel y que en la tabla, por construcción.

**Las auditorías se acotan al periodo revisado y el resto no.** Una no conformidad
abierta lo está hoy, independientemente de cuándo se detectara, y acotarla
escondería justo las que llevan años abiertas.

**Un cero es una entrada recogida, no una entrada que falte.** Una organización
puede celebrar su primera revisión sin auditorías, sin no conformidades y sin
objetivos, y el acta lo dirá. Exigir que haya contenido convertiría la primera
revisión en imposible, que es cuando más falta hace.

**La entrada e) comparte apartado con la c) y el acta lo dice.** Statera registra
**qué exige** cada parte interesada, no **qué ha dicho** últimamente: no hay
quejas, ni encuestas, ni comunicaciones recibidas. Repartirlas en dos apartados con
el mismo contenido daría la impresión de que las dos están cubiertas.

### Dos fechas y no una periodicidad

`fecha` es cuándo se celebra y `periodo_desde`/`periodo_hasta` de qué habla el
acta. **No se deduce lo uno de lo otro**: una revisión del ejercicio 2025 se
celebra en febrero de 2026, y es lo normal, no la excepción.

Y **dos columnas en vez de una `Periodicidad`**, a diferencia de un indicador: una
revisión por la dirección no parte el calendario en cubos iguales. La primera cubre
desde que se implantó el SGSI y una extraordinaria puede cubrir seis semanas. Al
convocar se **propone** el día siguiente al fin de la última aprobada, que es lo
que impide que dos actas seguidas dejen un hueco sin revisar.

### El quinto trigger de inmutabilidad

Hermano de los de `documento_versiones`, `riesgo_valoraciones`, `auditorias` y
`analisis_contexto`. **Una sola puerta: `aprobada → en_curso`**, la misma que tiene
una auditoría, y nunca a `planificada` —decir que la reunión no se celebró es
reescribir el pasado—.

**La firma y la instantánea NO se neutralizan al reabrir**, y por eso el `CHECK` de
la firma va en una sola dirección: la fila reabierta conserva quién la aprobó y qué
se congeló hasta que la siguiente aprobación lo sobreescribe. Al revés habría que
limpiarlas en la misma escritura que el trigger está vigilando, y el trigger la
rechazaría.

**Y el `CHECK` de aprobada exige dos cosas: firma e instantánea.** La segunda es la
que lo separa del de un objetivo: un acta aprobada sin las entradas congeladas es
un acta que no puede demostrar de qué habló.

### Aprobar tiene ruta, permiso y acción propios

`AprobarRevision` no pasa por `CambiarEstadoRevision`, y el `FormRequest` de la
transición **rechaza `aprobada` explícitamente**. Aprobar no es un cambio de
estado: es el acto que recoge las siete entradas y las sella. Con una ruta genérica,
cualquiera podría firmar un acta sin instantánea y el `CHECK` lo rechazaría con un
error que no menciona la palabra «entradas».

**Las entradas se recogen ANTES de tocar la fila**, que es el error exacto que se
cometió en `CerrarAuditoria`: congelar después de marcar el estado hace que el
trigger bloquee el propio congelado con un mensaje que habla de otra cosa.

**`revision_direccion.aprobar` es el octavo verbo de supervisión**, y el más
literal de todos: la cláusula se llama «revisión por la **dirección**». Preparar la
reunión, recoger las entradas y redactar las conclusiones es trabajo de quien lleva
el SGSI; firmar que la dirección lo ha revisado, no.

**Sin tabla de transiciones**, a diferencia de tareas, no conformidades, objetivos
y mejoras. No es un descuido: lo que el auditor pregunta de una revisión no es desde
cuándo está en curso, es **qué se revisó y qué se decidió**, y eso lo contesta la
instantánea con su firma. Un histórico aquí guardaría el ir y venir de una reunión
que se aplaza, que no es una pregunta que nadie haga.

### Las salidas son tareas, y se leen en los dos sentidos

La 9.3.3 pide registrar las decisiones, y una decisión que no acaba en algo que
alguien hace para una fecha es un acta que no sirve. `revision_tarea` es N:M como
sus hermanas y **se lee hacia delante y hacia atrás**: de ésta son sus decisiones y,
desde la siguiente revisión, son «el estado de las acciones de revisiones previas».
Eso es lo que hace que la serie de actas signifique algo.

**Se pueden registrar decisiones sobre un acta ya firmada**, y conviene decirlo
porque es justo donde uno espera un error: escribir en la pivote no pasa por el
trigger —que blinda el acta, no lo que cuelga de ella— y es lo correcto, porque una
decisión se ejecuta en las semanas siguientes. Mismo caso que la acción correctiva
de una auditoría cerrada, y hay test.

**`OrigenTarea::RevisionDireccion` pasa a ofrecerse, y no hizo falta migración**:
el valor estaba en el `CHECK` desde la primera, porque el enum se declaró entero y
lo que faltaba era su módulo. Es la diferencia con `objetivo` y `mejora`.

**`RevisionDireccion::anterior()` busca por fecha de celebración y no por
`created_at`**: una revisión del ejercicio pasado puede registrarse después que la
de este año —pasa al meter el histórico— y ordenar por cuándo se tecleó daría
«acciones previas» de una reunión que todavía no había ocurrido. Hay test, y otro
que fija que la anterior nunca es la de otra organización.

### El acta: el quinto documento calculado

`TipoDocumento::ActaRevision`, de **ámbito organizativo** —lo que la dirección
revisa es el SGSI entero— y por tanto `exigeSistema()` a `false`. **`documentos_sistema_check`
no se tocó**, y eso es la noticia: la migración del § 4.1 lo rehízo por tercera vez
para cambiar su motor a `exigeSistema()` precisamente para que un tipo nuevo de
ámbito organizativo no obligara a rehacerlo otra vez. Aquella decisión se paga aquí.

`ActaRevisionDireccion` implementa `GeneradorDocumento` **directamente**, como
`AnalisisDelContexto` y `DocumentoRedactado`. Los apartados del cuerpo van **en el
orden en que la norma enumera las entradas**, de la a) a la g): un auditor las
recorre con el acta delante, y reordenarlas le obliga a buscar cada una.

**Las decisiones son la excepción y se leen en vivo**, a diferencia de las
entradas: son las salidas y pueden crecer después de firmar. Lo que se congeló es
lo que la dirección **tuvo delante**, no lo que mandó hacer.

### El tercer fallo silencioso de la familia, cerrado

El acta es el primer documento que imprime badges de objetivos de seguridad, y
`EstadoObjetivo::Propuesto` gasta el tono `en_revision` — que **no estaba en
`EsquemaCuerpo::TONOS_BADGE` ni en `documento.css`**. `RenderizadorCuerpo` cae a
`neutro` cuando no reconoce el tono, así que el badge salía **gris y sin punto en el
PDF que se le entrega al auditor**, sin que nada avisara. Es el mismo fallo que
`IconoTipo` tenía con los iconos y `tonos.ts` con los colores, por tercera vez.

Tres cosas para cerrarlo, y las tres hacen falta:

1. **`.badge--en_revision` en `documento.css`**, con los hex de DESIGN.md §3
   (`#7B45C4` sobre `#F7F2FF`) y no estimados.
2. **`Nodo::badge()` lanza `LogicException`** con un tono que no esté en el mapa.
   Es seguro porque ahí **sólo llega código**: los tonos los escriben los
   materializadores desde enums, nunca un cuerpo editado —ése pasa por
   `SanearCuerpo`, que anula el tono desconocido, y ahí el `?? neutro` del
   renderizador es lo correcto—. Mismo razonamiento que los dos `match` sobre
   cadenas de `MaterializarCuerpo`.
3. **`TonosDelDocumentoTest`**, que compara el mapa con las clases del CSS en las
   dos direcciones. **No recorre los enums del dominio a propósito**: el
   vocabulario tiene familias que el papel no imprime nunca —los nueve `--tipo-*`,
   los cuatro `--dafo-*`, las prioridades y los ordinales— y exigirle que las
   conozca sería pedirle que supiera pintar badges que ningún generador le pasa. Lo
   que se fija es la puerta, no el inventario.

### Lo que este módulo declara que no hace todavía

Las cuatro van impresas en el acta, no sólo aquí:

- **La retroalimentación de las partes interesadas (9.3.2 e) se aporta fuera.**
  Statera registra qué exige cada parte, no qué ha dicho.
- **Los asistentes son texto libre**, y no se comprueba que quien figura tenga
  potestad para revisar el sistema de gestión ni que la dirección estuviera
  representada. Tampoco hay firma electrónica cualificada. `users` son cuentas de
  Statera y a una revisión por la dirección asiste gente que no tiene cuenta —§ 4.8
  no existe—.
- **No se comprueba que la revisión se celebre con la periodicidad comprometida**:
  se registran las que se convocan y no se avisa de la que falta. Ese aviso vive en
  el § 4.16, y `Aviso\Fuente` sigue con tres casos. **No entra una `Fuente` nueva
  aquí**: lo que vence es la revisión del acta aprobada, que `Fuente::Documento` ya
  recoge — mismo argumento que dejó fuera al análisis del contexto.
- **Desvincular una decisión de un acta firmada sí cambia** lo que la revisión
  siguiente verá como «acciones previas». El acta congeló las entradas, no las
  salidas.

---

## Las personas

§ 4.8, la cláusula 5.3 y `mp.per.*`. **El primero de los dos módulos que muerden
hoy**: en categoría básica ya son exigibles los deberes por escrito (`mp.per.2`),
la concienciación (`mp.per.3`) y la formación (`mp.per.4`), y hasta aquí no había
dónde registrarlos. Vive en `app/Domain/Persona/`, con seis tablas.

### `personas` no es `users`, y no se fusionan

Es la decisión que da forma al módulo. `users` son las **cuentas** de Statera
—quien entra, mira y cierra tareas— y `personas` es la **plantilla**: quien firma
un acuerdo, asiste a la formación y puede ser designado responsable de seguridad,
tenga o no cuenta. La mayoría no la tiene.

Por eso los responsables de activos, tareas, evidencias y objetivos **siguen
apuntando a `users` y no se migran**: asignar una tarea a quien no puede entrar a
cerrarla no sirve de nada. `personas.user_id` es el puente, nullable y único, y
esa unicidad es de toda la tabla y no por organización — una cuenta pertenece como
mucho a una persona, y dos organizaciones no comparten cuentas.

**La consecuencia, declarada y reescrita en el PDF**: el acuse de lectura sigue
registrando **usuarios de Statera**, y quien no tiene cuenta no puede acusar
recibo. `CoberturaAcuse` **no lee de `personas` a propósito**: hacerlo convertiría
a media organización en «pendiente de leer» para siempre, sin ninguna puerta por
la que dejar de estarlo, que es la clase de cifra inalcanzable que este producto
evita en todas partes.

**`activa` no es columna: es `fecha_baja IS NULL`.** Mismo criterio que `vigente`
en el análisis del contexto y que el ámbito derivado de una cuestión del DAFO.
Reincorporar a alguien es vaciar un campo y no acordarse de dos.

### La incompatibilidad del 5.3, que es lo que paga el módulo

La especificación no dice «avisar» ni «señalar»: dice que el sistema debe
**impedir** que el responsable de seguridad y el responsable del sistema recaigan
en la misma persona. Quien decide qué protección hace falta no puede ser quien
responde de haberla puesto.

**La regla vive en `DesignarRol` y no en un `CHECK`**, y el motivo es estructural:
es una condición **entre filas** —dos designaciones vigentes de la misma persona
en el mismo sistema— y un `CHECK` sólo ve una. Es el precedente exacto de
`RegistrarDependencia`, que rechaza los ciclos del grafo de activos porque el
`CHECK` de `activo_dependencias` sólo cubre el bucle de un salto. Y vive en el
dominio y no en el `FormRequest` porque vale igual para un importador y para el
seeder.

**Lo que sí impone la base es el titular único**: un índice único parcial sobre
`(sistema_id, rol) WHERE hasta IS NULL`, y sólo para los tres roles singulares.
Responsable de la información y responsable del servicio pueden ser varios —uno
por cada información tratada y por cada servicio prestado—, y exigirles unicidad
sería inventarse una restricción que la guía no pone. La guarda del dominio existe
para que el mensaje sea legible y no el nombre del índice, como la del cierre de
una auditoría.

**El administrador de la seguridad no entra en la incompatibilidad**, aunque sea
tentador: la guía lo pone bajo la dirección del responsable de seguridad, no en
conflicto con él, y en una organización pequeña es habitual que coincidan.

**Por sistema y no por organización**, que es donde la incompatibilidad significa
algo y como CCN-STIC 801 reparte los roles: una persona puede ser responsable de
seguridad de un sistema y responsable del sistema de otro sin perder separación de
funciones.

**Las designaciones llevan vigencia y no se borran.** «¿Desde cuándo es
responsable de seguridad?» es literalmente la pregunta del auditor (invariante 7),
y «¿hasta cuándo?» es la otra mitad. Revocar pone `hasta`; vigente es
`hasta IS NULL`.

**Y `RolEns` NO son los roles de `Domain\Autorizacion\Enums\Rol`.** Aquéllos
deciden quién puede tocar qué dentro de Statera; éstos son cargos de la
organización, se designan por escrito y el auditor pide el nombramiento. Una
persona puede ser responsable de seguridad del sistema sin tener cuenta, y quien
tiene el rol `ResponsableSeguridad` de la aplicación puede no ser quien lo es de
verdad. El aviso ya estaba escrito en la cabecera de `Permiso` antes de que este
módulo existiera.

### La formación: convocar y asistir son dos cosas distintas

Quien no está en la lista no fue convocado; quien está con `asistio = false` fue
convocado y no fue, y **ése es el que un auditor pregunta**. Sin esa diferencia,
«formación impartida al 100 % de los convocados» saldría siempre. Por eso quien
sale de la convocatoria se borra de la pivote en vez de marcarse a `false`.

**`/formacion` es pantalla propia y no un bloque de la ficha de una persona**, por
el mismo motivo que la checklist de una auditoría: lo que se registra es una
sesión con veinte convocados, y marcar veinte asistencias exige marcado en bloque.
Al revés —apuntar veinte sesiones desde cada ficha— son veinte peticiones y veinte
oportunidades de dejarlo a medias.

**`TipoAccionFormativa` son dos casos y no un campo libre** porque `mp.per.3` y
`mp.per.4` son medidas distintas: concienciar es recordar lo que todo el mundo
tiene que saber y formar es enseñar a hacer algo a quien lo tiene que hacer. Una
organización puede cumplir una y no la otra.

**Los doce meses de vigencia son una convención del producto y no de la norma.**
El ENS dice «periódicamente» y no pone número; doce meses es el ciclo con el que
ya trabajan la revisión por la dirección, la auditoría interna y el informe INES.
Vive en `Persona::MESES_DE_VIGENCIA_FORMATIVA`, y la factory lo lee de ahí para
que cambiar la cadencia no deje en verde un test que prueba lo contrario.

### El IND-03 pasó de manual a calculado

`CalculoIndicador::PersonalFormado` — activas con al menos una asistencia en los
últimos doce meses, sobre el total de activas. **El numerador sale de restar**
`sinFormacionReciente()` del total, no de una segunda consulta con la condición
contraria, que sería la misma regla escrita dos veces.

Sin marco (`admiteMarco()` → `false`), como las evidencias y los activos: la
plantilla es de la organización entera y sirve a los dos marcos (invariante 6).

**Y el `CHECK` de `indicadores.calculo` necesitó migración**, con la lista escrita
a mano en las dos direcciones: construirla desde el enum haría que `migrate:fresh`
admitiera cualquier caso nuevo sin migración y ningún test se pondría rojo. Su
`down()` borra los indicadores del cálculo nuevo **a través de
`ContextoOrganizacion::comoMantenimiento()`**, y eso no es adorno: una migración no
tiene petición ni usuario, así que RLS deniega por defecto y `DB::table(...)
->delete()` afecta a **cero filas sin fallar** — el `ALTER TABLE` de la línea
siguiente muere con «is violated by some row». Es el segundo sitio del producto
que atraviesa las tres capas, y el primero fue el recuento del importador.

**El hueco del indicador manual lo ocupa la satisfacción de las partes
interesadas**, que es honesto: es justo la entrada 9.3.2 e) que el acta de la
revisión por la dirección declara que se aporta fuera de Statera.

### Las dos checklists

`pasos_persona` es el patrón de las subtareas de una tarea: la lista se guarda
entera en una sola ruta, el orden va implícito en la posición del array,
`hecho_en` **no se vuelve a sellar** si ya estaba marcado, lo que no viene se borra
y un `id` que no es de esa persona se trata como un paso nuevo.

**Las dos se guardan por separado**, y eso sí es de aquí: la de alta y la de baja
se rellenan con meses de diferencia y por gente distinta, y una sola ruta haría que
guardar la de salida borrara la de entrada si el cliente se dejara un campo.

**Marcar todos los pasos no da de baja a nadie**, igual que marcar todas las
subtareas no cierra una tarea: la baja es una fecha y se pone al editar la persona.

### Tres verbos: el noveno de supervisión

`personas.ver`, `personas.gestionar` y **`personas.designar`**. Dar de alta a
alguien, apuntar su formación y marcar su checklist es trabajo del técnico;
designar al responsable de seguridad de un sistema es un nombramiento que la
organización firma y que el auditor pide por escrito. Es la misma familia que
`sistemas.valorar`, `riesgos.aceptar`, `documentos.aprobar`, `contexto.aprobar`,
`no_conformidades.verificar`, `objetivos.aprobar` y `revision_direccion.aprobar`.

### El único rojo del módulo

**La salida sin cerrar**: alguien que se fue con la checklist de baja a medias es
un acceso que puede seguir vivo, y es el hermano exacto de
`Activo::esperaBorradoSeguro()` — la herramienta no corrige el dato, lo pone
delante. Sólo se mira en quien ya no está: una checklist de salida sin empezar en
alguien que sigue trabajando no es una laguna, es que todavía no toca.

**No estar formado no va en rojo**, ni no tener acuerdo: son la distancia que
queda, y el quinto principio del producto. Y el anillo del panel es de los **roles
ENS designados** y no del personal formado, porque aquél tiene denominador estable
—los sistemas por los cinco roles— y mide cuánto está decidido, que es el caso del
anillo del inventario; el porcentaje de formados sube al impartir una sesión y baja
solo al pasar doce meses, así que castigaría por tener plantilla nueva.

### `resolveChildRouteBinding()`, por cuarta vez en el producto

`scopeBindings()` deduce la relación pluralizando el nombre del parámetro **en
inglés** —`designacion` → `designacions`— y aquí el dominio se nombra en español.
Sin escribirlo a mano, `/personas/{persona}/designaciones/{designacion}` responde
500 y de paso deja de acotar: el nombramiento de otra persona se revocaría desde
ésta. Los precedentes son `Documento`, `Indicador` y `Objetivo`, y **lo cazó un
test de aislamiento y no una revisión**, igual que las tres veces anteriores.
`acuerdo` y `paso` no hacen falta: su plural inglés coincide con el español, que es
justo lo que hace este fallo difícil de ver leyendo las rutas.

**Y el parámetro de una sesión es `{accion}` y no `{accion_formativa}`.** El
binding implícito empareja por **nombre de parámetro**, así que con
`{accion_formativa}` y un argumento `$accion` Laravel inyecta un modelo vacío y la
escritura muere con un «null value in column». No lanza: escribe mal.

### Lo que este módulo declara que no hace todavía

- **No sustituye a `users`**, y no lo pretende. Ver arriba.
- **No comprueba que un nombramiento esté firmado** por quien tiene potestad, ni
  que la persona designada reúna la competencia que `mp.per.1` pide —que en básica
  está en `no_aplica`—. Va impreso en la DdA.
- **No gestiona bajas automáticas**: dar de baja a alguien no cierra sus
  designaciones ni revoca su cuenta. La checklist de salida es lo que lo recuerda,
  y el rojo del módulo es no haberla cerrado.
- **No comprueba que la plantilla esté completa**, ni que todo puesto tenga
  caracterización.
- **No entra en el calendario de obligaciones**, y aquí sí hará falta: la
  formación que toca este año **no la cubre ni `Fuente::Documento` ni las tareas**,
  a diferencia de objetivos, mejoras y revisión por la dirección. Es la primera
  `Fuente` que el § 4.16 va a necesitar de verdad.

---

## Los incidentes

§ 4.10 y `op.exp.7`. **El segundo de los dos módulos que muerden en categoría
básica**, junto a las personas: `op.exp.7` es exigible desde el primer día y no
tenía dónde registrarse. Vive en `app/Domain/Incidente/`, con tres tablas.

### El reloj, y dónde no lo hay

Es la decisión del módulo. **Sólo hay cuenta atrás donde la ley pone un número.**

`PlazoNotificacion::aepd()` cuenta **72 horas desde `fecha_deteccion`**, y el
número sale del **artículo 33.1 del RGPD**, citado en el código porque un plazo
sin su fuente es una opinión. Corre únicamente si el incidente está marcado como
notificable a la AEPD, es decir, si hubo datos personales de por medio.

**Para el CCN-CERT no hay reloj.** El RD 311/2022 no fija horas: dice «sin
dilación». Poner un número sería exactamente lo que este producto se niega a
hacer con el riesgo residual —una opinión de la herramienta disfrazada de
cálculo— y además sería un número que alguien acabaría defendiendo delante de un
auditor. Se registra si es notificable y cuándo se notificó, **y la ficha lo dice
por escrito**.

**Notificable y notificado son dos campos y no uno.** «No había que notificar» y
«había que notificar y no se hizo» serían la misma columna vacía si se
colapsaran, y la segunda es un incumplimiento y la primera no.

**Y notificar tarde sigue constando como tarde.** `PlazoNotificacion` distingue
«notificada dentro de plazo» de «notificada fuera de plazo»: esconderlo al anotar
la notificación sería borrar la prueba. Por eso la fecha **se escribe y no se
impone**, al revés que la de cierre de una tarea: la notificación se hace en la
sede del supervisor y se apunta aquí después, y en un incidente fuera de plazo
esa fecha es lo que decide si hubo incumplimiento.

**Anotar la notificación no cambia el estado**, y deja fila en el histórico. Se
puede notificar con el incidente abierto, en tratamiento o resuelto; meterlo en
la máquina de estados obligaría a inventarse un «notificado» que no dice nada de
cómo va la contención.

### Cerrar exige lección aprendida

`op.exp.7` pide aprender del incidente, y es **el paso que todo el mundo se
salta** el día que el servicio vuelve. Por eso:

1. **`resuelto` no es `cerrado`.** Resuelto es que el servicio está
   restablecido; cerrado es que además se ha escrito qué se aprendió. Con un solo
   estado final, la lección se queda sin escribir justo cuando todo el mundo se va
   a dormir. Es el mismo reparto que `Cerrada` frente a `Verificada` en una no
   conformidad.
2. **La regla está en `CambiarEstadoIncidente` y en un `CHECK`**, como todas las
   de esta familia: en el dominio porque vale igual para un importador, y la
   guarda para que el mensaje sea legible y no el nombre de una restricción.
3. **La lección tiene ruta propia** (`PUT /incidentes/{incidente}/leccion`). Se
   escribe **mientras se resuelve**, a trozos y según se va sabiendo; obligar a
   abrir el formulario entero para añadir una línea es cómo se consigue que esa
   línea no se escriba.

**De `cerrado` se vuelve a `resuelto` y nunca a `abierto`**, misma puerta que
tienen la auditoría cerrada y el acta aprobada. Y **volver atrás exige motivo
escrito; avanzar no**: pedir un texto para pasar de abierto a en tratamiento
convertiría en trámite el gesto que más se repite mientras se apaga el fuego.

### El único rojo del módulo

**El plazo de la AEPD vencido sin notificar**, y nada más. Ni los estados —un
incidente abierto no va mal, va siendo atendido— ni la peligrosidad, que se
reparte como `NivelRiesgo` y sólo llega al rojo en `critica`. Hay test que
recorre el enum de estados comprobándolo, como en tareas y en mejoras.

Y **«en plazo» va separado de «fuera de plazo»** en las cifras: uno es un
incumplimiento y el otro es trabajo urgente, y colapsarlos pondría en rojo a quien
lo está haciendo bien.

### Lo que va en columnas y lo que no

**Las cinco dimensiones son cinco columnas booleanas**, no filas ni JSONB. Mismo
reparto que la valoración propia de un activo y por lo mismo: son cinco, no van a
ser seis, se consultan y se indexan, y «qué se vio afectado» es la primera
pregunta de un informe de incidente y la que decide si hay que notificar.

**Las dos notificaciones van en columnas y no en una tabla**, como dibuja la
§ 2.2: son dos destinatarios fijados por ley, y una tabla de notificaciones con
dos filas posibles es una tabla que nadie consulta. **Declarado**: un tercer
supervisor —NIS2, o un regulador sectorial— sí pedirá tabla, y entonces se migra.

**`incidente_activo` es N:M**, contra la letra de § 2.2 que dice
`activos_afectados`: un cifrado por ransomware toca treinta equipos y sigue
siendo un solo incidente. Mismo argumento aritmético que en riesgo ↔ activo.

**`sistema_id` es opcional**, al revés que en una auditoría: un correo
fraudulento a toda la organización no es de ningún sistema, y obligarlo haría que
quien lo apunta a las tres de la mañana eligiera el que menos mal le suena.

**Y son dos fechas, no una**: cuándo empezó y cuándo se detectó. La diferencia
entre las dos es la primera cifra que enseña un informe de incidente, y con una
sola columna se pierde. `fecha_inicio` nula es «no se sabe», que es lo normal al
principio.

### La clasificación, y lo que queda por contrastar

`ClasificacionIncidente` son las clases de nivel superior de la taxonomía
**CCN-STIC 817**, y `PeligrosidadIncidente` sus cinco niveles. **Enums y no
catálogo en YAML**, por el mismo reparto que `GrupoAmenaza` frente a las 56
amenazas de MAGERIT: las clases son la **estructura** de la taxonomía y no su
contenido.

> **Sin contrastar contra la guía, y queda dicho**, igual que las dimensiones de
> las amenazas de MAGERIT. Se usan como **clasificación de trabajo**: agrupan y
> filtran, y **no deciden nada** —ni la peligrosidad, ni si hay que notificar, ni
> a quién—. Los subtipos de la 817 no están cargados.

**La peligrosidad la declara una persona y no se calcula.** Sería tentador
derivarla de las dimensiones afectadas, y sería una opinión disfrazada de
cálculo: la 817 no publica ninguna función que lo haga, y el mismo compromiso de
confidencialidad es crítico en un sistema y bajo en otro. Mismo razonamiento que
el riesgo residual.

**`Otros` existe a propósito**: quien apunta un incidente a las tres de la mañana
no está clasificando taxonomías, y sin un valor para «todavía no lo sé» elegiría
el que menos mal le suena. Es el argumento de `OrigenTarea::Propia` y el de
`EstadoControl::PorConfirmar`.

### Dos verbos, y ninguno de supervisión

`incidentes.ver` e `incidentes.gestionar`. **Notificar a un supervisor no es una
decisión que se delibere**: es una obligación con reloj, y un permiso aparte
metería un paso entre el reloj y la notificación. Lo que sí exige firma de
dirección es la no conformidad que salga del incidente, y ésa ya tiene la suya.

### Las costuras, y la que es nueva

**Tres enganches que llevaban puestos desde hacía meses**, y ninguno necesitó
migración porque el valor estaba en el `CHECK` desde la primera —el enum se
declaró entero y lo que faltaba era su módulo—:

- `OrigenTarea::Incidente` pasa a ofrecerse. Y a diferencia de `Hallazgo`, aquí
  **no hay eslabón por medio**: contener un incidente produce trabajo directo que
  no espera a ninguna no conformidad, porque puede que no llegue a haberla.
- `OrigenNoConformidad::Incidente`, ídem.
- `OrigenNoConformidad::RevisionDireccion`, que **se quedó en `false` y era falso
  desde el § 4.15**: una frase que envejeció en el tramo anterior.

**`OrigenMejora::Incidente` sí necesitó migración del `CHECK`**, porque es un
valor nuevo. Es la diferencia con los tres de arriba, y la misma que hubo entre
`revision_direccion` y `objetivo`/`mejora` en `OrigenTarea`.

**Y `no_conformidades.incidente_id` es el espejo exacto de `hallazgo_id`**:
nullable, único —un incidente se trata una vez—, `nullOnDelete` —borrar el
incidente no se lleva por delante la prueba de que se trató— y un `CHECK` de que
implica `origen = 'incidente'` en una sola dirección. **Más uno que el hallazgo
no tiene**: no puede venir de un hallazgo y de un incidente a la vez, porque con
las dos columnas puestas `origen` tendría que valer dos cosas y los dos `CHECK`
anteriores se contradirían con un mensaje que no explica nada.

**La mejora, en cambio, no lleva clave foránea.** Desde un incidente sólo se
hereda el origen y el título. Es el mismo reparto que
`OrigenMejora::RevisionDireccion`: la mejora que sale de una lección aprendida no
«trata» el incidente —ése ya está cerrado—, así que atarla sería fingir una
trazabilidad que no hay.

### Lo que este módulo declara que no hace todavía

- **No entra en el calendario de obligaciones**, y aquí el argumento cambia
  respecto a los tres módulos anteriores: el plazo de la AEPD se mide en **horas**
  y una rejilla de meses no es donde se mira un reloj de 72 h. Vive en la ficha y
  en el panel.
- **No genera el informe de incidente** como documento. Es del § 4.18, que sigue
  pendiente.
- **No notifica por sí solo a ningún supervisor**, ni prepara el formulario de la
  sede: registra la decisión y la fecha.
- **No decide si hay que notificar.** `notificable_aepd` lo marca una persona, y
  la herramienta no comprueba que esa decisión sea correcta — igual que no
  comprueba la peligrosidad.
- **Los subtipos de la CCN-STIC 817 no están cargados**, y las clases no están
  contrastadas celda a celda contra la guía.

**Y ninguna limitación impresa pasó a ser falsa con este módulo dentro** —
comprobado: ni «incidente» ni `op.exp.7` aparecían en ninguna—. Es la segunda vez
que ocurre, después del § 4.1. Las dos que sí se reescribieron en este tramo son
las del § 4.8: la de los roles ENS de la DdA y la del acuse de lectura.

---

## El panel: tres vistas

El panel creció por acumulación —un módulo, una tarjeta— hasta trece secciones
apiladas, y el problema no era la longitud: **mezclaba tres preguntas**. Cómo va
el cumplimiento, qué está pasando y de qué organización hablamos estaban en la
misma columna, y había que recorrerla entera para contestar cualquiera de las
tres.

| Vista | Qué contesta | Qué lleva |
|---|---|---|
| `/panel` | ¿Cómo vamos con lo exigible? | anillo, reparto por estado, pruebas, por marco, sistemas |
| `/panel/ciclo` | ¿Qué está pasando y mejoramos? | plan, no conformidades, incidentes, desempeño, objetivos |
| `/panel/organizacion` | ¿De qué estamos hablando? | contexto, personas, inventario |

**Son rutas y no estado de cliente**, que es la decisión ya tomada para las tres
pantallas del plan de acción: «un conmutador que recuerda la última vista hace
que el enlace que alguien pega en un correo abra otra pantalla». `ConmutadorPanel`
está calcado de `ConmutadorVista`.

**Y no entran en `lib/navegacion.ts`**, igual que `/tareas/tablero` y
`/tareas/calendario`: aquel fichero es el mapa de **módulos**, y añadir ahí tres
entradas pondría tres «Panel» en el sidebar. El sidebar lleva a `/panel`, que es
la vista por defecto, y `esSeccionActiva` ya marca las tres porque cuelgan de
ella.

**Cada vista consulta sólo lo suyo.** Antes cada carga calculaba trece resúmenes
aunque nadie mirara doce.

**Las tres tienen estado vacío.** Una pestaña en blanco no se lee como «no hay
nada», se lee como rota — y con los cinco registros del ciclo a cero, que es el
estado de quien acaba de empezar, esa vista no diría literalmente nada. En
primer arranque el conmutador tampoco se pinta: ahí la pantalla no resume,
orienta.

### El punto de la pestaña, que es lo que sujeta el reparto

Partir el panel tiene **un solo riesgo**, y es el que hay que sujetar: una
pestaña puede esconder un incumplimiento detrás de un clic que nadie da.
`AlertasDelPanel` cruza los once registros, cuenta **lo rojo que no está a
cero**, y cada pestaña sale con su recuento en `VistaPanel::$alertas`.

**Se filtra por tono y no por una lista de claves.** `alertas()` de cada registro
devuelve también cosas que piden atención sin estar incumplidas —`bloqueadas` en
tareas, `con_no_conformidades` en auditorías— y contarlas aquí pondría punto en
las tres pestañas siempre. El rojo del producto es `caducada`, tiene dueños
contados y cada módulo declara el suyo: **un módulo nuevo entra declarando su
alerta con ese tono, y esa es toda la conexión que hace falta.**

**No se manda la lista de alertas al cliente, sólo el recuento.** Lo que el
conmutador necesita es saber si las hay; el detalle vive dentro, en la tarjeta
del módulo que lo produce, con su enlace a la lista exacta.

> **Lo que se probó y se quitó.** El primer intento sacaba además **todos** los
> rojos a una tira fija encima de las pestañas. Con el registro de ejemplo salían
> **doce tarjetas rojas** —el seeder planta un caso de cada—, que es exactamente
> la fila de cifras que hay que leerse entera y que el panel ya tenía. El punto
> dice lo mismo en un píxel, y el rojo se queda donde puede explicarse: al lado
> de su cifra y de su enlace.

**Cada fuente va con su permiso**, como ya iba cada tarjeta por separado:
conectar dos módulos abre una puerta lateral al registro del otro si nadie lo
decide. Que hoy los tres roles del § 4.19 tengan todos los `.ver` no la hace
innecesaria: la hace **no ejercida**.

### La lista de fuentes es literal, y hay test que la descubre

`AlertasDelPanel::FUENTES` es una lista escrita a mano, como `Rol::permisos()`, y
con el mismo riesgo: olvidar un módulo nuevo **no rompe nada** — su pestaña deja
de marcarse, que es el fallo silencioso que el punto existe para cerrar.

Por eso `AlertasTest` no enumera módulos: **recorre `app/Domain/` buscando
registros con `alertas()`** y exige que estén declarados, en las dos direcciones.
Es el sexto de la familia que descubre en vez de enumerar. Y su `glob` lleva la
lección de `FactoriesSinOrganizacionTest`: si deja de encontrar nada, el test se
pone rojo en vez de pasar dando por cubierto lo que no cubre.

### Los dos callejones sin salida que quedaban

**Ninguna cifra de la tarjeta de pruebas llevaba a su lista.** «3 caducadas», y
ahora búscalas — filtrando a mano por un rango de fechas. Era justo lo que
`Indicador` existe para evitar, y en el módulo que sostiene el rojo más antiguo
del producto. Ahora `EvidenciaRecurso` declara `caducadas` y `por_caducar` por
scope, y las cuatro cifras de la tarjeta enlazan.

**Y la cuarta no tenía scope siquiera.** «Implantados sin prueba» vivía escrito
en `ResumenCumplimiento` y en ningún otro sitio, así que no había filtro que
pudiera reproducirla. Ahora es `Implantacion::sinEvidencia()`, lo invocan el
resumen y el filtro de `/implantaciones`, y por construcción no pueden
discrepar.

---

## El plan de adecuación

El tercer documento **calculado**, y el que cierra la fase 2. Hace la pregunta contraria a una
declaración: la DdA dice qué medidas se exigen y cómo está cada una; el plan lista **sólo las que no
están implantadas**, con quién responde, para cuándo y cuánto cuesta. Es el documento que junta el
§ 4.4 con el § 4.7, y no hizo falta ninguna tabla nueva: `implantaciones.fecha_objetivo` y
`responsable_id` estaban desde la primera migración, y `tareas.coste_estimado` llevaba desde el
principio con un comentario que decía que era «para el plan de adecuación» — y **no lo leía nadie**.

**`DeclaracionAplicabilidad` pasó a llamarse `DocumentoCalculado`.** La clase es la tubería —la
consulta por tipo de requisito, el agrupado por el nodo padre, las correspondencias cruzadas— y no un
género documental; con un plan heredando de ella el nombre mentía. Hace pareja con
`DocumentoRedactado`, que es el otro lado de la frontera que define `TipoDocumento::esRedactado()`.
Mismo caso que `IndicadorInventario` → `Indicador`.

**Y `resumen()` se volvió abstracto en el movimiento.** Las cifras de una declaración —porcentaje
implantado, excluidos— **no significan nada** sobre filas que son todas pendientes por construcción:
darían cero siempre. Heredar una implementación que un hijo no debe llamar es una mina que no caza
ningún `match` exhaustivo, así que las dos declaraciones la reciben por el trait
`Concerns\ResumeLaAplicabilidad` y el plan escribe la suya. Lo mismo con la fila: los quince campos de
una medida del ENS viven en `Concerns\ArmaFilaDelAnexoII`, porque `FilaRequisito` es `readonly` y PHP
no tiene `clone with` — sin el trait, el plan copiaba las quince asignaciones.

**El fallo caro de este módulo es el coste, y se cuenta dos veces si nadie lo impide.**
`implantacion_tarea` es N:M: una actuación hace avanzar varias medidas a la vez, así que sumar la
columna presupuestaría tres veces una tarea que cubre tres medidas — en el documento que se le lleva a
la dirección a pedir dinero. El total lo calcula `Tarea\Coste::total()` **sobre tareas distintas**, y
la columna sigue imputando a cada medida lo suyo: los dos números son correctos y **no cuadran entre
sí**, así que el documento lo dice por escrito. Es el mismo argumento aritmético que dejó las subtareas
fuera de `tareas` y que hizo N:M a riesgo↔activo.

**`Domain\Tarea\Coste` existe por eso**, y de paso recoge el formato del euro, que estaba escrito dos
veces —la columna de la tabla y la ficha— e iba camino de la tercera. Mismo criterio que `Tarea\Plazo`.

**Cuatro scopes nuevos en `Implantacion`**, que hasta ahora sólo tenía `aplicables()` y `delSistema()`:
`pendientes()`, `objetivoVencido()`, `sinFechaObjetivo()` y `sinTrabajo()`. Los invocan por nombre la
cifra del panel, los filtros de `/implantaciones` y la consulta del plan, que es lo que garantiza que
pulsar el número enseñe exactamente ese número. **«Pendientes» era una cifra del panel que no se podía
pulsar**, y llegar a esa lista exigía marcar a mano «aplica» y tres de los cuatro estados. Van con las
columnas cualificadas —`implantaciones.estado`— porque quien los llama suele traer `requisitos` unida.
`fecha_objetivo` existía desde el principio y **no se comparaba con hoy en ningún punto del producto**;
aquí empieza a significar algo.

**Dos fuentes nuevas**: `resumen_plan`, porque las cifras de una declaración tienen otra forma, y
`tabla_sin_trabajo`, que repite las medidas pendientes sin ninguna tarea abierta detrás. La duplicación
es deliberada, igual que la tabla de exclusiones de la SoA: es lo que la dirección va a mirar seguro.
**Un plan completo no es el que no tiene ninguna, es el que las declara.**

**El denominador va impreso y con palabras.** «51 de 52» y, debajo, «al sistema se le exigen 52 medidas
del Anexo II, de las cuales 1 figura implantada; este plan recoge las 51 restantes». Sin eso una tabla
de 51 filas se lee como si al sistema se le exigieran 51. Y las implantadas salen **por resta** y no
por una segunda consulta con la condición contraria: `pendientes()` es exactamente «aplicable y no
implantada», así que escribir `where estado = implantado` sería la misma regla por segunda vez.

**`documentos_sistema_check` se reescribió**, y la migración anterior pedía expresamente que no se
tocara. Su razón seguía siendo buena y por eso hay que decir por qué deja de valer: estaba en negativo
—`tipo NOT IN ('soa_iso','dda_ens')`— para que un tipo **de ámbito organizativo** no obligara a
rehacerlo, y política, norma y procedimiento lo eran. El plan es el primer tipo **calculado** que llega
detrás, y un plan sin sistema no es un documento raro, es un documento imposible. Ahora la lista se
construye desde el enum filtrando `! esRedactado()`.

**El `CHECK` no lo prueba `migrate:fresh`.** Los `CHECK` de tipo se construyen desde `TipoDocumento::cases()`
**en ejecución**, así que sobre una base recién migrada ya incluyen el tipo nuevo aunque falte la
migración: ningún test se pone rojo si se olvida. `PlanExigeSistemaTest` prueba la mitad que sí importa
—que la base rechaza un plan sin sistema— y hay que correr `migrate`, no sólo `fresh`.

**Y tres frases más pasaron a ser falsas con el plan dentro**, todas corregidas: `limitacionesBase()`
decía «sobre N requisitos registrados» contando sólo las filas —en un plan, 51 habiendo 52 exigibles—;
la ficha del documento pintaba «N requisitos · N excluidos · N implantados», que en un plan es «0
excluidos · 0 implantados» para siempre, y ahora el recuento lo escribe el servidor según el tipo; y el
mensaje de `GuardarDocumentoRequest` decía «La %s es de %s», que con un tipo masculino salía «La Plan
ENS» (y el «de el ENS» ya estaba mal antes).

**Lo que el plan declara que no hace**: no contrasta plazos contra capacidad, no ordena las medidas por
dependencia, no exige que toda medida pendiente tenga fecha o responsable, y **el calendario de
obligaciones todavía no incluye las fechas objetivo** — el aviso diario y la vista de mes siguen
recogiendo sólo tareas y evidencias. Esa cuarta `Fuente` es un trabajo aparte; mientras tanto va
declarada, que es lo que este proyecto hace con lo que aún no puede afirmar.

```sh
php artisan documentos:generar PLA-ENS-01 --html   # sigue siendo el bucle rápido
```

---

## El análisis de riesgos

Vive en `app/Domain/Riesgo/`. Abre la fase 2, y es lo que cobra el grafo de dependencias del
inventario: el impacto de un riesgo sale de la valoración **efectiva** de sus activos, así que un
riesgo sobre una base de datos valorada «bajo» que sostiene un servicio esencial se puntúa contra
«alto». Una hoja de cálculo no hace eso.

**El catálogo de amenazas de MAGERIT es catálogo global**, en `catalogo/magerit-amenazas.yaml` y
cargado por el mismo `catalogo:importar`, que pasa a reconocer **tres** claves raíz —`marco`,
`mapeos` y `amenazas`—. Sin `organizacion_id` y sin RLS (invariante 2): «E.1 Errores de los
usuarios» no es un hecho de nadie en particular. Lo que sí es enum es `GrupoAmenaza`, porque los
cuatro grupos son la **estructura** del catálogo y no su contenido — mismo reparto que
`TipoRequisito` frente a `requisitos`. Y no entra en `requisitos` con un `tipo` nuevo: una amenaza no
se implanta, no tiene fila en `aplicabilidad_ens` y `GeneradorImplantaciones` tendría que aprender a
excluirla.

Van 56 amenazas con los códigos originales, **descripciones redactadas para Statera** —misma
disciplina que con ISO 27002— y `revisado: false`: las dimensiones de cada amenaza están asignadas
por criterio y no contrastadas celda a celda contra el Libro II, así que se usan como **sugerencia** y
nunca para descartar nada.

**La metodología es tabla por organización, no `config/`.** El precedente de `config/obsolescencia.php`
empuja en la otra dirección y su propio comentario dice por qué: aquello son hechos del mundo, iguales
para todos los clientes. Los criterios de aceptación de riesgo los fija la dirección de cada
organización (ISO 27001, 6.1.2 a) y el auditor pide el papel firmado. Y hay un segundo motivo que pesa
más: en `config/` un despliegue cambiaría la escala **retroactivamente para todo el histórico** y sin
dejar constancia.

**Cadena de dos eslabones, y gana el primero que exista:** `metodologias_riesgo` →
`MetodologiaDeFabrica`. Igual que la narrativa de los documentos, y por lo mismo: que no haga falta
materializar una fila para poder registrar el primer riesgo. **`GuardarMetodologia` borra la fila
cuando coincide con la de fábrica** —«no lo he tocado» y «no hay fila» tienen que ser lo mismo—, con
una excepción: una fila **aprobada** no se borra aunque coincida, porque ahí ya no dice «no lo he
tocado», dice «lo he mirado y lo firmo», y esa firma es lo que pide el auditor.

**El riesgo residual lo declara una persona; lo derivado se enseña al lado y no lo sobrescribe
nunca.** Es el precedente exacto de `ValoracionEfectiva`. El invariante 4 —«la aplicabilidad se
deriva, no se selecciona»— **no aplica aquí**, y conviene tenerlo escrito: aquél es una derivación
*legal*, con una respuesta correcta en el BOE, y dejar elegir a mano deja a alguien fuera de
conformidad sin enterarse. El residual no tiene BOE: **no existe ninguna función publicada** de
(`estado`, `nivel_madurez`) a riesgo residual, cualquiera que inventáramos sería una opinión de la
herramienta disfrazada de cálculo, y ISO 6.1.3 f) exige que lo apruebe el propietario del riesgo, que
no puede aprobar lo que dedujo la máquina.

Lo que sí hace la herramienta es **señalar la contradicción**: `Riesgo::residualSinRespaldo()` marca
el riesgo cuyo residual declarado baja del intrínseco sin una sola salvaguarda implantada. Es el
mismo papel que hace `Activo::esperaBorradoSeguro()` con un equipo retirado sin constancia de borrado
— no corrige el dato, lo pone delante.

**`riesgo_valoraciones` es histórico al estilo de `documento_versiones`, no de
`implantacion_transiciones`.** Una transición registra un delta sobre un campo; una reevaluación es un
juicio nuevo y entero sobre seis valores correlacionados, emitido contra una metodología que puede
haber cambiado. La especificación pide «histórico **comparable**», y comparar marzo con octubre exige
saber con qué escala se midió marzo. De ahí las dos `jsonb` congeladas: `escala` —sin ella un 12 de
marzo es un número sin unidades— y `salvaguardas` —sin ella la fila **miente** en cuanto una
implantación cambie de estado, que es el mismo motivo por el que el `.docx` se construye
`desdeInstantanea()`—. Cuál es la vigente lo marca un **índice único parcial**, como el borrador de un
documento.

**Riesgo ↔ activo es N:M, contra la letra de §2.2.** «Robo de un portátil» es UN riesgo sobre treinta
portátiles: con clave singular, o se crean treinta riesgos —y el indicador de § 4.14 cuenta treinta
donde hay una cosa que decidir, que es el argumento aritmético que dejó las subtareas fuera de
`tareas`— o se apunta a uno arbitrario y los otros veintinueve son invisibles. Y §2.2 ya se corrigió
una vez por lo mismo, con la tabla única de documentos.

**Y se recorre en los dos sentidos.** `Riesgo::activos()` contesta «sobre qué pesa» y
`Activo::riesgos()` contesta «a qué está expuesto»; sin la segunda, el inventario decía cuánto vale
una cosa y qué se cae con ella, pero no contra qué hay que protegerla. En la tabla de activos va como
**recuento** y no como nivel máximo: el nivel se lee con la escala congelada de cada valoración y eso
no es algo que SQL pueda comparar entre filas sin mentir; quién está por encima del umbral lo
contesta el filtro `riesgo_sobre_umbral`, que delega en `Riesgo::scopeSobreUmbral()` —el mismo que
cuenta el registro— en vez de reescribir la condición. Los dos van por `whereHas` y no por `join`,
por lo mismo que el alcance: un activo con tres riesgos saldría tres veces y la paginación contaría
mal.

Y **el bloque de la ficha no se manda si quien mira no tiene `riesgos.ver`**. Conectar dos módulos
abre una puerta lateral al registro del otro sin que nadie la decida; el frontend decide qué pinta y
nunca qué autoriza.

**Las salvaguardas apuntan a `implantaciones` y no a `requisitos`.** Es la diferencia entre «el ENS
pide cifrado» y «lo tenemos puesto en este sistema», y es lo que hace que un mismo control valga a la
vez de prueba de cumplimiento y de tratamiento de un riesgo **sin registrarlo dos veces**.

---

## Comandos del análisis de riesgos

```sh
php artisan catalogo:importar catalogo/magerit-amenazas.yaml   # las 56 amenazas
php artisan catalogo:importar --dry-run                        # el diff las nombra aparte
```

El seeder de desarrollo deja cuatro riesgos, y cada uno enseña una cosa distinta: uno por encima del
umbral con el **residual sin respaldo**, uno **aceptado** y con la reevaluación vencida, uno **sin
valorar** y uno con **amenaza libre** y decisión de transferir. La metodología se queda a propósito en
la de fábrica y sin aprobar, que es el estado real de partida de cualquier cliente: guardarla
escondería justamente el aviso que hay que ver.

---

## Desvíos vigentes respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **La aplicación corre en un contenedor también en desarrollo**, que es lo contrario
  de lo que decía la cabecera del `docker-compose.yml` («hay PHP local y el ciclo de
  edición es más rápido así»). El motivo no es comodidad: el lock exige diez
  extensiones —`pdo_pgsql`, `gd`, `zip`, `gmp`, `intl`, `pcntl`…— y con el intérprete
  del host lo que funciona depende de qué tenga compilado cada máquina. En Windows
  faltaban `pcntl` y `posix`, que `laravel/horizon` pide como requisitos duros, así que
  **Horizon no podía arrancar** y cada `composer install` necesitaba
  `--ignore-platform-req`. Un entorno donde una dependencia declarada no se puede
  ejecutar no es un entorno de desarrollo, es un entorno parecido. El destino es
  Ubuntu, y aun ahí la imagen es lo que hace que el entorno sea el mismo en todas
  partes. Con la aplicación dentro, `queue` corre Horizon de verdad y el test de
  integración de Gotenberg deja de auto-saltarse.

  Cuatro cosas que no se ven leyendo el `docker-compose.yml`:

  1. **MinIO tiene DOS endpoints, y el reparto no es cosmético: conectar y firmar
     son dos preguntas distintas.** `AWS_ENDPOINT` es por dónde sale el servidor y
     `AWS_ENDPOINT_PUBLICO` es con qué host se firma la URL temporal que abre el
     navegador; los monta `AlmacenServiceProvider` sobre `DiscoConEndpointPublico`,
     y si coinciden —o el público está vacío, que es el caso de producción— no se
     monta nada y el disco es el de Laravel.

     Las dos mitades, porque cada una tiene su trampa:

     - **Firmar.** `temporaryUrl()` firma con SigV4 y **el host va dentro de la
       firma**, así que reescribirlo después la invalida —eso es exactamente lo que
       hace la opción `temporary_url` de Laravel, y por eso no se usa—. El nombre
       tiene que ser desde el principio el que el navegador vaya a resolver:
       `minio.localhost`, porque los navegadores mandan cualquier `*.localhost` a
       loopback por su cuenta y ahí está publicado el 9000.
     - **Conectar.** Ese nombre **el servidor no lo puede usar**: libcurl, desde la
       7.77, resuelve internamente todo nombre terminado en `.localhost` a
       127.0.0.1 sin preguntar al resolutor. El alias de red de Docker estaba bien
       puesto —`getent hosts minio.localhost` devolvía la IP del contenedor— y curl
       ni lo consultaba. Durante cinco días **no se generó un solo PDF**: cada
       subida moría en 0 ms con «Connection refused», y dentro del contenedor
       127.0.0.1:9000 es php-fpm, así que el síntoma no menciona ni a MinIO ni al
       DNS. El endpoint de conexión es `http://minio:9000` y **nunca un
       `*.localhost`**.

     Y el hook que Laravel documenta para esto, `buildTemporaryUrlsUsing()`, **no
     sirve en un disco de S3**: lo consulta `FilesystemAdapter::temporaryUrl()` y
     `AwsS3V3Adapter` sobrescribe ese método sin mirarlo. Registrarlo compila, no
     avisa y no se aplica nunca. De ahí la subclase.
  2. **Las imágenes de MinIO vienen de `quay.io`, no de Docker Hub**, donde
     `minio/minio` y `minio/mc` ya no existen. Un repositorio que no existe se anuncia
     como «pull access denied», que parece un problema de credenciales y no lo es. Van
     ancladas, como Gotenberg y PostgreSQL, y **`minio` se queda sin healthcheck a
     propósito**: el recomendado es `mc ready local`, y que `mc` siga dentro de esa
     imagen es un detalle de MinIO que ya ha cambiado una vez. Quien espera es
     `minio-init`, en su propio bucle.
  3. **Dónde están los servicios lo declara el compose, no el `.env`.** `DB_HOST`,
     `REDIS_HOST`, `GOTENBERG_URL` y `AWS_ENDPOINT` van en el `environment` de `app` y
     `queue` aunque también estén en `.env.example`, y no es duplicación por descuido:
     son la topología de esta red, no una preferencia de nadie. Dotenv **no pisa una
     variable que ya esté en el entorno**, así que el compose gana y un `.env`
     heredado de otra máquina no manda la aplicación a `127.0.0.1` — que dentro de un
     contenedor es el propio contenedor, y el error que sale habla de que PostgreSQL
     no acepta conexiones, no de que el host esté mal.
  4. **Los contenedores escriben en la carpeta del proyecto con el UID del host**
     (`ARG UID`, y `gosu www-data` en el entrypoint). En Linux el uid del contenedor es
     el que queda en el fichero: sin eso, `vendor/`, `node_modules/` y `storage/` se
     llenan de ficheros de root que el dueño del repositorio no puede borrar. php-fpm
     es la excepción y arranca como root, porque el maestro tiene que poder crear sus
     workers.

  El arranque es completo a propósito —dependencias, `APP_KEY`, migraciones, catálogo
  y buckets— porque cada paso manual documentado es un paso que alguien se salta: el
  bucket de MinIO llevaba desde el principio creándose a mano, y olvidarlo fallaba
  mucho después, al subir una evidencia.

- **La clase base se llama `Recurso`, no `Resource`.** El directorio sí es `app/Http/Resources/`, como dice §2.1 del stack, pero `Resource` colisiona con el pseudo-tipo `resource` de PHP: Pint lo pasa a minúsculas en los docblocks (`@extends resource<Sistema>`) y a partir de ahí Larastan no resuelve el genérico. En español encaja además con `ConsultaRecurso`, `DefinicionRecurso` y `RespondeConRecurso`.

- **`EstablecerContextoOrganizacion` va antes de `SubstituteBindings`**, y por eso `bootstrap/app.php` saca este último de su sitio por defecto y lo vuelve a poner detrás. El *route model binding* resuelve los modelos con una consulta de Eloquent que pasa por el scope de organización: sin contexto fijado, el scope no devuelve nada y **cualquier** ruta con `{sistema}` responde 404, también las propias. Hay un test que lo fija (`tests/Feature/Sistemas/CrudTest.php`), y falla si se revierte el orden.

- **Un parámetro de filtro u ordenación no declarado se ignora, no rompe la petición** (`config/query-builder.php`). El 400 por defecto de spatie convierte cualquier URL guardada en un error en cuanto se renombra un filtro. Silencioso no es: `MetaTabla` devuelve el orden y los filtros aplicados de verdad.

- **SSR desactivado** (`INERTIA_SSR_ENABLED=false`). La aplicación vive tras un login: no hay SEO ni primer pintado crítico que lo justifique. Con `@inertiajs/vite` volver a activarlo es cambiar la variable.

- **`DatabaseSeeder` no usa `WithoutModelEvents`.** `PerteneceAOrganizacion` rellena `organizacion_id` en el evento `creating`; silenciar los eventos deja la columna a nulo y RLS rechaza la inserción con un error de privilegios que no dice nada de la causa.

- **Ninguna factory declara `organizacion_id` en su `definition()`**, y es la misma trampa por el otro
  lado. El trait rellena la columna **sólo si viene a nulo**, así que un valor por defecto en la factory
  lo cortocircuita: la fila nace con un tenant que no es el del contexto y el `WITH CHECK` de la
  política la rechaza. `SistemaFactory` lo hacía, era la única del repositorio que lo hacía, y tenía
  **nueve tests de riesgos en rojo desde el día que se escribieron** — el error habla de privilegios y
  no menciona la palabra «organización», así que pasó por una regresión de otra cosa durante semanas.
  En un `state` —`->de($organizacion)`— sí vale: ahí es una decisión explícita de quien escribe el
  test. Lo clava `FactoriesSinOrganizacionTest`.

- **La traza vive en `app/Domain/Traza/`, no en `Domain\Auditoria\`.** Lo que hay ahí es
  `eventos_auditoria`, el log inmutable de quién tocó qué dentro de Statera, y eso es una traza. El
  nombre `Auditoria` hizo falta para el módulo del § 4.12, que registra auditorías de verdad: con los
  dos en la misma carpeta habría un `RegistroAuditoria` a una «s» de distancia de un
  `RegistroAuditorias`. El servicio pasó a `RegistroTraza`; **`EventoAuditoria` conserva su nombre**,
  porque es el modelo de `eventos_auditoria` y la tabla se llama así en la § 2.2. Mismo criterio que
  `IndicadorInventario` → `Indicador`.

- **Los avisos van por el canal de flash de Inertia v3** (`Inertia::flash()` + `router.on('flash')`), no como prop compartido. Un prop se reenvía en cada recarga parcial y el aviso volvía a saltar al filtrar o paginar.

- **Cliente de Redis: `predis`, no `phpredis`.** La máquina de desarrollo no tiene la extensión `phpredis` compilada y el stack no elige cliente. `predis` es PHP puro y no requiere extensión. Si en producción se instala `phpredis`, basta cambiar `REDIS_CLIENT` en el entorno.
- **La aplicación se conecta a PostgreSQL como `statera_app`, no como `statera`.** El rol que crea `POSTGRES_USER` es superusuario, y PostgreSQL exime a los superusuarios y a los roles con `BYPASSRLS` de la seguridad a nivel de fila **incluso con `FORCE ROW LEVEL SECURITY`**. Conectarse con él dejaría la tercera capa del aislamiento presente en el esquema y sin ningún efecto, que es peor que no tenerla porque parece puesta. `statera_app` es `NOSUPERUSER NOBYPASSRLS` y propietario del esquema `public`; `statera` queda como rol administrativo. Lo crea `docker/postgres/init/02-crear-rol-de-aplicacion.sql` en el primer arranque del volumen.

- **`DESIGN.md` se corrigió al código, no al revés.** El documento venía describiendo otra marca: un símbolo en cinta con degradado teal→violeta, teal en hue 212, neutros `ink-*` y Montserrat. Nada de eso estaba implementado y las tres decisiones del código tenían motivo escrito, así que ganaron ellas: **la balanza** (§2), **hue 196** (§3) e **Instrument Sans** (§4). Lo único que se tomó del documento tal cual fue el violeta de acento. Los hex y los contrastes de §3 son conversión calculada de los `oklch` de `app.css`: si se retoca la paleta, se recalculan, no se estiman.

- **El color de marca es teal petróleo, hue 196** (`oklch(0.52 0.13 196)` en claro, `oklch(0.8 0.12 196)` en oscuro; los valores de `app.css`, que es quien manda). No es preferencia estética: la paleta de estados del dominio ocupa 245 (`planificado`), 155 (`implantado`), 70 (`en_progreso`) y 27 (`destructive`), y el teal es el hue libre más alejado de todos ellos. Un botón primario en verde o en ámbar se confundiría con un badge de estado. Los tokens `--estado-*` son semántica del dominio y **no se retocan** al cambiar la marca.

- **`--acento` (violeta de marca) y `--accent` (superficie de hover de shadcn) son cosas distintas y tienen nombres distintos a propósito.** `--accent` es el teal pálido que pintan el ítem activo del sidebar, el menú, el desplegable y el select; unificarlo con el acento de marca los rompe todos a la vez. El violeta vive en `--acento`, `--acento-suave`, `--acento-borde` y la escala `--violeta-*`.

- **El violeta se queda en cuatro sitios y sólo cuatro:** el filete de `CabeceraPagina` (uno por pantalla), la variante `acento` del botón —reservada a flujos de revisión y auditoría, y hoy en «Aprobar y entregar»—, el token `--estado-en-revision` —que desde el § 4.5 **sí tiene flujo detrás**: es el badge de una versión esperando firma— y la balanza del acceso. Ese token tiene desde el § 4.13 **dos dueños**, y no es una grieta: el otro es `EstadoNoConformidad::Cerrada`, «tratada y pendiente de verificar», que significa exactamente lo mismo —hecho y esperando a que alguien con potestad lo confirme—. Un token con dos dueños que quieren decir lo mismo sigue significando algo; el violeta se rompe cuando pasa a ser decoración, no cuando lo usa el segundo flujo de revisión del producto. **No** en enlaces, **no** en el anillo de foco y **no** en el resto de badges de estado. El reparto es 60/30/10 y el violeta que se ve en todas partes deja de ser acento.

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

- **Las gráficas se pintan a mano, y en el PDF las pintará el servidor.** El stack no decía nada de gráficas, ni a favor ni en contra, así que queda escrito aquí. Dos renderizadores por un motivo concreto: en un documento que va a PDF/A-3b y aspira a PDF/UA no debería ejecutarse JavaScript, porque un canvas entra como mapa de bits y se lleva por delante el texto seleccionable. En pantalla, SVG y CSS sobre los tokens de `app.css` (`AnilloProgreso`, `BarraSegmentada`, `components/grafica/`); en el documento, SVG generado en PHP cuando llegue el módulo de documentos. **Chart.js se descartó** por lo anterior y porque obliga a escribir los colores en JavaScript en vez de leerlos de los tokens. Una librería —`d3-scale` y `d3-shape`, que son funciones puras sin DOM— entra el día que haya una serie histórica **con eje de tiempo irregular**: escalas y ticks legibles es lo único que no compensa escribir a mano. **Con el § 4.14 dentro ya hay serie histórica y la librería sigue fuera**, y el matiz es el que importa: el eje de un indicador son cubos etiquetados y equiespaciados que impone `Periodicidad` —«T1 2026», «T2 2026»—, así que los ticks vienen escritos de casa y no hay escala que elegir. Lo pinta `grafica/GraficaSerie.vue` a mano.

- **El resumen del inventario está repartido a propósito entre el panel y la tabla.** Los repartos —por tipo, por ciclo de vida, cobertura de cifrado y copia— viven en el panel, que es donde se pregunta cómo va la cosa —desde el rediseño, en `/panel/organizacion`—; en `/activos` sólo queda lo que pide acción hoy. Antes eran nueve recuentos del mismo tamaño encima de la tabla, varios a cero, mezclando tres cosas distintas: incumplimiento real, dato que falta y perfil. Había que leerse los nueve para saber si algo iba mal. **Un indicador a cero ya no ocupa una tarjeta**: si no hay nada abierto se pinta una línea diciéndolo, que es un estado vacío de verdad y no una fila de ceros. Y toda cifra va con su denominador — «2 sin cifrar» sobre 4 es una urgencia y sobre 307 es un martes.

- **`ResumenInventario::controlesResueltos()` cuenta `no_aplica` como resuelto.** Un router no cifra en reposo porque no almacena nada; contarlo como pendiente pondría un techo que la organización no puede alcanzar por mucho que trabaje, y un indicador que nunca llega al cien por cien se deja de mirar a las dos semanas.

- **No entró ninguna librería de gráficas, y hubo permiso para meterla.** Sigue valiendo lo que ya decía este documento: en un documento que va a PDF/A-3b no debe ejecutarse JavaScript, y una librería obliga a escribir los colores en JS en vez de leerlos de los tokens. La puerta abierta —`d3-scale` y `d3-shape`— es para cuando haya una **serie con fechas irregulares**; un inventario es una foto de hoy, y la serie de un indicador (§ 4.14) va por periodos regulares con la etiqueta puesta, que es el caso en el que esa librería no compra nada. `AnilloProgreso`, `BarraSegmentada` y `grafica/GraficaBarras` ya cubren el caso y ya llevan dentro lo que cuesta acertar: porcentaje con denominador, `role="img"` con su descripción, colores de token y movimiento reducido.

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

- **Los dos `match` sobre cadenas de `MaterializarCuerpo` fallan ruidosamente, y antes no.** Los dos
  —la fuente de un bloque y la clave de una columna— cerraban con un `default` silencioso: una fuente
  declarada en `EsquemaCuerpo::FUENTES` y olvidada allí se materializaba como un grupo **vacío**, o sea
  un apartado que desaparece del PDF sin ningún error, y una columna olvidada salía como una raya en
  las noventa y tres filas. PHPStan no los señala porque no son `match` sobre un enum. Ahora los dos
  lanzan `LogicException`, que es seguro porque `recorrer()` sólo entra si `EsquemaCuerpo::esFuente()`
  y las claves de columna las declara código, nunca un dato de usuario.

  **Y por eso no hay test de regex**, que fue lo primero que se pensó copiando a `EsquemaEnDosIdiomasTest`:
  aquél parsea el fichero fuente porque el otro lado es TypeScript y no se puede leer desde PHP. Aquí
  los dos lados son PHP, así que `HuecosCalculadosTest` recorre `FUENTES` y las columnas de cada tipo y
  comprueba que ninguna rama salta. **Se parametriza solo** para el cuarto documento calculado.

- **Cabo suelto que sigue abierto:** `CuerpoRenderizadoTest` monta todo sobre ISO, así que los bloques
  exclusivos del ENS —`tabla_derivacion`, `notas_anexo_ii`, `tabla_madurez`— y los dos del plan
  —`resumen_plan`, `tabla_sin_trabajo`— no tienen ninguna aserción sobre su HTML materializado.
  `CuerpoSeguroTest` recorre los tipos, pero sobre el **esqueleto** de fábrica, con los huecos sin
  rellenar.

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

- **El plan de acción tiene tres pantallas y cada una es una ruta**: `/tareas`, `/tareas/tablero` y
  `/tareas/calendario`. No son pestañas: el servidor manda datos distintos en cada una —el tablero
  agrupa, el calendario acota por mes— y así se pueden enlazar y compartir. Precedente: `activos.etiquetas`.
  El conmutador **no guarda nada en el navegador**: el estado es la URL, porque un conmutador que
  recuerda la última vista hace que el enlace que alguien pega en un correo abra otra pantalla.

- **El tablero tiene cuatro columnas y no cinco.** `descartada` no tiene columna porque descartar exige
  motivo y eso no cabe en un gesto, y porque una columna de descartadas crece para siempre sin que nadie
  la mire; se descarta desde el menú de la tarjeta, con su diálogo. En «Hecha» sólo entra lo cerrado en
  los últimos catorce días: el tablero enseña el trabajo en curso, y una columna con las trescientas
  cerradas desde enero deja de decir nada. Cada columna lleva tope y su cuenta real, con un «y N más»
  que enlaza a la tabla — quinientas tarjetas en el DOM no son un tablero.

- **Se arrastra con `@atlaskit/pragmatic-drag-and-drop`, y el menú de la tarjeta es el mecanismo
  canónico.** La librería entró porque es agnóstica de framework —sólo APIs del DOM, y CLAUDE.md apuesta
  a que la capa de presentación sea desechable— y porque se apoya en el arrastre nativo del navegador en
  vez de reimplementarlo. Lo que **no** da, y ninguna da, es teclado ni táctil: DESIGN.md § 11 exige que
  todo sea accionable por teclado, así que el menú se construye igual y ofrece exactamente los mismos
  destinos. Va pinada a versión exacta, como TanStack Table: que una librería de interacción cambie de
  comportamiento bajo los pies no lo caza ningún test.

- **La columna prohibida se marca DURANTE el arrastre, leyendo `transiciones` de la tarjeta.** El
  servidor las manda con cada tarjeta justamente para eso. Aceptar el soltado y fallar después se explica
  mucho peor que no dejar soltar. El servidor lo vuelve a comprobar igual —`CambiarEstadoTarea` es quien
  manda—: esto es para que el gesto no mienta, no para fiarse del navegador.

- **El calendario enseña vencimientos, no tareas.** Una tarea que vence y una evidencia que caduca son la
  misma pregunta para quien mira el mes, y § 4.16 —calendario de obligaciones— incluye literalmente la
  caducidad de evidencias. Por eso `CalendarioVencimientos` vive en `app/Domain/Aviso/` y no en `Tarea/`:
  es su primera pieza, y cuando lleguen la revisión por la dirección o la auditoría interna se cuelgan de
  `Fuente` sin mudar nada. Es además **el único sitio donde se decide qué es un vencimiento**: el resumen
  diario que sale por correo se apoya en él, porque si cada uno consultara por su cuenta acabarían
  discrepando y el que se mira menos es el que se queda mal.

- **La rejilla del mes se calcula en el servidor (`RejillaMes`), no en el navegador.** No es preferencia:
  aquí hay con qué probarla —meses de 28, 30 y 31 días, bisiestos, meses que empiezan en domingo, cambios
  de año— y en `resources/js` no hay runner de tests. La aritmética de fechas es justo donde un fallo se
  ve tarde y mal. **Seis semanas siempre**, aunque el mes quepa en cinco: una rejilla que cambia de alto
  al pasar de mes hace saltar la página bajo el cursor. Y **un mes que no se entiende es el de hoy**,
  mismo criterio que los extremos de un rango de fechas: un 500 en una URL que alguien comparte es peor
  que enseñar otro mes. No entró ninguna librería de fechas, ni el `Calendar` de Reka UI: ése es un
  **selector**, no una rejilla de eventos.

- **`IndicadorInventario` y `RepartoInventario` pasaron a `Indicador` y `Reparto`**, y la tira que los
  pinta a `components/TiraIndicadores.vue`. La forma era genérica y el nombre mentía; duplicarlos por
  módulo es el «cuatro dialectos distintos para el sexto» que la capa de recursos existe para evitar. El
  indicador lleva `base` —`/activos`, `/tareas`— para que quien lo pinta no tenga que saber de qué tabla
  salió.

- **La tarjeta del plan en el panel no lleva anillo de progreso, a diferencia del inventario.** Allí el
  denominador es estable —los activos vigentes— y el porcentaje mide cuánto está decidido. Aquí crece cada
  vez que alguien apunta trabajo: «porcentaje de tareas hechas» baja al ser honesto y sube al cerrar cosas
  pequeñas, así que mide actividad y no salud. **Un indicador que castiga por apuntar lo que falta enseña
  a no apuntarlo.** Lo que abre la tarjeta es cuántas quedan abiertas, con su denominador.

- **El plazo y el tono de prioridad viven en el dominio** (`Tarea\Plazo`, `PrioridadTarea::tono()`), no en
  `TareaRecurso`. Los leen la tabla, el tablero y el calendario: con la regla escrita tres veces, la tabla
  dice «Vencida» y el tablero «En plazo» el día que una cambie.

- **Los filtros ya no están atados a la paginación.** `ConsultaRecurso::consultaFiltrada()` aplica los
  `allowedFilters` y devuelve la consulta **sin ordenar ni paginar**; `paginador()` le encadena lo suyo.
  En el cliente, `useFiltrosServidor` guarda el estado de los filtros y `useTablaServidor` es el
  envoltorio que le añade `sort`, `page` y `por_pagina`. La barra (`BarraFiltros.vue`) y `lib/filtros.ts`
  nunca supieron nada de páginas: se reutilizan tal cual. **Nada de fabricar un `MetaTabla` con ceros**
  para una pantalla que no pagina — `useTablaServidor` lo leería y se lo devolvería al servidor.

- **`RespondeConRecurso::filtros()` no usa `Inertia::once()`, a diferencia de `tabla()`.** La clave de
  `tabla()` es `recurso:{clave}` y la comparten las tres pantallas del mismo recurso: si el tablero
  emitiera ahí su lista recortada, ganaría la primera pantalla visitada y la otra vería filtros que no
  le sirven. La lista pesa poco y, como no va en el `only` de las recargas parciales, se queda en el
  cliente igual.

- **El tablero no ofrece `estado` ni `bloqueadas`.** Las columnas **son** el estado: filtrar por él
  vacía tres de las cuatro y deja un tablero que parece roto. Se declara en
  `TareaController::FILTROS_QUE_SOBRAN`, no escondiéndolo en el cliente.

- **El calendario declara sus propios filtros y no hereda los de tareas.** Enseña vencimientos: la mitad
  de lo que sale son evidencias, que no tienen prioridad ni origen. Filtrar por «prioridad crítica» o
  dejaría las evidencias intactas —el filtro mintiendo— o las haría desaparecer sin explicación. Los
  tres de `FiltrosVencimiento` —fuente, responsable, sólo lo vencido— significan lo mismo para las dos
  fuentes, y seguirán valiendo cuando § 4.16 traiga el resto de lo periódico.

- **En el calendario el color dice QUÉ es la cosa, y el rojo que se pasó de fecha.** `Vencimiento` lleva
  dos pares de campos y no uno: `tono` es distancia temporal y lo lee el **correo diario**;
  `estadoTono`/`estadoEtiqueta` son el estado —de la tarea, o la vigencia de la evidencia— y los lee el
  calendario. Reinterpretar `tono` habría cambiado el asunto del correo sin querer. **Lo vencido gana
  siempre** y es el único rojo de la pantalla; hay un test que recorre los estados comprobando que
  ninguno se lo gasta. Y el estado viaja **también en texto**, porque § 11 no deja que dependa del color.

- **Los días del calendario se distinguen con cuatro fondos sólidos**, no con alfa. Antes eran
  `bg-muted/40` y `bg-muted/20` sobre `bg-card` —dos transparencias casi idénticas y, peor, las dos en
  el mismo atributo, así que decidía el orden en que Tailwind emite las clases y no el código—. Es el
  mismo fallo que ya está documentado para las celdas ancladas de la tabla. Hoy lleva además la barra de
  2 px del ítem activo del sidebar: `accent` es un teal demasiado pálido para cargar solo con eso.

- **Tope de tres vencimientos por día, con su «y N más».** Un día con doce estiraba la fila entera y el
  mes dejaba de caber en la pantalla. Mismo patrón que el tope por columna del tablero.

- **Una subtarea es un paso de una lista de comprobación, no una tarea.** No está en `tareas` con un
  `parent_id` y el motivo es aritmético: **hoy hay trece sitios que cuentan tareas** —panel,
  indicadores, repartos, columnas del tablero, calendario y aviso diario— y con las subtareas como filas
  de `tareas` cada uno tendría que decidir si suma la madre, las hijas o las dos. El día que uno se
  despiste, el panel dice doce abiertas donde hay cuatro cosas que hacer. **Contar de más es el fallo
  caro, y aquí se evita no dando la ocasión**; hay un test que lo fija comparando todas las cifras antes
  y después de trocear las tareas.

  Lo que se pierde —asignar un paso o ponerle fecha— se resuelve con una tarea de pleno derecho
  vinculada al mismo requisito, no con una subtarea con más campos.

- **La lista se guarda entera, en una sola ruta.** Añadir, renombrar, marcar, reordenar y borrar van
  juntos en una lista de comprobación, y el orden llega implícito en la posición del array, así que
  reordenar no necesita ni campo ni gesto propio. `hecha_en` **no se vuelve a sellar** si ya estaba
  marcado: la fecha es cuándo se hizo el paso, no cuándo se guardó la lista. Y un `id` que no es de esa
  tarea se trata como un paso nuevo — lo que llega del cliente no manda sobre a quién pertenece una fila.

- **Marcar todos los pasos no cierra la tarea.** Cerrarla es una decisión con su transición, su fecha y
  su autor; deducirla de una casilla dejaría el histórico contando algo que nadie decidió.

- **Un estado se comunica con tres canales: color, icono y texto.** Lo pedía `DESIGN.md` § 3 desde el
  principio —«nunca comunicar un estado sólo con color»— y los badges se conformaban con un punto, que
  no identifica nada: es el mismo círculo para «Implantado» que para «Bloqueada». Ahora **el icono lo
  declara el dominio** (`EstadoTarea::icono()` y once enums más) y no el mapa de CSS, porque el mismo
  tono significa cosas distintas según el módulo: el azul de `planificado` es «Planificado» en una
  implantación y «Bloqueada» en una tarea, y un icono por tono mentiría en una de las dos.

  `IconoTipo` **no pinta nada si el nombre no está en su mapa** —falla en silencio—, así que
  `tests/Unit/Diseno/IconosTest.php` comprueba que todo nombre que el servidor puede emitir está en el
  cliente, que no sobra ninguno, y que dos estados del mismo tono no comparten icono.

- **Ni emojis ni una segunda librería de iconos.** Se valoró y se descartó: un emoji se dibuja distinto
  en cada sistema operativo, no hereda el color del estado ni el tema oscuro, el lector de pantalla lo
  anuncia antes que la etiqueta y acaba en el PDF que se le entrega al auditor. § 7 ya pedía «un solo
  estilo de icono y un solo grosor de trazo en toda la aplicación». Lo que un emoji promete —que se
  reconozca sin leer— lo da un icono de línea sin romper nada.

- **`lib/tonos.ts` es el único mapa de tono → clases.** Estaba copiado en cinco sitios —`CeldaBadge`,
  `ColumnaTablero`, `Calendario`, `BarraSegmentada` y `GraficaBarras`— y cinco copias del mismo
  vocabulario es cómo se acaba con un «implantado» verde en una pantalla y gris en otra. Lleva `badge`,
  `punto`, `relleno`, `tramo` e `icono` de respaldo. **`tramo` no es `relleno`**: en una barra por
  tramos los dos grises van más apagados a propósito, porque son el hueco que queda por llenar y a plena
  saturación pesan tanto como lo que sí se ha hecho.

- **Los botones de transición se pintan como el estado al que llevan** (`BotonEstado.vue`). Pulsa el que
  se parece al que quieres. A intensidad de badge —fondo suave y texto del tono, como ya hace la variante
  `destructive`— y **nunca de relleno**: un solo primario por vista, y dos botones de color lleno hacen
  que no mande ninguno. En implantaciones el cambio de estado es un formulario con desplegable y nota,
  no cuatro botones, así que ahí se queda como está.

- **La deuda de contraste de los estados está saldada, y con ella la de protanopía.**
  `en-progreso`, `no-iniciado` y `no-aplica` daban 3.32, 3.14 y 3.47 sobre su fondo suave, por debajo
  del 4.5:1 que pide § 11. El disparador fue el botón de transición: en cuanto un tono pinta la etiqueta
  de un control, deja de ser un matiz y pasa a ser texto que hay que poder leer. Se bajó la luminosidad
  del tono de texto sin tocar hue ni croma, que es como `DESIGN.md` decía que había que arreglarlo, y de
  paso `implantado` y `en_progreso` pasaron de ΔE 5.9 a 7.6 con protanopía.

  **Y el «validador de paletas» que `DESIGN.md` citaba no existía**: las cifras estaban escritas y no
  había forma de comprobarlas. Ahora es `tests/Unit/Diseno/PaletaTest.php`, que lee los `oklch` de
  `app.css` —no una copia—, los convierte a sRGB, mide contraste y distancia con simulación de
  protanopía (Viénot 1999), y **reproduce las cifras que el documento tenía anotadas**. Vive en `tests/`
  y no en `app/` porque el producto no lo ejecuta nunca. La pareja de grises `no-iniciado`/`no-aplica`
  sigue a ΔE 2.3 a propósito y está declarada como separada por el icono.

- **El rojo tiene un tercer dueño: `NivelRiesgo::MuyAlto`.** Hasta ahora eran dos —una evidencia
  caducada y una tarea fuera de plazo—, y este documento decía que el rojo es de lo que **se pasó de
  fecha**. La regla de verdad, la de `DESIGN.md` §3, es más ancha: rojo para lo que va mal de verdad, no
  para lo que es grande. `MuyAlto` entra porque **es, por construcción, estar en o por encima del umbral
  crítico que puso la propia organización** — no un juicio de la herramienta sobre si el número le
  parece alto. Lo que **no** gasta rojo es `DecisionRiesgo`: aceptar un riesgo alto es una decisión de
  la dirección, no un incumplimiento, y pintarla de alarma sería convertir en fallo algo que la
  organización tiene todo el derecho a decidir.

- **Las bandas de `NivelRiesgo` salen de los umbrales, no de quintiles de la escala.** `MuyAlto` es el
  umbral crítico, `Alto` es por encima del de aceptación, y los tres de abajo reparten en tercios la
  zona aceptable. Eso es lo que hace que `porEncimaDelUmbral()` y `NivelRiesgo::sobreUmbral()` digan
  siempre lo mismo, **por construcción y no por coincidencia**. Con quintiles, un riesgo podía salir
  «muy alto» —badge rojo— estando dentro del apetito declarado, y entonces la tabla y el indicador del
  panel discreparían sobre la misma fila. Con umbrales muy bajos alguna banda queda vacía, y es
  correcto: la organización ha decidido que casi nada le resulta aceptable y la herramienta no le
  inventa grados que no ha pedido.

- **El trigger de `riesgo_valoraciones` deja apagar `vigente` en una fila aceptada.** Es la única
  excepción a la inmutabilidad y hace falta sí o sí: jubilar la anterior es el primer paso de toda
  reevaluación, y una valoración aceptada hace un año es justo la que hay que jubilar al volver a mirar
  el riesgo. Un trigger que lo bloqueara dejaría un riesgo aceptado **sin poder revaluarse nunca**, que
  es lo contrario de lo que pide § 4.3. Se compara el registro entero con `vigente` neutralizado en vez
  de enumerar columnas: una columna nueva quedaría fuera de la lista y sería editable sin que nadie lo
  notara.

- **`nota_aceptacion` es columna propia, y no se reutiliza `nota`.** Las escriben dos personas en dos
  momentos: `nota` es el razonamiento de quien valoró y `nota_aceptacion` es lo que dijo quien firmó
  —«aceptado en el comité del 3 de marzo»—. Con una sola columna, firmar pisaría el razonamiento, que
  es justo lo que el auditor quiere leer al lado de la firma.

- **`riesgos.aceptar` es el tercer verbo de permiso del producto**, junto a `sistemas.valorar`. Un
  técnico registra riesgos y los puntúa; firmar que la organización convive con una exposición es de
  dirección, y no es un matiz de permisos: es la razón entera por la que ISO 6.1.3 f) pide la
  aprobación del propietario del riesgo. Cubre también definir la metodología, porque fijar el apetito
  es decidir de antemano qué se va a poder aceptar. **Y en `Rol::permisos()` hay que acordarse a mano**:
  `ResponsableSeguridad` usa `Permiso::cases()` y se entera solo, pero `Tecnico` y `Auditor` son listas
  literales y olvidarlas no rompe nada — el módulo simplemente no aparece.

- **`MetodologiaVigente` se registra como `scoped`, no como `singleton`**, por lo mismo que
  `ContextoOrganizacion` y con un fallo aún más silencioso: la metodología resuelta es la de UNA
  organización, y un worker que la arrastrara al job siguiente valoraría los riesgos de un cliente con
  la escala de otro. No reventaría nada: devolvería números plausibles y equivocados.

- **`Riesgo::scopeSobreUmbral()` resuelve el umbral él mismo cuando no se le pasa**, aunque un scope que
  pide un servicio no sea bonito. El indicador del panel y el filtro de la tabla invocan los scopes por
  nombre y **sin argumentos** —`$consulta->{$scope}()`, `Filtro::porScope()`—, así que un parámetro
  obligatorio obligaría a escribir la condición una segunda vez para el filtro. Y esa es exactamente la
  duplicación que deja el panel diciendo 12 y la tabla enseñando 9.

- **`CalculoRiesgo::bandas()` devuelve el tono y el icono dentro de cada banda.** El cliente no los
  deduce de un mapa propio: es la misma regla que con los estados —el mismo tono significa cosas
  distintas según el módulo—, y un mapa nivel→color en `MatrizRiesgo.vue` sería la sexta copia del
  vocabulario que `lib/tonos.ts` existe para centralizar.

- **La matriz de riesgo es rejilla CSS, no SVG**, aunque sea una gráfica. `DESIGN.md` §9 admite las dos
  —«SVG y CSS a mano sobre los tokens»— y aquí gana CSS por un motivo concreto: tiene que caber a 375 px
  sin scroll horizontal, y una rejilla se encoge con su contenedor mientras que un `viewBox` escala el
  texto hasta hacerlo ilegible. Es lo que ya hace `BarraSegmentada`; `AnilloProgreso` es SVG porque allí
  hay un arco que dibujar. **El número sólo se pinta en las celdas señaladas**: pintarlo en las
  veinticinco convierte el mapa de calor en una tabla de multiplicar, que es justo lo que la cuadrícula
  evita tener que leer.

- **`CampoTexto` gana `etiquetaOculta`.** `CampoBase` ya lo tenía y `CampoTexto` no lo reenviaba. Lo
  necesitan las filas repetidas —los diez escalones de las dos escalas—, donde el título de la sección ya
  dice qué son y repetir «Etiqueta del escalón 3» en cada fila es ruido. El `<label>` sigue existiendo y
  asociado: quitarlo dejaría el control sin nombre accesible.

- **Los enums de riesgo no llevan `#[TypeScript]`, y es deliberado.** `NivelRiesgo`, `DecisionRiesgo` y
  `GrupoAmenaza` viajan serializados como `ValorEtiquetado` —valor, etiqueta, tono, icono—, igual que
  `EstadoTarea`, y las pantallas declaran interfaces locales para lo que el controlador serializa a
  mano. El atributo lo llevan los que cruzan **como tipo**: `Permiso`, `Rol`, `Fuente`, `Filtro`.

- **`User` NO lleva `PerteneceAOrganizacion`, y toda consulta de usuarios se acota a mano.** La
  autenticación tiene que poder encontrar a alguien *antes* de saber de qué organización es, así que
  ese modelo se queda fuera de las tres capas: no hay scope global y no hay RLS. La consecuencia es
  que un `User::query()` inocente —el desplegable de responsables, la lista de destinatarios de un
  acuse— **lista a los usuarios de todos los clientes**, y no lo caza ningún test de aislamiento
  porque el modelo no está protegido en ninguna capa. Se acota con
  `->where('organizacion_id', …)`, y la organización se toma preferentemente de la fila que se está
  mirando —`$version->organizacion_id`— y no del contexto: bajo RLS es la misma, y así la consulta no
  depende de que alguien haya fijado el contexto antes. Ya había un caso de esto en el formulario de
  documentos, corregido al llegar el § 4.5.

- **La cifra de un documento en la tabla sale por subconsulta, incluido su estado documental.** Es la
  misma regla que ya regía para el número de versión: un `join` contra `documento_versiones`
  multiplicaría las filas y la paginación contaría mal. Y lo que hace que un `max()` sobre un texto
  no sea un disparate es que los dos índices únicos parciales —un borrador vivo, una aprobada viva—
  garantizan que agrega sobre una fila como mucho.

- **`GenerarDocumento::encolar()` hace `refresh()` tras insertar**, igual que `CrearTarea`. Los valores
  por defecto de `estado` los pone la base, y repetirlos en el modelo sería el mismo dato en dos
  sitios que pueden desincronizarse. Sin eso, un borrador recién encolado llega con `estado` a nulo y
  lo primero que lea su máquina de estados revienta con un «call to a member function on null» que no
  menciona la palabra «estado». Por lo mismo, `DocumentoVersionFactory` declara `estado` explícito:
  `create()` no relee la fila.

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
