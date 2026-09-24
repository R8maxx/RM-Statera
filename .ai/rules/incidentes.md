---
paths:
  - app/Domain/Incidente/**
  - resources/js/pages/incidentes/**
  - resources/js/components/incidente/**
---

# Los incidentes

§ 4.10 y `op.exp.7`. **El segundo de los dos módulos que muerden en categoría
básica**, junto a las personas: `op.exp.7` es exigible desde el primer día y no
tenía dónde registrarse. Vive en `app/Domain/Incidente/`, con tres tablas.

### El reloj, y dónde no lo hay

Es la decisión del módulo. **Sólo hay cuenta atrás donde la ley pone un número.**

`PlazoNotificacion::aepd()` cuenta **72 horas desde `fecha_deteccion`**, y el
número sale del **artículo 33.1 del RGPD**, citado en el código porque un plazo
sin su fuente es una opinión. Corre únicamente si el incidente está marcado como
notificable a la AEPD, es decir, si hubo datos personales de por medio.

**Para el CCN-CERT no hay reloj.** El RD 311/2022 no fija horas: dice «sin
dilación». Poner un número sería exactamente lo que este producto se niega a
hacer con el riesgo residual —una opinión de la herramienta disfrazada de
cálculo— y además sería un número que alguien acabaría defendiendo delante de un
auditor. Se registra si es notificable y cuándo se notificó, **y la ficha lo dice
por escrito**.

**Notificable y notificado son dos campos y no uno.** «No había que notificar» y
«había que notificar y no se hizo» serían la misma columna vacía si se
colapsaran, y la segunda es un incumplimiento y la primera no.

**Y notificar tarde sigue constando como tarde.** `PlazoNotificacion` distingue
«notificada dentro de plazo» de «notificada fuera de plazo»: esconderlo al anotar
la notificación sería borrar la prueba. Por eso la fecha **se escribe y no se
impone**, al revés que la de cierre de una tarea: la notificación se hace en la
sede del supervisor y se apunta aquí después, y en un incidente fuera de plazo
esa fecha es lo que decide si hubo incumplimiento.

**Anotar la notificación no cambia el estado**, y deja fila en el histórico. Se
puede notificar con el incidente abierto, en tratamiento o resuelto; meterlo en
la máquina de estados obligaría a inventarse un «notificado» que no dice nada de
cómo va la contención.

### Cerrar exige lección aprendida

`op.exp.7` pide aprender del incidente, y es **el paso que todo el mundo se
salta** el día que el servicio vuelve. Por eso:

1. **`resuelto` no es `cerrado`.** Resuelto es que el servicio está
   restablecido; cerrado es que además se ha escrito qué se aprendió. Con un solo
   estado final, la lección se queda sin escribir justo cuando todo el mundo se va
   a dormir. Es el mismo reparto que `Cerrada` frente a `Verificada` en una no
   conformidad.
2. **La regla está en `CambiarEstadoIncidente` y en un `CHECK`**, como todas las
   de esta familia: en el dominio porque vale igual para un importador, y la
   guarda para que el mensaje sea legible y no el nombre de una restricción.
3. **La lección tiene ruta propia** (`PUT /incidentes/{incidente}/leccion`). Se
   escribe **mientras se resuelve**, a trozos y según se va sabiendo; obligar a
   abrir el formulario entero para añadir una línea es cómo se consigue que esa
   línea no se escriba.

**De `cerrado` se vuelve a `resuelto` y nunca a `abierto`**, misma puerta que
tienen la auditoría cerrada y el acta aprobada. Y **volver atrás exige motivo
escrito; avanzar no**: pedir un texto para pasar de abierto a en tratamiento
convertiría en trámite el gesto que más se repite mientras se apaga el fuego.

### El único rojo del módulo

**El plazo de la AEPD vencido sin notificar**, y nada más. Ni los estados —un
incidente abierto no va mal, va siendo atendido— ni la peligrosidad, que se
reparte como `NivelRiesgo` y sólo llega al rojo en `critica`. Hay test que
recorre el enum de estados comprobándolo, como en tareas y en mejoras.

Y **«en plazo» va separado de «fuera de plazo»** en las cifras: uno es un
incumplimiento y el otro es trabajo urgente, y colapsarlos pondría en rojo a quien
lo está haciendo bien.

### Lo que va en columnas y lo que no

**Las cinco dimensiones son cinco columnas booleanas**, no filas ni JSONB. Mismo
reparto que la valoración propia de un activo y por lo mismo: son cinco, no van a
ser seis, se consultan y se indexan, y «qué se vio afectado» es la primera
pregunta de un informe de incidente y la que decide si hay que notificar.

**Las dos notificaciones van en columnas y no en una tabla**, como dibuja la
§ 2.2: son dos destinatarios fijados por ley, y una tabla de notificaciones con
dos filas posibles es una tabla que nadie consulta. **Declarado**: un tercer
supervisor —NIS2, o un regulador sectorial— sí pedirá tabla, y entonces se migra.

**`incidente_activo` es N:M**, contra la letra de § 2.2 que dice
`activos_afectados`: un cifrado por ransomware toca treinta equipos y sigue
siendo un solo incidente. Mismo argumento aritmético que en riesgo ↔ activo.

**`sistema_id` es opcional**, al revés que en una auditoría: un correo
fraudulento a toda la organización no es de ningún sistema, y obligarlo haría que
quien lo apunta a las tres de la mañana eligiera el que menos mal le suena.

**Y son dos fechas, no una**: cuándo empezó y cuándo se detectó. La diferencia
entre las dos es la primera cifra que enseña un informe de incidente, y con una
sola columna se pierde. `fecha_inicio` nula es «no se sabe», que es lo normal al
principio.

### La clasificación, y lo que queda por contrastar

`ClasificacionIncidente` son las clases de nivel superior de la taxonomía
**CCN-STIC 817**, y `PeligrosidadIncidente` sus cinco niveles. **Enums y no
catálogo en YAML**, por el mismo reparto que `GrupoAmenaza` frente a las 56
amenazas de MAGERIT: las clases son la **estructura** de la taxonomía y no su
contenido.

> **Sin contrastar contra la guía, y queda dicho**, igual que las dimensiones de
> las amenazas de MAGERIT. Se usan como **clasificación de trabajo**: agrupan y
> filtran, y **no deciden nada** —ni la peligrosidad, ni si hay que notificar, ni
> a quién—. Los subtipos de la 817 no están cargados.

**La peligrosidad la declara una persona y no se calcula.** Sería tentador
derivarla de las dimensiones afectadas, y sería una opinión disfrazada de
cálculo: la 817 no publica ninguna función que lo haga, y el mismo compromiso de
confidencialidad es crítico en un sistema y bajo en otro. Mismo razonamiento que
el riesgo residual.

**`Otros` existe a propósito**: quien apunta un incidente a las tres de la mañana
no está clasificando taxonomías, y sin un valor para «todavía no lo sé» elegiría
el que menos mal le suena. Es el argumento de `OrigenTarea::Propia` y el de
`EstadoControl::PorConfirmar`.

### Dos verbos, y ninguno de supervisión

`incidentes.ver` e `incidentes.gestionar`. **Notificar a un supervisor no es una
decisión que se delibere**: es una obligación con reloj, y un permiso aparte
metería un paso entre el reloj y la notificación. Lo que sí exige firma de
dirección es la no conformidad que salga del incidente, y ésa ya tiene la suya.

### Las costuras, y la que es nueva

**Tres enganches que llevaban puestos desde hacía meses**, y ninguno necesitó
migración porque el valor estaba en el `CHECK` desde la primera —el enum se
declaró entero y lo que faltaba era su módulo—:

- `OrigenTarea::Incidente` pasa a ofrecerse. Y a diferencia de `Hallazgo`, aquí
  **no hay eslabón por medio**: contener un incidente produce trabajo directo que
  no espera a ninguna no conformidad, porque puede que no llegue a haberla.
- `OrigenNoConformidad::Incidente`, ídem.
- `OrigenNoConformidad::RevisionDireccion`, que **se quedó en `false` y era falso
  desde el § 4.15**: una frase que envejeció en el tramo anterior.

**`OrigenMejora::Incidente` sí necesitó migración del `CHECK`**, porque es un
valor nuevo. Es la diferencia con los tres de arriba, y la misma que hubo entre
`revision_direccion` y `objetivo`/`mejora` en `OrigenTarea`.

**Y `no_conformidades.incidente_id` es el espejo exacto de `hallazgo_id`**:
nullable, único —un incidente se trata una vez—, `nullOnDelete` —borrar el
incidente no se lleva por delante la prueba de que se trató— y un `CHECK` de que
implica `origen = 'incidente'` en una sola dirección. **Más uno que el hallazgo
no tiene**: no puede venir de un hallazgo y de un incidente a la vez, porque con
las dos columnas puestas `origen` tendría que valer dos cosas y los dos `CHECK`
anteriores se contradirían con un mensaje que no explica nada.

**La mejora, en cambio, no lleva clave foránea.** Desde un incidente sólo se
hereda el origen y el título. Es el mismo reparto que
`OrigenMejora::RevisionDireccion`: la mejora que sale de una lección aprendida no
«trata» el incidente —ése ya está cerrado—, así que atarla sería fingir una
trazabilidad que no hay.

### Lo que este módulo declara que no hace todavía

- **No entra en el calendario de obligaciones**, y aquí el argumento cambia
  respecto a los tres módulos anteriores: el plazo de la AEPD se mide en **horas**
  y una rejilla de meses no es donde se mira un reloj de 72 h. Vive en la ficha y
  en el panel.
- **No genera el informe de incidente** como documento. El § 4.18 no lo nombra
  entre sus seis y está hecho sin él, así que no hay ningún módulo pendiente que lo
  traiga: sería un tipo de documento nuevo.
- **No notifica por sí solo a ningún supervisor**, ni prepara el formulario de la
  sede: registra la decisión y la fecha.
- **No decide si hay que notificar.** `notificable_aepd` lo marca una persona, y
  la herramienta no comprueba que esa decisión sea correcta — igual que no
  comprueba la peligrosidad.
- **Los subtipos de la CCN-STIC 817 no están cargados**, y las clases no están
  contrastadas celda a celda contra la guía.

**Y ninguna limitación impresa pasó a ser falsa con este módulo dentro** —
comprobado: ni «incidente» ni `op.exp.7` aparecían en ninguna—. Es la segunda vez
que ocurre, después del § 4.1. Las dos que sí se reescribieron en este tramo son
las del § 4.8: la de los roles ENS de la DdA y la del acuse de lectura.
