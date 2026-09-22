---
paths:
  - app/Domain/RevisionDireccion/**
  - resources/js/pages/revision-direccion/**
  - resources/js/components/revision/**
---

# La revisión por la dirección

§ 4.15 y la cláusula 9.3. **Es el módulo que llevaba bloqueado desde el
principio**, y no por su complejidad: la 9.3 cierra la lista de entradas
obligatorias —son siete, no «las que se tengan»— y dos de ellas no salían de
ninguna parte. El § 6.2 y el § 10.1 existen para desbloquear esto.

Vive en `app/Domain/RevisionDireccion/`, con dos tablas: `revisiones_direccion` y
`revision_tarea`.

> **Ojo con el nombre, y está comprobado:** `/revisiones` ya estaba ocupada por las
> revisiones del **inventario de activos** (`RevisionInventario`), que son el
> «inventario mantenido» de A.5.9 y `op.exp.1`. Esta ruta es `/revision-direccion`.
> Mismo caso que `Contexto` frente a `ContextoOrganizacion` y que `Domain\Traza`
> frente a `Domain\Auditoria`: se anota, no se renombra lo que ya está. En la
> paleta de comandos, **el alias «revisión» a secas no se le da a ninguno de los
> dos**, porque sería empatar dos módulos con la palabra que más se teclea.

### La instantánea es lo que da forma al módulo

Las siete entradas se **congelan al aprobar** y nunca se consultan en vivo. Es el
fallo más caro que este módulo podía tener y el repositorio ya lo ha evitado
cuatro veces —`documento_versiones.instantanea`, `analisis_contexto`, la exigencia
congelada al cerrar una auditoría y `mediciones.objetivo`—; aquí sería el peor de
todos, porque el acta de marzo enseñaría las no conformidades y los riesgos de
octubre **bajo la fecha y la firma de marzo**.

De ahí la única decisión de interfaz que importa: **la ficha enseña las entradas
en vivo mientras la revisión está abierta y congeladas cuando el acta está
firmada**. Antes de firmar, lo que se mira es cómo está la cosa hoy —que es para
lo que se convoca la reunión—; después, lo que se revisó aquel día.

**Y no hay vigente**, a diferencia del análisis del contexto: allí el contexto es
un estado de cosas que se sustituye, y aquí cada revisión es un **acto** con su
fecha. La del año pasado no deja de haber ocurrido porque se celebre la de este
año, así que no hay índice único parcial ni estado `obsoleta`.

### Las siete entradas, y de dónde sale cada una

| 9.3.2 | De dónde |
|---|---|
| a) Acciones de revisiones previas | `revision_tarea` de la revisión anterior |
| b) Cambios en cuestiones internas y externas | § 4.1, desde el análisis **aprobado** |
| c) Necesidades de las partes interesadas | § 4.1 |
| d) Desempeño: NC, medición, auditorías y **objetivos** | § 4.13, § 4.14, § 4.12 y la **6.2** |
| e) Retroalimentación de las partes interesadas | § 4.1, **con limitación declarada** |
| f) Riesgos y estado del tratamiento | § 4.3 |
| g) **Oportunidades de mejora** | **10.1** |

**`EntradasRevision` no lee los resúmenes del panel**, y podría: `RegistroNoConformidades::paraElPanel()`
cuenta casi lo mismo. Sería acoplar un acta que se entrega a un auditor a la forma
que hoy tiene una tarjeta. Lo que sí comparte son los **scopes**, que es donde vive
la regla: `NoConformidad::pendientesDeVerificar()` cuenta aquí lo mismo que en el
panel y que en la tabla, por construcción.

**Las auditorías se acotan al periodo revisado y el resto no.** Una no conformidad
abierta lo está hoy, independientemente de cuándo se detectara, y acotarla
escondería justo las que llevan años abiertas.

**Un cero es una entrada recogida, no una entrada que falte.** Una organización
puede celebrar su primera revisión sin auditorías, sin no conformidades y sin
objetivos, y el acta lo dirá. Exigir que haya contenido convertiría la primera
revisión en imposible, que es cuando más falta hace.

**La entrada e) comparte apartado con la c) y el acta lo dice.** Statera registra
**qué exige** cada parte interesada, no **qué ha dicho** últimamente: no hay
quejas, ni encuestas, ni comunicaciones recibidas. Repartirlas en dos apartados con
el mismo contenido daría la impresión de que las dos están cubiertas.

### Dos fechas y no una periodicidad

`fecha` es cuándo se celebra y `periodo_desde`/`periodo_hasta` de qué habla el
acta. **No se deduce lo uno de lo otro**: una revisión del ejercicio 2025 se
celebra en febrero de 2026, y es lo normal, no la excepción.

Y **dos columnas en vez de una `Periodicidad`**, a diferencia de un indicador: una
revisión por la dirección no parte el calendario en cubos iguales. La primera cubre
desde que se implantó el SGSI y una extraordinaria puede cubrir seis semanas. Al
convocar se **propone** el día siguiente al fin de la última aprobada, que es lo
que impide que dos actas seguidas dejen un hueco sin revisar.

### El quinto trigger de inmutabilidad

Hermano de los de `documento_versiones`, `riesgo_valoraciones`, `auditorias` y
`analisis_contexto`. **Una sola puerta: `aprobada → en_curso`**, la misma que tiene
una auditoría, y nunca a `planificada` —decir que la reunión no se celebró es
reescribir el pasado—.

**La firma y la instantánea NO se neutralizan al reabrir**, y por eso el `CHECK` de
la firma va en una sola dirección: la fila reabierta conserva quién la aprobó y qué
se congeló hasta que la siguiente aprobación lo sobreescribe. Al revés habría que
limpiarlas en la misma escritura que el trigger está vigilando, y el trigger la
rechazaría.

**Y el `CHECK` de aprobada exige dos cosas: firma e instantánea.** La segunda es la
que lo separa del de un objetivo: un acta aprobada sin las entradas congeladas es
un acta que no puede demostrar de qué habló.

### Aprobar tiene ruta, permiso y acción propios

`AprobarRevision` no pasa por `CambiarEstadoRevision`, y el `FormRequest` de la
transición **rechaza `aprobada` explícitamente**. Aprobar no es un cambio de
estado: es el acto que recoge las siete entradas y las sella. Con una ruta genérica,
cualquiera podría firmar un acta sin instantánea y el `CHECK` lo rechazaría con un
error que no menciona la palabra «entradas».

**Las entradas se recogen ANTES de tocar la fila**, que es el error exacto que se
cometió en `CerrarAuditoria`: congelar después de marcar el estado hace que el
trigger bloquee el propio congelado con un mensaje que habla de otra cosa.

**`revision_direccion.aprobar` es el octavo verbo de supervisión**, y el más
literal de todos: la cláusula se llama «revisión por la **dirección**». Preparar la
reunión, recoger las entradas y redactar las conclusiones es trabajo de quien lleva
el SGSI; firmar que la dirección lo ha revisado, no.

**Sin tabla de transiciones**, a diferencia de tareas, no conformidades, objetivos
y mejoras. No es un descuido: lo que el auditor pregunta de una revisión no es desde
cuándo está en curso, es **qué se revisó y qué se decidió**, y eso lo contesta la
instantánea con su firma. Un histórico aquí guardaría el ir y venir de una reunión
que se aplaza, que no es una pregunta que nadie haga.

### Las salidas son tareas, y se leen en los dos sentidos

La 9.3.3 pide registrar las decisiones, y una decisión que no acaba en algo que
alguien hace para una fecha es un acta que no sirve. `revision_tarea` es N:M como
sus hermanas y **se lee hacia delante y hacia atrás**: de ésta son sus decisiones y,
desde la siguiente revisión, son «el estado de las acciones de revisiones previas».
Eso es lo que hace que la serie de actas signifique algo.

**Se pueden registrar decisiones sobre un acta ya firmada**, y conviene decirlo
porque es justo donde uno espera un error: escribir en la pivote no pasa por el
trigger —que blinda el acta, no lo que cuelga de ella— y es lo correcto, porque una
decisión se ejecuta en las semanas siguientes. Mismo caso que la acción correctiva
de una auditoría cerrada, y hay test.

**`OrigenTarea::RevisionDireccion` pasa a ofrecerse, y no hizo falta migración**:
el valor estaba en el `CHECK` desde la primera, porque el enum se declaró entero y
lo que faltaba era su módulo. Es la diferencia con `objetivo` y `mejora`.

**`RevisionDireccion::anterior()` busca por fecha de celebración y no por
`created_at`**: una revisión del ejercicio pasado puede registrarse después que la
de este año —pasa al meter el histórico— y ordenar por cuándo se tecleó daría
«acciones previas» de una reunión que todavía no había ocurrido. Hay test, y otro
que fija que la anterior nunca es la de otra organización.

### El acta: el quinto documento calculado

`TipoDocumento::ActaRevision`, de **ámbito organizativo** —lo que la dirección
revisa es el SGSI entero— y por tanto `exigeSistema()` a `false`. **`documentos_sistema_check`
no se tocó**, y eso es la noticia: la migración del § 4.1 lo rehízo por tercera vez
para cambiar su motor a `exigeSistema()` precisamente para que un tipo nuevo de
ámbito organizativo no obligara a rehacerlo otra vez. Aquella decisión se paga aquí.

`ActaRevisionDireccion` implementa `GeneradorDocumento` **directamente**, como
`AnalisisDelContexto` y `DocumentoRedactado`. Los apartados del cuerpo van **en el
orden en que la norma enumera las entradas**, de la a) a la g): un auditor las
recorre con el acta delante, y reordenarlas le obliga a buscar cada una.

**Las decisiones son la excepción y se leen en vivo**, a diferencia de las
entradas: son las salidas y pueden crecer después de firmar. Lo que se congeló es
lo que la dirección **tuvo delante**, no lo que mandó hacer.

### El tercer fallo silencioso de la familia, cerrado

El acta es el primer documento que imprime badges de objetivos de seguridad, y
`EstadoObjetivo::Propuesto` gasta el tono `en_revision` — que **no estaba en
`EsquemaCuerpo::TONOS_BADGE` ni en `documento.css`**. `RenderizadorCuerpo` cae a
`neutro` cuando no reconoce el tono, así que el badge salía **gris y sin punto en el
PDF que se le entrega al auditor**, sin que nada avisara. Es el mismo fallo que
`IconoTipo` tenía con los iconos y `tonos.ts` con los colores, por tercera vez.

Tres cosas para cerrarlo, y las tres hacen falta:

1. **`.badge--en_revision` en `documento.css`**, con los hex de DESIGN.md §3
   (`#7B45C4` sobre `#F7F2FF`) y no estimados.
2. **`Nodo::badge()` lanza `LogicException`** con un tono que no esté en el mapa.
   Es seguro porque ahí **sólo llega código**: los tonos los escriben los
   materializadores desde enums, nunca un cuerpo editado —ése pasa por
   `SanearCuerpo`, que anula el tono desconocido, y ahí el `?? neutro` del
   renderizador es lo correcto—. Mismo razonamiento que los dos `match` sobre
   cadenas de `MaterializarCuerpo`.
3. **`TonosDelDocumentoTest`**, que compara el mapa con las clases del CSS en las
   dos direcciones. **No recorre los enums del dominio a propósito**: el
   vocabulario tiene familias que el papel no imprime nunca —los nueve `--tipo-*`,
   los cuatro `--dafo-*`, las prioridades y los ordinales— y exigirle que las
   conozca sería pedirle que supiera pintar badges que ningún generador le pasa. Lo
   que se fija es la puerta, no el inventario.

### Lo que este módulo declara que no hace todavía

Las cuatro van impresas en el acta, no sólo aquí:

- **La retroalimentación de las partes interesadas (9.3.2 e) se aporta fuera.**
  Statera registra qué exige cada parte, no qué ha dicho.
- **Los asistentes son texto libre**, y no se comprueba que quien figura tenga
  potestad para revisar el sistema de gestión ni que la dirección estuviera
  representada. Tampoco hay firma electrónica cualificada. `users` son cuentas de
  Statera y a una revisión por la dirección asiste gente que no tiene cuenta —§ 4.8
  no existe—.
- **No se comprueba que la revisión se celebre con la periodicidad comprometida**:
  se registran las que se convocan y no se avisa de la que falta. Ese aviso vive en
  el § 4.16, y `Aviso\Fuente` sigue con tres casos. **No entra una `Fuente` nueva
  aquí**: lo que vence es la revisión del acta aprobada, que `Fuente::Documento` ya
  recoge — mismo argumento que dejó fuera al análisis del contexto.
- **Desvincular una decisión de un acta firmada sí cambia** lo que la revisión
  siguiente verá como «acciones previas». El acta congeló las entradas, no las
  salidas.
