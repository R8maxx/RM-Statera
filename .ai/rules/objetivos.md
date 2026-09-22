---
paths:
  - app/Domain/Objetivo/**
  - resources/js/pages/objetivos/**
  - resources/js/components/objetivo/**
---

# Los objetivos de seguridad

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

- **No entra en el calendario de obligaciones**, y **sigue sin entrar después del § 4.16**. El objetivo
  vencido es el rojo del módulo y sólo se ve en la tabla, en el panel y en su ficha. No entra una
  `Fuente` propia a propósito: un objetivo tiene tareas detrás y sus plazos ya pintan chip, así que una
  `Fuente` suya pintaría dos el mismo día para un solo compromiso. Es el argumento exacto que dejó fuera
  la `fecha_prevista` de una no conformidad.

  > Lo que **sí** entró con el § 4.16 fue la `fecha_objetivo` del plan de adecuación, que esta misma
  > frase citaba como ejemplo de lo descartado. No es una contradicción: una medida pendiente **no**
  > tiene por qué tener tarea detrás —el hallazgo que el plan existe para enseñar es justamente ésa—,
  > así que ahí no hay chip que duplicar. Un objetivo sin tareas sí es un objetivo sin plan.
- **No comprueba que los objetivos cubran la política de seguridad.** La 6.2 a) pide que sean
  coherentes con ella; Statera registra lo que se declare y no dice si falta algo.
- **No exige que todo objetivo tenga indicador**, lo señala. Exigirlo impediría apuntar la idea el
  día que se tiene, que es el mismo motivo por el que la fecha es opcional en el borrador.
- **No entra en ningún documento.** El acta de la revisión por la dirección es el sitio donde estos
  objetivos se leen, y ese documento llega con el § 4.15.
