# Statera — Sistema de diseño

> Convierte la seguridad en un sistema que puedes gestionar, medir y demostrar.
> Seguridad · Cumplimiento · Confianza

Referencia única para la web pública y la plataforma de Statera: gestión de seguridad de la información, cumplimiento normativo (ISO 27001, ENS) y gestión de riesgos.

Este archivo es la fuente de verdad de lo visual y manda sobre el apartado de marca de `stack-gestor-cumplimiento.md`. Los valores están tomados de `resources/css/app.css`, que es donde viven de verdad; los hex y los contrastes son conversión calculada de esos `oklch`, no estimaciones. Si algo no está aquí, se añade aquí antes de usarlo.

**Audiencia.** Responsables de seguridad, consultores de cumplimiento, direcciones de sistemas y —por el ENS— administración pública y sus proveedores. Gente que decide con criterio técnico, que va a leer letra pequeña y que desconfía del marketing vacío. La interfaz tiene que parecerse más a una herramienta de auditoría que a una landing de SaaS.

---

## 1. Principios

**Equilibrio.** Es lo que dice el símbolo y lo que dice el nombre. Un solo elemento fuerte por pantalla; todo lo demás en calma. Si dos cosas compiten por la mirada, una baja de nivel.

**Demostrable.** El producto vende evidencia, no sensación. Cada estado va acompañado de su fecha, su responsable y su origen. Nada de porcentajes sin denominador ni semáforos sin explicación: si alguien pregunta «¿por qué está en verde?», la interfaz ya tiene la respuesta a la vista.

**Estructura.** La información se lee por su forma antes que por su texto. Jerarquía tipográfica clara, alineación estricta, agrupación por proximidad. Bordes y divisores solo donde separan cosas realmente distintas.

**Protección.** El usuario nunca teme pulsar. Acciones destructivas separadas, confirmación explícita, estados reversibles y siempre visible dónde está y qué va a pasar.

---

## 2. Marca

### Símbolo

**La balanza romana.** *Statera* es su nombre en latín, y el símbolo es literal: el fiel vertical, el brazo horizontal con el punto de apoyo en el centro, y dos platillos colgando. Dos marcos normativos puestos en la misma balanza, que es exactamente lo que hace el producto.

El platillo derecho cuelga algo más bajo que el izquierdo, y no es un descuido: **una balanza en equilibrio perfecto no está midiendo nada.**

Geometría plana, trazo de 2 sobre retícula de 32, extremos y uniones redondeados, todo en `currentColor`. **Sin degradado.** El sistema de RM Technology —chip, Poppins, degradado morado— comunica marca de desarrollo, y Statera le habla a auditores y a compradores del sector público, donde ese degradado resta credibilidad. Identidad propia, no derivada.

La fuente de verdad de la geometría es `resources/js/components/Logotipo.vue`. Cualquier redibujo —favicon, sello de informe, material impreso— sale de ahí.

**Variantes**, en este orden de preferencia:

| Fondo | Símbolo | Uso |
|---|---|---|
| Blanco o `background` | `marca-600` | Principal en interfaz y documentación |
| `marca-800` | `marca-100` | Panel de acceso, cabeceras de marca, portadas |
| `marca-950` | `marca-300` | Icono de aplicación, fondo profundo |
| Blanco | `foreground` | Impresión a una tinta, sellos, fax de cumplimiento |

### Logotipo

- **Horizontal** (símbolo + wordmark en texto, 15 px / 600 / tracking ajustado): cabecera de web y de aplicación, firmas, portadas.
- **Con respaldo** (`un producto de RM Technology`, 11 px, en `muted-foreground` debajo del wordmark): sólo donde hay sitio y donde el respaldo aporta —portadas, pie del panel de acceso—. Nunca en la barra de navegación.
- **Símbolo solo**: favicon, avatar, marca de agua, sello en informes, espacios estrechos.

### Reglas

- **Zona de seguridad:** el alto del platillo por los cuatro lados.
- **Mínimos:** símbolo 24 px, logotipo horizontal 120 px de ancho.
- **Sobre claro:** wordmark en `foreground`. **Sobre oscuro:** wordmark en blanco. **Sobre foto:** monocromo blanco y siempre con velo oscuro debajo.
- **Nunca:** deformar, recolorear fuera de la paleta, añadir sombra o contorno, encerrarlo en una forma ajena, separar o recomponer símbolo y wordmark, ni igualar los dos platillos.
- **Girarlo tampoco**, con una excepción declarada: la animación del panel de acceso, que no es el logotipo sino una nube de puntos construida con sus mismas proporciones (`lib/balanza.ts`). Ahí el giro es el contenido, no un maltrato del símbolo.

### El símbolo como recurso gráfico

La balanza ampliada, recortada por el borde del lienzo y al 6–10 % de opacidad, sirve de fondo en portadas, cabeceras de informe y estados vacíos. Una por pieza, nunca repetida en patrón.

## 3. Color

Tres familias: teal (marca), violeta (acento) y una neutra con tinte frío. Todas en `oklch`, que es como están escritas en `resources/css/app.css` — la fuente de verdad. Los hex de estas tablas son la conversión a sRGB, para PDF, correo e impresión.

**Reparto 60 / 30 / 10.** 60 % neutros y fondos, 30 % teal, 10 % violeta. El violeta que se ve en todas partes deja de ser acento y empieza a parecer otra marca.

### Teal — marca

Hue 196, y esto es funcional, no estético: los estados del dominio ocupan 245, 155, 70, 27 y 285, y 196 es el hue libre más alejado de todos ellos. Un botón primario en verde o en ámbar se leería como un badge de estado. La metáfora de la balanza pedía verde; la semántica de la tabla lo descartó.

| Token | Hex | oklch | Sobre blanco | Uso |
|---|---|---|---|---|
| `marca-50` | `#EDFAFA` | `0.975 0.014 196` | 1.07 | Fila seleccionada, hover sutil |
| `marca-100` | `#D5F4F4` | `0.945 0.032 196` | 1.16 | Fondo de badge informativo |
| `marca-200` | `#B0E9E9` | `0.895 0.058 196` | 1.34 | Bordes de elemento seleccionado |
| `marca-300` | `#7AD6D7` | `0.82 0.088 196` | 1.69 | Texto de marca sobre oscuro |
| `marca-400` | `#23B7B9` | `0.71 0.115 196` | 2.45 | Decorativo sobre claro; acento sobre oscuro |
| `marca-500` | `#009D9F` | `0.62 0.128 196` | 3.33 | Iconos grandes, bordes, gráficos |
| `marca-600` | `#007E81` | `0.52 0.13 196` | **4.87** | **Acción primaria, enlaces, anillo de foco** |
| `marca-700` | `#006467` | `0.44 0.112 196` | 6.96 | Hover del primario, texto de marca |
| `marca-800` | `#004B4C` | `0.36 0.09 196` | 10.00 | Panel de acceso, cabeceras oscuras |
| `marca-900` | `#003537` | `0.29 0.07 196` | 13.39 | Secciones de marca |
| `marca-950` | `#002122` | `0.22 0.05 196` | 16.88 | Fondo profundo |

**La escala no se invierte en oscuro.** 50 es el tono más claro y 950 el más oscuro en los dos temas. Invertirla parecía elegante y era una trampa: `bg-marca-900 text-marca-950` deja de tener sentido en la mitad de los casos y el contraste se rompe sin que se vea en el fichero que lo usa. Lo que cambia de tema son los roles.

### Violeta — acento

| Token | Hex | oklch | Sobre blanco | Uso |
|---|---|---|---|---|
| `violeta-50` | `#F7F2FF` | `0.97 0.02 299` | 1.10 | Fondo de estado «en revisión» |
| `violeta-100` | `#EEE6FF` | `0.94 0.04 299` | 1.21 | Fondo de badge de acento |
| `violeta-200` | `#DFD0FF` | `0.885 0.07 299` | 1.44 | Bordes suaves |
| `violeta-300` | `#C7AEFB` | `0.8 0.11 299` | 1.93 | Acento en la balanza, series sobre oscuro |
| `violeta-400` | `#A781E9` | `0.682 0.152 299` | 3.02 | **Filete de acento, acento sobre oscuro** |
| `violeta-500` | `#9161DB` | `0.6 0.18 299` | 4.26 | Iconos y bordes sobre claro |
| `violeta-600` | `#7B45C4` | `0.52 0.19 299` | **6.03** | **Texto y acciones de acento sobre claro** |
| `violeta-700` | `#6437A2` | `0.45 0.165 299` | 8.06 | Hover del acento |
| `violeta-800` | `#4E2B7E` | `0.38 0.135 299` | 10.65 | Paneles de acento |
| `violeta-900` | `#3B2260` | `0.32 0.105 299` | 13.22 | Fondo profundo |
| `violeta-950` | `#2A1747` | `0.26 0.085 299` | 15.96 | Fondo profundo |

`violeta-400` no vale como texto sobre blanco (3.02). Para texto y acciones sobre claro, `violeta-600`; sobre oscuro, `violeta-400`.

**Dónde va el violeta, y sólo ahí:** el filete de acento, el estado «en revisión», la variante `acento` del botón y la balanza del panel de acceso. No en enlaces, no en el anillo de foco, no en badges de estado que no sean el de revisión, no en el ítem activo del sidebar.

### Neutros

Los grises llevan una gota del hue frío (215–235) en lugar de azul-pizarra. Una paleta fría coherente lee como una sola decisión; dos familias de gris en la misma pantalla se notan aunque nadie sepa decir por qué. Se consumen por rol, no por escala: `background`, `card`, `superficie`, `muted`, `border`, `input`, `foreground`, `muted-foreground`.

En pantalla se leen del token `oklch` y no hace falta el hex. Se tabulan aquí porque **los documentos PDF no pueden usar `oklch`**: un color que dependa de la gestión de color del navegador no es archivable, y la conversión a PDF/A se comporta mejor con sRGB plano. Estos son los valores que consume `resources/documentos/documento.css`, y salen de convertir los `oklch` de `app.css`, no de estimarlos.

| Rol | Hex (tema claro) | `oklch` |
|---|---|---|
| `background` | `#F9FCFC` | `0.988 0.003 215` |
| `card` | `#FFFFFF` | `1 0 0` |
| `superficie` | `#F2F7F8` | `0.972 0.005 215` |
| `muted` | `#ECF2F4` | `0.958 0.007 215` |
| `border` | `#DAE1E3` | `0.905 0.008 215` |
| `muted-foreground` | `#5D6C72` | `0.52 0.02 225` |
| `secondary-foreground` | `#26323A` | `0.31 0.022 235` |
| `foreground` | `#151E24` | `0.23 0.018 235` |

El documento se pinta **siempre en tema claro**: se imprime, y un PDF que se adapte al tema del lector no existe.

### Semánticos

Los estados del dominio. Se declaran una vez en `app.css` como `--estado-*` con su pareja `-suave`, y los consumen `CeldaBadge` y `BarraSegmentada`. **No se retocan al cambiar la marca**: son semántica, no decoración.

| Token | Hex | Fondo suave | Contraste sobre su fondo | Estado |
|---|---|---|---|---|
| `estado-implantado` | `#007E46` | `#DAF7E3` | 4.53 | Implantado · Conforme |
| `estado-planificado` | `#036EAE` | `#DFF1FF` | 4.73 | Planificado |
| `estado-en-progreso` | `#BB7400` | `#FFF0D4` | 3.32 ⚠ | En progreso |
| `estado-no-iniciado` | `#80878F` | `#ECEFF2` | 3.14 ⚠ | No iniciado |
| `estado-no-aplica` | `#7F7F86` | `#F0F0F3` | 3.47 ⚠ | No aplica |
| `estado-en-revision` | `#7B45C4` | `#F7F2FF` | 5.50 | En revisión *(declarado, sin flujo todavía)* |

⚠ **Deuda conocida:** tres estados quedan por debajo del 4.5:1 que pide §11 para texto normal. El hue de cada uno es correcto y no se toca; lo que hay que bajar es la luminosidad del tono de texto. Pendiente.

⚠ **Y una segunda, medida:** `estado-implantado` y `estado-en-progreso` **no se distinguen entre sí con protanopia** — ΔE 5.7 en OKLab, por debajo del suelo de 6. El verde y el ámbar son vocabulario del dominio y no se cambian, así que la separación se resuelve en el uso, no en la paleta:

- En una barra por tramos, el azul de `planificado` va **entre** los dos. Con ese orden la peor pareja contigua sube a ΔE 14.0. Lo fija `ResumenCumplimiento::porEstado()` en el servidor, con el motivo escrito, para que el panel y el informe usen el mismo.
- Tramos separados por 2 px de superficie y con los extremos redondeados: pegados se leen como una mancha.
- Leyenda siempre que haya dos tramos o más, y badge con punto + texto en la tabla. La identidad nunca depende sólo del color.

Las cifras salen de ejecutar el validador de paletas sobre los hex de esta tabla, no de estimarlas. Si se retoca un `--estado-*`, se vuelve a medir.

Nunca comunicar un estado sólo con color: color + icono + texto. Aquí un badge mal leído es un hallazgo de auditoría que nadie vio.

### Tipología — activos

Familia **aparte** de los estados, y la separación no es decorativa: un estado dice *cómo va* algo y un tipo dice *qué es*. Compartir paleta haría que un badge de tipo en verde se leyera como «implantado». Se separan por croma —0.10 aquí frente a 0.13 en los estados— y por tono.

| Token | Hex | Fondo suave | Contraste | Icono | Tipo MAGERIT |
|---|---|---|---|---|---|
| `tipo-servicios` | `#315C92` | `#E4F2FF` | 5.99 | `Globe` | Servicios |
| `tipo-datos` | `#006C67` | `#DCF7F4` | 5.58 | `Database` | Datos e información |
| `tipo-software` | `#5B508F` | `#EFEDFF` | 6.15 | `AppWindow` | Software |
| `tipo-hardware` | `#006585` | `#DDF5FF` | 5.77 | `HardDrive` | Hardware |
| `tipo-comunicaciones` | `#326935` | `#E5F6E5` | 5.78 | `Network` | Redes de comunicaciones |
| `tipo-soportes` | `#7E4F04` | `#FDEDDC` | 6.11 | `Archive` | Soportes de información |
| `tipo-equipamiento-auxiliar` | `#59610F` | `#EFF3DE` | 5.90 | `Plug` | Equipamiento auxiliar |
| `tipo-instalaciones` | `#884053` | `#FFE9ED` | 6.22 | `Building2` | Instalaciones |
| `tipo-personal` | `#774579` | `#FAEAFB` | 6.25 | `Users` | Personal |

En oscuro, mismos tonos con luminosidad invertida entre texto y fondo; el peor contraste del conjunto sube a 6.90.

**Las tres cifras que mandan aquí, medidas:**

- **Contraste 5.58 en el peor caso.** Los nueve pasan AA con margen — más que los propios `--estado-*`, que arrastran tres por debajo de 4.5.
- **ΔE 6.2 en el peor par tipo↔estado** (`comunicaciones` frente a `implantado`), por encima del suelo de 6. Ningún badge de tipo se confunde con uno de estado.
- **ΔE 5.2 en el peor par tipo↔tipo**, y eso **no llega al suelo**. Nueve categorías no caben en el hueco que dejan los estados. Es aceptable aquí y no lo sería en una barra por tramos, porque estos badges nunca se tocan, siempre llevan texto y **siempre llevan icono**: la identidad del tipo la carga el icono y el color sólo agrupa. Por eso el icono de la tabla **no es opcional** — quitarlo deja la distinción por debajo del umbral.

Las cifras salen de convertir los `oklch` de `app.css` a sRGB y medir; si se retoca un tono, se vuelven a medir. La misma regla que los semánticos.

### Degradados

Reservados a portadas de informe y cabeceras de material comercial. **Nunca** en botones, tarjetas, cabeceras de tabla ni sobre el símbolo, que es plano por decisión (§2).

```css
--gradient-dark: linear-gradient(160deg, #002122 0%, #003537 55%, #3B2260 100%);
```

### Modo oscuro

No es una inversión automática. Sólo cambian los roles.

- Fondo, superficie y elevación: los roles `background`, `card`, `popover`, `superficie`
- Acción primaria: `--primary` sube a `oklch(0.8 0.12 196)` con texto oscuro. El 600 sobre fondo oscuro no llega
- Acento: `--acento` pasa a `violeta-400`, nunca `violeta-600`
- Semánticos: suben una o dos posiciones de luminosidad y su `-suave` baja; nunca se reutiliza el valor del modo claro

## 4. Tipografía

**Instrument Sans** para la interfaz. Pesos 400 (cuerpo), 500 (interfaz y etiquetas), 600 (títulos), 700 (cifras destacadas del panel). Autoalojada, `font-display: swap`, sólo esos cuatro pesos.

Los `.woff2` viven en `resources/fonts/` y el `@font-face` está en `app.css`. **Un solo juego de ficheros para dos consumidores**: Vite emite su copia con hash para el navegador, y `AssetsDocumento` lee los mismos ficheros en crudo para incrustarlos en el CSS de los documentos PDF, donde PDF/A-3b exige toda fuente embebida. Antes esto lo hacía `bunny()` desde `vite.config.ts`, que descargaba las familias y emitía un `fonts-*.css` **que no enlazaba nadie**: la interfaz se pintó con la fuente del sistema hasta que se corrigió. Las dos familias son OFL y sus licencias están junto a los ficheros.

**JetBrains Mono** para lo que es código y para lo que se compara en vertical: identificadores de control (`op.acc.4`), referencias de norma, contadores, porcentajes, hashes. Se aplica con la clase `.cifra`, que además activa `tabular-nums` y `zero`.

Instrument Sans es estrecha y de altura de x generosa: aguanta una tabla de controles a 13 px sin abrirse, que es la razón por la que está aquí y no una geométrica ancha. En una herramienta cuyo grueso es tabla y formulario, el ancho de la letra es presupuesto de columna.

| Rol | Tamaño / interlineado | Peso | Tracking |
|---|---|---|---|
| Título de acceso | 24 / 30 px | 600 | −0.02em |
| Titular de panel de marca | 30 / 34 px | 600 | −0.02em |
| Título de página | 20 / 28 px | 600 | −0.015em |
| Título de sección o tarjeta | 16 / 24 px | 600 | −0.01em |
| Cuerpo | 16 / 26 px | 400 | 0 |
| Interfaz | 14 / 20 px | 500 | 0 |
| Etiqueta | 13 / 18 px | 500 | 0 |
| Anotación | 12 / 16 px | 400 | 0.01em |

### Reglas

- Línea de texto corrido de 68 caracteres como máximo (`max-w-2xl` en descripciones, `max-w-prose` en texto largo).
- Los titulares largos llevan `text-balance`; los párrafos, `text-pretty`. Una palabra huérfana en la última línea de un titular se ve, y se ve mal.
- Mayúsculas con tracking amplio: nunca en la interfaz. Capitalización de frase en todo, títulos incluidos.
- Nada de etiquetas pequeñas encima de cada título salvo que aporten un dato real: un estado, un identificador, una fecha.

## 5. Espacio y retícula

Base de 4 px. Escala: 4, 8, 12, 16, 24, 32, 48, 64, 96, 128.

- Web pública: contenedor de 1200 px, secciones de 96–128 px de alto interior.
- Aplicación: contenedor de 1440 px, márgenes laterales 32 / 24 / 16 px según tamaño.
- Contenido de lectura: 720 px.
- Retícula de 12 columnas, canal de 24 px.
- Ritmo vertical en producto: 64 px entre secciones, 32 px entre bloques, 16 px dentro del bloque, 8 px entre etiqueta y campo.
- Alineación a la izquierda por norma. Centrado solo en estados vacíos, acceso y diálogos. Cifras y fechas a la derecha en tablas.

Breakpoints: `sm 640` · `md 768` · `lg 1024` · `xl 1280` · `2xl 1536`.

---

## 6. Forma y elevación

**Un solo sistema de radios**, con `--radius: 0.625rem` (10 px) como base, y se respeta:

| Nivel | Clase | Qué |
|---|---|---|
| Superficie | `rounded-xl` (14 px) | Tarjeta, tabla, diálogo, aviso, panel |
| Control | `rounded-md` (8 px) | Botón, input, select, textarea |
| Píldora | `rounded-full` | Badge, avatar, chip, indicador |

El escalón de superficie lo trae el estilo `reka-vega` de shadcn-vue en `Card`, y todo lo que hace de panel lo iguala. Mezclar radios sin regla es lo que hace que una interfaz parezca ensamblada por partes distintas.

Bordes: 1 px en `--border`. El borde es el separador principal; la sombra se reserva para lo que flota de verdad.

**Sombras teñidas**, nunca negro puro: sobre un fondo frío, el negro al 8 % ensucia en vez de separar. Se declaran en `app.css` con el hue del texto y se usan como `shadow-sombra-1/2/3`.

- `sombra-1` — elevación mínima: botón `outline`, input enfocado
- `sombra-2` — desplegable, popover, tarjeta que se levanta
- `sombra-3` — diálogo, panel lateral

**Filete de acento.** Regla de 3 px por 40 de ancho en violeta —`bg-acento` sobre claro, `bg-violeta-400` sobre superficie de marca—, colocada encima de un titular. Es el gesto distintivo: **uno por pantalla, nunca dos**. Vive en `CabeceraPagina` (prop `filete`) y en el panel de acceso.

## 7. Iconografía

`@lucide/vue`. Trazo lineal, extremos redondeados, retícula de 24. Tamaños: 16 px en línea de texto y en botones, 20 px en menús, 24 px en navegación, 40 px en estados vacíos.

Color por defecto en interfaz: `muted-foreground`. `text-primary` cuando es marca o acción activa; el token de estado correspondiente cuando acompaña un estado. Un solo estilo de icono por pantalla, y un solo grosor de trazo en toda la aplicación.

Iconos fijos de los cinco pilares, y no se cambian una vez publicados:

| Pilar | Icono | Color |
|---|---|---|
| ISO 27001 | Escudo con check | `marca-500` |
| ENS | Edificio institucional con columnas | `marca-500` |
| Riesgos | Triángulo de alerta | `estado-en-progreso` |
| Documentación | Documento con líneas | `marca-500` |
| Auditoría | Gráfico ascendente | `acento` |

El favicon es el símbolo solo, en `marca-600` sobre transparente (`public/favicon.svg`).

## 8. Web pública

> Todavía no existe: hoy Statera es sólo la aplicación tras el acceso. Esta sección es la intención, no una descripción de código. Sobre superficie de marca los colores salen de la escala `marca-*`, no de los roles, porque ahí el color no depende del tema.

**Hero.** Fondo oscuro (`--gradient-dark` o fotografía con velo `oklch(0.22 0.05 196 / 75%)`), logotipo horizontal arriba a la izquierda, titular de tres líneas alineado a la izquierda, filete violeta debajo, párrafo de apoyo en `marca-200` y una sola acción. La imagen de fondo, si la hay, es abstracta y fría: horizonte, luz baja, nada de candados, huellas dactilares ni código verde en cascada.

**Acción del hero.** Un botón primario que dice lo que pasa al pulsar: «Solicitar una demo» o «Ver cómo funciona». Un enlace secundario al lado, sin caja. Nunca dos botones del mismo peso.

**Banda de pilares.** Los cinco pilares en fila sobre fondo `marca-950`, separados por divisores verticales `marca-800`, cada uno con icono de 40 px, título de 16/600 y dos líneas de descripción en `marca-200`. En móvil pasan a dos columnas, no a carrusel.

**Secciones de contenido.** Alternancia de `card` y `background`; una sección oscura como mucho cada tres, para que el oscuro siga significando algo. Cada sección: un título, un párrafo y una prueba (captura de producto, cifra con su fuente, cita de cliente con nombre y cargo).

**Prueba de confianza.** Sellos de certificación, logos de cliente y referencias normativas en gris, sin color, a una altura óptica común. Si el logo del cliente no está autorizado, no se pone.

**Pie.** Fondo `marca-950`, cuatro columnas, símbolo y claim arriba a la izquierda, aviso legal, política de privacidad y contacto siempre visibles. En una web de cumplimiento, un pie descuidado contradice el argumento entero.

**Lo que no va en esta web:** contadores animados al hacer scroll, testimonios genéricos sin nombre, insignias inventadas, iconos de candado por decoración, mapas del mundo con puntos parpadeando.

---

## 9. Componentes de aplicación

Lo que sigue describe los componentes tal y como están construidos, no como nos gustaría que estuvieran. Si algo cambia en `resources/js/components/`, cambia aquí.

**Botones** (`buttonVariants`). Alto 36 px (`h-9`), 32 compacto, 40 destacado. Radio `rounded-md`, texto 14/500, `active:translate-y-px` para que el pulsado se sienta.

- Primario: `bg-primary`, texto `primary-foreground`. Hover `marca-700` en claro, `marca-500` en oscuro — **oscurecer, no mezclar con el fondo**: `bg-primary/80` aclaraba el botón sobre claro y el contraste del texto caía a 3.4:1 justo al pasar el puntero.
- Secundario: `bg-secondary`, neutro.
- Contorno: `bg-background` con `border-border` y `shadow-xs`.
- Sutil (`ghost`): sin caja, hover `bg-muted`.
- Acento: `bg-acento`, texto blanco, hover `violeta-700`. **Sólo** para la acción que abre un flujo de revisión o auditoría; nunca junto a un primario.
- Destructivo: `bg-destructive/10` con texto `destructive`, no relleno rojo. Separado del resto y nunca por defecto en un diálogo.
- Enlace: `text-primary` con subrayado al pasar.

Un solo primario por vista. El texto nombra la acción concreta: «Guardar cambios», «Publicar política», «Cerrar hallazgo». Sin flecha al final.

**Campos.** Alto 36 px, borde `input`, radio `rounded-md`, `shadow-xs`. Foco: borde `ring` y anillo de 3 px en `ring/50`. Etiqueta encima, 13/500. Ayuda debajo, 12 px en `muted-foreground`, presente siempre que el campo tenga reglas, no sólo al fallar. Error: `aria-invalid` pinta borde y anillo en `destructive`, y el mensaje dice qué corregir. Cada campo lleva `label` real y encadena ayuda y error con `aria-describedby`. La envoltura que pone todo eso es `CampoBase.vue`, una sola vez.

- **Lo obligatorio se marca en teal, nunca en `destructive`.** Asterisco en `primary` junto a la etiqueta, filete de 2 px en `primary` a la izquierda del campo, y la leyenda «Los campos marcados con * son obligatorios» bajo el título de la pantalla. Pintar el asterisco de rojo —como estaba— hace que un formulario recién abierto se lea como un formulario lleno de errores: *falta* y *está mal* son dos estados distintos y no pueden compartir color. Como el color no puede ser el único portador (§11), el control lleva `aria-required` y la etiqueta un «(obligatorio)» sólo para lector de pantalla.
- **Lo que falta se cuenta en vivo, y sólo cuando falta.** Cada `SeccionFormulario` dice «2 sin rellenar» junto a su título mientras queden obligatorios vacíos, y no dice nada cuando está completa —una palomita por sección es la fila de ceros del inventario otra vez—. El total va en la nota de `BarraAcciones`. Se lee del `FormData` del propio `<form>` con un único oyente, así que no duplica ni una regla: **el `FormRequest` sigue siendo la única fuente de verdad y el botón de envío nunca se deshabilita.** Un botón muerto que no explica por qué es peor que un intento fallido con su resumen.
- **Una escala corta se elige de un clic, no en un desplegable** (`CampoOpciones.vue`, sobre `RadioGroupRoot` de Reka). Vale para lo que **ordena**: los niveles del Anexo I, la madurez. Es el mismo argumento que hay más abajo para no pintar la madurez como badge —lo que se pregunta es si esto es más que aquello, y eso lo contesta la posición antes que el texto—, y con las cinco dimensiones a la vista se ve de golpe cuál manda, que es la que decide la categoría. A menos de `sm` se reparte en dos columnas.
- **Una sección se pliega desde su título**, que es donde se mira y donde se pulsa; el chevron va delante y el estado en `aria-expanded`. El recuento de obligatorios que faltan se queda **fuera de lo que se pliega**, para que plegar no esconda que la sección todavía debe dos campos.
- **Una sección plegada sigue en el DOM.** Se pliega con `v-show`, no con `v-if` ni con el `Collapsible` de Reka: lo que sale del DOM sale del `FormData`, y con campos `nullable` una edición los borraría en silencio. `display:none` no excluye nada del envío —`FormData` sólo se salta los deshabilitados y los que no tienen `name`—, mientras que el `forceMount` de Reka deja el contenido montado pero **visible**, que no es plegar. Y el resumen de errores abre la sección antes de saltar al campo: dentro de un `display:none` no se puede enfocar ni desplazar nada.
- **Salir con cambios sin guardar pregunta.** `BarraAcciones` intercepta Cancelar en fase de captura —en burbujeo competiría con el manejador del `<Link>` de Inertia— y registra un `beforeunload`. No se intercepta toda navegación: `router.on('before')` es síncrono y no admite esperar a un diálogo.

**Tarjetas.** `bg-card`, radio `rounded-xl`, `ring-1 ring-foreground/10` en lugar de borde, padding vertical 24. Más de cuatro tarjetas idénticas seguidas suele significar que eso era una tabla.

**Avisos.** Radio `rounded-xl`, borde e icono del tono correspondiente sobre un fondo al 5 %: `destructive` para el error, `estado-implantado` para el éxito, neutro para el informativo. El error lleva `role="alert"`; el resto no, para no interrumpir al lector de pantalla por una confirmación.

**Tablas.** La pieza más usada del producto y la que más decisiones concentra.

- **Cabecera de 40 px, pegajosa**, en versalitas de 12/600 con `tracking` de 0.06 em sobre `muted-foreground`. No es texto de 14/500 como el resto: la cabecera es una etiqueta, no un dato, y en una tabla de doce columnas la versalita separa la banda de encabezado del contenido sin necesidad de una regla ni de un fondo distinto.
- **Fila de filtros**, debajo de la cabecera y también pegajosa, con un control por columna: el filtro se pone donde está el dato que estrecha. Se pliega entera desde la barra y la preferencia se recuerda. Un filtro cuya columna está oculta no desaparece: baja al desplegable «Filtros» de la barra.
- **Búsqueda en la barra**, no en la fila: cruza varios campos declarados en el `Recurso` y no pertenece a ninguna columna.
- **Chips de lo aplicado** debajo de la búsqueda, uno por valor, que se quitan de uno en uno. Salen de `MetaTabla` —lo que el servidor aplicó de verdad— y son la única forma de ver qué estrecha la tabla con la fila de filtros plegada.
- **La columna que filtra se marca**: su cabecera pasa a `foreground` y gana una regla de 2 px en `primary` debajo. Es lo único que se ve con la fila de filtros plegada, y sin eso un filtro guardado en la URL explica un resultado corto sin decir por qué. La columna entera **no** se tiñe: con tres filtros puestos rompe el reparto 60/30/10 y compite con los badges.
- **Las coincidencias se resaltan** dentro de la celda, `bg-primary/20`, como hace el buscador del navegador. Vale para la búsqueda de la barra y para el filtro de cada columna, y sólo en columnas de texto: en un badge o en una fecha estorbaría. Se compone por fragmentos, nunca con `v-html` — el texto es dato de la organización y no vuelve al DOM como marcado.
- **Menú por columna** en su propia cabecera (ordenar, anclar, mover, ocultar). Aparece al pasar el puntero o al llegar con el tabulador. Con doce columnas, la que estorba se quita desde donde está.
- **Columnas ancladas pegadas al margen izquierdo** con desplazamiento medido, no calculado: la mitad de las columnas no declara ancho. La última anclada lleva `border-r` para que se vea dónde acaba el bloque fijo. Se fijan desde el panel «Columnas» o desde el menú «⋮» de la cabecera; las que el `Recurso` declara `anclada()` no se pueden soltar.
- **La columna de acciones va fija a la derecha**, con `border-l` en espejo. El «⋯» es lo que más se busca en una tabla ancha y estaba al final del desplazamiento horizontal. Fija siempre, también cuando la tabla cabe entera: ahí no se distingue, y encenderlo por ancho haría aparecer y desaparecer el borde al redimensionar.
- **Una celda pegada en horizontal no puede tener alfa en el fondo.** Las celdas fijas heredan el fondo de su fila con `bg-inherit`, así que un `/50` deja ver a través de la columna fija lo que se desplaza por debajo. Pasó con el hover, y no se ve venir leyendo la clase. Por eso el hover y la selección son tokens ya compuestos —`--fila-hover`, `--fila-seleccionada`, `color-mix` contra `--card`—: mismo color de siempre, sin canal alfa. La cabecera esmerilada (`bg-card/95` + desenfoque) se queda sólo donde únicamente se pega en vertical.
- **Las cifras de una tabla no se animan.** El contador de entrada (`Cifra`, con NumberFlow) es para los números que resumen —panel, tarjetas, tiras de alerta—, no para los que se listan: trescientas celdas contando a la vez al filtrar es ruido, no énfasis.
- Filas con `border-b`, hover `--fila-hover`, seleccionada `--fila-seleccionada` — muted al 50 % y accent al 40 % sobre `card`, ya mezclados. Sin cebra. Cifras a la derecha con la clase `.cifra` (`tabular-nums`).
- **La vista se guarda en el navegador**, por recurso: visibilidad, orden y anclado de columnas, densidad y fila de filtros. Es preferencia de un puesto, no un dato de la organización. Siempre hay una vuelta atrás: «Restablecer la vista», en el desplegable de columnas.
- **Filtros, orden y página viven en la URL** y se resuelven en el servidor; lo que se aplicó de verdad vuelve en `MetaTabla`, no lo que se pidió.
- La exportación a CSV es de **la página visible con sus columnas visibles**, y el botón lo dice. Los documentos archivables del SGSI no salen de aquí: se generan con Gotenberg y se almacenan firmados.

**Badges de estado.** `rounded-full`, alto 20 px, 12/500, fondo suave del semántico y texto en su versión oscura, con punto o icono delante. Vocabulario cerrado y usado igual en todo el producto: *Implantado · En progreso · Planificado · No iniciado · No aplica*, y *En revisión* cuando llegue ese flujo.

**Qué se pinta con color y qué no.** El color de una celda dice algo o no se pone. El criterio, columna a columna:

| Qué es el dato | Cómo se pinta | Por qué |
|---|---|---|
| Estado de implantación | Badge con su `--estado-*` y punto | Vocabulario cerrado y semántica fija |
| Valor ordinal (madurez L0–L5) | **Escala de pasos** + su forma corta | Lo que se pregunta es si L4 es más que L2, y eso lo contesta la longitud antes que el texto. Un badge no ordena |
| Categoría ENS (básica < media < alta) | Badge que **sube en énfasis**: `muted` → `accent` → `primary/15` | También es ordinal. Estaba en rojo `destructive` y eso mentía: una categoría alta no es un error, es un sistema que exige más |
| Exigencia (aplica / refuerzos) | Badge neutro, y `accent` para cualquier refuerzo | Un refuerzo es «aplica, y además esto». Los `Rn` comparten tono: su número no está acotado por el marco |
| Procedencia (origen de la exigencia) | Texto legible, **sin color** | Explica de dónde sale la exigencia; pintarla sería ruido |
| Identificador (marco, código) | Chip neutro monoespaciado, sin punto | No tiene grados, y un punto de color sugeriría que sí |

El tono que viaja del servidor es **un nombre de estado del dominio, no un color** (`ValorEtiquetado`), y la traducción a clases vive en un solo sitio, `CeldaBadge.vue`.

**Navegación.** Lateral de 240 px (68 px plegada) sobre `bg-superficie` con `border-r` — clara, no oscura: el chrome se separa del lienzo por tono, no por inversión. Ítem activo: `bg-accent text-accent-foreground` más una barra de 2 px en `bg-primary` pegada al borde izquierdo. El mapa de la navegación es `lib/navegacion.ts` y lo leen los cuatro sitios que lo pintan.

**Gráficas.** Pocas, y cada una con un trabajo concreto.

- **La forma la decide el dato, no el gusto.** Una cifra sola es una cifra grande con su fracción debajo, no un anillo; una composición es una barra por tramos; una comparación entre pocas categorías son barras horizontales, porque en vertical la etiqueta no cabe sin girarla. Nada de medidores tipo velocímetro, nada de tarta, y **nunca dos ejes verticales** en la misma gráfica.
- **El color hace un trabajo y sólo uno.** Estado del dominio → la paleta semántica `--estado-*`, que está reservada y no se recicla como «serie 3». Magnitud de una sola serie → un solo hue, el de marca. El texto —valores, etiquetas, leyendas— va siempre en tokens de texto, nunca del color de la serie.
- **Con dos series o más, leyenda.** Y cuando son cuatro o menos, además rotuladas directamente. La identidad no puede depender del color.
- **Marcas finas**, extremos redondeados, 2 px de superficie entre rellenos contiguos, rejilla y ejes recesivos. Etiquetas directas y selectivas: nunca un número sobre cada punto.
- **Ningún porcentaje sin su denominador.** «62 %» va siempre con «31 de 50» al lado. Es el principio de §1: el producto vende evidencia, no sensación.
- **Sin librería de gráficas.** Lo que hay hoy —anillo, barra por tramos, barras horizontales— es SVG y CSS a mano sobre los tokens, y así funciona en los dos temas sin repintar nada. Entra una librería cuando llegue la primera serie histórica con eje de tiempo, que es lo único que no merece la pena escribir a mano; y en los documentos PDF el SVG lo genera el servidor, sin JavaScript, para que el vector y el texto sobrevivan a PDF/A y PDF/UA.

**Panel de cumplimiento.** El elemento con más peso de la plataforma: grado de implantación por dominio de control, controles abiertos por estado, próximos vencimientos y última evidencia registrada. Un solo número grande —el porcentaje de controles conformes— con su fracción real debajo («112 de 146 controles»), y el resto en barras horizontales (`BarraSegmentada`) y anillos (`AnilloProgreso`). Nada de medidores tipo velocímetro.

**Modales.** `rounded-xl`, padding 24, ancho máximo `sm:max-w-md` salvo formularios largos. Primario a la derecha. Escape y clic fuera cierran, salvo con cambios sin guardar.

**Estados vacíos.** Título de una línea que dice qué falta, una frase de contexto y un botón. Nada de ilustraciones genéricas.

**Carga.** Esqueletos con la forma del contenido real y pulso, no ruedas girando. Spinner sólo donde no hay forma que anticipar. Cuando ya hay contenido y sólo se está reconsultando —filtrar, ordenar, paginar— no se sustituye por esqueletos: un hilo de 2 px recorre el borde superior de la tabla y el resto se queda quieto. Es el único bucle del chrome de trabajo, dura lo que dura la petición y con `prefers-reduced-motion` se pinta quieto.

## 10. Movimiento

Los números viven en `resources/js/lib/motion.ts` y en `app.css`, una sola vez, y ningún componente los escribe a mano.

- 120 ms en hover y foco, 220 ms en lo general, 380 ms en modales y paneles laterales
- Curva `cubic-bezier(0.16, 1, 0.3, 1)`: salida rápida y frenada larga, que se percibe como respuesta y no como espera
- Muelle (`stiffness: 220, damping: 26`) sólo para lo que se arrastra: paneles, popovers
- Las entradas se desplazan 8 px, no 30. Ocho ordenan la lectura; treinta la interrumpen

El listón para que algo se anime es que comunique jerarquía, narrativa, feedback o un cambio de estado. **Lo decorativo no entra**: esto es una herramienta que alguien tiene abierta ocho horas, y un bucle infinito en la periferia cansa mucho antes de lo que parece. Nada de entradas animadas por sección al hacer scroll, ni de transición en cada tarjeta, ni de contadores que suben solos.

**Una excepción, y está acotada:** la balanza del panel de acceso (`BalanzaPixeles.vue`). El panel se mira quince segundos antes de entrar y está fuera del chrome de trabajo. Se apaga entera con `prefers-reduced-motion`, con la pestaña en segundo plano y por debajo de `lg`.

`prefers-reduced-motion: reduce` se resuelve en tres capas y las tres tienen que seguir puestas: el `@media` global de `app.css`, el `<MotionConfig reduced-motion="user">` de los layouts y el composable `useMovimientoReducido` para lo que no es ni CSS ni una variante.

## 11. Accesibilidad

Una web de ISO 27001 y ENS que falla accesibilidad se contradice a sí misma, y en contratación pública el EN 301 549 no es opcional.

- Texto normal ≥ 4.5:1, texto grande y componentes ≥ 3:1. Sobre blanco, `marca-600` da 4.87 y `violeta-600` 6.03; `marca-400` (2.45) y `violeta-400` (3.02) son decorativos sobre claro y no valen como texto. Las cifras salen de convertir los `oklch` de `app.css` a sRGB, no de estimarlas: cada retoque de la paleta obliga a recalcularlas.
- Foco siempre visible: `outline-ring outline-2 outline-offset-2`, en el teal de marca. El anillo **no** es violeta, y no se elimina el outline sin sustituto.
- Objetivos táctiles de 44 × 44 px mínimo en móvil.
- Todo accionable por teclado, en orden lógico. Los modales atrapan el foco y lo devuelven al cerrar.
- Estados también en texto, nunca solo en color.
- Toda animación ambiente se apaga con `prefers-reduced-motion: reduce`, sin excepción — incluida la balanza del acceso, que en ese caso se pinta quieta.
- `label` real asociado a cada campo, errores anunciados con `aria-live`.
- Idioma declarado, títulos de página únicos, jerarquía de encabezados sin saltos.

---

## 12. Implementación

Tailwind v4, configurado en `resources/css/app.css`. **No hay `tailwind.config.js`** y no hace falta.

La estructura es siempre la misma: los valores se declaran en `:root`, los roles se redefinen en `.dark`, y `@theme inline` los expone como utilidades.

```css
@custom-variant dark (&:is(.dark *));

:root {
  --radius: 0.625rem;

  /* Roles de shadcn-vue */
  --background: …;  --foreground: …;
  --card: …;        --card-foreground: …;
  --popover: …;     --popover-foreground: …;
  --superficie: …;  /* propio: el chrome, separado del lienzo */
  --primary: oklch(0.52 0.13 196);
  --secondary: …;   --muted: …;
  --accent: …;      /* superficie de hover, NO el acento de marca */
  --destructive: …;
  --border: …;      --input: …;  --ring: oklch(0.52 0.13 196);

  /* Escalas */
  --marca-50 … --marca-950;      /* hue 196 */
  --violeta-50 … --violeta-950;  /* hue 299 */

  /* El acento de marca */
  --acento: var(--violeta-600);
  --acento-foreground: …;
  --acento-suave: var(--violeta-50);
  --acento-borde: var(--violeta-200);

  /* Estados del dominio, cada uno con su pareja -suave */
  --estado-no-iniciado … --estado-en-revision;

  --sombra-1; --sombra-2; --sombra-3;
  --duracion-rapida: 120ms; --duracion: 220ms; --duracion-lenta: 380ms;
  --curva: cubic-bezier(0.16, 1, 0.3, 1);
}

.dark {
  /* Sólo roles, estados y sombras. Las escalas NO se redefinen. */
}

@theme inline {
  --color-<rol>: var(--<rol>);   /* genera bg-, text-, border-, fill-, stroke- */
  --shadow-sombra-1: var(--sombra-1);
  --radius-sm/md/lg/xl: calc(var(--radius) ± n);
  --ease-marca: var(--curva);
}
```

**Convenciones**

- **`--acento` no es `--accent`.** `--accent` es la superficie de hover de shadcn y lo consumen decenas de primitivos; `--acento` es el violeta de marca. Unificarlos rompe menú, desplegable, select y sidebar de una vez.
- Los componentes leen tokens de rol (`bg-primary`, `text-muted-foreground`, `border-border`), no de escala. Así el modo oscuro no obliga a tocar componentes. Las escalas `marca-*` y `violeta-*` se usan sólo donde el color no depende del tema: el panel de acceso y los gráficos.
- **Ningún hex suelto** en plantillas ni en estilos, y tampoco en canvas: `BalanzaPixeles.vue` lee sus colores con `getComputedStyle` de los mismos tokens. Si un color no está en la paleta, se cambia el diseño, no se añade el color.
- Un componente base por elemento, con variantes por prop (`buttonVariants`, `badgeVariants`), no copias con clases distintas.
- Los tipos de TypeScript se **generan** desde PHP con `spatie/laravel-typescript-transformer` (`composer types`). Nunca se escriben a mano dos veces.

## 13. Voz

Frases cortas, verbos activos, tono profesional sin rigidez. Se habla de lo que el usuario hace, no de cómo está construido el sistema: «documentación pendiente de firma», no «registros con estado 2».

- El verbo se mantiene de principio a fin: si el botón dice «Publicar», el aviso dice «Publicado».
- Los errores explican qué ha pasado y qué hacer. No piden perdón ni culpan al usuario.
- Fechas completas y con vencimiento a la vista: «Vence el 14 de marzo de 2026 (en 12 días)».
- Nombres de norma exactos: «ISO/IEC 27001:2022», «Esquema Nacional de Seguridad (Real Decreto 311/2022)». En este sector, la imprecisión cuesta credibilidad.
- Sin exclamaciones ni emoji en la interfaz.

---

## 14. Comprobación antes de dar algo por terminado

- [ ] ¿Hay un único elemento fuerte en la pantalla?
- [ ] ¿El violeta se queda en torno al 10 % de la superficie?
- [ ] ¿Todo se alinea a la retícula de 4 px?
- [ ] ¿Los contrastes cumplen 4.5:1 en texto?
- [ ] ¿Se entiende cada estado sin ver el color?
- [ ] ¿Se distingue un campo obligatorio de uno que ha fallado?
- [ ] ¿El foco es visible recorriendo la pantalla con el tabulador?
- [ ] ¿Lo que se mueve se para con `prefers-reduced-motion: reduce`?
- [ ] ¿Funciona a 375 px de ancho?
- [ ] ¿Cada afirmación de la web tiene detrás un dato o una evidencia?
- [ ] ¿Hay algo decorativo que se pueda quitar sin perder información? Quítalo.
