---
paths:
  - app/Domain/Auditoria/**
  - resources/js/pages/auditorias/**
---

# Las auditorías

§ 4.12, la cláusula 9.2 de ISO, y la primera pieza de la **fase 3**. Tres tablas —`auditorias`,
`auditoria_puntos`, `hallazgos`— y cuatro desvíos de la § 2.2, cada uno con su motivo en la cabecera
de la migración.

**Cerrarla es lo que la vuelve un hecho**, y lo garantiza el **tercer trigger de inmutabilidad** del
producto, hermano de los de `documento_versiones` y `riesgo_valoraciones`. A partir del cierre, ni la
checklist ni los hallazgos admiten cambios: si se pudieran reescribir desde PHP, bastaría con pasar un
`no_conforme` a `conforme` y borrar el hallazgo para que la auditoría del año pasado dijera otra cosa.
Con su puerta, como los otros dos: de `cerrada` se vuelve a `en_curso` —reabrir— y **nunca a
`planificada`**, que sería decir que nunca se hizo.

**Y al cerrar se congela lo derivado.** Cada punto guarda la exigencia y el estado que la implantación
tenía ese día. Sin eso, revalorar el sistema en octubre cambiaría bajo los pies el denominador de la
auditoría de marzo y la fila **mentiría** — el mismo motivo por el que `riesgo_valoraciones` congela su
escala y sus salvaguardas. De ahí que `CerrarAuditoria` congele **antes** de marcar el estado: al revés,
el trigger bloquea el propio congelado con un error que habla de la checklist y no del orden.

**`sistema_id` es obligatorio y `marco_id` no existe.** § 2.2 dibuja lo contrario, pero ella misma
define `sistemas` como «la unidad de alcance y de certificación»: el SGSI **es** un sistema. Sin él no
hay checklist, que es la mitad del módulo — el mismo argumento que ya se escribió para el plan de
adecuación. Y con el sistema puesto, `marco_id` sería el mismo dato en dos sitios que pueden
desincronizarse.

**Un punto no se puede marcar «no aplica».** La checklist se precarga desde lo aplicable, así que todo
punto lo es **por construcción**: un auditor marcando «no aplica» estaría contradiciendo una derivación
legal desde un desplegable, que es lo que prohíbe el invariante 4. Lo que sí necesita decir es
`fuera_de_muestra`, que es una decisión suya sobre el alcance y no sobre la aplicabilidad. Y
`pendiente` no es `conforme`, que es el argumento de `EstadoControl::PorConfirmar`: sin la checklist,
la ausencia de hallazgo se lee como conformidad y una auditoría por muestreo miente.

**Los hallazgos cuelgan del punto, no de la pareja (auditoría, requisito)** —con la pareja, nada
impediría un punto «conforme» con una no conformidad encima del mismo requisito—, y su
`auditoria_punto_id` es **nullable** contra la letra de § 2.2: una auditoría ISO produce hallazgos que
no cuelgan de ninguna medida —«el programa de auditoría interna no está definido»— y con la columna
obligatoria acabarían colgados de un requisito arbitrario.

### La checklist: el primer `Recurso` acotado a un padre

Es **pantalla propia** (`/auditorias/{auditoria}/checklist`) y no un bloque de la ficha: son 52 medidas
en categoría básica y unas **122** en un sistema de ISO —los 93 controles del Anexo A más las cláusulas
4 a 10, el mismo 122-contra-93 que ya mordió a la SoA—. A ese tamaño hacen falta filtros, orden y
marcado en bloque. Precedente de forma: `/tareas/tablero` y `/activos/etiquetas`.

**La auditoría entra por el constructor.** `Recurso::consulta()` no recibe argumentos y sólo lo llama
`ConsultaRecurso`; cambiar esa firma contaminaría las once implementaciones para que la use una.

**Y el aislamiento tiene aquí un eje que no existía.** Las tres capas tapan el cruce entre
organizaciones; entre dos auditorías de la **misma** organización no hay nada. Así que el `where` de la
consulta es la frontera y no un filtro, la acción masiva acota por `auditoria_id` además de por los ids,
y todo lo que cuelga de `{auditoria}` va con `scopeBindings()`.

**`Inertia::once()` colisionaba, y hay test.** La definición viaja con la clave `recurso:{clave}` y el
cliente la reclama por esa clave **copiando el valor viejo**: dos checklists con la misma clave harían
que la segunda se pintara con la definición de la primera, incluida la URL de su acción masiva. Por eso
`RespondeConRecurso::tabla()` acepta un sufijo de caché y `clave()` **se queda estable**: `clave()` es
además el nombre con el que la vista de columnas se guarda en el navegador y con el que se nombra el
CSV, así que hacerla dinámica guardaría una vista por auditoría —y quien ordena sus columnas las
perdería en la siguiente— y metería dos puntos en el nombre del fichero. Las columnas son idénticas
auditoría a auditoría: compartir la vista es lo que se quiere.

**La checklist entera cabe en una página** (`porPagina` 100, con escalón de 200 que no existe en el
resto del producto). `DataTable` limpia la selección cada vez que cambia `meta`, así que paginar la
borra: sin eso, «marcar veinte conformes de golpe» obliga a empezar de nuevo en cada página.

**La acción masiva marca `conforme` y sólo `conforme`**, por `update` masivo y no por bucle tolerante.
Lo primero, porque «no conforme» y «observación» piden un hallazgo detrás y marcar cuarenta de golpe
fabricaría cuarenta huecos —el argumento que dejó `descartada` fuera de la masiva de tareas—. Lo
segundo, porque el bucle de implantaciones existe para rechazar filas según su máquina de estados, y un
punto no tiene: el recuento de rechazadas sería siempre cero, un mensaje que miente sobre su propio
esfuerzo.

**La guarda del cierre está en el dominio aunque el trigger también lo impida.** Un `update` sobre una
auditoría cerrada levanta el `RAISE EXCEPTION` y sube como `QueryException` sin capturar: el usuario ve
el 500 genérico y el mensaje de la base —sin tildes, porque es SQL— no lo lee nadie.

**Los puntos no llevan `RegistraTraza`, y es deliberado.** Un `update` masivo por Query Builder no
dispara eventos, así que la traza aparecería en el camino de uno en uno y no en el masivo: media traza
es peor que ninguna, que es el razonamiento que ya está escrito para `marcarRevisados`. Y cerrar una
auditoría ISO escribiría 122 eventos que no dicen nada que el cierre no diga. **La limitación que eso
deja**: «¿quién marcó conforme esta línea?» se contesta con `auditorias.auditor` y nada más fino. Para
una auditoría basta —el acta la firma el auditor, no cada casilla—, pero es una limitación y no una
ausencia.

**`RegistrarAuditoria` existe por una línea**, el `refresh()`: `estado` lo pone la base y la instancia
recién creada llega sin él, así que lo primero que lo lea revienta con un «call to a member function on
null» que no menciona la palabra «estado». Es lo mismo que ya le pasó a `CrearTarea` y a
`GenerarDocumento::encolar()`. Aquí mordió en el seeder.

**`Auditoria::puntos()` no lleva joins ni orden**, y tuvo los dos durante un rato. El *route model
binding* acotado resuelve `{punto}` a través de la relación con un `where` **sin cualificar**: con
`implantaciones` y `requisitos` unidas, la consulta muere con «column reference "id" is ambiguous», un
error que no menciona ni la ruta ni la relación. Una relación dice de quién cuelga qué; cómo se ordena
es de quien consulta.

**El rol `Auditor` lee y no escribe**, y conviene decirlo porque el nombre del rol y el del módulo
coinciden y parece un olvido: este rol es el auditor **externo** que § 4.19 describe como de sólo
lectura, y quien registra la auditoría interna es el responsable de seguridad. Dejarle escribir sería
que quien audita redactara el acta de su propia auditoría.

### El informe (§ 4.18)

**Tres columnas de la 9.2.2 que la tabla no tenía**: `criterios`, `metodo` y `equipo`. El trigger de
inmutabilidad no se tocó y no hacía falta: compara la fila entera, así que quedan blindadas desde el
cierre. Lo comprueba `InformeTest`, no la cabecera de la migración.

**La ficha ofrece «Preparar el informe» sólo con la auditoría cerrada** y con `documentos.generar`
además de `auditorias.gestionar`, el mismo par que preparar la Declaración de Conformidad. Una
auditoría con informe **no se elimina**: el controlador lo impide antes que la clave foránea. El
porqué del vínculo está en `documentos.md`.
