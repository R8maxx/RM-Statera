---
paths:
  - app/Domain/Implantacion/**
  - resources/js/pages/implantaciones/**
  - resources/js/components/implantacion/**
---

# Las implantaciones

## Lo que está contado en otro sitio

Este módulo es la cocina del producto y casi nada de lo suyo se decidió aquí:

- **Los cuatro scopes** —`pendientes()`, `objetivoVencido()`, `sinFechaObjetivo()` y
  `sinTrabajo()`— y por qué cada uno se invoca por nombre desde el panel, desde el
  filtro y desde la consulta del plan: `.ai/rules/documentos.md`, en «El plan de
  adecuación». **`sinTrabajo()` mira `implantacion_tarea`**, y ése es el fallo caro
  que explica `.ai/rules/no-conformidades.md`.
- **Las transiciones de estado y el recálculo** tras un cambio de valoración son el
  punto 3 de la prioridad de cobertura de tests (`CLAUDE.md`).
- **Las salvaguardas de un riesgo apuntan aquí y no a `requisitos`**:
  `.ai/rules/riesgos.md`.
- **El filtro sobre una columna de otra tabla** —«Requisito» se pinta desde
  `requisitos.titulo`— es el ejemplo canónico de `.ai/rules/recursos.md`.

## La agrupación por atributo es una vista, no un modo de la tabla

`/implantaciones/atributos` (`ResumenPorAtributo`) agrupa lo exigible por un
atributo de la ISO 27002. **No es una opción de `DataTable`**, que no tiene
sub-filas a propósito: agrupar una tabla paginada en el servidor es otra consulta.
Cada fila enlaza a la tabla con el filtro de su valor, así que lo que se cuenta
aquí es lo que filtra allí. **Las filas no suman el total**: un control con varios
valores de una dimensión cuenta en cada uno, y la pantalla lo dice. La dimensión
llega de la petición y **sólo se consulta si está en el vocabulario**; la ruta del
JSONB va como binding. El orden de los tramos es `ResumenCumplimiento::ORDEN_DE_LA_BARRA`.

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **Ninguna cifra del panel se calcula en el controlador.** Viven en `app/Domain/Implantacion/ResumenCumplimiento.php`, porque son las mismas preguntas que contestará el informe de estado. Se cuenta siempre sobre lo exigible (`aplica = true`): un requisito que no se le exige al sistema no está pendiente, no cuenta. Y una media viaja siempre con su denominador — `madurezMedia` con `madurezEvaluadas`—: una media de madurez sobre cuatro requisitos de doscientos no dice lo mismo que sobre los doscientos.
