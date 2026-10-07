---
paths:
  - CLAUDE.md
  - README.md
  - PRODUCT.md
---

# Orden de arranque

El orden importa: el catálogo y el motor son la parte más específica del dominio y la que más se estropea si se improvisa; el resto es CRUD con reglas de negocio encima.

1. ✅ Esquema del catálogo + comando de importación idempotente desde YAML.
2. ✅ Motor de categorización ENS, con tests exhaustivos de la matriz completa.
3. ✅ Generación de implantaciones desde el motor, con transiciones y recálculo.
4. ✅ Capa de recursos genérica (`Recurso` + `DataTable` + formularios), validada con Sistemas (CRUD) e Implantaciones (lectura y acción masiva).
5. ✅ Primer módulo completo de punta a punta: inventario de activos.
6. ✅ Primeros documentos con Gotenberg: la SoA de ISO y la DdA del ENS.
7. ✅ Plan de acción (§ 4.7) y la capa de avisos. Con esto la **fase 1** de la
   especificación —«sustituir las hojas de cálculo»— queda completa: catálogo,
   motor, inventario, implantaciones, evidencias y tareas.
8. ✅ Análisis de riesgos (§ 4.3), que abre la **fase 2** —«el papel formal»—.
   Catálogo de amenazas de MAGERIT, metodología por organización, valoración con
   histórico comparable y salvaguardas sobre implantaciones.
9. ✅ Flujo de aprobación documental con acuse de lectura (§ 4.5): estado del
   documento, firma de la dirección, obsolescencia de la versión anterior,
   periodicidad de revisión con su aviso, y la segunda familia de documentos —los
   **redactados**: política, norma y procedimiento—.
10. ✅ Plan de adecuación del ENS, el tercer documento calculado. **No es el
    § 4.18**, que es «Informes y exportación» y sigue pendiente: el plan no es un
    módulo numerado de los diecinueve —aparece dentro de la lista de exportables
    del 4.18 y en la fase 2—, y llamarlo así hacía creer que ese módulo estaba
    hecho.

    Con él la **fase 2** —«el papel formal»— queda completa: riesgos con metodología,
    documentos con flujo de aprobación, y SoA, DdA y plan de adecuación.
11. ✅ Auditorías (§ 4.12), que abre la **fase 3** —«el ciclo vivo»—: los tres
    tipos, checklist generada desde el catálogo, hallazgos, y el cierre que
    congela e inmoviliza lo auditado.
12. ✅ No conformidades y acciones correctivas (§ 4.13), la otra mitad del módulo
    anterior: un hallazgo sin tratamiento detrás no cierra ningún ciclo. Causa
    raíz, acciones correctivas —que son **tareas**— y la **verificación de
    eficacia**, que es el paso que la cláusula 10.2 pide y el que más se olvida.
    Con esto el ciclo se recorre entero: auditar, encontrar, tratar y comprobar.
13. ✅ Contexto de la organización (§ 4.1): el DAFO, las partes interesadas con
    sus requisitos y el alcance declarado, en revisiones **versionadas** que se
    aprueban y se congelan. Era el único de los diecinueve módulos que no estaba
    asignado a ninguna fase, y es lo que le faltaba al § 4.15: la cláusula 9.3
    pide «cambios de contexto» como entrada obligatoria y hasta aquí no había de
    dónde sacarla.

14. ✅ Indicadores y mediciones (§ 4.14, cláusula 9.1). El panel llevaba desde el
    principio contando cosas, y todas esas cifras eran de **hoy**: la 9.1 no pide
    una cifra, pide un seguimiento con cadencia, objetivo y responsable. Con la
    serie sellada, «¿ha mejorado esto desde la última revisión?» —que es
    literalmente lo que pregunta la 9.3— tiene contra qué compararse.

    Va **antes** que los objetivos de seguridad (6.2) porque el «cómo se
    evaluarán los resultados» que esa cláusula exige **es** un indicador: al
    revés, el objetivo nacería con el campo que el auditor más mira y nada
    detrás. Es el mismo orden que llevó a hacer el § 4.1 antes que el § 4.15.

    La **fase 3 sigue abierta**, y el § 4.15 sigue bloqueado por lo mismo que lo
    bloqueaba el § 4.1: de las siete entradas obligatorias de la 9.3, ya salen
    cinco —acciones previas, cambios de contexto, partes interesadas, no
    conformidades, auditorías y riesgos— y **faltan dos**: el cumplimiento de los
    **objetivos de seguridad** (6.2, sin módulo) y las **oportunidades de mejora**
    (10.1, que hoy sólo existen como `TipoHallazgo::OportunidadMejora` dentro de
    una auditoría). Queda además el calendario de obligaciones completo (§ 4.16) y
    los otros dos tercios del flujo de conformidad (§ 4.17).

15. ✅ Objetivos de seguridad (cláusula 6.2). La primera de las dos entradas que
    le faltaban a la 9.3, y la que el punto anterior dejó preparada: el «cómo se
    evaluarán los resultados» que la 6.2 exige **es** un indicador, así que el
    § 4.14 fue antes a propósito. Hasta aquí el producto **medía** y no había
    dónde comprometerse a una cifra; son dos cosas distintas y la norma las pide
    las dos.

    Es además una de las cinco cláusulas que tenían requisito en el catálogo,
    implantación esperando y **ningún sitio donde escribirse** — exactamente lo
    que le pasaba al § 4.1 hasta que se construyó.

    **A la 9.3 le falta ya una sola entrada**: las oportunidades de mejora (10.1),
    que siguen existiendo únicamente como `TipoHallazgo::OportunidadMejora` dentro
    de una auditoría. Ése es el punto 16.

16. ✅ Oportunidades de mejora (cláusula 10.1). La segunda mitad del capítulo 10 y
    **la última entrada que le faltaba a la 9.3**: con esto, las siete entradas
    obligatorias de la revisión por la dirección salen todas del producto y el
    § 4.15 deja de estar bloqueado.

    Hasta aquí una oportunidad de mejora **sólo existía dentro de una auditoría**,
    como `TipoHallazgo::OportunidadMejora`: la que se le ocurría a alguien un
    martes, o la que salía de un indicador que no llegaba a su objetivo, no tenía
    dónde apuntarse.

    Es además el módulo que **cierra la bifurcación del capítulo 10**: la 10.2
    trata lo que incumple y la 10.1 lo que se puede mejorar sin que nada incumpla,
    y desde aquí un hallazgo va al registro que le toca — con las dos puertas
    cerradas en el dominio, no sólo en el formulario.

17. ✅ Revisión por la dirección (§ 4.15, cláusula 9.3). **El módulo que llevaba
    bloqueado desde el principio**, y no por su complejidad: la 9.3 cierra la
    lista de entradas obligatorias y dos de las siete no salían de ninguna parte.
    Con los dos puntos anteriores dentro, las siete existen y esto las recoge.

    Con él **la fase 3 —«el ciclo vivo»— llega a su pieza central**: auditar,
    encontrar, tratar, comprobar, medir, comprometerse, mejorar y **revisarlo todo
    desde arriba**. El quinto documento calculado, el quinto trigger de
    inmutabilidad y el octavo verbo de supervisión.

    Lo que **sigue abierto de la fase 3**: el calendario de obligaciones completo
    (§ 4.16) —cerrado en el punto 24— y la continuidad (§ 4.11) —cerrada en el
    punto 25—, más los otros dos tercios del flujo de conformidad (§ 4.17), que la
    especificación no asigna a esta fase.

18. ✅ Personas (§ 4.8) y la cláusula 5.3. **El primero de los dos módulos que
    muerden hoy**: en categoría básica ya son exigibles `mp.per.2`, `mp.per.3` y
    `mp.per.4` —deberes por escrito, concienciación y formación— y no tenían dónde
    registrarse. El otro es incidentes (§ 4.10), que sigue sin construirse.

    Es además el módulo que llevaba **cuatro enganches puestos esperando**: el
    IND-03 del seeder, que era manual con un comentario que decía «mientras el
    § 4.8 no exista»; las dos limitaciones impresas —la de los roles ENS de la DdA
    y la del acuse de lectura—, que pasaron a ser falsas en el PDF entregado y se
    reescribieron; y la cláusula 5.3, que era un hueco declarado en `PRODUCT.md`.

    Lo que cierra la 5.3 no es la tabla de nombramientos: es que la
    incompatibilidad se **impide** y no se avisa, que es lo que la especificación
    pide con esas palabras.

19. ✅ Incidentes (§ 4.10, `op.exp.7`). **El segundo de los dos módulos que
    muerden hoy**, y con él los dos quedan cubiertos: en categoría básica ya no
    hay ninguna medida exigible sin dónde registrarse.

    Es además el módulo que cierra **tres enganches** puestos hace meses:
    `OrigenTarea::Incidente` y `OrigenNoConformidad::Incidente` pasan a ofrecerse
    sin migración —el valor estaba en el `CHECK` desde la primera—, y de paso
    `OrigenNoConformidad::RevisionDireccion`, que se quedó en `false` y era falso
    desde el § 4.15.

    La decisión del módulo no es el ciclo: es **dónde hay reloj y dónde no**. Las
    72 h de la AEPD salen del artículo 33.1 del RGPD y van citadas; el CCN-CERT
    no tiene cuenta atrás porque el RD 311/2022 dice «sin dilación», y
    inventarle un número sería una opinión de la herramienta disfrazada de plazo
    legal — lo mismo que el producto se niega a hacer con el riesgo residual.

20. ✅ Puestos, datos de la persona y adjuntos. **No es un módulo de los
    diecinueve**: es lo que al § 4.8 le faltaba para poder usarse. Quién es cada
    persona —hasta aquí `nombre` y `email` y poco más—, qué puesto ocupa —hasta
    aquí una **cadena de texto libre**, así que «Analista» y «analista» eran dos
    puestos para cualquier recuento— y dónde se guarda el título de un curso
    —hasta aquí en ningún sitio: sólo se podía **señalar una evidencia que ya
    existiera**—.

    Con él, `mp.per.1` —la caracterización del puesto de trabajo— pasa a tener
    dónde escribirse, que es una de las dos cosas que el § 4.8 declaraba que no
    hacía. La otra —comprobar que la plantilla esté completa— sigue sin hacerse.

    De paso, el **ritmo vertical de la página** deja de ponerlo cada componente
    por su cuenta y pasa al contenedor: una tarjeta intercalada entre dos bloques
    salía pegada a lo de abajo, y se veía en `/personas`.

21. ✅ Mi cuenta: la pantalla propia. **Tampoco es un módulo de los diecinueve**,
    y es la única pantalla de escritura que no se había construido con la capa de
    recursos: iba montada a mano sobre `Card` + `useForm`, con **tres anchos de
    campo distintos** conviviendo en la misma columna. Eso es lo que se veía.

    Lo que no se veía era el fallo: el formulario arrancaba con el nombre y el
    correo **vacíos** y el valor real puesto sólo de `placeholder`, así que quien
    pulsaba «Guardar» sin reescribir los dos campos recibía un error de
    validación, y quien cambiaba sólo el nombre mandaba el correo en blanco.

    Trae además la foto de perfil y el bloque de sólo lectura de permisos. Lo que
    **no** trae es el § 4.19 —dar de alta cuentas y asignar roles—, que sigue sin
    pantalla.

22. ✅ La ficha de la organización. La raíz del tenant llevaba desde la primera
    migración **sin ninguna pantalla**: sus columnas sólo se tocaban por seeder o
    entrando en la base. Y dos de ellas ya decidían cosas impresas —
    `url_base_etiquetas` gobierna los QR **ya pegados** en el parque, y las dos
    banderas del ENS deciden qué dice la DdA—.

    Trae además la identificación legal: **razón social y domicilio fiscal**, que
    no existían. Desde aquí la portada y la cabecera de todo documento imprimen la
    razón social, porque **una Declaración de Aplicabilidad la firma una persona
    jurídica** y `nombre` es un nombre de pantalla.

23. ✅ La marca del cliente. El logo de la organización en la portada del PDF, en
    la cabecera de cada página y en el desplegable del panel lateral. Es
    **co-branding y no marca blanca**: Statera se queda arriba y firma el pie.

    La decisión que lo hace barato es que **el logo es marca y no contenido**, lo
    mismo que el filete y la palabra «Statera» de la portada: entra por una regla
    de CSS generada, así que el esquema del cuerpo, el renderizador, el `.docx` y
    la instantánea no se enteran de que existe. `EsquemaCuerpo` sigue declarando
    que no hay nodo de imagen, y sigue siendo verdad.

    Trae la primera dependencia de seguridad del repositorio,
    `enshrined/svg-sanitize`, porque un logo corporativo llega en SVG y eso es un
    documento XML.

24. ✅ El calendario de obligaciones (§ 4.16). **El módulo que más módulos tenían
    esperando**: cinco ficheros de reglas lo citaban por su nombre en su sección de
    limitaciones —personas, métricas, documentos, revisión por la dirección y el
    propio plan de acción—, y tres de esas limitaciones iban impresas en PDF que se
    le entregan a un auditor.

    La decisión del módulo no son las fuentes: es **haber visto que son dos mitades
    y que ninguna sustituye a la otra**. Seis de las once cosas periódicas que
    enumera la especificación se derivan de datos que ya existían —la fecha límite
    de una tarea, la caducidad de una evidencia, la revisión de una versión
    firmada, la última formación de una persona, el periodo de un indicador, la
    fecha objetivo de una medida— y entraron como `Fuente`, sin una columna nueva.
    Las otras cinco **no tienen de dónde derivarse**: lo que vence en el informe
    INES no es una fila que exista, es una fila que debería existir y no está. Una
    `Fuente` más no lo resuelve porque no hay nada que consultar, y por eso hay
    tres tablas.

    Es además el módulo que cierra **el hueco que el acta de la revisión por la
    dirección llevaba impreso**: «no se comprueba que la revisión se celebre con la
    periodicidad comprometida». No se cerró con una `Fuente` —lo que vence es la
    revisión del acta aprobada, que `Fuente::Documento` ya recogía— sino con una
    fila del catálogo: lo que faltaba era avisar de la reunión que **no** se
    convocó.

    Lo que lo hace barato de crecer es que las obligaciones son **datos**: siete
    casos de `Fuente` y no once, porque el informe INES, la renovación de
    conformidad y la auditoría de seguimiento son filas de `catalogo/obligaciones.yaml`
    y no casos de un enum. La reevaluación de proveedores, que aquí se daba por
    «una línea de YAML», acabó siendo una `Fuente` cuando llegó el § 4.9: sale de
    un registro, y lo que no sale de ninguno es justo lo que va al catálogo.

    **Y entró con once fallos de comportamiento dentro**, con la suite verde y
    Larastan limpio. Están enumerados en `obligaciones.md`; dos perdían datos o
    reventaban la petición. La lección va aquí porque es de la bitácora y no del
    módulo: **la suite verde dice que nada de lo que se comprueba está roto, no que
    el comportamiento sea el correcto.** Los nueve tests que descubren cubren los
    olvidos de forma —una fuente sin icono, una factory con `organizacion_id`, un
    permiso sin rol—; que retirar no borre las notas del usuario no lo descubre
    ningún glob.

    Y trae dos hallazgos que no eran del módulo. El primero, **doce `User::query()`
    sin acotar por organización** en seis módulos —controlador y `Recurso` de cada
    uno—, que listaban a los usuarios de todos los clientes en el desplegable de
    responsable; los otros diecinueve sitios sí lo hacían y uno lo llevaba
    comentado, así que lo que faltaba no era disciplina sino un test. El segundo,
    que el rótulo del importador de catálogo salía por defecto como «mapeos» para
    cualquier fichero sin marco: el cuarto tipo lo puso en evidencia.

25. ✅ Continuidad (§ 4.11). **Cierra la fase 3.** Era el último módulo del ciclo
    vivo y el único con requisitos en el catálogo —`op.cont.1` a `op.cont.4`— sin
    ningún sitio donde escribirse: ni el análisis de impacto, ni el plan, ni la
    prueba de que el plan funciona. En básica están en `no_aplica`; `op.cont.3`
    lo activa la Disponibilidad en alto, que por ser la categoría el máximo de
    las cinco dimensiones deja al sistema en categoría alta.

    Lo que lo hace barato son tres decisiones, y las tres son no construir algo.
    **El plan es un documento**, `TipoDocumento::PlanContinuidad`, y hereda
    aprobación, versiones, firma, acuse y PDF/A sin una línea de flujo propio.
    **El MTPD se deriva** de los cinco tramos del BIA y no se guarda, así que no
    hay copia que desincronizar. Y **la obligación anual sale del requisito y no
    de la categoría**: `obligaciones.requisito_id` contra lo que el motor ya
    decidió, porque `categoria_minima: media` —como estaba sembrada desde el
    § 4.16— exigía de más: la proponía a sistemas media y alta cuya
    Disponibilidad no llega a alto. El requisito la propone exactamente donde el
    motor hace exigible `op.cont.3`, y copiar la regla en el YAML era calcular
    la aplicabilidad dos veces.

    Trae dos `Fuente` más —las pruebas planificadas y la revisión del BIA
    aprobado—, una cuarta referencia de cumplimiento y tres costuras: tarea, no
    conformidad y mejora desde una prueba parcial o fallida. Y una limitación
    impresa que dejó de ser verdad, la del plan de adecuación.

    **La lección se repite, y esta vez con cifra.** La revisión de cada tarea
    encontró **cinco defectos reales con la suite en verde**: un `down()` de
    migración que abortaba con datos porque se había verificado sobre una base
    vacía; el rojo gastado en una incoherencia que no es un plazo vencido; un
    orden por defecto que declaraba el nombre de la columna en vez de su clave y
    desaparecía en silencio al paginar; una sincronización de pivote que dejaba
    colar activos ajenos en una prueba ya hecha, y una edición sin lista blanca
    que cambiaba el estado sin pasar por el histórico. Ninguno lo habría
    encontrado un test que descubre. Cuatro llevan ahora el suyo; el `down()` se
    comprobó a mano —datos por el camino real, `migrate:rollback` y vuelta—,
    porque la suite no ejecuta rollbacks, y eso sigue siendo un hueco.

26. ✅ Conformidad con el ENS (§ 4.17), categoría básica. **El primer punto fuera
    de las tres fases**, y el que le faltaba al objetivo declarado de la fase
    actual —ENS categoría básica—: la autoevaluación existía como
    `TipoAuditoria::Autoevaluacion` y cerrarla no producía nada. Ahora el flujo
    se recorre entero: autoevaluación cerrada → Declaración de Conformidad
    firmada → distintivo publicado, por sistema y con histórico.

    Tres decisiones hacen el módulo pequeño. **La firma no es suya**: es la de la
    Declaración, un sexto documento calculado que hereda aprobación, versiones,
    huella y PDF/A sin flujo propio. **La categoría se congela** al iniciar, como
    la exigencia de un punto de checklist, y **caducar es una fecha y no un
    estado**. Y **el distintivo se registra y no se sirve**: no hay ruta pública,
    por decisión expresa, porque la herramienta entra en el alcance del SGSI. La
    vía de media y alta —ENAC y Certificación— se modela en el esquema y no se
    recorre, que es lo que pedía la especificación.

    **La lección, otra vez con cifra.** La suite en verde y Larastan limpio no
    encontraron dos de los tres fallos que tuvo. El primero lo destapó un test al
    escribirse: `documento_versiones.emitida_en` es `timestamptz` y se escribe con
    la hora de Madrid sin desfase, así que leída por Eloquent queda dos horas por
    delante de cualquier `created_at`. Es anterior a este módulo y no se ha
    tocado; aquí se compara en SQL, donde las dos columnas se leen igual. El
    segundo lo destapó el recorrido en el navegador: recién declarada, la ficha
    ofrecía renovar sobre la misma autoevaluación, que habría reiniciado los dos
    años sin volver a comprobar nada. El tercero, que `aprobada_en` es una fecha
    y no un instante, se vio leyendo el esquema antes de probar.
27. ✅ Informes y exportación (§ 4.18). **Cuatro de los seis documentos que
    nombra ya existían** —SoA, DdA, plan de adecuación y acta, en PDF/A y en
    Word— y faltaban el informe de auditoría interna y el informe de estado. Los
    dos son documentos calculados sobre la tubería de siempre, y el Word les llega
    gratis porque `CuerpoAWord` despacha por nodo y no por tipo.

    **El informe de auditoría es el primer tipo que nombra su fuente**, en
    `documentos.auditoria_id`: el acta imprime la última revisión aprobada, y aquí
    cada auditoría tiene su informe. El vínculo va en `documentos` porque la fila
    de una auditoría cerrada es inmutable. Y trajo tres columnas que la 9.2.2 pide
    y la tabla no tenía: criterios, método y equipo.

    **El informe de estado no calcula ninguna cifra.** Cinco clases llevaban escrito
    que sus preguntas «son las que contestará el informe de estado»; el generador
    se las hace y las imprime, y `PanelController::sistemas()` se mudó a
    `ResumenCumplimiento::porSistema()` para que panel e informe cuenten con la
    misma consulta. **No es el INES**, y lo dice.

    **Y la lección de siempre**: la suite en verde no encontró tres fallos que
    salieron al recorrerlo en el navegador —el formulario presentaba el acta y el
    informe de estado como una política, crear un documento sin responsable
    fallaba, y el pie de portada hablaba de una categoría que esos documentos no
    tienen—. Los tres eran anteriores a este punto.
28. ✅ Cuentas, roles y el alcance del auditor (§ 4.19). **El primero de los tres que
    quedaban**, y va delante porque es el que bloqueaba el uso real: una cuenta sólo
    se creaba desde el seeder, así que nadie salvo las tres sintéticas podía entrar.
    Los otros dos —proveedores y vulnerabilidades— muerden en básica
    (`op.nub.1`, `op.exp.4`), pero sin cuentas no hay nadie que los registre.

    Las dos frases del § 4.19 que ninguna lista de permisos expresa eran el módulo
    de verdad. **El auditor externo ve sólo los sistemas que audita y hasta una
    fecha**: una cuarta capa encima de las tres —un scope y no RLS, porque dentro de
    un tenant RLS no distingue a nadie— y un décimo test que descubre, el que exige
    `AcotadoPorAlcance` a todo modelo con `sistema_id`. **El técnico lee todo y
    escribe lo suyo**: un middleware en el grupo de escritura de tareas e
    implantaciones, y no un `authorize()` por `FormRequest`, porque la mitad de esas
    rutas no tienen uno.

    Tres decisiones lo hacen pequeño, y las tres son no guardar algo: **el estado de
    la cuenta se deriva** de cuatro fechas, **desactivar no borra** y **la traza se
    escribe a mano**, porque `users` está fuera de las tres capas y la traza no.
    Trae además el registro de sesiones y el bloqueo por inactividad del § 6, que
    estaban en los requisitos no funcionales desde el principio.

    Y un hallazgo de paso: las fichas de tarea e implantación le pintaban al auditor
    botones que le respondían 403. Ahora las dos reciben quién puede escribir, y lo
    dicen cuando es otra persona.

29. ✅ Proveedores y terceros (§ 4.9). **Muerde hoy**: `op.nub.1` es exigible en
    categoría básica y A.5.19 a A.5.23 en ISO, aunque `op.ext.*` no lo sea hasta
    media. Cerró cuatro enganches puestos desde hacía módulos:
    `activos.proveedor_id`, la frase impresa del plan de adecuación, la exclusión
    escrita en `catalogo/obligaciones.yaml` y el hueco del grupo «Organización» del
    menú.

    Tres decisiones, las tres de César y las tres en la línea de lo que ya había.
    **La criticidad tiene un mínimo derivado** de lo que presta —subirla es
    libre, bajarla se justifica—, que es el invariante 4 aplicado a un tercero.
    **Lo que se comprueba es catálogo**, un sexto fichero YAML con su importador
    idempotente, y no un enum (invariante 3). **La reevaluación es política de
    la organización**, como su metodología de riesgos, porque ni ISO ni el ENS
    fijan el plazo.

    Y una más que no se eligió: **el estado lo pone la evaluación**, no un
    desplegable. Homologar sin evaluar sería exactamente lo que el módulo existe
    para impedir.

30. ✅ Vulnerabilidades (invariante 8, A.8.8, `op.exp.4`). **El último de los
    tres, y el que desmentía una frase de `CLAUDE.md`**: el invariante 8 decía que
    la herramienta contiene las vulnerabilidades y llevaba una nota debajo
    diciendo que no. Lo que había era `riesgos.vulnerabilidad`, la condición de un
    escenario de MAGERIT, y no un hallazgo con severidad, activos y plazo. La nota
    se ha quitado.

    Cuatro decisiones, las cuatro en la línea de lo que ya había. **La severidad
    se deriva del CVSS** cuando lo hay, con los tramos de FIRST, y la impone un
    `CHECK`, que es el invariante 4 aplicado a un hallazgo. **El plazo es política
    de la organización**, como la reevaluación de un proveedor, porque ninguna de
    las dos normas fija un número. **Aceptar es de supervisión**, como aceptar un
    riesgo. Y **cerrar exige verificación escrita**, que es la distancia entre
    mitigada y cerrada y la parte de la 10.2 que más se olvida.

    Cerró dos enganches: el aviso de fuera de soporte de la ficha del activo, que
    ahora ofrece registrarlo como vulnerabilidad, y `ClasificacionIncidente::Vulnerable`,
    que ya tiene el registro al que apuntar desde el otro lado.

    **Y la lección, otra vez**: con la suite en verde, el recorrido encontró que el
    rojo de vulnerabilidades se contaba y no marcaba ninguna pestaña del panel.
    No era de este punto: `PanelController` llevaba escrito a mano qué módulo
    cae en qué pestaña, y obligaciones y proveedores ya se habían quedado fuera.
    Ahora es `AlertasDelPanel::VISTAS`, con un test que pregunta a cada fuente, y
    proveedores y vulnerabilidades tienen tarjeta donde explicar su rojo.

    **Con él no queda ningún punto de la lista.** Lo que falta son los huecos que
    `PRODUCT.md` anota, que no son módulos.

## Tramo «listo para producción»

Con la lista de módulos terminada, lo que falta no es funcionalidad: es lo que el
invariante 8 y el § 6 de la especificación exigen antes de que entre alguien de
verdad. La herramienta guarda el inventario, las vulnerabilidades y las
evidencias de su propio SGSI, y hasta aquí sólo cumplía tres de los siete
requisitos no funcionales: el segundo factor, el registro de sesiones y el
bloqueo por inactividad. El orden va de lo que ya es un fallo a lo que exige
decidir algo: 31 validación, 32 traza, 33 horas, 34 copias, 35 cifrado en reposo
y 36 retención RGPD.

31. ✅ La validación de usuarios, acotada. **Era un fallo, y por eso va
    primero.** Siete reglas `exists:users,id` —seis `FormRequest` y
    `ObligacionController::asumir()`— aceptaban de responsable la cuenta de
    otro cliente, mientras veintitrés sitios ya usaban `Rule::exists()` con su
    `where`. `ConsultasDeUsuarioAcotadasTest` vigilaba las consultas y no la
    validación, que es la otra puerta: `users` no tiene RLS y el resto de
    `exists:` sí pasan por ella. Ahora vigila las dos, y encontró exactamente
    esas siete.

32. ✅ Traza completa e inmutable. **Completa, ya**: dieciocho modelos con
    `organizacion_id` no dejaban traza —las once tablas de transiciones y siete
    de detalle—, y la emisión de una versión, que es lo que más le importa al
    auditor, iba por el query builder y tampoco. Las transiciones se quedaban
    fuera a propósito («la traza registra sobre el histórico y no sobre sí
    misma»), y César decidió que entren: el histórico contesta «¿desde
    cuándo?» y la traza contesta que la fila no se ha tocado después. En
    `CompromisoCumplimiento` había además un motivo de peso, porque se corrige
    borrando y ese borrado no dejaba rastro. Tres borrados en bloque pasaron a
    hacerse fila a fila para que no saliera media traza, y un undécimo test que
    descubre exige el trait.

    **E inmutable, que no lo era.** `statera_app` corría las migraciones y era
    la cuenta de la aplicación, así que era dueña de las 113 tablas. Un dueño
    puede devolverse cualquier privilegio: el `REVOKE` sobre
    `eventos_auditoria` paraba al código distraído, pero unas credenciales
    comprometidas lo deshacían con un `GRANT`, y con un `DISABLE TRIGGER`
    apagaban los triggers de inmutabilidad. Ahora el esquema es de
    `statera_migrador`, que sólo usan las migraciones, y la aplicación lee y
    escribe filas y nada más. Los tests migran como dueño y corren como
    aplicación (`RefrescaLaBase`), porque un test que corriera como dueño vería
    salirle bien lo que hay que impedir.

    **Lo que no cierra, y queda declarado:** RLS decide por dos variables de
    sesión, `app.organizacion_actual` y `app.mantenimiento`, y cualquier rol
    puede fijarlas con `set_config()`. La tercera capa para a una consulta mal
    escrita, pero no a quien ya ejecuta SQL arbitrario con las credenciales de
    la aplicación. Cerrarlo pide que la organización no la declare el propio
    cliente de la base, y eso ya es otro diseño.

    **La lección tiene un matiz nuevo.** El test que comprobaba el `GRANT` salió
    rojo con la puerta ya cerrada: PostgreSQL no falla cuando alguien sin opción
    de concesión hace un `GRANT`; avisa de que no concedió nada y sigue. Un test
    que espera la excepción da por abierta una puerta cerrada, y en otro motor
    daría por cerrada una abierta. Se comprueba el efecto, con
    `has_table_privilege()`.

33. ✅ Los instantes con zona, bien guardados. `emitida_en` y otras cinco
    columnas `timestamptz` guardaban un instante dos horas desplazado: Laravel
    escribe la hora de Madrid sin desfase, y PostgreSQL la interpretaba en la
    zona de la sesión, que era UTC. César pidió primero UTC de verdad y, visto
    el alcance, eligió la vía pequeña. UTC habría supuesto convertir 192
    columnas `timestamp`, añadir una zona de presentación para los formatos del
    servidor, los PDF y los `datetime-local`, y dar un «hoy» de Madrid a los
    vencimientos, porque entre las 00:00 y las 02:00 «hoy» sería ayer. La vía
    elegida es **la sesión en la zona de la aplicación**: una línea en cada
    conexión, una migración que recoloca lo ya escrito y el apaño en SQL de
    `RegistrarDeclaracion` fuera. Si algún día la aplicación pasa a UTC, esto
    sigue funcionando, porque la zona de la sesión sale de `APP_TIMEZONE`.

    Dos cosas de la migración. **Apaga el trigger de inmutabilidad de
    `documento_versiones`**, porque el instante era el mismo y lo que estaba
    mal era cómo se había guardado. Sólo puede hacerlo el dueño de la tabla, que
    desde el punto 32 es el migrador y no la aplicación. Y **su `down()` se
    comprobó a mano** —`migrate:rollback` y vuelta sobre datos reales de
    desarrollo, con el trigger encendido al terminar—, porque la suite sigue
    sin ejecutar rollbacks.

    Una frase de `documentos.md` decía «la aplicación trabaja en UTC». No era
    verdad, y es justo la confusión que produjo el fallo.

34. ✅ Copias cifradas con restauración probada. **No había ninguna copia de
    nada**: ni un volcado, ni un espejo de los ficheros, ni un comando. Y el § 6
    no pide copias, pide «restauración probada». Así que son dos comandos y los
    dos van programados: `copias:hacer` cada noche y `copias:verificar` cada
    semana. El segundo restaura en una base aparte, compara tabla a tabla y
    fichero a fichero, y sale con error si algo no cuadra.

    **Sin ninguna dependencia nueva**, y eso decidió medio diseño. El plan dejaba
    abierto `spatie/laravel-backup`, pero `pg_dump` 17 ya estaba en la imagen
    para `pg_isready`, y libsodium viene con PHP. Lo que quedó es poco y está
    todo a la vista: una instantánea exportada que comparten los recuentos y el
    volcado, un *secretstream* que detecta una copia truncada, un rol
    `statera_copias` que lee todo y no escribe nada, y un espejo que nunca borra.

    El recorrido sobre la base de desarrollo dio 113 tablas restauradas sin una
    fila de diferencia y las dos versiones emitidas con su huella. Salió también
    lo que no se comprueba: dos ficheros de adjuntos sin fila, el logo y las
    fotos, que se copian pero no tienen huella con la que contrastarlos. Va
    declarado en `copias.md`, junto con lo demás que no hace.

35. ✅ Cifrado en reposo. **Dos mitades, y cada una en su capa.** Los ficheros
    los cifra el almacén: SSE con el KMS interno de MinIO en desarrollo y el del
    proveedor en producción, pedido en cada subida y puesto también por defecto
    en los cuatro buckets. Los datos personales de `personas` —NIF, teléfonos,
    domicilio y fecha de nacimiento— los cifra la aplicación, con los casts de
    Eloquent. El volumen de la base se deja a la infraestructura, y va declarado.

    Lo que costó no fue cifrar, fue **lo que se rompe al cifrar**. El índice
    único del NIF dejaba de ver dos iguales, porque cifrado da un texto
    distinto cada vez, y pasó a una huella HMAC con su propia clave. La fecha
    cifrada se daba por cambiada en cada guardado, lo que llenaba la traza de
    eventos falsos, y su cast compara descifrado. De paso, la traza deja de
    guardar esos datos en claro, aunque lo anterior sigue ahí: es el punto 36.

    **Y el hueco H mordió por primera vez.** El `down()` de la migración reventaba
    con una persona sin ningún dato personal: un `UPDATE` sin columnas. La suite
    no ejecuta rollbacks, así que salió al hacer el viaje de ida y vuelta a mano
    sobre la base de desarrollo. El error que se veía no era ése, sino el
    `set_config()` del `finally` de `comoMantenimiento()` sobre una transacción
    ya abortada: el síntoma apuntaba al contexto y la causa estaba en una fila.

36. ✅ Retención y supresión de los datos de personas. **Cierra el tramo**, y con
    él los siete requisitos no funcionales del § 6 tienen dónde vivir. Era el
    único que exigía decidir algo antes de escribir una línea, porque choca de
    frente con el invariante 8: la traza no se modifica, y el derecho de
    supresión dice que los datos de una persona tienen que dejar de estar.

    **César eligió depurar la traza, y no declararlo como limitación.** Se hizo
    en dos piezas. La primera es una **puerta estrecha**: una función
    `SECURITY DEFINER` que sólo quita claves de una lista fija, sólo de una
    persona y de sus adjuntos y sólo en la organización de la sesión. La
    segunda, una **migración única** que cifra lo que la traza guardó en claro
    antes del punto 35. Cifrarlo y no borrarlo fue un ajuste propuesto a la
    decisión: la traza sigue diciendo qué cambió, tiene un solo formato y nadie
    lo lee sin la clave. El borrado de verdad lo hace la supresión, persona a
    persona.

    Lo demás sigue la línea de siempre. **Seudonimizar y no borrar**, porque de
    la persona cuelga histórico del SGSI. **El plazo lo declara la
    organización y no tiene valor por defecto**, porque inventarlo sería la
    herramienta decidiendo cuándo se borra el expediente de alguien. Y **dos
    condiciones que impiden y no avisan**: baja pasada y ningún nombramiento
    vigente.

    **La lección, esta vez en la verificación.** El primer rojo del test de la
    traza no probaba nada: la edición que quitaba la llamada a la función dejó
    un error de sintaxis, y el test falló con cero aserciones. Un rojo sólo
    prueba algo si falla por la aserción que se quería ver romper. El recorrido
    en el navegador encontró además que Vite llevaba días sin publicar su
    puerto. No era del punto: el contenedor había perdido la publicación tras
    varios reinicios por cambios en `.env`, y se resolvió recreándolo.

37. ✅ Planificación de cambios del SGSI (cláusula 6.3). **El primero de los huecos
    de `PRODUCT.md`**, y va antes que la 7.4 porque es más pequeño y porque la
    9.3.3 ya producía «necesidades de cambio» sin tener dónde dejarlas.

    **César acotó el alcance a los cambios del propio SGSI.** Los técnicos
    —A.8.32, `op.exp.5`— se cuentan por decenas y ahogarían los pocos que la 6.3
    quiere ver planificados; cuando haga falta, serán otro registro. Por eso el
    permiso se llama `cambios_sgsi.*` y deja libre `cambios.*`.

    No hubo que inventar nada: es el esqueleto de mejoras con la firma de
    objetivos, y las dos decisiones propias del módulo son que **revisar es un
    estado aparte que exige decir si el cambio sirvió**, y que **un implantado
    sin revisar no es rojo**, porque la norma no fija plazo para esa
    comprobación y pintarlo sería inventarse una obligación.

    De paso, el filtro por atributos de la ISO 27002 (sin número propio) destapó
    que A.8.24 llevaba una capacidad operativa que no existe en la norma. La
    encontró la validación nueva del vocabulario, no una revisión.

38. ✅ Comunicación (cláusula 7.4). **Cierra los dos huecos de cláusula** que
    anotaba `PRODUCT.md`. Dos pantallas: el plan —qué, cuándo, a quién, quién y
    cómo— y lo comunicado.

    **César decidió registrar también lo recibido**, y fue la decisión que más
    dio de sí: con las quejas, sugerencias y encuestas dentro, la 9.3.2 e) del
    acta deja de aportarse fuera. Las actas ya aprobadas no cambian —su
    instantánea no lleva la clave nueva y se siguen pintando como entonces—, y
    eso hubo que hacerlo en los tres sitios que pintan la entrada.

    Lo demás es el patrón de los compromisos del § 4.16 copiado entero —meses y
    `Cadencia`, próxima fecha derivada, `cubre_hasta` congelado, nada con fecha
    futura— más una `Fuente` propia. Lo único que mordió fue PostgreSQL
    recortando a 63 caracteres los nombres de la pivote, que dejó la clave
    foránea y el índice único llamándose igual.

39. ✅ Perfiles CCN-STIC 890, **la pantalla y no los datos**. El modelo, el
    importador y el paso 4 del motor ya estaban; faltaba poder asignarlos. César
    decidió no cargar ninguna medida hasta tener la guía contrastada, por el mismo
    motivo que se contrastó el Anexo II con el BOE.

    El perfil entra por la pantalla de valoración y no por la ficha del sistema,
    porque es la otra entrada del motor: el diff que se previsualiza tiene que
    contar las dos. Y la decisión que define el punto es una guarda: **un perfil
    sin medidas no se asigna**, porque el paso 4 deja fuera lo que no está en él
    y el sistema se quedaría sin nada exigible, en silencio. Está en el dominio
    y no sólo en el desplegable.

40. ✅ Agrupar por atributo de la ISO 27002, lo que le faltaba al § 4.4 después
    del filtro. Una vista propia y no un modo de la tabla, porque `DataTable` no
    tiene sub-filas y agrupar en servidor es otra consulta: una fila por valor,
    con la misma barra por estado que el panel, enlazada al filtro que ya
    existía. Lo único que hay que saber al leerla es que las filas no suman el
    total, y la pantalla lo dice.

    La lección fue de método: lancé los tests del módulo con la suite completa
    corriendo y salieron interbloqueos, exactamente lo que la memoria del
    proyecto avisa. Se repitió la suite sola.

41. ✅ La plataforma: quien administra Statera y el alta de una organización.
    **Abre el tramo «vendible»**, y saca del «fuera de alcance» el panel de
    superadministración. Hasta aquí un tenant sólo nacía en el seeder, y su
    primer responsable no tenía por dónde entrar: invitar exige que ya haya
    alguien dentro que invite.

    César decidió que el administrador viviera en `users` con una marca, que
    viera la ficha comercial y nada del SGSI del cliente, y que el cobro se
    modelara sin pasarela. De ahí sale el resto:
    - `SoloPlataforma` en vez de `can:`, porque sin organización no hay «team»
      de spatie.
    - Una traza propia e inmutable, porque la del tenant exige un dueño.
    - `AltaOrganizacion` como única receta. Entra en la fila recién creada con
      `paraOrganizacion()` y no toca `comoMantenimiento()`.

    Lo que mordió fue `ConsultasDeUsuarioAcotadasTest`: un `User::query()` que
    crea una cuenta **sin** organización también tiene que decirlo en las cinco
    líneas siguientes. Se resolvió con un `forceFill` explícito, que de paso deja
    la marca fuera de la asignación masiva.
