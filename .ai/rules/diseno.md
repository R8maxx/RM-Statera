---
paths:
  - resources/css/**
  - resources/js/components/ui/**
---

# La paleta y los tokens

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **`DESIGN.md` se corrigió al código, no al revés.** El documento venía describiendo otra marca: un símbolo en cinta con degradado teal→violeta, teal en hue 212, neutros `ink-*` y Montserrat. Nada de eso estaba implementado y las tres decisiones del código tenían motivo escrito, así que ganaron ellas: **la balanza** (§2), **hue 196** (§3) e **Instrument Sans** (§4). Lo único que se tomó del documento tal cual fue el violeta de acento. Los hex y los contrastes de §3 son conversión calculada de los `oklch` de `app.css`: si se retoca la paleta, se recalculan, no se estiman.

- **El color de marca es teal petróleo, hue 196** (`oklch(0.52 0.13 196)` en claro, `oklch(0.8 0.12 196)` en oscuro; los valores de `app.css`, que es quien manda). No es preferencia estética: la paleta de estados del dominio ocupa 245 (`planificado`), 155 (`implantado`), 70 (`en_progreso`) y 27 (`destructive`), y el teal es el hue libre más alejado de todos ellos. Un botón primario en verde o en ámbar se confundiría con un badge de estado. Los tokens `--estado-*` son semántica del dominio y **no se retocan** al cambiar la marca.

- **`--acento` (violeta de marca) y `--accent` (superficie de hover de shadcn) son cosas distintas y tienen nombres distintos a propósito.** `--accent` es el teal pálido que pintan el ítem activo del sidebar, el menú, el desplegable y el select; unificarlo con el acento de marca los rompe todos a la vez. El violeta vive en `--acento`, `--acento-suave`, `--acento-borde` y la escala `--violeta-*`.

- **El violeta se queda en cuatro sitios y sólo cuatro:** el filete de `CabeceraPagina` (uno por pantalla), la variante `acento` del botón —reservada a flujos de revisión y auditoría, y hoy en «Aprobar y entregar»—, el token `--estado-en-revision` —que desde el § 4.5 **sí tiene flujo detrás**: es el badge de una versión esperando firma— y la balanza del acceso. Ese token tiene desde el § 4.13 **dos dueños**, y no es una grieta: el otro es `EstadoNoConformidad::Cerrada`, «tratada y pendiente de verificar», que significa exactamente lo mismo —hecho y esperando a que alguien con potestad lo confirme—. Un token con dos dueños que quieren decir lo mismo sigue significando algo; el violeta se rompe cuando pasa a ser decoración, no cuando lo usa el segundo flujo de revisión del producto. **No** en enlaces, **no** en el anillo de foco y **no** en el resto de badges de estado. El reparto es 60/30/10 y el violeta que se ve en todas partes deja de ser acento.

- **La escala `--marca-*` no se invierte en oscuro.** 50 es el tono más claro y 950 el más oscuro en los dos temas. Invertirla parecía elegante y era una trampa: `bg-marca-900 text-marca-950` deja de tener sentido en la mitad de los casos y el contraste se rompe sin que se vea en el fichero que lo usa. Lo que cambia de tema son los roles (`--primary`, `--accent`).

- **Un solo sistema de radios.** Superficies (tarjeta, tabla, diálogo, aviso) `rounded-xl`; controles (botón, input, select) `rounded-md`; badges, avatares y chips `rounded-full`. El escalón de superficie lo trae el estilo `reka-vega` de shadcn-vue en `Card` y todo lo que hace de panel lo iguala.

- **`hover:bg-primary/80` del botón primario se cambió por `hover:bg-marca-700`.** El original mezcla con el fondo, y sobre claro **aclara** el botón: con el teal de marca el contraste del texto caía a 3.4:1 y dejaba de pasar AA justo al pasar el puntero. Oscurecer un paso lo sube en lugar de bajarlo.

- **Los tipos de activo tienen paleta propia, `--tipo-*`, y no reutilizan los `--estado-*`.** Un estado dice *cómo va* algo y un tipo dice *qué es*; con la misma saturación, un badge de tipo en verde se leería como «implantado». Se separan por croma —0.10 frente a 0.13— y las cifras están medidas en `DESIGN.md` §3: contraste 5.58 en el peor caso y ΔE 6.2 frente al estado más cercano. **La peor pareja tipo↔tipo queda en ΔE 5.2, por debajo del suelo de 6**, y es aceptable sólo porque estos badges nunca se tocan y **siempre llevan icono**: el color agrupa, el icono identifica. Quitar el icono de `CeldaBadge` deja la distinción por debajo del umbral, así que no es decoración.

- **La variante `acento` del botón ya tiene su primer uso: «Emitir versión».** Era la que DESIGN.md
  reservaba a los flujos de revisión y auditoría, y entregar un documento al auditor es exactamente
  eso. Con ella en pantalla, «Generar borrador» baja a `outline`: **dos botones de color lleno a la
  vez y no manda ninguno**.

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
