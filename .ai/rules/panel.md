---
paths:
  - app/Domain/Panel/**
  - resources/js/pages/panel/**
  - resources/js/components/panel/**
---

# El panel: tres vistas

El panel creció por acumulación —un módulo, una tarjeta— hasta trece secciones
apiladas, y el problema no era la longitud: **mezclaba tres preguntas**. Cómo va
el cumplimiento, qué está pasando y de qué organización hablamos estaban en la
misma columna, y había que recorrerla entera para contestar cualquiera de las
tres.

| Vista | Qué contesta | Qué lleva |
|---|---|---|
| `/panel` | ¿Cómo vamos con lo exigible? | anillo, reparto por estado, pruebas, por marco, sistemas |
| `/panel/ciclo` | ¿Qué está pasando y mejoramos? | plan, obligaciones, no conformidades, incidentes, desempeño, objetivos |
| `/panel/organizacion` | ¿De qué estamos hablando? | contexto, personas, inventario |

**Son rutas y no estado de cliente**, que es la decisión ya tomada para las
pantallas del plan de acción: «un conmutador que recuerda la última vista hace
que el enlace que alguien pega en un correo abra otra pantalla». `ConmutadorPanel`
está calcado de `ConmutadorVista`.

**Y no entran en `lib/navegacion.ts`**, igual que `/tareas/tablero`: aquel fichero
es el mapa de **módulos**, y añadir ahí tres entradas pondría tres «Panel» en el
sidebar.

> El ejemplo era `/tareas/calendario` y se volvió del revés con el § 4.16: el
> calendario **sí** entró en el mapa, precisamente porque dejó de ser una vista de
> otra cosa y pasó a ser módulo. La regla no cambia —el mapa lista módulos— pero el
> ejemplo ya no vale. El sidebar lleva a `/panel`, que es
la vista por defecto, y `esSeccionActiva` ya marca las tres porque cuelgan de
ella.

**Cada vista consulta sólo lo suyo.** Antes cada carga calculaba trece resúmenes
aunque nadie mirara doce.

**Las tres tienen estado vacío.** Una pestaña en blanco no se lee como «no hay
nada», se lee como rota — y con los seis registros del ciclo a cero, que es el
estado de quien acaba de empezar, esa vista no diría literalmente nada. En
primer arranque el conmutador tampoco se pinta: ahí la pantalla no resume,
orienta.

### El punto de la pestaña, que es lo que sujeta el reparto

Partir el panel tiene **un solo riesgo**, y es el que hay que sujetar: una
pestaña puede esconder un incumplimiento detrás de un clic que nadie da.
`AlertasDelPanel` cruza los doce registros, cuenta **lo rojo que no está a
cero**, y cada pestaña sale con su recuento en `VistaPanel::$alertas`.

**Se filtra por tono y no por una lista de claves.** `alertas()` de cada registro
devuelve también cosas que piden atención sin estar incumplidas —`bloqueadas` en
tareas, `con_no_conformidades` en auditorías— y contarlas aquí pondría punto en
las tres pestañas siempre. El rojo del producto es `caducada`, tiene dueños
contados y cada módulo declara el suyo: **un módulo nuevo entra declarando su
alerta con ese tono, y esa es toda la conexión que hace falta.**

**No se manda la lista de alertas al cliente, sólo el recuento.** Lo que el
conmutador necesita es saber si las hay; el detalle vive dentro, en la tarjeta
del módulo que lo produce, con su enlace a la lista exacta.

> **Lo que se probó y se quitó.** El primer intento sacaba además **todos** los
> rojos a una tira fija encima de las pestañas. Con el registro de ejemplo salían
> **doce tarjetas rojas** —el seeder planta un caso de cada—, que es exactamente
> la fila de cifras que hay que leerse entera y que el panel ya tenía. El punto
> dice lo mismo en un píxel, y el rojo se queda donde puede explicarse: al lado
> de su cifra y de su enlace.

**Cada fuente va con su permiso**, como ya iba cada tarjeta por separado:
conectar dos módulos abre una puerta lateral al registro del otro si nadie lo
decide. Que hoy los tres roles del § 4.19 tengan todos los `.ver` no la hace
innecesaria: la hace **no ejercida**.

### La lista de fuentes es literal, y hay test que la descubre

`AlertasDelPanel::FUENTES` es una lista escrita a mano, como `Rol::permisos()`, y
con el mismo riesgo: olvidar un módulo nuevo **no rompe nada** — su pestaña deja
de marcarse, que es el fallo silencioso que el punto existe para cerrar.

Por eso `AlertasTest` no enumera módulos: **recorre `app/Domain/` buscando
registros con `alertas()`** y exige que estén declarados, en las dos direcciones.
Es el sexto de la familia que descubre en vez de enumerar. Y su `glob` lleva la
lección de `FactoriesSinOrganizacionTest`: si deja de encontrar nada, el test se
pone rojo en vez de pasar dando por cubierto lo que no cubre.

### El segundo eslabón: en qué pestaña cae cada rojo

Estar en `FUENTES` hace que el rojo se cuente; **en qué pestaña cae lo decide
`AlertasDelPanel::VISTAS`**, por la `base` del indicador. Esa lista vivía en
`PanelController::comunes()` con los módulos que tenían tarjeta, y obligaciones
(§ 4.16), proveedores (§ 4.9) y vulnerabilidades entraron en `FUENTES` sin
entrar en ella: su rojo se contaba y no marcaba ninguna pestaña. Lo destapó el
recorrido de vulnerabilidades en el navegador —una fuera de plazo y «El ciclo»
seguía diciendo 6—, no la suite.

Ahora está en el dominio, completa, y `AlertasTest` pregunta a cada fuente qué
`base` llevan sus alertas —la llevan aunque valgan cero— y exige que cada una
esté **en una vista y en una sola**. Un módulo nuevo que declare su rojo sin
decir en qué pestaña cae pone el test en rojo.

**Y cada rojo necesita una tarjeta donde explicarse**, porque el punto dice
«mira aquí» y la pestaña tiene que contestar. Por eso vulnerabilidades entró en
«El ciclo», pegada a incidentes —lo que pasó y lo que puede llegar a pasar—, y
proveedores en «La organización», detrás del inventario del que dependen.
**Continuidad sigue sin tarjeta**: su rojo cae en «El ciclo» y se explica en
`/continuidad`, no en el panel. Es un hueco conocido.

### Los dos callejones sin salida que quedaban

**Ninguna cifra de la tarjeta de pruebas llevaba a su lista.** «3 caducadas», y
ahora búscalas — filtrando a mano por un rango de fechas. Era justo lo que
`Indicador` existe para evitar, y en el módulo que sostiene el rojo más antiguo
del producto. Ahora `EvidenciaRecurso` declara `caducadas` y `por_caducar` por
scope, y las cuatro cifras de la tarjeta enlazan.

**Y la cuarta no tenía scope siquiera.** «Implantados sin prueba» vivía escrito
en `ResumenCumplimiento` y en ningún otro sitio, así que no había filtro que
pudiera reproducirla. Ahora es `Implantacion::sinEvidencia()`, lo invocan el
resumen y el filtro de `/implantaciones`, y por construcción no pueden
discrepar.

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **`IndicadorInventario` y `RepartoInventario` pasaron a `Indicador` y `Reparto`**, y la tira que los
  pinta a `components/TiraIndicadores.vue`. La forma era genérica y el nombre mentía; duplicarlos por
  módulo es el «cuatro dialectos distintos para el sexto» que la capa de recursos existe para evitar. El
  indicador lleva `base` —`/activos`, `/tareas`— para que quien lo pinta no tenga que saber de qué tabla
  salió.

- **La tarjeta del plan en el panel no lleva anillo de progreso, a diferencia del inventario.** Allí el
  denominador es estable —los activos vigentes— y el porcentaje mide cuánto está decidido. Aquí crece cada
  vez que alguien apunta trabajo: «porcentaje de tareas hechas» baja al ser honesto y sube al cerrar cosas
  pequeñas, así que mide actividad y no salud. **Un indicador que castiga por apuntar lo que falta enseña
  a no apuntarlo.** Lo que abre la tarjeta es cuántas quedan abiertas, con su denominador.
