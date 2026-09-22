---
paths:
  - routes/**
---

# Las rutas

## `scopeBindings()` pluraliza en inglés, y el dominio se nombra en español

Es el mismo fallo cuatro veces, y las cuatro lo cazó un test de aislamiento y no una
revisión. `scopeBindings()` deduce el nombre de la relación pluralizando el nombre del
**parámetro de ruta en inglés**. Con el dominio en español eso falla de dos maneras a
la vez: la ruta responde 500 con un «Call to undefined method» que no menciona ni la
ruta ni la relación, y **de paso deja de acotar** —el hijo de otro padre se resuelve
desde éste—.

Se arregla escribiendo `resolveChildRouteBinding()` a mano en el modelo padre. Los
cuatro precedentes, cada uno con su razonamiento entero en el fichero de su módulo:

| Modelo padre | Ruta | Lo que dedujo mal | Dónde está contado |
|---|---|---|---|
| `Documento` | `/documentos/{documento}/versiones/{version}` | `version` → `versions` | `.ai/rules/documentos.md` |
| `Indicador` | `/indicadores/{indicador}/mediciones/{medicion}` | `medicion` → `medicions` | `.ai/rules/metricas.md` |
| `Objetivo` | `/objetivos/{objetivo}/indicadores/{indicador}` | `indicador` → `indicadors` | `.ai/rules/objetivos.md` |
| `Persona` | `/personas/{persona}/designaciones/{designacion}` | `designacion` → `designacions` | `.ai/rules/personas.md` |

**Lo que hace este fallo difícil de ver leyendo las rutas** es que no falla siempre:
`tarea`, `acuerdo` y `paso` no hacen falta declararlas porque su plural inglés coincide
con el español. Que una ruta hermana funcione no dice nada de la de al lado.

**Y el parámetro de una sesión de formación es `{accion}` y no `{accion_formativa}`.**
El binding implícito empareja por **nombre de parámetro**, así que con
`{accion_formativa}` y un argumento `$accion` Laravel inyecta un modelo vacío: no
lanza, **escribe mal**, y la escritura muere con un «null value in column».

## Todo lo que cuelga de un padre va con `scopeBindings()`

Entre dos registros de la **misma** organización no hay ninguna de las tres capas de
aislamiento: el `where` de la relación es la frontera y no un filtro. Está contado en
`.ai/rules/auditorias.md`, que es donde ese eje apareció por primera vez.

## El orden del middleware

`EstablecerContextoOrganizacion` va **antes** de `SubstituteBindings`, y por eso
`bootstrap/app.php` saca este último de su sitio por defecto. Sin contexto fijado, el
scope no devuelve nada y **cualquier** ruta con `{sistema}` responde 404. El
razonamiento entero y el test que lo fija están en `.ai/rules/aislamiento.md`.
