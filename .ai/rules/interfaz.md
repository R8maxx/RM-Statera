---
paths:
  - resources/js/**
---

# La interfaz: vocabulario, librerías y movimiento

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **SSR desactivado** (`INERTIA_SSR_ENABLED=false`). La aplicación vive tras un login: no hay SEO ni primer pintado crítico que lo justifique. Con `@inertiajs/vite` volver a activarlo es cambiar la variable.

- **Los avisos van por el canal de flash de Inertia v3** (`Inertia::flash()` + `router.on('flash')`), no como prop compartido. Un prop se reenvía en cada recarga parcial y el aviso volvía a saltar al filtrar o paginar.

- **La balanza del panel de acceso es la única animación decorativa del producto**, y contradice a propósito el «lo decorativo no entra» de `lib/motion.ts`. El motivo: el panel se mira quince segundos antes de entrar, no ocho horas, y está fuera del chrome de trabajo. A cambio se apaga en tres condiciones —`prefers-reduced-motion`, pestaña en segundo plano y por debajo de `lg`— y en las tres se pinta un solo fotograma quieto. Es canvas 2D a mano (`lib/balanza.ts` + `components/BalanzaPixeles.vue`), sin librería: para setecientos puntos no hace falta un motor 3D, y aquí cada dependencia hay que justificarla en una revisión. La geometría sale del `viewBox` de `Logotipo.vue`, así que lo que gira **es** el logotipo; si alguien redibuja el símbolo, hay que redibujar la nube. **El tamaño lo decide la caja, nunca una medida escrita a mano:** `extension()` mide cuánto ocupa la figura en el fotograma más ancho de toda la vuelta y de ahí sale el `tam` que cabe, así que basta con meter el componente en un `flex-1` para que se adapte a la ventana y ningún platillo se sale en ningún ángulo. Volver a poner anchos en `rem` por punto de ruptura es el error que ya se cometió una vez.

- **Las cifras de resumen llegan contando, con `@number-flow/vue`, y la tabla no.** Es la única librería de animación que entra además de `motion-v`, y la frontera es la que importa: `components/Cifra.vue` se usa en el panel, en la tarjeta de inventario y en la tira de alertas —números que **resumen**— y **nunca en las celdas de una tabla**. Trescientas cifras contando a la vez cada vez que alguien filtra no es énfasis, es ruido, y contradice la regla de que las recargas parciales no se animan. El argumento a favor es el mismo que ya justificaba el contador de `AnilloProgreso`: una cifra que ya está puesta se da por leída y la vista pasa por encima.

  Tres detalles que costaron y que no se ven en el código de quien la usa: NumberFlow anima **al cambiar** el valor, no al montarse, así que la cuenta de entrada arranca en cero y salta al valor real en el siguiente fotograma; **no** arranca en cero si la pestaña está en segundo plano o hay movimiento reducido, porque `requestAnimationFrame` no corre ahí y el panel se quedaría enseñando ceros; y un `watch` sigue los cambios posteriores, porque sin él la cifra se quedaba con la del primer montaje y el panel mentía en silencio. Las duraciones salen de `lib/motion.ts`, no de la librería. Pesa 20 kB en su propio chunk, que sólo cargan las pantallas que la usan.

  `AnilloProgreso` conserva su propio contador con `useTransition`: ahí el número y el anillo tienen que moverse juntos, y separarlos en dos motores los desincronizaría.

- **El motion va con `motion-v`, no con GSAP.** Aquí no hay scroll-telling, ni *pinning*, ni *scrub*: lo que se anima son entradas, escalonados y transiciones de estado, y para eso GSAP es peso muerto en una herramienta que entra en el alcance del propio SGSI. Las duraciones y curvas viven en `resources/js/lib/motion.ts`, una sola vez, y la preferencia de movimiento reducido se resuelve en tres capas: el `@media` global de `app.css`, el `<MotionConfig reduced-motion="user">` de los layouts y el composable `useMovimientoReducido`.

- **`lib/navegacion.ts` es el mapa único de la aplicación.** Lo leen el sidebar, el panel lateral de móvil, las migas de pan y la paleta de comandos. Un módulo nuevo se añade ahí y aparece en los cuatro sitios; mantener cuatro listas a mano termina dejando un módulo fuera del buscador sin que nadie lo note. Los que pintan menú —sidebar, móvil y paleta— lo leen a través de `navegacionPara(permisos)`, que quita las entradas con un `permiso` que la sesión no tiene; las migas siguen con `entradaDe()` sobre el mapa entero. El grupo «Cumplimiento» se partió en Estado, Plan, Ciclo y Medida: los comentarios de posición de cada entrada («va detrás de…») son dentro de su grupo.

- **Las páginas de error se pintan con Inertia** (`resources/js/pages/Error.vue`, conectado en `bootstrap/app.php`). No es cosmética: el aislamiento multi-tenant responde **404, no 403**, cuando alguien pide un recurso de otra organización, porque decir «existe pero no es tuyo» ya sería filtrar información. Ese 404 lo ve gente real y con frecuencia, así que tiene que explicar qué ha pasado y llevar a alguna parte. Los 500 sólo se maquillan fuera de depuración: en local se quiere la traza de Laravel. `tests/Feature/ErroresTest.php` fija que la respuesta conserva su código de estado.

- **El formulario de acceso se ancla arriba, no se centra en vertical.** Con centrado, aparecer el aviso de credenciales incorrectas empuja todos los campos hacia abajo y hay que volver a buscar el cursor. El panel de marca de la derecha es de color sólido en los dos temas a propósito: es una superficie de marca, como lo sería una fotografía, no una sección que se haya quedado sin invertir.

- **En el acceso, logotipo, título, campos, ayuda y pie forman una sola pila y comparten borde izquierdo.** El logotipo estaba pegado al borde del navegador y el formulario centrado en una columna de casi mil píxeles: sin ningún eje en común se leían como dos cosas sueltas flotando en el mismo hueco. Por eso el `<footer>` repite el `mx-auto w-full max-w-[26rem]` de la pila en vez de centrarse en la columna. Y por eso **el símbolo no se repite**: el panel llevaba un `Logotipo` de 36 px justo encima de la balanza que gira, la misma figura dos veces en la misma superficie. El respaldo «un producto de RM Technology» vive en la columna del formulario, que es la única que se ve por debajo de `lg`.

- **`d3-hierarchy` y `@vue-flow/core` entran por el organigrama, y sólo por él.**
  La puerta que este documento tenía abierta a d3 era para escalas de tiempo, así
  que ésta es otra: lo que se compra es **la disposición de un árbol**, el
  tidy-tree de Reingold–Tilford, que es el otro caso de «no compensa escribirlo a
  mano» — hacerlo son unas ciento cincuenta líneas y ramas que se solapan en
  cuanto el árbol se ensancha. `d3-hierarchy` sigue cumpliendo el criterio de
  siempre: **función pura, sin DOM**.

  Vue Flow es la excepción de verdad, y va con su motivo: aporta el lienzo con su
  pan y su zoom, que es lo que hace usable un diagrama que no cabe en la pantalla,
  y **no calcula la disposición** — por eso `d3-hierarchy` hace falta igual y no
  es uno u otro. Lo que **no** aporta es el aspecto: los nodos son componentes
  nuestros con los tokens de `app.css`, así que la regla que descartó Chart.js
  —«obliga a escribir los colores en JavaScript en vez de leerlos de los tokens»—
  se sigue cumpliendo. El tema propio de la librería se reescribe contra los
  tokens en `Grafo.vue`.

  Las tres van a **versión exacta**, como TanStack Table y pragmatic-drag-and-drop:
  que una librería de interacción cambie de comportamiento bajo los pies no lo
  caza ningún test. Y pesan **72 kB gzip en su propio chunk**, que sólo carga esa
  pantalla: el bundle principal no se mueve. Mismo criterio que `@number-flow/vue`
  y que el editor de TipTap.

  **Dónde NO entran**: las gráficas del panel y de los documentos siguen a mano,
  por lo que dice el punto siguiente.

- **`elkjs` entra por el grafo de activos, y `d3-hierarchy` no servía.** No es
  preferencia: un organigrama es un **árbol** —cada puesto reporta a uno— y el
  grafo de activos es un **DAG**, porque `activo_dependencias` es N:M. Dos
  servicios pueden apoyarse en la misma base de datos, y ese rombo es justamente
  lo que hay que ver: es de donde a esa base de datos le sube la valoración
  efectiva. Un tidy-tree no sabe dibujarlo — tendría que romper uno de los dos
  vínculos, que es perder el dato por el que existe la pantalla.

  **Corre en el hilo principal y no en un web worker**, que es su diseño, y aquí
  no se puede tener: en desarrollo la aplicación se sirve por el 8000 (nginx) y
  los assets por el 5173 (Vite), y **un `Worker` de otro origen lo prohíbe el
  navegador** —«cannot be accessed from origin»—. Es una regla de seguridad, no
  algo que CORS arregle. Con `elk.bundled.js` no hay worker que construir; lo
  que se paga es que la colocación bloquea el hilo, y no se nota porque esta
  pantalla dibuja la **vecindad** de un activo y no el inventario entero.

  **Lo que cuesta, dicho con el número**: el chunk de esa pantalla pesa
  **445 kB gzip**, y es el más gordo del producto con diferencia. Va en su propio
  chunk y el bundle principal no se mueve, pero si algún día molesta, el cambio
  es a `@dagrejs/dagre` —unos 30 kB, también coloca DAGs— a cambio de perder el
  enrutado de aristas que es lo que hace legible un rombo. La decisión fue
  consciente.

- **Las gráficas se pintan a mano, y en el PDF las pintará el servidor.** El stack no decía nada de gráficas, ni a favor ni en contra, así que queda escrito aquí. Dos renderizadores por un motivo concreto: en un documento que va a PDF/A-3b y aspira a PDF/UA no debería ejecutarse JavaScript, porque un canvas entra como mapa de bits y se lleva por delante el texto seleccionable. En pantalla, SVG y CSS sobre los tokens de `app.css` (`AnilloProgreso`, `BarraSegmentada`, `components/grafica/`); en el documento, SVG generado en PHP cuando llegue el módulo de documentos. **Chart.js se descartó** por lo anterior y porque obliga a escribir los colores en JavaScript en vez de leerlos de los tokens. Una librería —`d3-scale` y `d3-shape`, que son funciones puras sin DOM— entra el día que haya una serie histórica **con eje de tiempo irregular**: escalas y ticks legibles es lo único que no compensa escribir a mano. **Con el § 4.14 dentro ya hay serie histórica y la librería sigue fuera**, y el matiz es el que importa: el eje de un indicador son cubos etiquetados y equiespaciados que impone `Periodicidad` —«T1 2026», «T2 2026»—, así que los ticks vienen escritos de casa y no hay escala que elegir. Lo pinta `grafica/GraficaSerie.vue` a mano.

- **No entró ninguna librería de gráficas, y hubo permiso para meterla.** Sigue valiendo lo que ya decía este documento: en un documento que va a PDF/A-3b no debe ejecutarse JavaScript, y una librería obliga a escribir los colores en JS en vez de leerlos de los tokens. La puerta abierta —`d3-scale` y `d3-shape`— es para cuando haya una **serie con fechas irregulares**; un inventario es una foto de hoy, y la serie de un indicador (§ 4.14) va por periodos regulares con la etiqueta puesta, que es el caso en el que esa librería no compra nada. `AnilloProgreso`, `BarraSegmentada` y `grafica/GraficaBarras` ya cubren el caso y ya llevan dentro lo que cuesta acertar: porcentaje con denominador, `role="img"` con su descripción, colores de token y movimiento reducido.

- **`BarraSegmentada` y `GraficaBarras` traducen el TONO, no la clave.** Lo que llega del servidor es el tono del dominio —`caducada`, `implantado`, `tipo:datos`—, no el valor del enum. Costó un rato: el tramo «No» de la cobertura salía gris porque el mapa buscaba `no` y el servidor mandaba `caducada`. Las clases van escritas enteras y nunca compuestas en ejecución, como en `CeldaBadge`.

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
