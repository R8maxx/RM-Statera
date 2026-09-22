---
paths:
  - app/Http/Resources/**
  - app/Http/Controllers/**
  - resources/js/components/tabla/**
  - resources/js/components/formulario/**
  - resources/js/composables/**
  - resources/js/lib/filtros.ts
  - resources/js/lib/celdas.ts
  - resources/js/lib/csv.ts
  - resources/js/lib/formularios.ts
---

# La capa de recursos

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

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **La clase base se llama `Recurso`, no `Resource`.** El directorio sí es `app/Http/Resources/`, como dice §2.1 del stack, pero `Resource` colisiona con el pseudo-tipo `resource` de PHP: Pint lo pasa a minúsculas en los docblocks (`@extends resource<Sistema>`) y a partir de ahí Larastan no resuelve el genérico. En español encaja además con `ConsultaRecurso`, `DefinicionRecurso` y `RespondeConRecurso`.

- **Un parámetro de filtro u ordenación no declarado se ignora, no rompe la petición** (`config/query-builder.php`). El 400 por defecto de spatie convierte cualquier URL guardada en un error en cuanto se renombra un filtro. Silencioso no es: `MetaTabla` devuelve el orden y los filtros aplicados de verdad.

- **Una celda pegada en horizontal no puede llevar alfa en el fondo.** En `DataTable`, las columnas ancladas heredan el fondo de su fila con `bg-inherit`. El hover era `bg-muted/50` y la fila seleccionada `bg-accent/40`: en cuanto el puntero entraba en la fila, la columna fija se volvía medio transparente y dejaba ver lo que se desplazaba por debajo. El comentario que exigía «un fondo opaco» estaba escrito justo encima de la línea que lo incumplía. Ahora son `--fila-hover` y `--fila-seleccionada`, la misma mezcla ya compuesta con `color-mix` contra `--card`. **Volver a poner un `/50` porque el hover parece fuerte reintroduce el fallo**, y el síntoma aparece a dos columnas de distancia de la causa.

- **`CampoBase` gana `etiquetaOculta`, y `FormularioRecurso` deja de anunciar asteriscos que no hay.**
  Lo primero, porque el título ya lo pone la `SeccionFormulario` y repetirlo justo debajo es ruido —el
  `<label>` sigue existiendo y asociado: quitarlo dejaría el control sin nombre accesible—. Lo segundo,
  porque la pantalla de textos no tiene ni un campo obligatorio y la leyenda seguía apareciendo.

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

- **`CampoTexto` gana `etiquetaOculta`.** `CampoBase` ya lo tenía y `CampoTexto` no lo reenviaba. Lo
  necesitan las filas repetidas —los diez escalones de las dos escalas—, donde el título de la sección ya
  dice qué son y repetir «Etiqueta del escalón 3» en cada fila es ruido. El `<label>` sigue existiendo y
  asociado: quitarlo dejaría el control sin nombre accesible.
