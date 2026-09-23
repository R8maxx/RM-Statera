---
paths:
  - app/Domain/Contexto/**
  - resources/js/pages/contexto/**
  - resources/js/pages/partes-interesadas/**
  - resources/js/components/contexto/**
---

# El contexto de la organización

§ 4.1, y las cláusulas 4.1 a 4.3 de ISO. Era el único de los diecinueve módulos **sin fase asignada**
y el que bloqueaba al § 4.15: la cláusula 9.3 pide «cambios de contexto» como entrada obligatoria de
la revisión por la dirección, y hasta aquí no había de dónde sacarla. Vive en `app/Domain/Contexto/`,
con siete tablas —`analisis_contexto`, `cuestiones_contexto`, `partes_interesadas`,
`requisitos_interesados` y tres pivotes—.

**La 4.3 ya estaba hecha y no se ha tocado.** `sistemas.alcance_declarado` y
`sistemas.exclusiones_justificadas` existen desde la primera migración, están en el formulario y ya se
imprimen en la portada de los cuatro documentos. Lo que este módulo les añade no es una tabla: es
**histórico**, congelándolas en la instantánea del análisis. Y por eso la pantalla las **enseña y no
las edita**: repetir el campo sería el mismo dato en dos sitios que pueden discrepar.

**Choca de nombre con `ContextoOrganizacion`**, que es la pieza de multi-tenancy, y es el mismo caso
que obligó a separar `Domain\Traza` de `Domain\Auditoria`. Aquí no se renombra nada: aquella clase
nunca se nombra `Contexto` a secas, vive en `Domain\Organizacion`, y mover la pieza más sensible del
aislamiento por una colisión conceptual sale mucho más caro que anotarla.

### El análisis es lo versionado; las cuestiones viven

Es la decisión que da forma al resto. Copiar el DAFO entero en cada revisión —el patrón literal de
`riesgo_valoraciones`— **rompería los tres vínculos** que dan sentido al módulo: un riesgo apuntaría a
la cuestión de marzo y en octubre apuntaría a una fila muerta. Así que las cuestiones y las partes
**viven**, con `analisis_alta_id` y `analisis_baja_id`, y lo que se congela es la **instantánea** del
análisis al aprobarse.

Sin ella la fila mentiría en cuanto alguien editara una cuestión, que es el mismo razonamiento ya
escrito para `riesgo_valoraciones.salvaguardas` y para `documento_versiones.instantanea`. Y es lo que
le da histórico al alcance sin migrar nada.

**`vigente` no es columna: es `estado = 'aprobado'`**, con índice único parcial, como el borrador de
un documento. Una bandera aparte sería el mismo dato dos veces.

**El borrador se estrena solo** al registrar la primera cuestión. Obligar a «abrir un análisis» antes
de poder escribir una debilidad pone un trámite delante del primer minuto de uso, y lo que la gente
hace entonces es apuntar el DAFO en otro sitio. Aprobar sí es explícito: es lo que congela.

**Y el borrador no se descarta**, a diferencia del de un documento. No es una propuesta que se pueda
tumbar: es donde se trabaja, y lo que ya está aprobado sigue vigente mientras tanto.

**El cuarto trigger de inmutabilidad** del producto, hermano de los de `documento_versiones`,
`riesgo_valoraciones` y `auditorias`. Una sola puerta —`aprobado → obsoleto`—, que pone el sistema al
aprobar el siguiente y nunca una persona; sin ella un contexto aprobado no podría revisarse nunca.
Se compara el registro entero con `estado` neutralizado, y `updated_at` **sólo** cuando el estado
cambia, por lo mismo que en `documento_versiones`.

### El cambio climático es una pregunta, no una casilla

La enmienda 1:2024 no pide apuntar cuestiones climáticas: pide **determinar si** el cambio climático
es pertinente. Con una casilla suelta, «no lo hemos mirado» y «lo hemos mirado y no aplica» serían
indistinguibles — que es justo lo que el auditor pregunta. De ahí `clima_pertinente` +
`clima_justificacion` y **un `CHECK` que impide aprobar sin contestar**, en la misma línea que
`rechazado` exige motivo. Las cuestiones llevan además `es_climatica`, que es lo que permite enseñar
cuáles son en vez de sólo afirmarlo.

### Lo derivado y lo guardado

**El ámbito y el signo de una cuestión NO son columnas**: una fortaleza es interna y favorable por
definición del DAFO, y guardarlo sería la misma información en tres sitios que pueden discrepar.
Reclasificar una cuestión es cambiar un campo y no acordarse de tres.

**En una parte interesada el ámbito SÍ se guarda**, y esa asimetría es real: un empleado es interno y
un regulador externo, pero un socio o un accionista son lo que cada organización decida. Deducirlo
acertaría en seis casos de ocho, que es la peor cifra posible — suficiente para que parezca que
funciona. `TipoParteInteresada::ambitoSugerido()` lo propone y el formulario lo rellena; no lo impone.

**`Ambito` es un solo enum para las dos cláusulas.** La 4.1 habla de cuestiones internas y externas y
la 4.2 de partes que también lo son: es literalmente el mismo eje, y con dos enums nadie podría
preguntar «¿qué tenemos de fuera?» sin cruzar dos vocabularios que dicen lo mismo.

**La otra clasificación de una cuestión se llama `materia` y no `ambito`**, precisamente porque
`ambito` ya es el interno/externo. Dos columnas con el mismo nombre y dos ejes distintos es cómo se
acaba filtrando por lo que no era.

### Un tono por cuadrante, en familia propia: `--dafo-*`

**Tercera familia semántica**, junto a `--estado-*` y `--tipo-*`, declarada en `DESIGN.md` §3. Un
estado dice *cómo va* algo, un tipo dice *qué es* y un cuadrante dice *dónde cae*; las tres preguntas
conviven en el panel y compartir paleta haría que una fortaleza se leyera como «implantado».

**Lo que la separa no es el hue, es la profundidad**: L 0.40 y croma 0.16, frente a L 0.52 / 0.13 de
los estados y L 0.47 / 0.10 de los tipos. No es estética: **los nueve `--tipo-*` ocupan ya la rueda de
hue entera**, y una familia que sólo se moviera de tono no se leería como familia. Se midió antes de
elegir: con los tipos dentro del suelo, ningún cuádruple de hues llegaba a ΔE 6 — bajar la
luminosidad es lo que abre el hueco.

**Los hues van por pares y no sueltos**, que es lo que hace legible un 2×2: verde 175 y azul 255 lo
favorable —dentro y fuera—, ocre 55 y magenta 340 lo adverso. El color dice las dos cosas a la vez:
de qué mitad es, por la temperatura, y qué cuadrante exacto, por el tono.

**Ninguno entra en el rojo**, que conserva sus tres dueños. Una debilidad apuntada en un análisis no
va mal: es algo que la organización ha sabido ver y escribir, y pintarla de alarma enseña a no
escribirla.

**Y el icono sigue sin ser opcional.** ΔE 10.7 en el peor par de la familia —el mejor de las tres—,
pero contra `destructive` la peor pareja queda en **2.7** con protanopía: un verde oscuro y un rojo
colapsan sobre el mismo eje. Es la convivencia que la paleta ya tenía —`destructive ↔ tipo-soportes`
está en 3.6— y la cargan el icono y el texto. Lo mide `PaletaTest`, que lee `app.css` y no la tabla
del documento.

`NaturalezaRequisito` usa la familia **ordinal** (`alta`/`media`/`basica`), que es lo que es: un
requisito legal obliga más que uno contractual y ése más que una expectativa. Es la solución que
`DESIGN.md` ya documentó para la categoría del ENS, donde el rojo mentía.

**`MatrizDafo.vue` es rejilla CSS y no SVG**, calcada de `MatrizRiesgo.vue` y por lo mismo: tiene que
caber a 375 px sin scroll horizontal. Los rótulos de los ejes viven **dentro** de la misma rejilla, y
cada cuadrante los repite escritos para que al apilarse por debajo de `sm` no se pierdan.

### Los tres vínculos, que son lo que paga el módulo

- **Cuestión ↔ riesgo**, N:M. Es lo que la cláusula 6.1.1 pide cuando dice que la apreciación de
  riesgos se hace **considerando** las cuestiones del 4.1. **Se vincula, no se crea**: deducir un
  riesgo de una amenaza produciría riesgos sin probabilidad, sin impacto y sin propietario, que es lo
  que ISO 6.1.3 f) no admite.
- **Cuestión ↔ tarea**, con `OrigenTarea::Contexto` —séptimo origen, y el segundo que no está en
  § 4.7—. El origen **se pone y no se pregunta**. **Sin doble vínculo**, a diferencia de la acción
  correctiva: allí hacía falta porque el plan de adecuación imprimía «sin trabajo planificado» sobre
  una medida que sí lo tenía, y aquí no hay medida detrás **por construcción**. La consecuencia,
  declarada: el coste de esa tarea no entra en el presupuesto del plan, porque ese plan presupuesta
  medidas.
- **Requisito de una parte ↔ implantación**, que apunta a `implantaciones` y no a `requisitos` por lo
  mismo que las salvaguardas. Es el que paga: a partir de él la SoA imprime **«exigido por el
  regulador X»** como justificación de inclusión, al lado de «Anexo A» y de «tratamiento del riesgo
  R-014». ISO 6.1.3 d) admite las tres. **Sólo lo que obliga** —legal y contractual—: una expectativa
  es razón para tener en cuenta un control, no para declararlo aplicable.

### El documento, y la frontera que rompe

`TipoDocumento::AnalisisContexto` es el **cuarto calculado** y el primero de **ámbito organizativo**.
Hasta él, «calculado» y «exige sistema» eran lo mismo por accidente, y ese accidente era el motor de
`documentos_sistema_check`. Ahora lo es **`TipoDocumento::exigeSistema()`**, y `esRedactado()` se
queda diciendo lo único que dice: quién escribe el contenido. Es la **tercera** reescritura de ese
`CHECK`, y la migración anterior pedía por escrito que no se tocara sin motivo — éste es el motivo.

`AnalisisDelContexto` implementa `GeneradorDocumento` **directamente**, como `DocumentoRedactado`: no
tiene tabla larga de requisitos, ni agrupado por nodo padre, ni correspondencias cruzadas. Lo común
—portada, historial y limitaciones— sale de `ArmaContenidoComun`.

**Se construye desde la instantánea y nunca de una consulta nueva.** Es el fallo más caro que este
módulo podía tener, el mismo que ya está documentado para el `.docx`: las cuestiones se siguen
editando entre revisiones, así que consultar las tablas enseñaría el DAFO de hoy bajo la fecha de la
aprobación de hace un año. Sin análisis aprobado no hay documento, y se dice.

Cuatro fuentes nuevas de cuerpo —`declaracion_climatica`, `dafo_cuadrantes`,
`tabla_partes_interesadas` y `alcance_sistemas`—, y **el clima va primero**: es una respuesta de una
línea, es lo que la enmienda añadió y es de lo primero que un auditor busca desde 2024; enterrada
detrás de dos tablas se lee como que no está. En papel el DAFO son **cuatro tablas y no una rejilla**:
un cuadrante con doce cuestiones partiría el 2×2 a mitad de página.

**Lo que el módulo declara que no hace**: no comprueba que el análisis esté completo —ni que toda
parte interesada relevante esté registrada, ni que las cuestiones cubran todos los ámbitos, ni que
cada amenaza acabe en un riesgo—; no comprueba que un requisito de una parte exista de verdad ni que
la medida vinculada lo satisfaga; no contrasta los alcances de los sistemas entre sí; y **no hay
pantalla de diff** entre dos instantáneas —la comparativa dice qué entró y qué salió, no qué cambió
por dentro de una cuestión que sigue en las dos—.

**Y no entra una `Fuente` propia en el calendario.** Lo que vence no es el análisis sino la revisión
de su documento aprobado, que `Fuente::Documento` ya recoge desde el § 4.5; una `Fuente` propia
pintaría dos chips el mismo día para un solo compromiso. Es el argumento exacto por el que se quedó
fuera la `fecha_prevista` de una no conformidad, que vence el mismo día que sus acciones correctivas.

> **Sin ordinal.** Decía «una cuarta `Fuente`» y con el § 4.16 son siete, así que habría envejecido
> igual que las otras dos veces que este repositorio ha pagado lo mismo. Y el ejemplo que remataba la
> frase —la `fecha_objetivo` del plan de adecuación— **sí entró**, como `Fuente::Implantacion`: una
> medida pendiente no tiene por qué tener tarea detrás, así que ahí no había chip que duplicar.

**Ninguna limitación existente pasó a ser falsa con este módulo dentro** —comprobado: ni «contexto»,
ni «partes interesadas», ni «4.1» aparecían en ninguna—. Es la primera vez en cinco módulos. La única
que sí se reescribió es la de la justificación de inclusión de la SoA, porque enumeraba los orígenes
posibles y se quedó corta en cuanto la columna empezó a imprimir uno más.
