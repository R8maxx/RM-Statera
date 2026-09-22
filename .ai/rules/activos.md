---
paths:
  - app/Domain/Activo/**
  - resources/js/pages/activos/**
  - resources/js/components/activo/**
  - resources/js/components/valoracion/**
  - resources/js/lib/grafoActivos.ts
  - config/obsolescencia.php
---

# El inventario de activos

## El grafo de dependencias de un activo

`/activos/{activo}/grafo`. **Ruta propia y no un bloque más de la ficha**, por lo
mismo que el organigrama: es un lienzo que se arrastra y se acerca, y eso no cabe
en la columna de una ficha. Los dos bloques —«Depende de» y «Lo sostiene»— se
quedan donde están **y siguen siendo el camino accesible**: se recorren con el
teclado y caben en 375 px sin arrastrar. El diagrama lo dice debajo y enlaza a
ellos.

### Lo que el diagrama añade y las dos listas no pueden: el rombo

`dependenciasDe()` y `dependientesDe()` devuelven los nodos alcanzables con su
**profundidad mínima** —el `DISTINCT ON (id)` de `recorrer()`—, que es
exactamente lo que una lista sangrada necesita. Para dibujar falta lo otro: si
dos servicios se apoyan en la misma base de datos, la lista la enseña **una vez
y a un salto**, y el diagrama tiene que dibujar **los dos vínculos**, porque es
de donde a esa base de datos le sube la valoración efectiva. Lo mismo con el
atajo: un servicio que depende de la aplicación **y** directamente de la base.

De ahí `GrafoActivos::vecindadDe()`, que es el método nuevo: **los nodos salen
del recorrido** —que ya sabe de profundidad, de organización y de ciclos— y **las
aristas de una segunda consulta acotada al conjunto de nodos**, que es lo que las
devuelve todas. El `IN` de los dos extremos no es adorno: sin él entrarían
vínculos hacia activos que no están en el lienzo, y Vue Flow los descarta en
silencio — aristas que no se ven y una consola limpia.

`sentido` dice de qué lado cae cada nodo: `arriba` lo que se cae con él, `abajo`
lo que necesita, `centro` él mismo. En un rombo que vuelve **gana `abajo`**,
porque es donde su valoración empieza a subir.

### El color es la valoración EFECTIVA, que es lo que paga la pantalla

Una base de datos valorada «bajo» que sostiene un servicio esencial vale «alto»,
y aquí se ve **por dónde** le sube. El tono lo declara el dominio con
`NivelDimension::tono()`, que **delega en `aCategoria()`** en vez de escribir un
segundo mapa: ese método ya dice que `Bajo/Medio/Alto` son `Basica/Media/Alta`, y
dos matches con la misma correspondencia es cómo se acaba con uno de los dos
desactualizado. `Na` va al gris de `no_aplica` y no al primer escalón: la
dimensión no aplica, que no es valorarla en lo más bajo.

El nivel va **además escrito** en la caja, porque el color no puede ser el único
canal y un ordinal de tres escalones distingue peor que un estado: «medio» y
«alto» son el mismo teal a distinta fuerza.

La valoración de todos los nodos sale de **una** consulta —
`ValoracionEfectiva::paraLaOrganizacion()`—, que es la misma entrada que alimenta
la tabla y que tiene un test fijando que coincide con la de la ficha.

### Tres cosas que costaron

1. **El worker de ELK no se puede usar aquí.** Ver el desvío del stack: assets en
   otro origen, y un `Worker` cross-origin lo prohíbe el navegador.
2. **El encuadre se pide por REFERENCIA al componente**, no con el `fitView` de
   `useVueFlow()`. El composable llamado en el `setup` crea su propio store, y
   este `<VueFlow>` monta más tarde —detrás del `v-if` del estado de carga,
   porque los nodos llegan de una promesa—, así que acaba en otra instancia. El
   `fitView` se llamaba sobre un store vacío y **no fallaba**: el lienzo se
   quedaba a zoom 1 sin desplazar, con el grafo medio fuera. Se vio a 485 px.
3. **`lib/grafoActivos.ts` NO importa los tipos de Vue Flow** y describe su
   propia forma. `motion-v` y Vue Flow amplían los dos los `HTMLAttributes` de
   Vue con un `DragControls` distinto, así que el `Node` que se resuelve en un
   `.ts` no es idéntico al que se resuelve en un `.vue` y `vue-tsc` rechaza la
   asignación con un error de doscientas líneas sobre `domAttributes`. De paso es
   lo correcto: ese fichero coloca, no dibuja.

### Lo que se probó y se quitó

**La nota del vínculo pintada sobre la arista.** Dos aristas que convergen en el
mismo nodo —el rombo, que es el caso que la pantalla existe para enseñar— dejan
sus etiquetas a la misma altura y se leen **como una sola frase**: «El servidor
aloja la base de datos. La información del gestor vive aquí.» parecía una nota y
eran dos. Inventar una frase que nadie escribió es peor que no enseñarla, y la
nota ya vive en las listas de la ficha, donde tiene sitio.

### Lo que esta pantalla declara que no hace

- **No es el mapa del inventario**: dibuja la vecindad de **un** activo. Un mapa
  completo necesitaría filtros por sistema o por tipo para decir algo, y con ELK
  en el hilo principal querría volver al worker.
- **No se edita arrastrando.** Los vínculos se declaran y se retiran desde la
  ficha, que es donde `RegistrarDependencia` rechaza los ciclos.
- **No se recorre con el teclado**, por ser un lienzo. Las dos listas de la ficha
  sí, y lo dice la propia pantalla.

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **El resumen del inventario está repartido a propósito entre el panel y la tabla.** Los repartos —por tipo, por ciclo de vida, cobertura de cifrado y copia— viven en el panel, que es donde se pregunta cómo va la cosa —desde el rediseño, en `/panel/organizacion`—; en `/activos` sólo queda lo que pide acción hoy. Antes eran nueve recuentos del mismo tamaño encima de la tabla, varios a cero, mezclando tres cosas distintas: incumplimiento real, dato que falta y perfil. Había que leerse los nueve para saber si algo iba mal. **Un indicador a cero ya no ocupa una tarjeta**: si no hay nada abierto se pinta una línea diciéndolo, que es un estado vacío de verdad y no una fila de ceros. Y toda cifra va con su denominador — «2 sin cifrar» sobre 4 es una urgencia y sobre 307 es un martes.

- **`ResumenInventario::controlesResueltos()` cuenta `no_aplica` como resuelto.** Un router no cifra en reposo porque no almacena nada; contarlo como pendiente pondría un techo que la organización no puede alcanzar por mucho que trabaje, y un indicador que nunca llega al cien por cien se deja de mirar a las dos semanas.

- **La valoración de un activo va en cinco columnas de `activos`, no en filas de `valoracion_dimensiones`.** Aquella tabla es la entrada del motor de categorización: lleva justificación por dimensión y su cambio recalcula las implantaciones. La del activo no hace nada de eso, y además la propagación por el grafo es un `GREATEST` sobre columnas dentro de una CTE recursiva — con filas habría que pivotar dentro de la recursiva. El value object `ValoracionDimensiones` se reutiliza igual, y `elevadaCon()` es la operación con la que la valoración sube por el grafo.

- **La valoración efectiva de un activo se calcula, no se almacena.** Es el máximo entre la suya y la de todo lo que depende de él: una base de datos valorada «bajo» que sostiene un servicio esencial vale «alto», y ese es justo el activo que una hoja de cálculo deja desprotegido. Guardar una copia sería abrir la puerta a que se desincronice del grafo que la justifica. **La propia nunca se sobrescribe** y las dos se enseñan juntas: el auditor pregunta qué valoró la organización, no qué dedujo la herramienta. `ValoracionEfectiva` tiene dos entradas —`de()` para una ficha y `paraLaOrganizacion()` para la tabla, en una sola consulta— y hay un test que fija que coinciden; si divergen, la tabla enseñaría una cifra y la ficha otra.

- **Los ciclos del grafo de activos los rechaza `RegistrarDependencia`, no la base.** El `CHECK` de `activo_dependencias` sólo cubre el bucle de un salto; uno de tres se cuela igual, y contra un grafo con un ciclo una CTE recursiva no devuelve un resultado raro: no termina. Se comprueba en el dominio y no en el `FormRequest` porque la prohibición vale también para un importador o para el seeder. Aun así, los recorridos de `GrafoActivos` arrastran la ruta en un array y se niegan a reentrar en un nodo visitado: la red de seguridad se paga una vez y evita colgar el proceso.

- **El registro de revisiones es una tabla, no una fecha suelta.** `A.5.9` de ISO y `op.exp.1` del ENS no piden un inventario, piden un inventario **mantenido**, y la diferencia entre las dos cosas es `revisiones_inventario`. `altas` y `bajas` se guardan como los contó quien revisó y no se calculan desde la traza: son la cifra que esa persona firmó ese día, y si mañana alguien da de alta un activo con fecha anterior, no cambia. La fecha por activo (`ultima_revision`) se pone con la acción masiva de la tabla, que es lo que conecta las dos cosas sin una pivote más.

- **`activo_sistema` es N:M.** El mismo servidor está en el alcance del SGSI de ISO y del sistema del ENS a la vez, y duplicarlo para que quepa en los dos sería volver a las hojas de cálculo duplicadas. De ahí sale `Filtro::porRelacion()`: filtrar por alcance con un `join` multiplicaría las filas —el activo de dos sistemas saldría dos veces y la paginación contaría mal—, así que va por `whereHas`. No contradice la regla de que el filtro no inventa joins: aquí no hay join, hay subconsulta, y `consulta()` se queda como estaba.

- **`retirado` y `dado_de_baja` no son lo mismo, y por eso son dos estados.** Retirado es que ya no presta servicio; dado de baja es que además hay constancia de que se borró o destruyó lo que contenía, que es lo que exige `mp.si.5`. Un disco retirado que sigue en un cajón con los datos dentro es un hallazgo, no un activo cerrado, y `Activo::esperaBorradoSeguro()` es lo que lo señala. El `FormRequest` no deja dar de baja sin esa fecha.

- **`proveedor_id` no está en `activos`, a propósito.** El módulo de proveedores (§ 4.9) no existe y no se declara una clave foránea contra una tabla que no está. Se añade con ese módulo, igual que la importación desde CSV y los importadores automáticos de § 4.2.

- **«Por confirmar» no es «No», y por eso `EstadoControl` tiene cuatro casos.** Un export de AWS informa del cifrado de los volúmenes pero no dice nada de las copias de los EC2; con tres valores, esas instancias figuran como incumplimiento y alguien se pasa una semana «arreglando» copias que ya existían. La ausencia de dato es una pregunta abierta y se cuenta aparte. `NoAplica` tampoco es `No`: un router no cifra en reposo porque no almacena nada.

- **La clasificación de la información no sustituye al nivel del Anexo I.** La clasificación se decide y se estampa (`mp.info.2`); el nivel se deriva de valorar el perjuicio. Un mismo activo puede ser de uso interno y valer «alto» en disponibilidad. Conviven, y ninguna se calcula desde la otra.

- **Cada indicador de `ResumenInventario` cuenta con el mismo scope que usa su filtro de la tabla.** No es comodidad: es lo que garantiza que pulsar una cifra enseñe exactamente esa cifra. Con la condición escrita dos veces, el día que cambie una el panel dirá 12 y la lista enseñará 9, y a partir de ahí nadie se fía del panel. `Filtro::porScope()` existe para eso, y `ResumenInventarioTest` recorre los nueve comparando indicador con filtro.

- **Los indicadores se cuentan sobre activos vigentes**, como el cumplimiento se cuenta sobre lo exigible. Un portátil dado de baja sin copia de seguridad no está pendiente: está cerrado. Lo que un activo retirado sí puede deber es el borrado seguro, y eso lo señala `Activo::esperaBorradoSeguro()` en su ficha. El noveno indicador del Excel original —«instancias detenidas», coste de AWS sin uso— se sustituyó por **soporte o garantía vencidos**: misma pregunta, y sin importador no hay de dónde sacar el otro.

- **El QR de la etiqueta codifica la URL de la ficha, no el código en texto plano.** Escanear la pegatina abre la ficha en el móvil; con el código suelto hay que memorizarlo, abrir la aplicación y buscarlo, y a la tercera vez nadie escanea. La base sale de `organizaciones.url_base_etiquetas` y sólo cae a `config('app.url')` si está vacía: una etiqueta impresa dura años y apuntar a la URL equivocada obliga a reimprimir el parque entero. Sólo se etiqueta lo **físico y vigente** —`TipoActivo::esFisico()`—: en AWS y en SaaS no hay carcasa donde pegar nada.

- **`bacon/bacon-qr-code` se declaró como dependencia directa.** Ya estaba instalado como transitiva de Fortify, que lo usa para el QR del segundo factor. Apoyarse en la transitiva de otro paquete es depender de que Fortify no la cambie. No se descargó nada: sólo cambió el hash del `composer.lock`.

- **`retirado`, `en_stock`, `en_reparacion` y `prestado` cuentan como vigentes.** Un portátil en el armario o en el taller sigue teniendo los datos dentro y sigue siendo responsabilidad de alguien; sacarlo del inventario activo es exactamente cómo se pierde el rastro de un equipo. Sólo `retirado` y `dado_de_baja` salen del recuento.

- **El fin de soporte del software base vive en `config/obsolescencia.php`, no en una tabla.** Son hechos del mundo, iguales para todos los clientes: meterlos en una tabla con `organizacion_id` sería duplicarlos por tenant y dejar que se desincronicen. Y no es catálogo normativo (invariante 3): son quince filas que se actualizan cuando sale una LTS. Las fechas son de soporte **estándar**, no extendido de pago: si el inventario contara ya el soporte extendido, la fecha nunca vencería y el aviso no saltaría nunca. Un sistema que no está en la lista **no** cuenta como obsoleto — eso convertiría cada macOS del parque en un falso positivo.
