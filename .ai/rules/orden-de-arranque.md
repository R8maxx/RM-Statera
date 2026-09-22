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
    (§ 4.16), del que hoy existen tres `Fuente` de las once que enumera la
    especificación, y los otros dos tercios del flujo de conformidad (§ 4.17).

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
