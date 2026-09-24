---
paths:
  - app/Domain/Continuidad/**
  - resources/js/pages/continuidad/**
  - resources/js/components/continuidad/**
---

# La continuidad

§ 4.11 y `op.cont.*`. **El módulo que cierra la fase 3.** El BIA de cada
servicio, el plan de continuidad que los cubre y las pruebas que demuestran que
el plan no es sólo papel. Vive en `app/Domain/Continuidad/`, con cuatro tablas
propias —`bia_servicios`, `pruebas_continuidad` y sus dos históricos— y tres
pivotes: `plan_continuidad_servicio`, `prueba_continuidad_servicio` y
`prueba_continuidad_tarea`.

### El BIA es por servicio, y uno solo

**Un activo de tipo `Servicios`, un BIA**: único por `(organizacion_id,
activo_id)`. El impacto de una caída se valora sobre lo que la organización
presta, no sobre el servidor que lo sostiene; lo que hay debajo ya lo recorre el
grafo de dependencias del inventario. Que el activo sea un servicio lo comprueba
`RegistrarBia` **en el dominio**, porque la base no puede mirar una columna de
otra tabla desde un `CHECK` y la regla vale igual para un importador.

**Que no tenga ya uno también se dice antes que la base.** El índice único es la
última línea, pero su error es un `QueryException`: `GuardarBiaRequest` lleva un
`Rule::unique` acotado a la organización, `RegistrarBia` lanza
`ServicioNoValido::yaTieneBia()` y el desplegable del alta ya no ofrece los
servicios con BIA. La primera versión respondía un 500.

**No hay BIA sucesivos como en `AnalisisContexto`.** `EditarBia` reescribe la
fila vigente; lo que queda del pasado vive en `bia_servicio_transiciones`, no en
filas nuevas.

### El MTPD se deriva y no se guarda

Cinco tramos de impacto —4 h, 1 día, 3 días, 1 semana, 1 mes— y el umbral
tolerable es **el primer tramo que llega a `muy_alto`**. Lo calcula
`UmbralTolerable::de()`; guardarlo en una columna sería una copia que se
desincroniza de los cinco tramos que la justifican, el mismo argumento que la
valoración efectiva de un activo.

**La monotonía la impone un `CHECK`** (`bia_servicios_monotonia_check`): el
impacto no puede bajar con el tiempo. Es lo que permite quedarse con el primer
`muy_alto` sin mirar los siguientes. Los literales del `CHECK` van escritos a
mano (`migraciones.md`). **Y `GuardarBiaRequest` la repite en su `after()`**,
con el error en el primer tramo que baja: sin ella, un formulario rellenado de
forma perfectamente predecible subía como un 500. En la edición compara lo que
llega sobre lo guardado, y el orden y el peso salen de `TramoImpacto::cases()` y
`NivelImpacto::peso()`, no de una lista copiada.

### Un RTO incoherente avisa y no bloquea

Un RTO por encima del umbral tolerable es una promesa que el propio BIA dice que
no se puede cumplir. **Se guarda igual.** Bloquearlo obligaría a quien valora a
inventarse un RTO que no tiene para poder guardar el análisis, y el hueco
desaparecería en vez de quedar a la vista.

**Va en ámbar (`en_progreso`) y no en rojo.** Es una contradicción que
corregir, no un plazo incumplido; la primera versión lo pintaba `caducada` y la
revisión lo tumbó. La regla está escrita dos veces —`rtoIncoherente()` en PHP y
`scopeRtoIncoherente()` en SQL, con un `CASE` sobre los mismos tramos— y **cada
una tiene su test**: divergir aquí es justo el fallo que el filtro de la tabla y
la cifra del panel no se pueden permitir. **Las dos dejan fuera los `obsoleto`**:
un servicio dado de baja no promete nada, y contarlo dejaba la alerta ámbar del
panel encendida para siempre.

### Editar lo aprobado lo devuelve a borrador

**Un BIA aprobado que se edita deja de estar vigente**, y decirlo con un estado
es lo que impide que la organización siga confiando en un RTO que alguien acaba
de cambiar. El paso pasa por `CambiarEstadoBia`, con fila en el histórico y una
nota que pone el sistema.

**`EditarBia` sólo acepta campos de contenido**, con una lista blanca, y un
campo de ciclo de vida —`estado`, `aprobado_por_id`, `fecha_aprobacion`,
`fecha_revision`— lanza `InvalidArgumentException`. `$fillable` los incluye
porque `CambiarEstadoBia` los escribe; sin la lista, un caller podía cambiar el
estado **sin fila en el histórico** (invariante 7) y, para `obsoleto`, sin que
ningún `CHECK` lo notara. Lo encontró la revisión de la primera tarea.

**Aprobar sella quién, cuándo y la revisión a doce meses.** Salir de aprobado
suelta los dos primeros y conserva la fecha de revisión. **Aprobar es
`continuidad.aprobar`, un verbo aparte**: aceptar un RTO es aceptar un riesgo,
la misma línea que separa `riesgos.aceptar` de `riesgos.gestionar`. La técnica
registra, edita y prueba; no aprueba.

### El plan es un documento

`TipoDocumento::PlanContinuidad`, el cuarto redactado. **No una tabla propia**:
aprobación, versiones, firma, acuse de lectura, revisión periódica y PDF/A ya
existen en `Documento/`, y un plan con su propio ciclo sería el segundo flujo
documental del producto. Lo único nuevo es la pivote de servicios cubiertos,
`Documento::serviciosCubiertos()`, que `VincularServicioAPlan` escribe tras
comprobar que el documento es un plan y el activo un servicio.

**Un servicio puede estar cubierto por varios planes**: el único es
`(documento_id, activo_id)`, no `activo_id`. Un plan general y uno específico
del servicio es lo normal. Por eso `BiaServicio::planes()` cruza por
`activo_id`: la pivote ata el plan al servicio, no a un BIA concreto.

**Un plan con pruebas no se borra.** `pruebas_continuidad.documento_id` lleva
`restrictOnDelete`, y `DocumentoController::destroy()` lo comprueba antes con un
mensaje legible: una prueba registrada es la evidencia de `op.cont.3`, y sin
guarda la respuesta era un `QueryException` crudo.

### Las pruebas, y el único rojo de la ficha

Cuatro tipos —sobremesa, simulacro, técnica, completa— en gris neutro, porque un
tipo dice qué se hizo y no cómo fue. **Dos salidas desde `planificada`, cada una
con su acción**: `RegistrarResultadoPrueba` y `CancelarPrueba`. No hay un
`CambiarEstadoPrueba` genérico porque las dos exigen datos distintos, y las dos
son terminales: lo que se hace después es planificar la siguiente.

**Registrar el resultado rechaza servicios ajenos a la prueba.** La pivote sólo
se actualiza con `updateExistingPivot()`, nunca se amplía; un `activo_id` que la
prueba no cubre lanza `servicioAjeno()`. La primera versión usaba
`syncWithoutDetaching()` y un formulario manipulado colaba cualquier activo de la
organización en una prueba ya hecha.

**Una prueba fallida no es roja, y no probar sí.** `ResultadoPrueba::Fallida` va
en `en_revision`: fallar un simulacro es la prueba **funcionando**, descubriendo
el hueco antes de la caída real. Lo que incumple es no probar, y eso lo pinta la
prueba planificada vencida (`vencidas`, `caducada`) y la ausencia de filas. El
único rojo de la ficha es **el RTO alcanzado por encima del objetivo**, en
`ComparativaRecuperacion`: un incumplimiento medido.

### Las costuras

`DerivarDePrueba`, calcando `AbrirAccionCorrectiva`: el origen se pone, no se
pregunta, y sólo desde una prueba `realizada` cuyo resultado no sea `superada`.

- **Tarea** con `OrigenTarea::Continuidad` y fila en `prueba_continuidad_tarea`:
  de ahí sale cuánto trabajo dejó una prueba.
- **No conformidad** por `no_conformidades.prueba_continuidad_id`, espejo exacto
  de `incidente_id`: nullable, único y `nullOnDelete`. **Una sola por prueba**,
  porque una prueba se trata una vez: la segunda lanza
  `TransicionDePruebaNoPermitida::yaTratada()` antes de llegar al índice, que era
  un `QueryException` con dos pestañas o un doble envío. **Y su origen queda
  fijo**: `GuardarNoConformidadRequest` rechaza cambiarlo en la edición, y el
  formulario no ofrece el desplegable (`origenFijo`); lo mismo con `incidente_id`.
- **Mejoras, varias y sin clave foránea**, como `OrigenMejora::Incidente`: la
  mejora no trata la prueba, así que atarla fingiría una trazabilidad que no hay.

Las tres rutas piden el permiso del módulo destino **y `continuidad.ver`**:
derivar parte de la ficha de la prueba, y quien no puede leerla no escribe a
partir de ella.

El `down()` de `…090600` reasigna a `propia` las no conformidades nacidas de una
prueba —y suelta `prueba_continuidad_id` **en el mismo `update`**, porque el
`CHECK` que acopla los dos sigue vivo en ese punto— antes de estrechar el de
origen, con `comoMantenimiento()` para que RLS no lo deje en cero filas sin
fallar. La primera versión se había verificado sobre una base vacía y abortaba
con datos. **Y el `down()` de `…090200` tenía el mismo fallo**: borraba los
documentos `plan_continuidad` sin mantenimiento, cero filas sin error, y el
`ALTER TABLE` moría con el `PLN-CONT-01` sembrado. Todo `down()` que toque
filas de una tabla con RLS va por `comoMantenimiento()`.

### Navegación: una entrada y dos pestañas

**Una sola entrada «Continuidad»** y las pestañas BIA | Pruebas dentro
(`PestanasContinuidad`), no una entrada hermana: `esSeccionActiva()` compara con
`startsWith` y dos entradas bajo `/continuidad` se encenderían a la vez. El
`href` es `/continuidad` porque `PermisosDeLaCuenta::href()` lo deriva del
prefijo del permiso, y `/continuidad` **redirige** a `/continuidad/bia` fuera de
cualquier `can:`, mismo sitio y motivo que `/` → `/panel`.

### El calendario, el panel y la obligación

**Dos `Fuente`**: `PruebaContinuidad` por `fecha_prevista` y `Bia` por
`fecha_revision`. **Sólo cuentan los BIA aprobados y las pruebas planificadas**:
un borrador no tiene revisión comprometida —su fecha es el recordatorio de
volver a aprobarlo— y una prueba terminal no tiene nada pendiente. El filtro de
la tabla y la alerta del panel usan los mismos scopes.

**`RegistroContinuidad` centraliza los indicadores** de los dos listados y de
`AlertasDelPanel`, y cada indicador cuenta con el scope de su filtro y lleva su
misma clave: pulsar la cifra enseña esa cifra.

**La obligación anual de probar los planes sale de `op.cont.3`, no de la
categoría.** `op.cont.3` sólo se exige cuando la Disponibilidad llega a alto, y
como la categoría es el máximo de las cinco dimensiones, eso siempre cae en un
sistema de categoría alta. `categoria_minima: media` exigía **de más**: la
proponía a todo sistema de categoría media, y a los de alta cuya Disponibilidad
no llega a alto. Nunca exigía de menos. El requisito la propone exactamente
donde el motor hace exigible `op.cont.3`, sin copiar su regla en el YAML. Es
`obligaciones.requisito_id`, contra `Implantacion::aplicables()`. Y
**una prueba puede citarse como cumplimiento**: `compromiso_cumplimientos` gana
`prueba_continuidad_id`, **sólo una `realizada` de la organización**
(`RegistrarCumplimientoRequest`): una planificada o cancelada no demuestra nada.
Detalle en `obligaciones.md`.

### Lo que este módulo declara que no hace todavía

- **No entra en la revisión por la dirección.** La 9.3.2 no nombra la
  continuidad entre sus entradas, y el acta no la recoge.
- **Ningún `CalculoIndicador`.** No hay indicador calculado de pruebas hechas ni
  de BIA vigentes; quien quiera medirlo lo declara manual.
- **No es un informe.** El plan se redacta; un informe de continuidad con el BIA
  y las pruebas impresos no está entre los seis del § 4.18, que está hecho sin él:
  sería un tipo de documento nuevo.
- **No mueve implantaciones** (invariante 4). Aprobar un BIA o superar una prueba
  no marca `op.cont.*` como implantada: eso sigue siendo una transición con autor.
- **`op.cont.4` —medios alternativos— no tiene registro propio.** Se documenta
  dentro del plan.
- **La comparación del RTO alcanzado es contra el BIA de hoy**, no contra el
  vigente cuando se hizo la prueba: `excedeRto()` lee `bia_servicios`, que se
  reescribe al editar.
- **El plan nace con los cinco huecos genéricos de todo redactado**
  —introducción, objeto y alcance, conclusiones, limitaciones y aprobación—, no
  con secciones propias como «criterios de activación» o «cadena de mando».
  `SeccionNarrativa` es un catálogo **cerrado y compartido** por todos los tipos:
  nombrar huecos por tipo rompería el diff entre versiones y la jerarquía de
  encabezados de PDF/UA. Se escriben dentro de esos cinco.

**Y una limitación impresa pasó a ser falsa con este módulo dentro**: la del plan
de adecuación, que decía que el calendario no recogía las pruebas de continuidad
«cuyos módulos no existen». Se reescribió; ahora sólo nombra la reevaluación de
proveedores.
