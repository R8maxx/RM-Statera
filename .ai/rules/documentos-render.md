---
paths:
  - resources/views/documentos/**
  - app/Domain/Documento/Render/**
  - resources/js/lib/cuerpoDocumento.ts
  - resources/js/lib/hojaDocumento.ts
  - resources/js/lib/markdownEditor.ts
---

# El renderizado de un documento

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **`pdfua()` está apagado a propósito.** Gotenberg rechaza la petición **entera** si el documento no
  es conforme, así que encenderlo antes de que las plantillas lleven `lang`, `<th scope>`, jerarquía
  de encabezados y `<title>` en cada SVG convierte la generación en algo que falla por sorpresa.
  `generateTaggedPdf()` sí va: es su prerrequisito y no rompe nada. PDF/A-3b sí está activo.

- **La cadena de tiempos de Gotenberg es `--api-timeout=120s` < Guzzle 180 s, y hasta cuatro llamadas
  por documento (4 × 120 s) < timeout de la cola `documentos` 600 s.** Las cuatro son la medida del
  índice, la portada, el cuerpo y la unión. Por eso `GotenbergHttp` construye un `GuzzleHttp\Client`
  explícito en vez de dejar que `Psr18ClientDiscovery` encuentre uno: el de por defecto trae 30 s y
  una SoA grande falla de forma intermitente con un error que apunta a Gotenberg, que no tiene
  ninguna culpa. El `uniqueFor` del job (900 s) va por encima del timeout.

- **A4 vertical, y una sola orientación por PDF.** Chromium sí imprime páginas con nombre de dos
  tamaños (`@page apaisada { size: A4 landscape }`), pero **la conversión a PDF/A-3b pasa por
  LibreOffice, que impone el tamaño de la primera página a todas** y corta lo que sobra: las
  apaisadas salían en vertical con la mitad derecha perdida. Comprobado con `pdfinfo` en el 8.9.1.
  Por eso la tabla larga de la SoA, la DdA y el plan va **en dos filas por requisito**
  (`ColumnasTabla::para()` arriba, `ColumnasTabla::detalle()` debajo a todo el ancho) y no en una
  sección apaisada. Cualquier tabla nueva tiene que caber en 6,85 in (`GeometriaPagina::anchoUtil()`).

- **La portada se imprime aparte y Gotenberg la une delante** (`pdfEngines()->merge()` con
  `pdfa()`). Chromium pinta cabecera y pie en **todas** las páginas de una impresión, también en una
  con margen cero: la cabecera salía encima de la banda. Consecuencias: el pie numera desde la
  primera página tras la portada, y la conversión a PDF/A se hace una sola vez, en la unión.
  `RenderizadorCuerpo::partes()` separa las dos, y **la banda no es un nodo del cuerpo**: la compone
  el renderizador con el wordmark, el título, el subtítulo y el filete que la portada ya tenía, así
  que los borradores guardados antes salen con ella sin migrar nada.

- **El índice se mide en una pasada aparte** (`IndiceDocumento`, `ClienteGotenberg::medir()`). Chromium
  no implementa `target-counter()`, así que se imprime el cuerpo sin PDF/A, con un marcador
  invisible por sección (`@@s-N@@`, en `position: absolute` para no mover nada) y con el índice ya
  puesto con números de relleno del mismo ancho; `LectorPaginas` lee con `pdftotext` en qué página
  cayó cada marcador, y la impresión definitiva lleva los números reales. Menos de tres secciones,
  no se mide; cuatro páginas de contenido o menos, no lleva índice. **El índice no se puede pulsar**
  en el PDF entregado: la conversión a PDF/A descarta los enlaces. Los marcadores laterales sí
  sobreviven y son el índice navegable.

- **Sin ligaduras y sin degradados**, por el mismo LibreOffice. Al resustituir las fuentes no
  encuentra el glifo de ligadura de la fuente original: «Justificación» salía «Justi ación». Y un
  `linear-gradient` lo trocea en tiras con una rendija de un pelo entre cada dos, que en pantalla se
  ve como un rayado diagonal. Por eso la banda de la portada es `marca-900` liso aunque DESIGN.md §3
  reserve el degradado justo a portadas, y `body` lleva `font-variant-ligatures: none`.

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
