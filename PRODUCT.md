# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**Hoy no hay ningún usuario real.** Se construye antes de usarse: la primera organización que estrene la herramienta —Avanza o un cliente— todavía no está decidida. Todo lo que hay en la base de datos es sintético y lo pone el seeder de desarrollo.

Esto importa porque el repositorio dice dos cosas distintas: la especificación habla de «uso interno de Avanza» y `CLAUDE.md` prohíbe datos reales de Avanza en seeds, fixtures, demos y tests. La segunda manda. El proyecto es personal de César y la separación se mantiene explícita.

**Audiencia del producto** (`DESIGN.md`): responsables de seguridad, consultores de cumplimiento, direcciones de sistemas y —por el ENS— administración pública y sus proveedores. Gente que decide con criterio técnico, que lee la letra pequeña y que desconfía del marketing vacío.

**Tres roles en el modelo**, y son papeles distintos, no niveles de un mismo permiso:

| Rol | Qué hace |
|---|---|
| Responsable de seguridad | Define el alcance, valora sistemas y firma la aceptación de riesgos |
| Técnico | Implanta y prueba: mueve estados, sube evidencias y las vincula. No redefine el alcance |
| Auditor | Sólo lectura. Ve el cumplimiento y sus pruebas, y no altera nada de lo que audita |

Dos verbos de permiso están aparte a propósito: `sistemas.valorar` y `riesgos.aceptar`. Firmar que la organización convive con una exposición es de dirección, no de quien la registró — es la razón entera por la que ISO 27001 6.1.3 f) pide la aprobación del propietario del riesgo.

**Decisión confirmada en esta sesión: se diseña ya para varios clientes**, incluidas categorías ENS media y alta, y para gente que no conoce ni la herramienta ni los marcos. Eso va por delante del encuadre de «fase actual: uso interno, básica» que llevan la especificación y `CLAUDE.md`, y se anota aquí para que nadie lo lea como una contradicción que hay que resolver hacia atrás. Lo que **no** cambia son las exclusiones de alcance: sigue sin haber registro self-service, facturación ni panel de superadministración.

## Product Purpose

Gestionar el ciclo completo de **ISO/IEC 27001:2022** y del **ENS (RD 311/2022)** en una sola herramienta, con mapeo cruzado entre los dos marcos: cada evidencia, tarea y documento se registra **una vez** y cuenta para todos los marcos donde aplique.

El problema que resuelve es concreto y está ocurriendo ahora: ese trabajo se lleva en hojas de cálculo duplicadas, y una misma evidencia que sirve a controles de los dos marcos se mantiene por separado en dos sitios que se desincronizan.

El éxito se mide de dos formas, y las dos son comprobables:

1. Que una auditoría se pase con lo que la herramienta produce —la Declaración de Aplicabilidad, la del ENS, el registro de evidencias, el histórico de estados— sin rehacer nada a mano.
2. Que nada se mantenga dos veces.

## Positioning

Cuatro cosas que una hoja de cálculo no hace, y que un gestor de cumplimiento genérico tampoco:

- **La DdA de ISO y la del ENS son dos consultas sobre la misma tabla**, no dos documentos mantenidos a mano. Y la SoA imprime «exigido por el ENS (op.acc.2)» como justificación de inclusión: un requisito legal es una justificación legítima para ISO, y de paso es el argumento del producto impreso dentro del entregable.
- **La aplicabilidad se deriva, no se selecciona.** Se valoran las cinco dimensiones, el motor calcula la categoría y de ahí sale el conjunto exigible. Nadie marca controles a mano.
- **El impacto de un riesgo sale de la valoración efectiva del activo**, que sube por el grafo de dependencias: una base de datos valorada «bajo» que sostiene un servicio esencial se puntúa contra «alto». Ese es justo el activo que una hoja de cálculo deja desprotegido.
- **La herramienta entra en el alcance de su propio SGSI.** Contiene el inventario, las vulnerabilidades y las evidencias, así que 2FA, cifrado en reposo, copias verificadas y traza inmutable no son aplazables. Es una restricción de producto, no una preferencia técnica. *(Las **vulnerabilidades** todavía no: no hay registro, y `riesgos.vulnerabilidad` es la narrativa MAGERIT de un escenario, no un hallazgo técnico con severidad y plazo. Está anotado en el invariante 8 de `CLAUDE.md` y llega con su módulo.)*

## Operating Context

**La pregunta del auditor no es «¿está implantado?», es «¿desde cuándo?».** De ahí que todo estado lleve histórico con fecha y autor, y que un documento entregado sea inmutable y demostrable tal cual se firmó.

**Superficies construidas**, todas tras el login:

`/panel` · `/contexto` (+ análisis, cuestiones) · `/partes-interesadas` · `/sistemas` (+ valoración) · `/implantaciones` · `/activos` (+ etiquetas QR) · `/evidencias` · `/tareas` (+ tablero, calendario) · `/riesgos` (+ metodología) · `/auditorias` (+ checklist) · `/no-conformidades` · `/indicadores` · `/documentos` (+ plantillas, cuerpo editable, versiones) · `/revisiones` · `/perfil` (+ segundo factor)

**Entregables que salen de la herramienta:** la SoA de ISO y la DdA del ENS, en PDF/A-3b con su huella, almacenados y no regenerados; más una copia de trabajo en `.docx` construida desde la instantánea de la versión, nunca desde una consulta nueva.

**Ritmos de uso:** evidencias que caducan, tareas con plazo, revisiones del inventario que hay que registrar, reevaluación de riesgos contra una metodología que la dirección aprueba y firma. Un resumen diario por correo dice cómo está la cosa; si no hay nada que decir, no se envía.

**Web pública:** prevista para la fase vendible, hoy sin construir. `DESIGN.md` ya se declara referencia de las dos superficies, así que las decisiones visuales deben servir también para ella, pero no es trabajo cercano.

## Capabilities and Constraints

**Construido** (§ de la especificación): catálogo normativo importable y versionado, motor de categorización ENS, implantaciones con transiciones y recálculo, capa de recursos genérica, contexto de la organización con DAFO y partes interesadas (4.1), inventario de activos con grafo de dependencias (4.2), análisis de riesgos con MAGERIT (4.3), documentos con Gotenberg, flujo de aprobación y narrativa editable (4.5), evidencias (4.6), plan de acción con tablero y calendario (4.7, 4.16 parcial), auditorías con checklist y hallazgos (4.12), no conformidades con verificación de eficacia (4.13), indicadores y mediciones con serie histórica (4.14).

**Pendiente de los 19 módulos:** personas (4.8), proveedores (4.9), incidentes (4.10), continuidad (4.11), revisión por la dirección (4.15), el calendario de obligaciones completo (4.16), dos tercios del flujo de conformidad (4.17) e informes y exportación (4.18).

**Huecos conocidos que no son un módulo de la lista.** Se anotan aquí porque la lista de diecinueve no los recoge y descubrirlos cuesta una tarde; los tres primeros son los que bloquean al 4.15.

*Cláusulas con requisito en el catálogo, con su implantación esperando, y sin ningún sitio donde escribirse* — que es exactamente lo que le pasaba al 4.1 hasta que se construyó:

| Cláusula | Qué falta |
|---|---|
| **6.2 Objetivos de seguridad** | Objetivos medibles con su plan: qué, quién, con qué recursos, para cuándo y cómo se evalúan. El 4.14 mide; comprometerse a una cifra es otra cosa |
| **10.1 Mejora continua** | La oportunidad de mejora sólo existe como `TipoHallazgo::OportunidadMejora` **dentro** de una auditoría; fuera de una no hay dónde apuntarla |
| **7.4 Comunicación** | Qué se comunica, cuándo, a quién y quién lo hace. El «a quién» ya está en `partes_interesadas` |
| **6.3 Planificación de cambios** | Está en el catálogo como requisito `6.3` y citada en la especificación; sin módulo |
| **5.3 Roles y autoridades** | Los roles ENS y la incompatibilidad que la especificación pide **impedir**. Los PDFs ya lo declaran como limitación |

*Cosas medio construidas, que es peor que ausentes porque parecen hechas:*

- **Perfiles de cumplimiento CCN-STIC 890.** `perfiles_cumplimiento`, `perfil_requisitos`, `sistemas.perfil_id`, `OrigenExigencia::Perfil` y el paso 4 de `MotorCategorizacion` están escritos y probados. **Cero datos en los YAML, cero clave en el importador, cero interfaz.** El perfil de requisitos esenciales es justo el que usaría un cliente pequeño de categoría básica.
- **Informe INES.** `implantaciones.nivel_madurez` guarda la escala L0–L5 porque la especificación dice «usada en el informe INES», el 4.16 lo lista como obligación anual, y el informe no existe. Mismo patrón que `tareas.coste_estimado` antes del plan de adecuación.
- **Atributos de la ISO 27002.** Los 93 controles los traen en el YAML y el 4.4 pide filtrar y agrupar por ellos; `ImplantacionRecurso` no declara ese filtro.
- **`personas` no es `users`.** La especificación define `personas` con puesto, alta, baja y roles ENS; hoy sólo hay cuentas de Statera, y `User` ni siquiera lleva el scope de organización. La limitación del acuse de lectura ya lo dice por escrito en el PDF.
- **Guías CCN-STIC por medida y catálogo CPSTIC**, que la especificación pide enlazar desde cada medida: no cargados.

**Qué muerde hoy y qué no**, que es lo que ordena los módulos pendientes: en categoría básica **ya son exigibles** `op.exp.7` (gestión de incidentes, 4.10) y `mp.per.2/3/4` (deberes, concienciación y formación, 4.8), y no tienen dónde registrarse. En cambio `op.ext.*` (proveedores, 4.9) y `op.cont.*` (continuidad, 4.11) están en `no_aplica` en básica y sólo aparecen al subir a media o al valorar disponibilidad. Un sistema básico echa de menos antes personas e incidentes que proveedores y continuidad.

**Fuera de alcance, y sigue estándolo:** facturación y suscripciones, registro self-service, panel de superadministración, white-labeling, integraciones con SIEM o escáneres, aplicación móvil. NIS2 no se carga todavía, pero el modelo de marcos tiene que admitirla sin cambios estructurales.

**Multi-tenancy es la frontera de seguridad principal** y se implementa a mano, con tres capas: `organizacion_id` en toda tabla de datos propios, global scope de Eloquent y Row Level Security en PostgreSQL. Un recurso de otra organización responde **404 y no 403**: decir «existe pero no es tuyo» ya sería filtrar información, y eso convierte una página de error en algo que ve gente real y con frecuencia.

**El dominio se nombra en español** porque los marcos están en español: requisitos, implantaciones, evidencias, no conformidades, refuerzos, aplicabilidad ENS, salvaguardas, activos, sistemas, amenazas. Traducirlos sólo añadiría una capa de traducción mental.

**Distinciones del dominio que la interfaz no puede aplanar**, porque cada una separa dos cosas que parecen la misma:

- `retirado` ≠ `dado_de_baja`: la segunda exige constancia de que se borró lo que contenía (`mp.si.5`). Un disco retirado en un cajón con los datos dentro es un hallazgo.
- `hecha` ≠ `descartada`: descartar es decidir que no se hará, y exige motivo.
- «Por confirmar» ≠ «No»: la ausencia de dato es una pregunta abierta y se cuenta aparte. `No aplica` tampoco es `No` — un router no cifra en reposo porque no almacena nada.
- La clasificación de la información no sustituye al nivel del Anexo I: una se decide y se estampa, el otro se deriva de valorar el perjuicio. Conviven.

**Decisiones de producto explícitamente abiertas** —a registrar cuando se tomen, no a inventar—: qué organización estrena el producto; cuándo entra la web pública y qué dice; si los flujos formales de ENS media y alta pasan de modelados a implementados, que es lo que la decisión de «diseñar ya para varios clientes» pone sobre la mesa; y si hay obligación legal de accesibilidad (ver más abajo).

## Brand Commitments

- **El producto se llama `Statera` a secas.** Nunca «RM Statera» ni «RM - Statera». El respaldo va en letra pequeña: «un producto de RM Technology».
- **El símbolo es una balanza.** La nube de puntos animada del acceso sale del `viewBox` del propio logotipo, así que lo que gira **es** el logotipo; redibujar el símbolo obliga a redibujar la nube.
- **Color de marca: teal petróleo, hue 196.** No es estética: la paleta de estados del dominio ocupa 245, 155, 70 y 27, y el teal es el hue libre más alejado de todos. Un botón primario en verde o ámbar se confundiría con un badge de estado.
- **El violeta de acento vive en cuatro sitios y sólo cuatro.** El reparto es 60/30/10, y un acento que se ve en todas partes deja de ser acento.
- **Tipografía: Instrument Sans y JetBrains Mono.**
- **Voz:** frases cortas y verbos activos. Se habla de lo que el usuario hace, no de cómo está construido el sistema. Los errores explican qué ha pasado y qué hacer, sin pedir perdón ni culpar. Nombres de norma exactos —«ISO/IEC 27001:2022», «Esquema Nacional de Seguridad (Real Decreto 311/2022)»—, porque en este sector la imprecisión cuesta credibilidad. **Sin exclamaciones y sin emoji en la interfaz.**
- **La interfaz tiene que parecerse más a una herramienta de auditoría que a una landing de SaaS.**
- `DESIGN.md` es la fuente de verdad de todo lo visual y manda sobre el apartado de marca del stack. Si un color, un tamaño o un radio no está allí, se añade allí antes de usarlo.

## Evidence on Hand

**Real y comprobable:**

- `catalogo/*.yaml` — ISO 27001:2022, ENS RD 311/2022, mapeos cruzados y 56 amenazas de MAGERIT. El Anexo II se contrastó celda a celda contra el texto consolidado de BOE-A-2022-7191: salieron **73 celdas mal de 273**, entre ellas nueve medidas exigibles en categoría básica marcadas como `no_aplica`. Corregido y clavado en `tests/Feature/Catalogo/AnexoIIVerificadoTest.php`.
- `DESIGN.md` con paleta medida, no estimada: `tests/Unit/Diseno/PaletaTest.php` lee los `oklch` de `app.css`, convierte a sRGB y reproduce los contrastes y las distancias con simulación de protanopía.
- Documentos generables hoy: `SOA-SGSI-01` y `DDA-ENS-01`.
- Datos de demostración **sintéticos** del seeder: una organización, tres usuarios, un sistema con 52 implantaciones, cinco activos con dependencias, tareas y cuatro riesgos —cada uno enseñando una cosa distinta, incluido el residual sin respaldo—.

**Ausencias que el trabajo futuro no puede rellenar inventando:** no hay ni un cliente, ni un testimonio, ni un caso de éxito, ni precio, ni licencia, ni benchmark, ni certificación obtenida, ni web pública, ni una línea de copy de marketing. Y hay una prohibición expresa: **ningún dato real de Avanza** en seeds, fixtures, demos ni tests.

Los tres YAML del catálogo siguen con `revisado: false`, y por dos motivos que son de modelo y no de datos: nueve medidas están moduladas por varias dimensiones a la vez y el esquema guarda una sola, y diez celdas exigen un refuerzo **a elegir** entre varios que el esquema no sabe expresar. Mientras tanto se exige de más, nunca de menos. Los dos documentos lo declaran por escrito: **un auditor respeta una limitación declarada y suspende una inventada.**

## Product Principles

1. **Registrar una vez, contar en todos los marcos.** Es la razón de existir del producto. Cualquier flujo que obligue a apuntar lo mismo dos veces reintroduce el problema que vino a resolver.
2. **Lo que se deriva no se marca a mano; lo que decide una persona no lo deduce la máquina.** La aplicabilidad la calcula el motor porque tiene una respuesta correcta en el BOE. El riesgo residual lo declara su propietario porque no existe ninguna función publicada que lo calcule, e ISO exige que lo apruebe alguien que no puede aprobar lo que dedujo una máquina. Confundir los dos casos rompe uno de los dos.
3. **Lo que se enseña junto se cuenta igual.** Toda cifra viaja con su denominador y con el alcance de lo que tiene al lado. Un panel que dice 12 donde la tabla enseña 9 deja de mirarse, y a partir de ahí nadie se fía de ninguna cifra.
4. **La contradicción se señala, no se corrige.** Un residual que baja sin una sola salvaguarda implantada, un equipo retirado sin constancia de borrado: la herramienta los pone delante y no toca el dato. Corregirlo en silencio sería opinar en nombre de la organización.
5. **Un indicador que castiga por apuntar lo que falta enseña a no apuntarlo.** Por eso el plan de acción no lleva porcentaje de tareas hechas. Toda métrica se juzga por lo que incentiva, no por lo fácil que es de calcular.

## Accessibility & Inclusion

Nivel exigido hoy por `DESIGN.md` §11, y verificado por tests donde se puede verificar:

- **Contraste 4.5:1 en texto.** `PaletaTest` lo mide sobre los `oklch` reales; la deuda que había en tres estados está saldada.
- **Un estado se comunica con tres canales: color, icono y texto.** Nunca sólo color. El icono lo declara el dominio y no el mapa de CSS, porque el mismo tono significa cosas distintas según el módulo.
- **Todo accionable por teclado.** Es lo que obliga a que el tablero de tareas ofrezca por menú exactamente los mismos destinos que por arrastre: ninguna librería de arrastrar y soltar da teclado ni táctil.
- **`prefers-reduced-motion` se resuelve en tres capas**, y lo decorativo se apaga entero.
- **375 px de ancho sin scroll horizontal.** Es lo que decidió que la matriz de riesgo fuera rejilla CSS y no SVG.

**Abierto:** no se ha establecido ninguna obligación **legal** de accesibilidad. Si entran clientes de sector público —plausible, porque el ENS va justamente de eso— habrá que decidir si aplica EN 301 549 / RD 1112/2018, que es un listón distinto y comprobable por terceros. No se da por supuesto ni en un sentido ni en otro.
