---
paths:
  - app/Domain/Obligacion/**
  - app/Domain/Aviso/**
  - resources/js/pages/obligaciones/**
  - resources/js/pages/calendario/**
  - resources/js/components/obligacion/**
  - resources/js/components/calendario/**
---

# El calendario de obligaciones (§ 4.16)

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **Los avisos son un resumen diario por organización, y de momento sólo por correo.** `avisos:enviar`
  recorre las organizaciones con `ContextoOrganizacion::paraOrganizacion()`, una cada vez: un comando
  programado no tiene petición ni usuario, así que sin contexto el scope no devuelve nada y RLS
  deniega por defecto — **no falla, no ve nada**, y un aviso que no salta es indistinguible de no
  tener nada que avisar. Nada de `withoutGlobalScopes()` ni de `comoMantenimiento()`: esto no cruza
  organizaciones. La notificación lleva **escalares y ningún modelo**, por lo mismo que los jobs.

  **Un resumen, no una alerta por evidencia**: dice cómo está la cosa hoy, así que repetirlo mañana no
  es spam y no hace falta una tabla de «ya avisado» para evitar duplicados. Si no hay nada que decir no
  se envía: un correo diario que casi siempre dice «todo en orden» se filtra a una carpeta en dos
  semanas y deja de verse el día que importa.

  El resumen lleva **una lista por fuente y dos mitades por lista**: una evidencia caducada es una
  prueba que ya no prueba, una tarea vencida es trabajo que no se hizo y una revisión documental vencida
  es un papel que nadie ha vuelto a mirar desde que se firmó. Se arreglan de formas distintas y las
  lleva gente distinta. Los títulos y los verbos los pone `Fuente`, no el correo: dos literales escritos
  a mano por fuente, en una lista que crece con cada módulo, es cómo se olvida uno.

  **No hay tabla de avisos y es a propósito.** Una bandeja en la interfaz necesitaría tabla propia con
  `organizacion_id` y RLS; la tabla `notifications` de Laravel no lleva organización, que es
  exactamente el motivo por el que se retiró `spatie/laravel-medialibrary`. Y `ResumenVencimientos` usa
  **los mismos scopes que cuenta el panel** —`Evidencia::caducadas()`, `Tarea::vencidas()`,
  `Persona::formacionCaducada()`, `Compromiso::vencidos()`—: con la condición escrita dos veces, el día
  que cambie una el correo dirá 12 y la pantalla enseñará 9.

- **`lang/es.json` existe por el correo.** Las cadenas de la plantilla de notificaciones de Laravel
  —«If you're having trouble clicking…», «All rights reserved.»— van por `__()` y salían en inglés en
  el primer correo que manda el producto, con todo lo demás en español.

## Lo que hay que saber antes de tocarlo

### El módulo son dos mitades y ninguna sustituye a la otra

La especificación enumera **once** cosas periódicas en su § 4.16, y se resuelven de dos formas
distintas:

**(a) Lo que se deriva de datos que ya existen.** Una `Fuente` por registro, y ninguna necesitó columna
nueva:

| Fuente | De dónde sale la fecha |
|---|---|
| `Tarea` | `tareas.fecha_limite` |
| `Evidencia` | `evidencias.fecha_caducidad` |
| `Documento` | `documento_versiones.fecha_proxima_revision`, congelada al firmar |
| `Formacion` | la última asistencia + `Persona::MESES_DE_VIGENCIA_FORMATIVA`. **La fila es la persona**, no la sesión: lo que vence es que a alguien le toca renovarla |
| `Indicador` | el fin del último periodo cerrado sin medición, por `Periodicidad::periodoAnteriorA()` |
| `Implantacion` | `implantaciones.fecha_objetivo` de una medida pendiente |
| `Obligacion` | derivada de `compromisos` (ver abajo) |
| `PruebaContinuidad` | `pruebas_continuidad.fecha_prevista` de una prueba **planificada** (llegó con el § 4.11) |
| `Bia` | `bia_servicios.fecha_revision` de un BIA **aprobado**: un borrador no tiene revisión comprometida (§ 4.11) |

**(b) Lo que no sale de ningún registro**, y por eso hay tres tablas: el informe INES, la renovación de
conformidad del ENS —**bienal**, que no coincide con el ciclo de tres años de ISO—, la auditoría de
seguimiento, la auditoría interna, la reevaluación de riesgos y la revisión por la dirección. Lo que
vence ahí no es una fila que exista: **es una fila que debería existir y no está**, y una `Fuente` más
no lo resuelve porque no hay nada que consultar.

### Las obligaciones son UNA fuente, no seis

`Fuente` tiene menos casos que cosas periódicas enumera la especificación, y es deliberado: las
obligaciones periódicas son **filas de un catálogo**, no casos de un enum (invariante 3). Una
organización que quiera añadir «reevaluación de proveedores» lo hace sin desplegar, `Fuente::url()`
puede seguir siendo un `match`, y el filtro de la pantalla no crece con cada fila del catálogo. Un caso
nuevo de `Fuente` es para un **registro** nuevo del que derivar fechas, como los dos que trajo el
§ 4.11. **Sin recuentos en esta prosa**: la cifra envejeció dos veces antes de quitarla.

### Tres tablas, y la de arriba no lleva `organizacion_id`

- **`obligaciones`** — el catálogo. Global y compartido (invariante 2), se carga desde
  `catalogo/obligaciones.yaml` con el importador idempotente, empareja por `codigo`, y **no borra**: lo
  que desaparece de una revisión se marca, porque puede haber compromisos colgando. `RlsDeclaradaTest`
  no la reclama porque interroga por la columna que no tiene, igual que con `marcos` y `amenazas`.
- **`compromisos`** — lo que la organización ha asumido. Con las tres capas de aislamiento.
- **`compromiso_cumplimientos`** — el histórico (invariante 7).

### La periodicidad es un entero de meses y no un enum

Es lo que ya hacen `documentos.periodicidad_revision_meses` y
`metodologias_riesgo.periodicidad_revision_meses`. El producto tiene **dos** enums de periodicidad
—`Metrica\Enums\Periodicidad` y `Evidencia\Enums\PeriodicidadRenovacion`— y ninguno sabe decir
«bienal», que es justo la cadencia de la conformidad del ENS. Un tercero para meter un caso más es cómo
se acaba con tres listas que casi coinciden. El nombre legible lo pone `Cadencia`, que además resuelve
la aritmética de meses en un solo sitio —sumar un mes al 31 de enero no da el 31 de febrero—.

### `titulo` y `periodicidad_meses` se copian al asumir, no se leen por join

Lo que la organización asumió en 2026 no puede repintarse porque el catálogo cambie la redacción en
2028. Mismo criterio que `mediciones.objetivo` y que la instantánea de una versión de documento.
`obligacion_id` se queda para saber de qué salió, y es lo que permite avisar de a cuántos compromisos
afecta retirar una fila del catálogo.

### La próxima fecha se deriva y se escribe una sola vez

`Compromiso::PROXIMA` es el último `cubre_hasta` de los cumplimientos y, sin ninguno, `computa_desde`
más la cadencia. La leen los tres scopes y `proximaFecha()`. Guardarla en columna serían dos sitios que
se desincronizan el día que alguien borre un cumplimiento.

Y **`cubre_hasta` se congela al registrar**, con la cadencia vigente entonces: subir la periodicidad de
anual a semestral en marzo no puede repintar como fuera de plazo un cumplimiento de enero que en enero
estaba al día. Hay un test que lo fija cambiando la cadencia entre dos cumplimientos.

### El índice único es parcial, y sin eso sólo cabría un compromiso propio

`compromisos_unicos` va con **`NULLS NOT DISTINCT`** —sin él, PostgreSQL trata dos nulos como
distintos y se podría asumir tres veces el informe INES, que no cuelga de ningún sistema— **y con
`WHERE obligacion_id IS NOT NULL`**. Lo segundo es lo que no se ve venir: con los nulos tratados como
iguales y sin el parcial, una organización podría tener **un solo compromiso propio**, porque el
segundo chocaría con el primero. Y el compromiso propio es justamente el caso en el que varios son
legítimos: no hay fila de catálogo que duplicar, que es lo único que ese índice existe para evitar. Lo
encontró el test de coherencia con el panel al sembrar dos.

### Un cumplimiento apunta a un registro con claves foráneas explícitas, no con un `morphTo`

El morph mete nombres de clase PHP dentro de la base —el motivo exacto por el que se descartó
`spatie/laravel-medialibrary`— y renombrar un modelo rompería filas históricas en silencio. Son
`auditoria_id`, `revision_direccion_id`, `documento_id` y, desde el § 4.11, `prueba_continuidad_id`,
con un `CHECK (num_nonnulls(...) <= 1)` —la forma que escala: ampliarlo fue añadir una columna a la
lista, sin reescribir la condición—, y las lee un único value object, `Referencia`, para que el
dominio de obligaciones **importe un enum y no un módulo por referencia**.

`evidencia_id` queda **fuera** de ese `CHECK` porque es otro eje: es la **prueba** —el PDF del INES
presentado, el certificado— y convive con el registro. Precedente literal:
`acciones_formativas.evidencia_id`.

### Las obligaciones no se asumen solas

`ObligacionesAplicables` **propone**; una persona acepta. Un observer que las sembrara al crear la
organización pondría filas en las bases de test de toda la suite, y sobre todo le pondría deberes a la
organización en su nombre: el invariante 4 dice que la *aplicabilidad* se deriva, y esto es lo otro —una
decisión, y las decisiones las firma alguien—.

Cuatro filtros: el marco del sistema, `Organizacion::leAplicaElEns()`, el requisito y
`categoria_minima` contra `Sistema::categoria()`, que **se deriva** de las cinco dimensiones. Se compara
contra la categoría **más alta** de los sistemas: una obligación que muerde a partir de media muerde en
cuanto un solo sistema llegue ahí.

**El cuarto, `obligaciones.requisito_id`, llegó con el § 4.11**, y es para lo que no depende de la
categoría. Las pruebas de continuidad no se proponen por ser media o alta: se proponen si `op.cont.3`
está entre lo exigible de algún sistema, y eso lo decide la **Disponibilidad**, que puede llegar a alto
en un sistema que en conjunto siga en básica. Filtrar por categoría exigiría de menos, y copiar esa
regla en el YAML sería la aplicabilidad calculada dos veces (invariante 4). Se compara contra
`Implantacion::aplicables()`, que es donde el motor ya lo decidió. Nullable y `nullOnDelete`: casi
ninguna obligación cuelga de un requisito —la revisión por la dirección no tiene uno— y el catálogo
normativo no se borra, se marca.

## El calendario

- **Enseña vencimientos, no tareas.** Una tarea que vence y una evidencia que caduca son la misma
  pregunta para quien mira el mes. Por eso `CalendarioVencimientos` vive en `app/Domain/Aviso/` y no en
  `Tarea/`, y es además **el único sitio donde se decide qué es un vencimiento**: el resumen diario que
  sale por correo se apoya en él, porque si cada uno consultara por su cuenta acabarían discrepando y el
  que se mira menos es el que se queda mal.

- **`entre()` recorre `Fuente::cases()` con un `match` exhaustivo**, no una lista de bloques `if`. Un
  caso nuevo que no se declare revienta con `UnhandledMatchError`; con bloques `if`, la fuente nueva
  sencillamente no salía.

- **Cada fuente delega en el scope de su módulo dueño** —`Tarea::vencidas()`,
  `Persona::formacionCaducada()`, `Compromiso::vencidos()`—. Con la condición escrita dos veces, el
  correo dice 12 y la pantalla enseña 9. Lo fija `CoherenciaConElPanelTest`.

- **La guarda por permiso vive en `FiltrosVencimiento`, no en `CalendarioVencimientos`.** Un `Recurso`
  describe y no autoriza, y esto es lo mismo un nivel más abajo. La rejilla enseña registros de muchos
  módulos, así que `calendario.ver` por sí solo sería una puerta lateral a todos: `Fuente::permiso()`
  decide qué se consulta y qué opciones se ofrecen.

- **Con un filtro de responsable puesto, la formación se excluye** en vez de quedarse intacta. No tiene
  responsable —la persona **es** la fila— y dejarla pasar sería el filtro mintiendo, que es literalmente
  el fallo que `FiltrosVencimiento` declara inaceptable en su cabecera.

- **La rejilla del mes se calcula en el servidor (`RejillaMes`), no en el navegador.** Aquí hay con qué
  probarla —meses de 28, 30 y 31 días, bisiestos, meses que empiezan en domingo, cambios de año— y en
  `resources/js` no hay runner de tests. **Seis semanas siempre**, aunque el mes quepa en cinco: una
  rejilla que cambia de alto al pasar de mes hace saltar la página bajo el cursor. Y **un mes que no se
  entiende es el de hoy**: un 500 en una URL que alguien comparte es peor que enseñar otro mes.

- **El color dice cómo va y el icono dice qué es.** `Vencimiento` lleva dos pares de campos y no uno:
  `tono` es distancia temporal y lo lee el **correo diario**; `estadoTono`/`estadoEtiqueta` son el estado
  y los lee el calendario. **Lo vencido gana siempre** y es el único rojo de la pantalla; hay un test que
  recorre todos los casos de `Fuente` comprobando que ninguno en plazo se lo gasta. Y el estado viaja **también en
  texto**, porque § 11 no deja que dependa del color.

- **No entra ninguna familia de color `--fuente-*`, y no va a entrar.** `DESIGN.md` § 3 tiene la rueda de
  hue agotada —nueve tipos de activo, cuatro del DAFO, seis de estado— y la peor pareja tipo↔tipo ya está
  en ΔE 5.2, por debajo del suelo. La fuente la identifica **el icono**, que es el de su módulo en
  `lib/navegacion.ts` para que quien lo aprende del sidebar lo reconozca aquí.

- **`FiltroFuentes` es filtro y leyenda a la vez.** Con tres fuentes el filtro podía vivir dentro del
  desplegable de `BarraFiltros`; con las que vinieron después, el control más importante de la pantalla no puede estar a dos
  clics detrás de un embudo. Y como el icono es el único canal que identifica la fuente, esa misma fila
  es su clave — sin ocupar sitio extra, porque ya tenía que estar. Por eso `Opcion` lleva `icono` desde
  este módulo.

- **El tope de tres por día abre un `PanelDia`, no un párrafo.** Era un `<p>` muerto —«y 4 más», sin
  decir qué son y sin llevar a ninguna parte—, que además no era alcanzable con el tabulador: lo que el
  tope escondía **no tenía ninguna otra puerta**. Mismo callejón sin salida que el panel cerró para sus
  cifras.

- **Los días se distinguen con cuatro fondos sólidos**, no con alfa. Antes eran `bg-muted/40` y
  `bg-muted/20` sobre `bg-card` —dos transparencias casi idénticas y las dos en el mismo atributo, así
  que decidía el orden en que Tailwind emite las clases—.

## El correo diario

- **`Vencimientos` es un mapa por fuente, no una propiedad por grupo.** Eran seis propiedades fijas
  —`evidenciasCaducadas`, `tareasVencidas`…— y cada fuente nueva habría añadido dos, con `pasados()` y
  `total()` sumando a mano. Ése es el fallo caro del módulo y es silencioso: olvidar una fuente no rompe
  nada, sólo hace que **el asunto diga «3 pasadas de fecha» habiendo 9**. Con el mapa, sumar es recorrer,
  y hay un test que siembra un vencido de cada caso de `Fuente` y comprueba que salen todos.

- **Los títulos y los verbos de cada bloque los pone `Fuente`**, no el correo. Eran catorce literales
  escritos a mano y catorce literales es cómo se olvida uno.

- **El indicador no tiene mitad «próxima».** Lo que se pinta es el periodo que ya cerró sin medición, y
  eso es siempre pasado. Avisar de que el trimestre en curso va a cerrar sería inventar un plazo al que
  nadie se comprometió — lo mismo que el producto se niega a hacer con el CCN-CERT y con el residual.

- **La ventana del correo es de 30 días también para las obligaciones**, aunque su scope use 90. Lo que
  el resumen contesta es «qué hay para los próximos treinta días»; el aviso largo vive en
  `/obligaciones` y en el panel.

## La pantalla de obligaciones

- **Dos superficies y no una con conmutador.** `/calendario` enseña fuentes de muchos módulos;
  `/obligaciones` enseña una sola cosa con su histórico. Un conmutador diría que son dos formas de ver el
  mismo dato. Precedente escrito en `lib/navegacion.ts`: Personas, Puestos y Formación van separadas.

- **La columna que se mira es «Próximo vencimiento»**, y por eso es un badge con «vence en 12 días» y no
  una fecha suelta: una fecha obliga a compararla con hoy fila a fila. Mismo argumento que «Plazo» en
  tareas.

- **La ventana de «por vencer» es de 90 días y no de 30.** No es un descuido: contratar a quien audita o
  abrir la ventana del INES no se hace en un mes, y un aviso que llega cuando ya no da tiempo a
  reaccionar no sirve.

- **El tipo va en tono `marco`**, el chip neutro monoespaciado que `DESIGN.md` § 9 reserva a los
  identificadores sin grados. Inventar una familia de color para los tipos de obligación rompería la
  rueda de hue, que ya está agotada.

- **Sin acciones masivas.** Marcar veinte compromisos como cumplidos de golpe escribe veinte filas de
  histórico que nadie ha mirado, y cada cumplimiento lleva su fecha, su prueba y su nota. Mismo argumento
  que en indicadores.

- **«Asumir las del catálogo» sólo aparece con la tabla vacía.** Sin esa salida el registro se queda
  vacío para siempre —nadie declara a mano seis obligaciones que ya se sabe de memoria— y un registro
  vacío es lo mismo que no tener el módulo. Con filas dentro estorba.

- **Cada asiento del histórico enseña sus dos fechas.** `fecha` es cuándo se cumplió y `created_at`
  cuándo se apuntó: la del auditor es la primera y la de la traza la segunda, y enseñar sólo una las
  confunde. Mismo reparto que `medidaEn` frente a `registradaPor` en una medición.

- **No se marca cumplida desde un chip del calendario.** Un cumplimiento necesita fecha, prueba y nota, y
  `DESIGN.md` § 1 no admite un gesto irreversible desde una rejilla densa. El chip lleva a la ficha.

- **Sin enum `EstadoCompromiso` con `tono()`.** El estado se deriva del último cumplimiento y de la
  cadencia, y reutiliza tonos que ya existen. Crear el enum obligaría a dar de alta tono e icono en los
  dos mapas del cliente para no ganar nada.

- **`{cumplimiento}` no necesita `resolveChildRouteBinding()` a mano**, porque `scopeBindings()`
  pluraliza en inglés y aquí coincide con el español. Que la ruta de al lado funcione no dice nada de
  ésta, así que lo fija un test de aislamiento y no la lectura de la ruta.

## Lo que salió mal en la primera versión

El módulo entró con la suite verde —1786 tests, Larastan nivel 6, Pint, `vue-tsc`—
y con **once fallos de comportamiento dentro**. Se anotan porque ninguno era
exótico y todos vuelven a ser posibles:

- **El motivo de retirada se escribía en `notas`**, que es un campo del usuario:
  retirar borraba lo que hubiera escrito. Ahora hay `motivo_retirada`, y retirar
  pide confirmación en vez de ejecutarse de un clic.
- **`proximaFecha()` consultaba aunque la relación estuviera cargada**, y la pintan
  dos columnas: veinticinco filas eran cincuenta consultas. La tabla trae la fecha
  por SQL con `expresionProxima()`; el método lee la colección cuando está cargada.
- **`codigo` era `nullable` en el request y `NOT NULL` en la base**, así que vaciarlo
  al editar daba un `QueryException`. Lo rellena `prepareForValidation()`, que es
  donde el alta y la edición comparten regla.
- **La redirección de `/tareas/calendario` vivía dentro de `can:tareas.ver`**, y le
  daba un 403 a quien tuviera `calendario.ver` sin él — el fallo exacto que ese
  respaldo existe para evitar. Una redirección no enseña nada: decide el destino.
- **El estado vacío de `/obligaciones` enseñaba dos botones de escritura sin mirar
  el permiso**, porque la acción que sí lo miraba vivía en el `DataTable` que ese
  estado sustituye.
- **`opciones()` se mandaba entero a las tres pantallas**: cuatro consultas de más
  por carga, una de ellas sin `limit`, y un aviso de Vue en cada una —`AppLayout`
  tiene raíz de fragmento, así que un prop no declarado no se puede heredar—.
- **El filtro de fuente se pintaba dos veces**: sólo se excluía de `sueltos`, y
  `chipsDe()` recorre `todos`.
- **No se podía desasignar responsable ni sistema.** Faltaban `conOpcionVacia()` y
  `NormalizaSeleccionVacia`, que son las dos mitades del mismo mecanismo: Reka
  prohíbe el `SelectItem` vacío, así que hace falta un centinela y alguien que lo
  traduzca.
- **El formulario del catálogo no pintaba ni un error**: iba con `<input>` y
  `<select>` en crudo, así que asumir con una fecha futura no hacía nada visible.
- **Se podía asumir una obligación que el importador había retirado.**
- **`ordenPorDefecto()` era `titulo`** mientras el propio recurso declaraba que la
  columna que se mira es el próximo vencimiento, que además no era ordenable.

Y **los tres iconos eran el mismo a 14 px**, que era lo único que quedó declarado
como pendiente de mirar y resultó cierto. Ver `Fuente::icono()`.

La lección que vale para el módulo siguiente: la suite verde dice que nada de lo
que se comprueba está roto, no que el comportamiento sea el correcto. Los nueve
tests que descubren cubren los olvidos de forma; para lo demás hace falta mirar.

## Lo que este módulo declara que no hace todavía

- **No recoge la reevaluación de proveedores (§ 4.9)**, cuyo módulo no existe. No entra en el catálogo
  todavía, porque la § 2.2 ya declara `proveedores.fecha_evaluacion` y `proxima_evaluacion` y crear el
  compromiso ahora obligaría a migrarlo. **La continuidad dejó de estar en esta lista con el § 4.11**:
  las pruebas y la revisión del BIA son dos `Fuente`, y la obligación anual de probar los planes se
  propone por su requisito, `op.cont.3`, y ya no por `categoria_minima: media` —que exigía de menos—.
- **De la formación, sólo avisa a quien ya ha recibido alguna.** Quien nunca la ha recibido no tiene
  fecha que pintar, y `fecha_alta + 12` sería inventarle un plazo. Sale donde ya salía: en
  `Persona::sinFormacionReciente()` y en el panel. El calendario es por tanto un **subconjunto** del
  panel y nunca al revés, y hay un test que lo fija.
- **De un indicador, sólo el último periodo cerrado sin medir.** Ni los anteriores, ni un aviso de que el
  periodo en curso va a cerrar.
- **La periodicidad de la reevaluación de riesgos se siembra, no se lee en vivo.** Cambiar
  `metodologias_riesgo.periodicidad_revision_meses` después no mueve el compromiso ya asumido. Leerla en
  vivo acoplaría `Domain\Obligacion` a `Domain\Riesgo` y convertiría una fila de catálogo en un caso
  especial del código.
- **`base_legal` cita el instrumento y el nombre de la obligación, no el artículo.** Las cláusulas de ISO
  27001 —8.2, 9.2 y 9.3— sí están contrastadas; la numeración de artículos del RD 311/2022 está
  pendiente de contraste celda a celda con BOE-A-2022-7191, y por eso el YAML va con `revisado: false`.
  Una cita de artículo equivocada dentro de un entregable es peor que no citarlo.
- **Avisar de que toca una auditoría no es llevar el programa anual.** La 9.2.2 llama programa a
  planificar alcance, criterios y método, y eso sigue sin hacerse. Va declarado en la DdA.
- **No comprueba que lo asumido cubra lo exigible.** Se puede tener el catálogo entero sin asumir y el
  módulo no lo señala: lo que hay es la cuenta de lo que queda por asumir, en una línea.

## Deuda declarada, y por qué no entró en el arreglo

Se anota porque descubrirla cuesta una tarde y porque afirmarla es más barato que
negarla. Nada de esto rompe nada hoy:

- **`Link` envolviendo `Button`** en el calendario y en `/obligaciones`: produce
  `<a><button>`, dos paradas de tabulación por control y un ancla sin nombre
  accesible. El patrón bueno —`<Button as-child><Link>`— está en 32 ficheros y aquí
  sólo se aplicó en la ficha.
- **`text-muted-foreground/60`** en las iniciales de los días y en los números
  fuera del mes: **2,4:1**, por debajo del 4,5:1 de § 11. Viene del fichero que se
  mudó, y `PaletaTest` no lo caza porque mira tokens y no clases de plantilla.
- **Objetivos táctiles de 32 px** en la navegación de mes (`size="icon-sm"`), que
  también se ve por debajo de `md`. `FiltroFuentes` y `ConmutadorVista` sí resuelven
  esto con `min-h-11 sm:min-h-8`.
- **La curva `[0.16, 1, 0.3, 1]` escrita a mano** en la entrada de la rejilla,
  pudiendo usar `lib/motion.ts`, cuya cabecera dice que ningún componente escribe
  esos números. Hay cinco reincidencias más en el repositorio.
- **`EstadoVacio` dentro de una tarjeta** en la ficha: su balanza de 22 rem está
  calibrada para un vacío a ancho de pantalla, no para un hueco de 400 px.
- **La ficha puede desbordar a 375 px**: tres botones en la cabecera y
  `CabeceraPagina` no envuelve el hueco de acciones.
- **El diálogo de cumplimiento no dice, hasta enviar, que sólo vale una
  referencia.** La base lo impone y el `FormRequest` lo valida, pero los tres
  desplegables se presentan como independientes y el error sale colgado del primero.
- **`DIAS_POR_VENCER` está en tres sitios** —la constante del recurso, el valor por
  defecto del scope y un literal en la etiqueta del panel— pese a que su propio
  docblock dice que una cifra escrita dos veces se desincroniza.
