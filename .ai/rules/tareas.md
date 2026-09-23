---
paths:
  - app/Domain/Tarea/**
  - resources/js/pages/tareas/**
  - resources/js/components/tarea/**
---

# El plan de acción y el tablero

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **`OrigenTarea::Propia` no está en la especificación y se añadió a conciencia.** § 4.7 enumera cinco
  orígenes —hallazgo, riesgo, brecha de implantación, incidente, revisión por la dirección— y los cinco
  dan por supuesto que toda tarea nace de otro registro. Muchas no: «pedir presupuesto del antivirus» no
  es ninguna de las cinco cosas. Sin un valor para eso, quien apunta una tarea a mano elige el que menos
  mal le suena y el campo deja de significar nada, que es lo contrario de por qué existe. Los cuatro
  orígenes cuyo módulo no existe **se declaran pero no se ofrecen** (`OrigenTarea::disponible()`, y el
  `FormRequest` los rechaza): una tarea marcada como «hallazgo de auditoría» sin auditoría detrás no es
  trazable, es una etiqueta.

- **`retirado`/`dado_de_baja` tiene su equivalente en tareas: `hecha` y `descartada` no son lo mismo.**
  Descartar es decidir que no se hará, y **exige motivo** —lo comprueban `CambiarEstadoTarea` y el
  `FormRequest`, porque la regla vale también para un importador—. Por eso la **acción masiva no
  descarta**: un motivo escrito una vez para cincuenta tareas no es un motivo, es un trámite. Y por eso
  no se borran las tareas que no se van a hacer: borrarlas deja el hallazgo sin rastro de qué se decidió.

- **La fecha de cierre la pone el dominio, no el formulario.** Un `CHECK` acopla `estado` y
  `fecha_cierre` en las dos direcciones, así que dejar que la escriba quien llame significa que el día
  que se cierre una tarea desde un job la inserción falle con un error de restricción que no menciona la
  palabra «cierre». `CrearTarea` hace `->refresh()` tras insertar por lo mismo: los valores por defecto
  de `estado`, `origen` y `prioridad` los pone la base, y repetirlos en el modelo sería el mismo dato en
  dos sitios que pueden desincronizarse.

- **En la tabla de tareas el rojo es sólo de la columna «Plazo».** Una tarea vencida es de las pocas
  cosas del dominio que van mal de verdad, y es el mismo uso que ya tenía `caducada` en evidencias. Los
  estados **no** lo gastan —`bloqueada` va en el azul de `planificado`: está aparcada, no incumplida— y
  hay un test que lo fija recorriendo el enum. Si los estados llevaran rojo, el plazo dejaría de saltar a
  la vista, que es la única razón por la que se pinta de rojo.

- **Los tres filtros de estado de la tabla van por `Filtro::porScope()`**, apuntando a los mismos scopes
  que cuenta el aviso diario (`abiertas`, `vencidas`, `sinResponsable`). Misma regla que en el inventario:
  con la condición escrita dos veces, el día que cambie una el correo dirá 12 y la tabla enseñará 9.

- **El bloque «Qué se está haciendo» vive en la ficha de la implantación, no sólo en `/tareas`.** Es
  donde alguien se pregunta qué falta para cumplir un requisito, igual que las evidencias están donde se
  pregunta cómo se prueba. Y de ahí sale el único camino que hoy produce tareas con origen trazable:
  `/tareas/crear?implantacion={id}`, que preselecciona el origen y **no lo deja cambiar** —preguntarlo
  invita a cambiarlo—.

- **El plan de acción tiene dos pantallas y cada una es una ruta**: `/tareas` y `/tareas/tablero`. No
  son pestañas: el servidor manda datos distintos en cada una y así se pueden enlazar y compartir.
  Precedente: `activos.etiquetas`. El conmutador **no guarda nada en el navegador**: el estado es la
  URL, porque un conmutador que recuerda la última vista hace que el enlace que alguien pega en un
  correo abra otra pantalla.

  **Eran tres, y el calendario se fue con el § 4.16** a `/calendario`, con su fichero de reglas y con
  `app/Domain/Aviso/**` detrás. No sobraba un botón: enseña vencimientos de siete registros de seis
  módulos y dejó de ser una vista del plan. Dejarlo apuntando a `/calendario?filter[fuente]=tarea`
  tampoco valía, por un detalle del componente: lo activo se marca comparando `pathname` exacto, así
  que ese botón nunca se habría visto activo. `/tareas/calendario` se queda como **redirección 302**
  —no 301, que el navegador cachea para siempre— porque la URL del mes se guarda y se comparte.

- **El tablero tiene cuatro columnas y no cinco.** `descartada` no tiene columna porque descartar exige
  motivo y eso no cabe en un gesto, y porque una columna de descartadas crece para siempre sin que nadie
  la mire; se descarta desde el menú de la tarjeta, con su diálogo. En «Hecha» sólo entra lo cerrado en
  los últimos catorce días: el tablero enseña el trabajo en curso, y una columna con las trescientas
  cerradas desde enero deja de decir nada. Cada columna lleva tope y su cuenta real, con un «y N más»
  que enlaza a la tabla — quinientas tarjetas en el DOM no son un tablero.

- **Se arrastra con `@atlaskit/pragmatic-drag-and-drop`, y el menú de la tarjeta es el mecanismo
  canónico.** La librería entró porque es agnóstica de framework —sólo APIs del DOM, y CLAUDE.md apuesta
  a que la capa de presentación sea desechable— y porque se apoya en el arrastre nativo del navegador en
  vez de reimplementarlo. Lo que **no** da, y ninguna da, es teclado ni táctil: DESIGN.md § 11 exige que
  todo sea accionable por teclado, así que el menú se construye igual y ofrece exactamente los mismos
  destinos. Va pinada a versión exacta, como TanStack Table: que una librería de interacción cambie de
  comportamiento bajo los pies no lo caza ningún test.

- **La columna prohibida se marca DURANTE el arrastre, leyendo `transiciones` de la tarjeta.** El
  servidor las manda con cada tarjeta justamente para eso. Aceptar el soltado y fallar después se explica
  mucho peor que no dejar soltar. El servidor lo vuelve a comprobar igual —`CambiarEstadoTarea` es quien
  manda—: esto es para que el gesto no mienta, no para fiarse del navegador.

- **El plazo y el tono de prioridad viven en el dominio** (`Tarea\Plazo`, `PrioridadTarea::tono()`), no en
  `TareaRecurso`. Los leen la tabla, el tablero y el calendario: con la regla escrita tres veces, la tabla
  dice «Vencida» y el tablero «En plazo» el día que una cambie.

- **El tablero no ofrece `estado` ni `bloqueadas`.** Las columnas **son** el estado: filtrar por él
  vacía tres de las cuatro y deja un tablero que parece roto. Se declara en
  `TareaController::FILTROS_QUE_SOBRAN`, no escondiéndolo en el cliente.

- **Una subtarea es un paso de una lista de comprobación, no una tarea.** No está en `tareas` con un
  `parent_id` y el motivo es aritmético: **hoy hay trece sitios que cuentan tareas** —panel,
  indicadores, repartos, columnas del tablero, calendario y aviso diario— y con las subtareas como filas
  de `tareas` cada uno tendría que decidir si suma la madre, las hijas o las dos. El día que uno se
  despiste, el panel dice doce abiertas donde hay cuatro cosas que hacer. **Contar de más es el fallo
  caro, y aquí se evita no dando la ocasión**; hay un test que lo fija comparando todas las cifras antes
  y después de trocear las tareas.

  Lo que se pierde —asignar un paso o ponerle fecha— se resuelve con una tarea de pleno derecho
  vinculada al mismo requisito, no con una subtarea con más campos.

- **La lista se guarda entera, en una sola ruta.** Añadir, renombrar, marcar, reordenar y borrar van
  juntos en una lista de comprobación, y el orden llega implícito en la posición del array, así que
  reordenar no necesita ni campo ni gesto propio. `hecha_en` **no se vuelve a sellar** si ya estaba
  marcado: la fecha es cuándo se hizo el paso, no cuándo se guardó la lista. Y un `id` que no es de esa
  tarea se trata como un paso nuevo — lo que llega del cliente no manda sobre a quién pertenece una fila.

- **Marcar todos los pasos no cierra la tarea.** Cerrarla es una decisión con su transición, su fecha y
  su autor; deducirla de una casilla dejaría el histórico contando algo que nadie decidió.
