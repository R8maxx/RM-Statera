---
paths:
  - app/Domain/Catalogo/**
  - app/Domain/Categorizacion/**
  - catalogo/**
---

# El catálogo

**Contrastado con el BOE, y lo que falta.** El Anexo II se contrastó celda a celda contra el texto consolidado de BOE-A-2022-7191 y salieron **73 celdas mal de 273**, cinco títulos parafraseados y una medida de sobra —`op.exp.11`, numeración del RD 3/2010—. Lo más grave: nueve medidas que el ENS exige en categoría **básica** estaban como `no_aplica`, entre ellas `op.exp.7` (gestión de incidentes), `op.exp.8` (registro de la actividad) y `op.mon.1` (detección de intrusión). Es exactamente el fallo silencioso que encabeza la lista de prioridades de cobertura: dejaba a un sistema básico fuera de conformidad sin que nadie se enterara. Todo eso está corregido, y `tests/Feature/Catalogo/AnexoIIVerificadoTest.php` clava las celdas para que no vuelvan a torcerse.

**Cinco ficheros y cuatro raíces.** `catalogo/` tiene `iso27001-2022`, `ens-rd311-2022`, `mapeos-iso-ens`, `magerit-amenazas` y `obligaciones`; el importador reconoce cuatro claves raíz —`marco`, `mapeos`, `amenazas` y `obligaciones`— y las declara **una sola vez**, en `ImportadorCatalogo::RAICES`, que es lo que hace que un tipo nuevo entre ahí, en el `match` de `importar()` y en `ficherosDe()` y en ningún sitio más. El orden de `ficherosDe()` no es alfabético: amenazas, marcos, obligaciones —apuntan a un marco por su código— y mapeos al final, que necesitan los dos extremos.

`revisado` sigue en `false` en **los cinco** YAML. En los cuatro primeros, por **dos cosas que son modelo, no datos**:

1. **Nueve medidas están moduladas por varias dimensiones a la vez** (`op.acc.1` por T y A; `op.acc.2`–`op.acc.6` por C, I, T y A; `mp.com.3` y `mp.info.3` por I y A; `mp.si.2` por C e I) y `aplicabilidad_ens` guarda una sola `dimension_moduladora`. Mientras tanto se leen por categoría —el máximo de las cinco—, que exige de más pero **nunca de menos**.
2. **Diez celdas de cuatro medidas** (`op.acc.5`, `op.acc.6`, `mp.com.4`, `mp.s.2`) exigen un refuerzo **a elegir** entre varios: «+ [R1 o R2]». `Exigencia` guarda un único `Rn` y no sabe expresar una alternativa; queda registrado lo que se exige con seguridad.

Y en `obligaciones.yaml`, por una que es de datos: **`base_legal` cita el instrumento y el nombre de la obligación, no el artículo**. Las cláusulas de ISO 27001 que aparecen —8.2, 9.2 y 9.3— sí están contrastadas contra la norma; la numeración de artículos del RD 311/2022 está pendiente del mismo contraste celda a celda con BOE-A-2022-7191 que se hizo con el Anexo II. Una cita de artículo equivocada dentro de un entregable es peor que no citarlo: un auditor respeta una limitación declarada y suspende una inventada.

Y sobre los refuerzos: en el Anexo II **se acumulan** —«+ R1 + R2» exige los dos—, así que el catálogo guarda el mayor y `R2` se lee como «hasta R2», no como «sólo R2».

**Sobre el texto normativo:** en los YAML van código, título corto y atributos. La redacción íntegra de los controles de ISO 27001/27002 tiene derechos de autor y no se vuelca al repositorio. El ENS es texto del BOE y no tiene esa restricción.
