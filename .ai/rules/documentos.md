---
paths:
  - app/Domain/Documento/**
  - resources/js/pages/documentos/**
  - resources/js/pages/plantillas/**
  - resources/js/components/documento/**
---

# Los documentos

Viven en `app/Domain/Documento/`. Una clase por tipo de documento —`DeclaracionAplicabilidadIso`,
`DeclaracionAplicabilidadEns`— sobre una tubería común que renderiza, llama a Gotenberg, calcula el
hash, almacena y registra la versión. El sexto documento cuesta una clase y una plantilla.

```sh
php artisan documentos:generar SOA-SGSI-01 --html   # vuelca el HTML, sin Gotenberg
php artisan documentos:generar SOA-SGSI-01 --sync   # genera el PDF en este proceso
php artisan documentos:generar SOA-SGSI-01          # lo encola en «documentos»
```

**`--html` es la opción que más se usa**: el noventa por ciento del trabajo de plantilla se hace
mirando el HTML en un navegador, no abriendo PDFs.

`documentos` es la **serie** —«la SoA del SGSI»— y `documento_versiones` es **cada entrega**. La
especificación (§2.2) describe una sola tabla con `version`, `estado` y `fichero` dentro; una fila
no sostiene un histórico, y la propia especificación pide versionado.

**`numero IS NULL` es el borrador** —hay uno como mucho por documento, lo garantiza un índice único
parcial— y se regenera cuantas veces haga falta. **Con número, la fila es inmutable**, y eso lo
impone un trigger de PostgreSQL, no la buena voluntad: si el documento entregado se pudiera cambiar
desde PHP, no habría forma de demostrar qué se firmó. Emitir mueve además el PDF de `borradores/` a
`emitidas/`, que es el prefijo que en producción lleva Object Lock.

La `instantanea` en JSONB **no es redundante con el PDF**: un PDF no se puede consultar, y sin ella
no se contesta «¿qué cambió entre la v3 y la v4?». Por eso el contrato del generador es que la
plantilla recibe arrays y value objects y **nunca modelos de Eloquent**: lo que se pinta y lo que se
congela son literalmente lo mismo.

**Los dos documentos no son el mismo con otras columnas.** En ISO la aplicabilidad es una *decisión*
que hay que justificar —de ahí las dos columnas de justificación, la de inclusión y la de
exclusión—; en el ENS es un *cálculo* del motor que hay que poder rastrear, y de ahí la exigencia, el
refuerzo, el origen y la dimensión moduladora, más la derivación de la categoría impresa en portada.

**Los dos declaran por escrito lo que no pueden afirmar.** Que no hay análisis de riesgos, que no hay
aprobación formal, que los roles ENS están pendientes de designación, que el texto normativo de ISO
no se reproduce por derechos de autor, y las dos brechas conocidas del Anexo II. Un auditor respeta
una limitación declarada y suspende una inventada.

**La SoA justifica la inclusión sin inventarse un riesgo.** Cada control aplicable dice «Anexo A» y,
cuando los hay, «tratamiento del riesgo R-014» —el vínculo de salvaguarda del §4.3, que es la
justificación que ISO 6.1.3 d) espera de verdad— y «exigido por el ENS (op.acc.2)» —que es un
requisito **legal** y una justificación de inclusión igualmente legítima para ISO, y de paso el
argumento del producto impreso en el entregable—. Lo que no se hace es escribir una referencia a un
riesgo que no está vinculado.

**Y por eso las limitaciones de los dos documentos se reescribieron cuando llegó el §4.3.** Decían
que el módulo de riesgos no estaba implantado, y eso pasó a ser **falso en el PDF que se le entrega
al auditor**, que es peor que una limitación ausente. No se borraron: se precisó qué es lo que la
herramienta sigue sin hacer —no exige que todo control aplicable tenga un riesgo detrás, ni comprueba
que el análisis cubra el alcance entero—, mismo tratamiento que ya se le había dado a la limitación
del flujo de aprobación. En la DdA se separaron las dos mitades: el análisis de riesgos existe y no
figura ahí **por diseño** —una Declaración de Aplicabilidad declara medidas, no riesgos—.

**Y se volvió a reescribir al llegar el plan de adecuación**, por tercera vez y por lo mismo: la DdA
decía que el plan «sigue pendiente: su módulo no está implantado» y eso pasó a ser falso en el PDF
entregado. Ahora dice que el plan existe, en documento aparte, y **por qué no figura ahí** — que es
una decisión y no una carencia. `ContenidoDdaTest` clava que la frase vieja no vuelva.

**Y por cuarta vez con las auditorías (§ 4.12 y § 4.13).** Decía que «el módulo de auditorías no está
implantado», y con el ciclo entero dentro eso era falso en el PDF entregado. Ahora dice que las
auditorías se registran, que su resultado no figura ahí **por diseño** —una Declaración de
Aplicabilidad declara la situación de cada medida, no el resultado de quien la revisó— y **qué sigue
sin hacer la herramienta**: el informe de auditoría como documento, el programa anual, y comprobar que
el alcance auditado cubra lo exigible. Ese último punto es el que vale: sin esa comprobación, «esta
medida no tiene hallazgos» se lee como «esta medida se auditó y estaba conforme», que es el mismo
argumento por el que un punto de la checklist distingue `pendiente` de `conforme`. El test comprueba
las dos mitades —que la frase vieja no vuelve **y que la nueva sigue declarando lo que falta**—, porque
si sólo mirara la primera daría por bueno borrar la limitación entera.

**Y por quinta con el § 4.18**, que trajo el informe de auditoría como documento: la lista de «lo que
sigue sin hacer» lo nombraba, y pasó a ser falso. Ahora la DdA dice que las internas y las
autoevaluaciones tienen su informe **en documento aparte**, y lo que falta se queda en el programa
anual y la cobertura del alcance. `ContenidoDdaTest` comprueba otra vez las dos mitades.

---

## Los textos de un documento

Un documento tiene dos mitades y sólo una se edita. **Las tablas, las cifras y la derivación de la
categoría se calculan desde `implantaciones` y no se tocan a mano** —el invariante sigue intacto—;
**el envoltorio narrativo lo redacta la organización**. Si hay que corregir una justificación, se
corrige en su requisito, que es donde vive.

Es el § 4.5 de la especificación, «plantillas base personalizables por organización», que hasta ahora
no estaba implementado: todo el aparato narrativo era literal en Blade o en PHP y **el usuario no podía
escribir ni un carácter que saliera en el PDF**.

**Once huecos, catálogo cerrado** (`SeccionNarrativa`). De ese enum salen a la vez el formulario, las
reglas del `FormRequest`, el `CHECK` de las dos tablas y las claves que acepta el resolutor: **no hay
forma de nombrar un hueco que no exista**, ni por la interfaz ni llamando a la ruta a mano. Ésa es la
primera de las tres capas que impiden tocar las limitaciones del sistema; las otras dos son el `CHECK`
y que el Blade de esos bloques no llama al parcial de narrativa.

**La cadena de lectura tiene tres eslabones y gana el primero que EXISTA:**

```
documento_secciones  →  documento_plantilla_secciones  →  TextosDeFabrica
```

**Incluida la cadena vacía.** Una fila vacía dice «aquí no va nada, lo he decidido yo» y una fila
ausente dice «vale lo que venga de más atrás». Sin esa distinción, borrar un texto lo resucitaría en
la siguiente generación. Y es lo que hace que **no haya hecho falta ninguna migración de datos**: los
documentos que ya existían no tienen filas, resuelven hasta fábrica y su PDF sale idéntico.

**Un documento materializado no se entera si la plantilla cambia después**, y es deliberado: lo que
dice un documento es un hecho del documento, no el resultado de un join que cambie bajo los pies. Es
el mismo razonamiento que hay detrás de `instantanea`. La pantalla de plantillas lo avisa, porque sin
ese aviso cualquiera daría por hecho que acaba de cambiar su SoA.

```sh
php artisan documentos:generar SOA-SGSI-01 --html    # sigue siendo el bucle rápido
```

---

## La aprobación de un documento

Es el § 4.5, y el hueco estaba reservado por escrito en tres sitios: `estado_generacion` se llama así
para dejar libre el nombre `estado`, `Permiso` anunciaba que `documentos.aprobar` «llegará con el
flujo de aprobación», y `DESIGN.md` tenía el token `--estado-en-revision` declarado **sin flujo
detrás**. Éste es ese flujo.

**Aprobar es lo que emite, y no es una preferencia.** La portada se congela en `instantanea` al
generar y el trigger vuelve la fila inmutable en cuanto tiene número: una firma posterior **no podría
salir impresa en el PDF que se entrega**, que es justamente donde el auditor la busca. Así que firmar
hace dos cosas —escribe la aprobación y **manda regenerar**— y `EmitirVersion` pasa a ser el último
paso de ese trabajo, sin ruta ni botón propios. Entre las dos cosas hay un hueco en el que la fila
está **firmada y sin numerar**; el `CHECK` lo admite a propósito —sólo exige firma para el estado
`aprobado`, no al revés— y si la generación falla, la versión se queda en revisión con su error.

De ahí salen dos detalles que no se ven leyendo el job:

1. **`etiquetaPrevista()` existe por el pie de página.** Esa generación corre con `numero` todavía
   nulo, así que `etiqueta()` diría «Borrador» en las noventa páginas del documento entregado y el
   fichero se llamaría `soa-sgsi-01-borrador.pdf`.
2. **«Borrador» dejó de ser «sin número» y pasó a ser «sin firma».** La limitación que se antepone en
   el PDF y el `esBorrador` de la portada miran `tieneFirma()`, no `numero`. Mirando el número, el
   documento entregado se declararía borrador a sí mismo.

**Cinco estados y no los cuatro de la § 2.2.** Falta uno para «la dirección lo ha mirado y ha dicho
que no», y sin él una versión tumbada se queda en «pendiente de firma» para siempre. `rechazado`
**exige motivo**, igual que `descartada` en tareas, y no gasta número: un hueco en la numeración es
una pregunta del auditor. Tampoco gasta rojo — que la dirección tumbe una versión es una decisión
legítima, mismo criterio que `DecisionRiesgo`.

**`obsoleto` es el hermano de `EstadoImplantacion::NoAplica`: lo pone el sistema**, al aprobarse la
siguiente, y nunca una persona. Es además **la única puerta del trigger**, tallada igual que la de
`riesgo_valoraciones` con `vigente`: sin ella un documento aprobado no podría revisarse nunca. Se
compara el registro entero con `estado` y `obsoleta_en` neutralizados, y **`updated_at` se neutraliza
sólo cuando el estado cambia** —neutralizarlo siempre dejaría pasar un «toque» suelto sobre una fila
entregada—. Quién es la vigente lo marca un índice único parcial, como el borrador.

**Las versiones que ya estaban emitidas se archivaron como obsoletas, sin firmante.** Bajo el modelo
nuevo una fila con número está aprobada, y aquéllas no lo están: rellenarles `aprobada_por_id` con
quien pulsó «Generar» sería **fabricar una firma**, que es lo que estas tablas existen para hacer
imposible. Por eso el `CHECK` de la firma no alcanza a `obsoleto`.

**Los destinatarios del acuse son todos los usuarios de la organización**, sin tabla de destinatarios:
los pendientes salen de restar. Es una simplificación **declarada en las limitaciones del PDF**, no un
descuido —quien tiene que conocer la política son las personas, y § 4.8 no existe— y `User` no lleva
el scope de organización, así que `CoberturaAcuse` lo acota a mano. El acuse cuelga de la **versión**:
quien leyó la v3 no ha leído la v4, y heredarlo convertiría el registro en un trámite que se pasa solo.

**La ruta del acuse va sin permiso propio y sin segundo factor**, y es la única escritura del producto
que va así: se escribe sobre uno mismo, como en `/perfil`. Un acuse que cuesta dos pasos se deja de
firmar. En cambio **mandar a revisión va con `documentos.redactar`** y no con `generar`: es el final
de escribir, no el principio de entregar, y quien lo redacta tiene que poder soltarlo sin depender de
nadie.

**Y la limitación impresa se reescribió, no se borró.** Decir que la herramienta «no implementa un
flujo de aprobación» pasó a ser **falso en el PDF que se le entrega al auditor**, que es peor que una
limitación ausente — el mismo tratamiento que ya se les dio a las dos de riesgos con el § 4.3. Lo que
sigue sin hacer: comprobar que quien firma tenga potestad para hacerlo, y guardar firma electrónica
cualificada. `LimitacionesBlindadasTest` lo clava.

---

## Los documentos redactados

La segunda familia, y no se parece a la primera. Una Declaración de Aplicabilidad es una consulta
sobre `implantaciones` congelada en un PDF; **una política no sale de ninguna consulta**. Son los tres
niveles de la jerarquía del § 4.5 —`politica`, `norma`, `procedimiento`— y son los que dan sentido al
acuse: nadie acusa recibo de una SoA.

El cuarto nivel de esa jerarquía, el **registro**, no entra: un registro es la salida de un
procedimiento, no un documento que Statera redacte y versione.

**Una sola clase para los tres** (`DocumentoRedactado`), que implementa `GeneradorDocumento`
directamente en vez de heredar de `DeclaracionAplicabilidad`: no tiene filas, ni tabla, ni
correspondencias cruzadas. Lo único que comparte con las declaraciones —portada, historial y
limitaciones— se extrajo al trait `ArmaContenidoComun`, y **las limitaciones son el motivo de fondo**:
dos copias de esa lista es cómo se acaba con una política declarando algo que la SoA ya no declara.

**No cuelgan de un sistema** —el `CHECK` en negativo ya lo admitía— y `marcoEsperado()` devuelve nulo,
que significa «no hay nada que casar» y nunca «no se ha rellenado». De los once huecos narrativos les
quedan cinco: ofrecerle «cómo leer la tabla» a un documento sin tabla es ofrecerle explicar algo que
no existe.

**El texto de fábrica del alcance se queda vacío a propósito.** A quién obliga y sobre qué se aplica
es lo más específico que tiene un documento así, y cualquier frase de relleno acabaría impresa en el
PDF que alguien aprueba. Un hueco vacío no se pinta —ni él ni su título—, así que se nota; una frase
genérica, no. Lo que sí trae es la introducción, porque `org.1` pide literalmente que la política
declare objetivos, compromiso de la dirección y a quién obliga: eso es lo que la § 4.5 llama
«plantilla base».

## El plan de adecuación

El tercer documento **calculado**, y el que cierra la fase 2. Hace la pregunta contraria a una
declaración: la DdA dice qué medidas se exigen y cómo está cada una; el plan lista **sólo las que no
están implantadas**, con quién responde, para cuándo y cuánto cuesta. Es el documento que junta el
§ 4.4 con el § 4.7, y no hizo falta ninguna tabla nueva: `implantaciones.fecha_objetivo` y
`responsable_id` estaban desde la primera migración, y `tareas.coste_estimado` llevaba desde el
principio con un comentario que decía que era «para el plan de adecuación» — y **no lo leía nadie**.

**`DeclaracionAplicabilidad` pasó a llamarse `DocumentoCalculado`.** La clase es la tubería —la
consulta por tipo de requisito, el agrupado por el nodo padre, las correspondencias cruzadas— y no un
género documental; con un plan heredando de ella el nombre mentía. Hace pareja con
`DocumentoRedactado`, que es el otro lado de la frontera que define `TipoDocumento::esRedactado()`.
Mismo caso que `IndicadorInventario` → `Indicador`.

**Y `resumen()` se volvió abstracto en el movimiento.** Las cifras de una declaración —porcentaje
implantado, excluidos— **no significan nada** sobre filas que son todas pendientes por construcción:
darían cero siempre. Heredar una implementación que un hijo no debe llamar es una mina que no caza
ningún `match` exhaustivo, así que las dos declaraciones la reciben por el trait
`Concerns\ResumeLaAplicabilidad` y el plan escribe la suya. Lo mismo con la fila: los quince campos de
una medida del ENS viven en `Concerns\ArmaFilaDelAnexoII`, porque `FilaRequisito` es `readonly` y PHP
no tiene `clone with` — sin el trait, el plan copiaba las quince asignaciones.

**El fallo caro de este módulo es el coste, y se cuenta dos veces si nadie lo impide.**
`implantacion_tarea` es N:M: una actuación hace avanzar varias medidas a la vez, así que sumar la
columna presupuestaría tres veces una tarea que cubre tres medidas — en el documento que se le lleva a
la dirección a pedir dinero. El total lo calcula `Tarea\Coste::total()` **sobre tareas distintas**, y
la columna sigue imputando a cada medida lo suyo: los dos números son correctos y **no cuadran entre
sí**, así que el documento lo dice por escrito. Es el mismo argumento aritmético que dejó las subtareas
fuera de `tareas` y que hizo N:M a riesgo↔activo.

**`Domain\Tarea\Coste` existe por eso**, y de paso recoge el formato del euro, que estaba escrito dos
veces —la columna de la tabla y la ficha— e iba camino de la tercera. Mismo criterio que `Tarea\Plazo`.

**Cuatro scopes nuevos en `Implantacion`**, que hasta ahora sólo tenía `aplicables()` y `delSistema()`:
`pendientes()`, `objetivoVencido()`, `sinFechaObjetivo()` y `sinTrabajo()`. Los invocan por nombre la
cifra del panel, los filtros de `/implantaciones` y la consulta del plan, que es lo que garantiza que
pulsar el número enseñe exactamente ese número. **«Pendientes» era una cifra del panel que no se podía
pulsar**, y llegar a esa lista exigía marcar a mano «aplica» y tres de los cuatro estados. Van con las
columnas cualificadas —`implantaciones.estado`— porque quien los llama suele traer `requisitos` unida.
`fecha_objetivo` existía desde el principio y **no se comparaba con hoy en ningún punto del producto**;
aquí empieza a significar algo.

**Dos fuentes nuevas**: `resumen_plan`, porque las cifras de una declaración tienen otra forma, y
`tabla_sin_trabajo`, que repite las medidas pendientes sin ninguna tarea abierta detrás. La duplicación
es deliberada, igual que la tabla de exclusiones de la SoA: es lo que la dirección va a mirar seguro.
**Un plan completo no es el que no tiene ninguna, es el que las declara.**

**El denominador va impreso y con palabras.** «51 de 52» y, debajo, «al sistema se le exigen 52 medidas
del Anexo II, de las cuales 1 figura implantada; este plan recoge las 51 restantes». Sin eso una tabla
de 51 filas se lee como si al sistema se le exigieran 51. Y las implantadas salen **por resta** y no
por una segunda consulta con la condición contraria: `pendientes()` es exactamente «aplicable y no
implantada», así que escribir `where estado = implantado` sería la misma regla por segunda vez.

**`documentos_sistema_check` se reescribió**, y la migración anterior pedía expresamente que no se
tocara. Su razón seguía siendo buena y por eso hay que decir por qué deja de valer: estaba en negativo
—`tipo NOT IN ('soa_iso','dda_ens')`— para que un tipo **de ámbito organizativo** no obligara a
rehacerlo, y política, norma y procedimiento lo eran. El plan es el primer tipo **calculado** que llega
detrás, y un plan sin sistema no es un documento raro, es un documento imposible. Ahora la lista se
construye desde el enum filtrando `! esRedactado()`.

**El `CHECK` no lo prueba `migrate:fresh`.** Los `CHECK` de tipo se construyen desde `TipoDocumento::cases()`
**en ejecución**, así que sobre una base recién migrada ya incluyen el tipo nuevo aunque falte la
migración: ningún test se pone rojo si se olvida. `PlanExigeSistemaTest` prueba la mitad que sí importa
—que la base rechaza un plan sin sistema— y hay que correr `migrate`, no sólo `fresh`.

**Y tres frases más pasaron a ser falsas con el plan dentro**, todas corregidas: `limitacionesBase()`
decía «sobre N requisitos registrados» contando sólo las filas —en un plan, 51 habiendo 52 exigibles—;
la ficha del documento pintaba «N requisitos · N excluidos · N implantados», que en un plan es «0
excluidos · 0 implantados» para siempre, y ahora el recuento lo escribe el servidor según el tipo; y el
mensaje de `GuardarDocumentoRequest` decía «La %s es de %s», que con un tipo masculino salía «La Plan
ENS» (y el «de el ENS» ya estaba mal antes).

**Lo que el plan declara que no hace**: no contrasta plazos contra capacidad, no ordena las medidas por
dependencia y no exige que toda medida pendiente tenga fecha o responsable.

~~Y el calendario de obligaciones todavía no incluye las fechas objetivo~~: **las incluye desde el
§ 4.16**, con `Fuente::Implantacion`, y la limitación impresa se reescribió. De paso, aquella frase
llevaba **dos** cosas falsas: decía que el aviso diario recogía «sólo tareas y evidencias» cuando
`Fuente::Documento` existía desde el propio § 4.5, y llamaba «cuarta» a una fuente que ya era la quinta.
Es la misma lección que `Aviso\Fuente` tiene escrita en su cabecera —un recuento dentro de un comentario
envejece cada vez que el producto crece—, y por eso la frase nueva declara **lo que falta** y no lo que
hay.

```sh
php artisan documentos:generar PLA-ENS-01 --html   # sigue siendo el bucle rápido
```

## El informe de auditoría interna

El séptimo calculado, y la cláusula 9.2.2. Se prepara desde la ficha de la auditoría cerrada
(`Auditoria\PrepararInformeAuditoria`) y se genera y se firma aquí, como la Declaración de
Conformidad.

**Es el único tipo que nombra su fuente: `documentos.auditoria_id`.** El acta imprime la última
revisión aprobada y la DdC la conformidad viva del sistema; un informe no, porque dos auditorías
cerradas del mismo sistema son dos informes y no dos versiones del mismo. **El FK va en
`documentos` y no en `auditorias`** porque la fila de una auditoría cerrada es inmutable —su
trigger compara la fila entera— y el informe se prepara justo después de cerrarla.

- **Índice único parcial** `documentos_auditoria_unica`: un informe por auditoría.
- **`CHECK` en las dos direcciones** (`documentos_auditoria_check`): ni informe sin auditoría ni
  auditoría colgada de una SoA. Dos violaciones en el mismo test no se pueden comprobar: la primera
  aborta la transacción y la segunda sentencia ya no llega al `CHECK`.
- **`ON DELETE NO ACTION` y no `RESTRICT`**: se comprueba al final de la sentencia, así que borrar un
  sistema, que arrastra a la vez sus auditorías y sus documentos, sigue funcionando. Borrar a mano una
  auditoría con informe lo impide antes el controlador, con mensaje.
- **`TipoDocumento::nacePorSuFuente()`**: el formulario de documentos no ofrece el tipo al crear, no
  deja convertir un informe en otra cosa ni cambiarle el sistema. Sin eso, el error que sube es el del
  `CHECK`.
- **Sólo interna y autoevaluación** (`TipoAuditoria::admiteInforme()`): el de la externa lo firma la
  entidad certificadora.

**Se construye desde lo congelado**: exigencia y estado de cada punto tal como quedaron al cerrar, y
sólo con la auditoría cerrada, comprobado también al generar porque entre preparar y generar se puede
reabrir. **La excepción, declarada en sus limitaciones**: el tratamiento de cada hallazgo es el de la
fecha de extracción, porque la no conformidad avanza después del cierre.

**El recuento de la checklist vive en `Auditoria\ResultadoAuditoria`** y lo usan el informe y la DdC,
y en el cuerpo los pinta el mismo `tablasDeResultado()`: con dos copias, la declaración y el informe
en que se apoya contarían distinto la misma checklist.

## El informe de estado

El octavo calculado, de la organización entera y **sin fuente congelada**: lo que se congela es la
`instantanea` de cada generación, como en la SoA.

**Ninguna cifra se calcula en el generador.** Pide a `ResumenCumplimiento`, `ResumenPlanDeAccion`,
`ResumenInventario` y a los `Registro*` lo mismo que el panel, y `PanelController::sistemas()` se
mudó a `ResumenCumplimiento::porSistema()` para que haya una sola consulta. Lo clava
`ContenidoInformeEstadoTest`, que compara el informe con la respuesta de `/panel`.

**Todos los módulos, sin mirar permisos**, a diferencia del panel: es un documento que prepara quien
tiene `documentos.generar` para la dirección y el auditor, y un informe que se salta los incidentes
según quién pulsó «Generar» no es un informe de estado. Imprime **recuentos**, nunca registros.

**Los dos bloques van en `SIEMPRE_RECALCULADOS`**: un informe de estado no tiene más contenido que sus
cifras, y una retocada a mano es el documento desmintiendo al registro.

**No es el INES**, y lo dicen sus limitaciones: el INES se cumplimenta en la plataforma del CCN.
Tampoco compara con el informe anterior, aunque la instantánea de cada versión lo permitiría.

```sh
php artisan documentos:generar INF-AUD-2025-01 --html   # el informe de la auditoría del seeder
```

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **Disco `documentos` aparte del de `evidencias`.** No es simetría: en producción el Object Lock se
  aplica **sólo al prefijo `emitidas/`**, porque un borrador tiene que poder reescribirse y una
  versión entregada no debe poder hacerlo nunca. Bloqueado el bucket entero, regenerar un borrador
  falla. Y regenerar escribe **una clave nueva** (ULID en el nombre), nunca sobre la anterior.

- **`Documento::resolveChildRouteBinding()` está escrito a mano.** `scopeBindings()` deduce la
  relación pluralizando el nombre del parámetro **en inglés** —`version` → `versions`— y aquí el
  dominio se nombra en español. Sin eso, `/documentos/{documento}/versiones/{version}/descargar`
  responde 500 con un «Call to undefined method» que no dice nada de la causa. Lo cazó
  `tests/Feature/Documentos/AislamientoTest.php`, y es la pieza que impide descargar la versión de
  otro documento desde una URL que no le corresponde.

- **Las cifras de versión del `DocumentoRecurso` llegan por subconsulta, no por `join`.** Un `join`
  contra `documento_versiones` multiplicaría las filas —un documento con cuatro entregas saldría
  cuatro veces— y la paginación contaría mal. Es el mismo razonamiento que llevó a
  `Filtro::porRelacion()` en activos. Esas subconsultas van en SQL crudo y **no pasan por el scope de
  Eloquent**: ahí quien filtra es RLS, que es justo el caso para el que existe la tercera capa.

- **El aviso de «generando…» va con `usePoll` de Inertia v3**, acotado a dos minutos y con
  `keepAlive: false`. `Inertia::defer()` no sirve —resuelve en **una** petición de seguimiento y no
  reintenta: responde a «carga lo lento después de pintar», no a «espera a un trabajo en segundo
  plano»—, y el flash tampoco, porque pertenece a la petición que lo provoca y el worker corre en
  otro proceso sin sesión. El servidor anuncia lo que hizo («generación encolada») y el cliente
  anuncia lo que vio («el borrador está listo»). Un poll infinito contra una cola atascada es un
  bucle caliente, y por eso se rinde y ofrece «Comprobar».

- **Las cifras de un documento se cuentan sobre las filas que ese documento lista**, no sobre las
  implantaciones del sistema. Acotar por sistema —que es lo que hacía `ResumenCumplimiento::deSistema()`,
  ya retirado— seguía sin bastar: un sistema de ISO lleva, además de los 93 controles del Anexo A, las
  cláusulas 4 a 10, que son el sistema de gestión y que el documento no enseña. **La barra decía 122 y
  la tabla que tenía debajo decía 93.** Una gráfica que contradice a su propia tabla no es un detalle
  de maquetación: es el documento desmintiéndose solo delante del auditor. Mismo criterio que ya regía
  en el panel de inventario — cada cifra se cuenta con el alcance de lo que enseña al lado.

- **La DdA imprime cuántas medidas tiene el Anexo II, no sólo cuántas se exigen.** A un sistema de
  categoría básica se le exigen 52 de 73, y una tabla que enseñe 52 sin denominador se lee como si el
  Anexo II tuviera 52. Que falten veintiuna es una consecuencia correcta de la categorización, pero el
  auditor tiene que poder **verla**, no deducirla. Mismo criterio que «toda cifra con su denominador».

- **`CorrespondenciasCruzadas::paraRequisitos()` existe por la Declaración de Aplicabilidad.** Llamar
  a `paraRequisito()` en un bucle sobre los noventa y tres controles del Anexo A son ciento ochenta y
  seis consultas. La versión en bloque resuelve todo en dos.

- **La fecha de extracción del documento lleva la zona horaria escrita.** La aplicación trabaja en
  UTC y quien lee el documento no tiene por qué: sin la marca, un documento generado a las 00:30 en
  España aparece fechado el día anterior, y una fecha que no cuadra con su registro es un hallazgo
  barato de encontrar.

- **Guardar la plantilla sin tocarla no deja fila.** `GuardarPlantilla` borra la fila cuando el texto
  coincide con el de fábrica, y no es una optimización: sin eso bastaría con abrir la pantalla y darle
  a guardar para que las once secciones quedaran congeladas y esa organización dejara de recibir
  cualquier mejora futura del texto de Statera, sin haberlo decidido y sin enterarse. **«No lo he
  tocado» y «no hay fila» tienen que ser lo mismo.** En el documento es al revés: ahí las filas se
  materializan todas a propósito, porque son una copia congelada.

- **El texto se guarda en Markdown, nunca en HTML.** Es lo diffeable —«¿qué frase cambió entre la v3 y
  la v4?» se contesta con un diff de texto plano—, lo que cabe en la instantánea sin inflarla y lo que
  no tiene superficie de inyección. `paraInstantanea()` congela el Markdown; el HTML es una función
  determinista de él y el PDF entregado ya está almacenado.

- **`MarkdownDocumento` NO usa `Str::markdown()`.** Ese helper monta un `GithubFlavoredMarkdownConverter`
  y trae tablas —y aquí los datos se calculan, no se escriben—, autoenlaces y listas de tareas. Se monta
  el convertidor a mano con `CommonMarkCoreExtension`, `html_input => 'escape'`, `allow_unsafe_links =>
  false` y un tope de anidamiento, más `NormalizarNarrativa`, que poda el árbol ya parseado.

- **Hay DOS renderizadores de Markdown, y el que alimenta al editor no baja los encabezados.** El
  desplazamiento `#`/`##` → `h3` y `###` → `h4` es maquetación del PDF: mantiene la jerarquía que exige
  PDF/UA y evita competir con el `<h2>` que pone la plantilla. Si el editor cargara el HTML del
  documento, un «Título» bajaría un nivel **en cada guardado** hasta tocar fondo. Lo que se almacena es
  `##`; lo que se imprime es `<h3>`.

- **`NormalizarNarrativa` elimina las imágenes, y eso no es cosmética.** `failOnResourceLoadingFailed()`
  está encendido y la allow-list de Gotenberg es `^(file:///tmp/|data:).*`: un `![](https://…)` escrito
  por cualquiera tumbaría la generación del PDF entero con un error que apunta a Gotenberg, que no
  tiene ninguna culpa. Un `<a href>` sí pasa: un enlace no se descarga al imprimir.

- **La regla `SinHtml` RECHAZA en vez de escapar en silencio.** Escapar dejaría un `<b>hola</b>`
  impreso tal cual en el PDF del auditor y quien lo escribió no sabría de dónde ha salido. El patrón
  exige que parezca una etiqueta entera —`<`, nombre y su `>`—: con uno más laxo caía prosa legítima
  como «el riesgo residual < bajo».

- **`realce()` y el Markdown conviven, y la frontera es quién escribió el texto.** Literal de PHP →
  `realce()`, que es un patrón y no un parser; texto de la organización → CommonMark restringido. Las
  cadenas de las limitaciones están llenas de códigos entre acentos graves que un parser convertiría en
  `<code>` y de guiones que leería como listas. De paso, `realce()` ahora convierte esos acentos graves
  en `<span class="cifra">`: **salían impresos en el PDF**, y el documento que se le entregaba al
  auditor decía «(`op.acc.1`)» con las comillas dentro.

- **El editor es TipTap 3 + `prosemirror-markdown`, y el argumento no es que sea popular.** En
  ProseMirror **el esquema del documento ES la lista blanca**: lo que no está declarado no se puede
  crear, ni escribiendo, ni pegando, ni arrastrando. No hay saneado de HTML pegado en cliente, que es
  justo la clase de código que no se quiere en una herramienta que entra en el alcance de su propio
  SGSI. Y el serializador se declara a mano —`lib/markdownEditor.ts`— porque ese mapa **es la lista
  blanca escrita otra vez en el camino de escritura**; un paquete que «detecta» el Markdown hace lo
  contrario. Pesa **170 kB gzip** y va en su propio chunk con `defineAsyncComponent`: el bundle
  principal no se movió ni un kilobyte. Mismo criterio que `@number-flow/vue`.

- **El `.docx` es una copia de trabajo, no la entrega, y se construye desde `instantanea`.** El
  entregable archivable es el PDF/A-3b con su huella. **Construirlo desde una consulta nueva sería el
  fallo más caro del módulo**: el Word de una versión emitida en marzo enseñaría los datos de octubre y
  contradiría al PDF que lo acompaña, con la huella de ese PDF impresa dentro. De ahí
  `ContenidoDocumento::desdeInstantanea()`, que además deja la instantánea **rehidratable** y habilita
  mañana una pantalla de diff entre versiones.

  No se almacena ni se versiona: `documento_versiones` existe para demostrar qué se entregó, sus
  `CHECK` acoplan la fila a **un** fichero con su hash, y el trigger de inmutabilidad no dejaría
  adjuntarlo a una versión emitida. Va marcado en tres sitios que sobreviven a un reenvío —el pie de
  cada página, las propiedades del fichero con la huella del PDF, y el nombre—.

- **`phpoffice/phpword` es LGPL-3.0 en un repositorio MIT.** Compatible como dependencia de Composer sin
  modificar y cargada en ejecución, pero queda escrito para que no lo descubra nadie más adelante. Y
  emite avisos de obsolescencia con PHP 8.4 que sólo se ven con `E_ALL` —`tinker`—: no afectan al
  fichero, que sale válido.

- **Los anchos del `.docx` van en twips ENTEROS.** `Converter::cmToTwip()` devuelve decimales y salían
  al XML como `w:w="1583.3333333333333"`. Lo cazó Larastan, no una revisión.

- **El cuerpo del documento está exento de `TrimStrings` y de `ConvertEmptyStringsToNull`**
  (`bootstrap/app.php`). Los dos son globales y recortan **toda** cadena de la petición; el cuerpo
  viaja como un árbol de ProseMirror donde cada trozo de texto es una cadena suelta, y el espacio que
  separa un trozo del anterior **no es relleno, es la separación**. Sin la exención, el PDF que se le
  entrega al auditor decía «no es una entrega.Este PDF se regenera», y además guardar sin tocar nada
  cambiaba el documento. `Str::is` entiende el comodín, así que `cuerpo.*` cubre el árbol a cualquier
  profundidad; el de cadenas vacías va por camino (`documentos/*/cuerpo`) porque **corre antes de
  resolver la ruta** y ahí no hay `routeIs()` que valga. Lo cazó un test, no una lectura: el síntoma
  aparece a tres capas de distancia de la causa.

- **`editado_en` significa «alguien guardó desde el editor», no «el contenido cambió».** Es
  deliberado y hay un test que lo fija (`DocumentoEditadoDeclaraTest`): guardar sin cambiar nada
  declara el documento mantenido a mano, y a la vez **no** marca ningún bloque calculado como
  modificado, porque la procedencia se comprueba contra la línea base y no se deduce de que alguien
  haya pulsado Guardar. De ahí que el editor tenga **dos eventos y no uno**: Tiptap normaliza el árbol
  al cargarlo y eso llega por `normalizado`; sólo `onUpdate` emite `cambio`. Emitir los dos como
  `cambio` dejaba el documento sucio nada más abrirse, «Ver el PDF» guardaba solo y el documento
  acababa declarando en portada que se había editado a mano por el hecho de abrirlo.

- **Se puede redactar antes de generar nada.** Una versión nace en `GenerarDocumento::encolar()`, así
  que un documento recién creado no tiene ninguna y el editor abortaba con 404 en el camino más corto
  que hay entre crear un documento y escribir en él. `DocumentoCuerpoController::versionVigente()` cae
  a una `DocumentoVersion` **en memoria y sin guardar** —`etiqueta()` ya dice «Borrador» con `numero`
  nulo—. No se crea la fila: `documento_versiones` es el registro de lo que se ha **entregado**, y
  meter ahí un documento que alguien abrió una vez le quita el único significado que tiene.

- **El `.docx` recorre el cuerpo de la instantánea; `CuerpoAWord` es el hermano de
  `RenderizadorCuerpo`.** Mismo árbol, mismo vocabulario cerrado de `EsquemaCuerpo`, otro destino.
  `EscritorWord` se queda con el continente —hoja, estilos, pie de copia de trabajo y propiedades del
  fichero—. Lo que **no** se traduce es el lenguaje de color y forma: los badges llegan como texto y la
  barra por tramos como sus cifras. Sin cuerpo en la instantánea se responde 404, igual que sin
  instantánea. Y ojo con PHPWord: **`TextRun` no tiene estilo de fuente propio y no protesta si se le
  pide** —un `__call` se traga `setFontStyle()` en silencio—, así que el estilo base baja hasta cada
  `addText`. Lo cazó Larastan.

- **Los dos `match` sobre cadenas de `MaterializarCuerpo` fallan ruidosamente, y antes no.** Los dos
  —la fuente de un bloque y la clave de una columna— cerraban con un `default` silencioso: una fuente
  declarada en `EsquemaCuerpo::FUENTES` y olvidada allí se materializaba como un grupo **vacío**, o sea
  un apartado que desaparece del PDF sin ningún error, y una columna olvidada salía como una raya en
  las noventa y tres filas. PHPStan no los señala porque no son `match` sobre un enum. Ahora los dos
  lanzan `LogicException`, que es seguro porque `recorrer()` sólo entra si `EsquemaCuerpo::esFuente()`
  y las claves de columna las declara código, nunca un dato de usuario.

  **Y por eso no hay test de regex**, que fue lo primero que se pensó copiando a `EsquemaEnDosIdiomasTest`:
  aquél parsea el fichero fuente porque el otro lado es TypeScript y no se puede leer desde PHP. Aquí
  los dos lados son PHP, así que `HuecosCalculadosTest` recorre `FUENTES` y las columnas de cada tipo y
  comprueba que ninguna rama salta. **Se parametriza solo** para el cuarto documento calculado.

- **Cabo suelto que sigue abierto:** `CuerpoRenderizadoTest` monta todo sobre ISO, así que los bloques
  exclusivos del ENS —`tabla_derivacion`, `notas_anexo_ii`, `tabla_madurez`— y los dos del plan
  —`resumen_plan`, `tabla_sin_trabajo`— no tienen ninguna aserción sobre su HTML materializado.
  `CuerpoSeguroTest` recorre los tipos, pero sobre el **esqueleto** de fábrica, con los huecos sin
  rellenar.

- **La cifra de un documento en la tabla sale por subconsulta, incluido su estado documental.** Es la
  misma regla que ya regía para el número de versión: un `join` contra `documento_versiones`
  multiplicaría las filas y la paginación contaría mal. Y lo que hace que un `max()` sobre un texto
  no sea un disparate es que los dos índices únicos parciales —un borrador vivo, una aprobada viva—
  garantizan que agrega sobre una fila como mucho.

- **`GenerarDocumento::encolar()` hace `refresh()` tras insertar**, igual que `CrearTarea`. Los valores
  por defecto de `estado` los pone la base, y repetirlos en el modelo sería el mismo dato en dos
  sitios que pueden desincronizarse. Sin eso, un borrador recién encolado llega con `estado` a nulo y
  lo primero que lea su máquina de estados revienta con un «call to a member function on null» que no
  menciona la palabra «estado». Por lo mismo, `DocumentoVersionFactory` declara `estado` explícito:
  `create()` no relee la fila.
