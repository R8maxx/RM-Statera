---
paths:
  - app/Domain/Proveedor/**
  - resources/js/pages/proveedores/**
  - app/Http/Controllers/ProveedorController.php
  - app/Http/Resources/ProveedorRecurso.php
  - catalogo/clausulas-proveedor.yaml
---

# Proveedores y terceros (§ 4.9)

Es el punto 29. A.5.19 a A.5.23 de ISO y `op.ext`/`op.nub` del ENS piden saber con
quién se trabaja, comprobar su contrato y volver a comprobarlo. `op.nub.1` es
exigible desde la categoría básica, y las de ISO siempre. Cerró cuatro enganches:

- `activos.proveedor_id`, que se aplazó desde el § 4.2;
- la frase impresa del plan de adecuación;
- la exclusión escrita en `catalogo/obligaciones.yaml`;
- el hueco del grupo «Organización» de `lib/navegacion.ts`.

## Tres decisiones, las tres de César

**La criticidad tiene un mínimo derivado.** Sale de la valoración más alta
—propia, en las cinco dimensiones— de los activos que presta, y se guarda en
`criticidad_derivada`.

- **Declararla por encima es libre; por debajo exige justificación**
  (`CriticidadProveedor::validar()`). Es la que decide cada cuánto se reevalúa, y
  bajarla sin decir por qué es la forma más barata de no volver a mirar un
  contrato.
- **Sin activos no hay derivada, y hay que declararla**: una gestoría o la
  limpieza con acceso físico no prestan nada del inventario y siguen siendo
  terceros.
- **Se recalcula sola** desde `Activo::booted()` cuando un activo cambia de
  proveedor o de valoración.
- Si deja de prestar el último activo con la declarada vacía, **la última
  derivada se congela como declarada**. El `CHECK` exige una de las dos, y dejar
  la fila sin criticidad reventaría el guardado del activo, que no tiene la culpa.

**Lo que se comprueba es catálogo** (`catalogo/clausulas-proveedor.yaml`, tabla
global `clausulas_contractuales`).

- Mismo contrato que las amenazas: clave natural, huella y retirada por marca.
- Las `referencias` van por `(marco, requisito)` y **el importador rechaza la que
  no existe**, porque una cláusula que dice sostener un control inexistente es
  una cita inventada.
- El fichero va después de los marcos en `ficherosDe()`, por lo mismo que las
  obligaciones.

**La reevaluación es política de la organización**: meses por criticidad en su
ficha (`organizaciones.reevaluacion_proveedor_*_meses`, 12/24/36 por defecto y
entre 1 y 120). Ni ISO ni el ENS fijan el plazo.

## El estado lo decide la evaluación

- Apto homologa, apto con condiciones deja condicionado y no apto rechaza
  (`ResultadoEvaluacion::estadoResultante()`).
- A mano sólo se **retira**, con motivo, y se **reactiva**, que vuelve a «en
  evaluación»: lo evaluado antes de retirarlo ya no dice nada del contrato de hoy.
- Todo pasa por `CambiarEstadoProveedor`, que deja la transición (invariante 7).
- **Una reevaluación que confirma el estado no escribe transición**: el histórico
  no gana nada con «homologado → homologado». Sí mueve la fecha.

**Una evaluación contesta todas las cláusulas vigentes**, y ninguna viene marcada
en la pantalla: una respuesta preseleccionada es una que nadie leyó.

- **Apto con cláusulas incumplidas no se admite**: eso es «apto con
  condiciones», y las condiciones van escritas. El `CHECK` exige conclusiones en
  todo lo que no es apto.
- **No se edita** después (`updating` lanza, y no hay ruta). Lo registrado es lo
  que decía el contrato ese día.
- **La criticidad se congela en la evaluación**, porque es la que decidió cuándo
  tocaba la siguiente.
- La pantalla de evaluar **no copia la evaluación anterior**: partir de lo que se
  contestó hace un año es la forma más rápida de no volver a leer el contrato.

## `proxima_evaluacion` es una copia, y hay que mantenerla

Se deriva de la última evaluación y de los meses de su criticidad
(`RecalcularReevaluacion`). Se guarda sólo porque el calendario la consulta por
rango, igual que `bia_servicios.fecha_revision`. Hay **cuatro sitios** que la
recalculan, y uno nuevo tendría que hacerlo también:

- `CambiarEstadoProveedor::aplicar()`, que cubre la evaluación, retirar y
  reactivar;
- `CriticidadProveedor::recalcular()`, cuando cambian los activos;
- `ProveedorController::update()`, cuando cambia la declarada;
- `GuardarFichaOrganizacion`, cuando cambia la política (`todos()`).

Sin evaluación no hay fecha: lo que falta no es reevaluar sino evaluar por
primera vez, y eso lo cuenta el panel como pendiente (`sinEvaluar`). Lo retirado
tampoco tiene.

## Las costuras

- **`Fuente::Proveedor`** en el calendario y en el correo diario. Es una `Fuente`
  y no una fila del catálogo de obligaciones, que es lo que el propio YAML dejó
  dicho: sale de un registro. La frase de la bitácora del § 4.16 que decía «una
  línea de YAML» se quedó corta por eso. Icono `Truck`, el mismo del menú.
- **`OrigenTarea::Proveedor`** con pivote `proveedor_tarea`
  (`DerivarTareaDeProveedor`), como la de una prueba de continuidad. La
  dependencia va de proveedores a tareas; el plan de acción no sabe de
  proveedores.
- **Dos rojos en el panel** (`RegistroProveedores::alertas()`): la reevaluación
  vencida y la certificación caducada de un proveedor no retirado. Los dos
  caducan solos, como una evidencia. Sin evaluar y condicionado son trabajo
  pendiente, no alarma. La tarjeta va en «La organización», detrás del
  inventario, con el reparto por la criticidad que manda. **Hasta el recorrido de
  vulnerabilidades el módulo declaraba que no tenía tarjeta, y el rojo tampoco
  marcaba ninguna pestaña**: estaba en `FUENTES` y no en `VISTAS`. Ver `panel.md`.
- **El informe de estado** lo imprime como un registro más.
- **El certificado es una evidencia**: `proveedor_certificaciones.evidencia_id`.
  Es donde ya viven los ficheros con caducidad, y así también puede probar
  A.5.19.

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **`Proveedor` no lleva `AcotadoPorAlcance`**, y no es un olvido: no tiene
  `sistema_id`, y un proveedor sirve a la organización. El auditor externo lo ve
  como la política o el contexto.
- **`Proveedor::resolveChildRouteBinding()` escrito a mano** para
  `/certificaciones/{certificacion}`: `scopeBindings()` habría pluralizado
  `certificacions`. Es el quinto caso de `routing.md`.
- **Tres permisos y `proveedores.evaluar` es de supervisión**, con su migración de
  siembra (`2026_09_29_090400`): un permiso nuevo no llega solo a las
  organizaciones que ya existen, que es la lección del punto 28.

## Lo que este módulo declara que no hace todavía

- **No hay no conformidad desde un proveedor.** Una evaluación no apta deja al
  proveedor rechazado y puede abrir una tarea; si además es un incumplimiento del
  SGSI, la no conformidad se abre a mano desde su módulo.
- **La evaluación no se vincula sola a los requisitos que dice cubrir.** Las
  referencias de cada cláusula están sin contrastar (`revisado: false`), así que
  la evaluación no marca ningún control como probado: es una evidencia más, que
  hay que vincular.
- **No se comprueba que la categoría ENS del certificado sea la del sistema.** La
  cláusula CLA-09 lo pide y se contesta a mano. Cruzarlo exigiría saber a qué
  sistema presta servicio el proveedor, y eso hoy sale de sus activos solamente.
- **La ficha de una tarea no enseña de qué proveedor viene**: la pivote existe y
  la ficha del proveedor la lista, pero el camino inverso no está pintado.
