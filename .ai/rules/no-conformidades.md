---
paths:
  - app/Domain/NoConformidad/**
  - resources/js/pages/no-conformidades/**
  - resources/js/components/no-conformidad/**
---

# Las no conformidades

§ 4.13, la cláusula 10.2 de ISO y la otra mitad del módulo anterior. Un hallazgo dice qué se encontró;
esto dice por qué pasó, qué se hizo, quién responde y **si funcionó**. Tres tablas —`no_conformidades`,
`no_conformidad_tarea` y `no_conformidad_transiciones`— y cuatro desvíos de la § 2.2, con el primero
dando forma al resto.

**`accion_correctiva` no es una columna de texto: es una tarea.** Una acción correctiva tiene
responsable, plazo, estado y coste, que es literalmente `tareas`. Con una columna de texto, el trabajo
correctivo quedaría fuera del tablero, del calendario, del aviso diario y del presupuesto del plan de
adecuación — cinco sitios donde hay que verlo. Y el vínculo es **N:M**, como `implantacion_tarea`:
«implantar MFA» cierra a la vez una no conformidad de la auditoría ISO y otra de la autoevaluación del
ENS.

**Dos columnas de fecha y dos `CHECK`, no una.** `fecha_cierre` es cuándo se dio por tratada y
`fecha_verificacion` cuándo se comprobó que la corrección sirvió. Son dos momentos distintos —la
eficacia se mira semanas después, cuando hay con qué mirarla— y con una sola columna la verificación
que llega en noviembre no tiene dónde fecharse. `anulada` entra en el acoplamiento del cierre por el
mismo argumento que metió `descartada` en el de tareas: la pregunta del auditor es «¿desde cuándo dejó
de estar abierta?».

**`eficacia_verificada` no es un booleano, es un estado.** Como bandera sería el mismo dato que
`estado = 'verificada'` en dos sitios que pueden desincronizarse. Lo que sí merece columna es
`resultado_verificacion`: **qué** se comprobó. Mismo reparto que `nota_aceptacion` en riesgos.

**La verificación fallida no es un estado, es la vuelta a `en_tratamiento`.** Un `no_eficaz` se
quedaría puesto sobre una no conformidad que sigue viva y volvería a contarse como cerrada en cuanto
alguien lo mirara por encima. Precedente exacto: `EstadoAuditoria::Cerrada → EnCurso`. Y esa vuelta
**suelta las tres columnas de la verificación**, no sólo la fecha: dejar el resultado puesto sin su
fecha sería enseñar una comprobación que ya no consta. No se pierde nada, porque al verificar el texto
se copia además a la nota de la transición.

**Tres transiciones exigen motivo escrito**, y la regla vive en el dominio y no en el `FormRequest`
porque vale también para un importador: anular —«esto no era una no conformidad»—, verificar —donde la
nota *es* el resultado— y reabrir el tratamiento —donde «qué falló» es lo único que explica el ir y
venir—. El motivo de `anulada` va en la nota y no en columna propia, como `descartada` en tareas.

**`no_conformidades.verificar` es el quinto verbo de supervisión**, junto a `sistemas.valorar`,
`riesgos.aceptar` y `documentos.aprobar`, y es el que mejor explica la familia: comprobar que una
acción correctiva funcionó no puede hacerlo quien la ejecutó. El técnico trata la no conformidad
entera y no la firma. Y sí, `no_conformidades.*` rompe el patrón de una palabra de los otros nueve
módulos: se queda así porque casa con la tabla y con la ruta, y tres nombres para la misma cosa
cuestan más que un guion bajo.

### El doble vínculo, que es el fallo caro

`Implantacion::sinTrabajo()` mira `implantacion_tarea`. Una acción correctiva colgada **sólo** de la no
conformidad no está ahí, así que el plan de adecuación imprimiría «sin trabajo planificado» sobre una
medida que sí lo tiene — en la tabla que la dirección mira seguro. Por eso
`VincularAccionCorrectiva::vincular()` ata los dos vínculos, y cuatro precisiones:

1. **Vive en la acción de dominio y no en el controlador.** Si sólo lo hiciera el formulario de alta,
   la tarea que alguien vincule más tarde desde la ficha no lo tendría y el falso positivo volvería por
   la otra puerta.
2. **Cubre una parte de los casos.** Hace falta hallazgo **con punto de checklist**: ahí hay medida
   detrás. Una no conformidad suelta, o de un hallazgo sobre el sistema de gestión, no tiene a qué
   apuntar, y forzarla contra una implantación arbitraria es el vicio que `OrigenTarea::Propia` existe
   para evitar.
3. **No depende del estado de la auditoría.** Escribir en la pivote no pasa por el trigger de
   inmutabilidad —que blinda la checklist y los hallazgos, no lo que cuelga de ellos—, y es lo
   correcto: las no conformidades se tratan **después** de cerrar. Pero es donde uno espera un error,
   así que hay test.
4. **Desvincular no suelta el vínculo con la medida.** No hay forma de saber si lo puso esto o una
   persona desde la ficha de la implantación, y quitarlo a ciegas borraría trabajo planificado a mano.

Ninguna de las trece cifras que cuentan tareas se mueve al vincular: todas cuentan filas de `tareas` y
esto no crea ninguna. Lo que sube es el total del plan de adecuación, porque trabajo que era invisible
pasa a estar presupuestado. `Coste::total()` sigue contando cada tarea una vez.

**`OrigenTarea::NoConformidad` no está en § 4.7 y es el que de verdad usa una auditoría.** La
especificación enumera «hallazgo», y una tarea no cuelga nunca de un hallazgo: cuelga de la no
conformidad que lo trata. `Hallazgo` sigue declarado y sin ofrecerse, y desde el § 4.13 **por otro
motivo** —no es que falte su módulo, es que hay un eslabón por medio—; esa frase estaba escrita en el
enum y pasó a ser falsa en cuanto llegó el § 4.12.

**El origen de una acción correctiva se pone, no se pregunta**, como en `/tareas/crear?implantacion=`:
preguntarlo invita a cambiarlo. `AbrirAccionCorrectiva` es además el único camino que produce tareas
con ese origen, y vive en `Domain\NoConformidad` y no en `Domain\Tarea` por la dirección de la
dependencia: este módulo sabe de tareas, y el plan de acción no tiene por qué saber de no
conformidades.

**Un hallazgo se trata una vez**, y lo impone un índice único sobre `hallazgo_id`. Sin él, «hallazgos
sin tratar» dependería de cuál de las dos filas se mirase. En PostgreSQL los nulos son distintos entre
sí, así que el mismo índice deja pasar todas las no conformidades sueltas que hagan falta. Y el
hallazgo va con `nullOnDelete` y no con cascada, a diferencia de casi todo el módulo: borrar el
hallazgo de una auditoría abierta no puede llevarse por delante la prueba de que se trató.

**`Tarea\Plazo` tiene desde aquí un segundo cliente**, y por eso la regla se extrajo a `Plazo::para()`:
una no conformidad también es «algo abierto con una fecha para cuándo», y una segunda copia de
«vencida en rojo, sin plazo en gris» es cómo se acaba con dos pantallas que discrepan. Se queda en
`Domain\Tarea` porque es donde nació; si llega un tercer contexto, se mueve al lado de `Indicador`.

**El rojo de este registro es de «Fuera de plazo» y de «Sin verificar»**, y ninguno de los estados.
La gravedad la lleva el tipo del hallazgo —`TipoHallazgo::NcMayor` sí es rojo— y pintar de rojo el
estado dejaría el registro entero en rojo por estar haciendo su trabajo. «Sin verificar» lo gasta
porque es la cláusula 10.2 e) sin hacer, y es el paso que el auditor comprueba **precisamente porque es
el que todo el mundo se salta**.

### En el panel (§ 4.14)

**Dos bloques nuevos, y los dos contestan a la misma pregunta por separado.** La tarjeta de no
conformidades dice qué se rompió y si se arregló; el reparto por origen del plan de acción dice de
dónde sale el trabajo que hay abierto. Van detrás del cumplimiento y del plan por el mismo orden de
siempre: qué falta → quién lo está haciendo → qué se rompió por el camino.

**«Sin verificar» sube al panel, y es la única cifra del módulo que está por la norma y no por la
pantalla.** Una no conformidad cerrada y sin verificar se lee como resuelta y no lo está, y la cláusula
10.2 e) es el paso que el auditor comprueba **precisamente porque es el que todo el mundo se salta**.
Va en rojo, en la misma línea que lo vencido.

**Sin porcentaje de cerradas**, como el plan de acción no lleva porcentaje de tareas hechas: esa cifra
sube al cerrar y baja al registrar una nueva, así que castigaría por auditar bien.

**El reparto por estado de este módulo sí incluye los estados cerrados**, a diferencia del de tareas,
que sólo cuenta lo abierto. La pregunta es otra: en tareas es «en qué punto está lo que queda» y aquí
es «cuántas de las que hemos encontrado hemos llegado a verificar». Sin `verificada` en la barra, la
única cifra que pide la norma no se vería.

**`OrigenTarea::tono()` devuelve tres tonos para siete orígenes, y no hay familia `origen:*`.** Era lo
previsto y no sale: siete colores distinguibles no existen en la paleta —los únicos siete medidos son
los `--tipo-*`, y un origen no es un tipo de activo— y el reparto se pinta con `GraficaBarras`, donde
**cada barra lleva su etiqueta escrita**. Con el nombre al lado el color no tiene que identificar, así
que dice lo que de verdad se mira: **ámbar lo reactivo** —hallazgo, no conformidad, incidente—, **azul
lo planificado** —brecha, riesgo, revisión por la dirección— y **gris la iniciativa propia**. Un plan
que es casi todo ámbar es una organización apagando fuegos; un arcoíris de siete colores no contesta
eso. Y ninguno gasta rojo: una acción correctiva no es un incumplimiento, es lo que hay que hacer con
uno.

**La tarjeta no se manda a quien no tiene `no_conformidades.ver`.** Conectar dos módulos abre una
puerta lateral al registro del otro sin que nadie la decida — misma regla que el bloque de riesgos de
la ficha de un activo. Hoy los tres roles del § 4.19 lo tienen, así que la guarda no la ejerce nadie;
el test la comprueba quitándole el permiso **al rol** y no al usuario, porque `revokePermissionTo`
sobre la persona no quita lo que hereda y el test pasaría por el motivo equivocado.

**Lo que este módulo declara que no hace todavía**, y está escrito además en la limitación de la DdA:
el informe de auditoría interna como documento generado, el programa anual de auditoría, comprobar que
el alcance auditado cubra lo exigible, y **su propia `Fuente` en el calendario de obligaciones** —la
`fecha_prevista` de una no conformidad vence el mismo día que sus acciones correctivas, y el calendario
pintaría tres chips para un solo compromiso—.

> El ordinal se ha quitado. Decía «la cuarta `Fuente`» y con el § 4.16 pasó a haber siete, así que la
> frase habría envejecido igual que la del plan de adecuación. El argumento —tres chips para un
> compromiso— no depende del número y se queda; el número, no.

**El § 4.17 ya no se queda a un tercio.** Decía que del flujo de categoría básica —autoevaluación →
Declaración de Conformidad → distintivo— sólo existía el primer paso. Los otros dos llegaron con su
propio módulo (`.ai/rules/conformidad.md`), y la costura con éste es una sola: **una no conformidad
mayor abierta sobre la autoevaluación impide iniciar la declaración**, y «abierta» se lee con
`EstadoNoConformidad::esCerrada()`. Las menores no bloquean. Se reescribe y no se borra, como las
limitaciones de los documentos: la frase anterior había pasado a ser falsa.
