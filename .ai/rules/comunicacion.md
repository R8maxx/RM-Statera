---
paths:
  - app/Domain/Comunicacion/**
  - resources/js/pages/plan-comunicacion/**
  - resources/js/pages/comunicaciones/**
  - app/Http/Controllers/ComunicacionController.php
  - app/Http/Controllers/ComunicacionPrevistaController.php
  - app/Http/Resources/ComunicacionRecurso.php
  - app/Http/Resources/ComunicacionPrevistaRecurso.php
---

# La comunicación

Cláusula 7.4: qué se comunica, cuándo, a quién, quién lo hace y cómo. Vive en
`app/Domain/Comunicacion/`, con tres tablas: `comunicaciones_previstas` (el plan),
`comunicacion_prevista_parte_interesada` y `comunicaciones` (lo comunicado y lo
recibido). Dos pantallas, como obligaciones y calendario: `/plan-comunicacion` y
`/comunicaciones`.

### Emitido y recibido en la misma tabla

**César decidió registrar también lo recibido**, y es lo que hace que la 9.3.2 e)
—la retroalimentación de las partes interesadas— deje de ser una limitación del
acta. Una queja, una sugerencia o una encuesta son la misma conversación con la
misma parte interesada que un aviso emitido, así que comparten tabla y se separan
por `sentido`. Los `CHECK` lo cierran en las dos direcciones: lo recibido lleva
`tipo_recibida` y puede llevar `respuesta`; lo emitido no lleva ninguna de las
dos, y **sólo lo emitido cumple una línea del plan**.

**Nada de lo recibido gasta rojo**, tampoco la queja: una queja apuntada es una
organización que escucha, y pintarla de alarma enseña a no apuntarla. «Sin
respuesta» es un pendiente en gris, porque no hay plazo para contestar.

### Es el patrón de los compromisos, copiado y no reutilizado

Una línea del plan **no es un compromiso del § 4.16**: tiene destinatarios y canal,
y una obligación del catálogo no. Pero la regla es la misma y se copia entera:

- la periodicidad es **un entero de meses** que lee `Cadencia`, y no un enum;
- la próxima fecha **se deriva** —`ComunicacionPrevista::PROXIMA`, último
  `cubre_hasta` o `computa_desde` más la cadencia— y nunca se guarda;
- `cubre_hasta` **se congela al registrar** en `RegistrarComunicacion`, así que subir
  la cadencia no repinta lo que estaba cubierto;
- una comunicación es un hecho: **no se registra con fecha futura**;
- en la edición **no se mueven ni la fecha, ni el sentido, ni la línea del plan**,
  porque de ellos cuelga `cubre_hasta`. Se borra y se registra otra vez.

**«Cuando proceda» es una respuesta.** Sin cadencia, `computa_desde` es nula —`CHECK`
en las dos direcciones— y la línea no vence nunca, ni sale en el calendario ni en
el panel. Inventarle una cadencia sería pintar un plazo al que nadie se
comprometió.

### Una `Fuente` propia

`Fuente::Comunicacion`, con `Megaphone`, base `/plan-comunicacion`. Entra por
`acotarCalculado()` como las obligaciones, porque la fecha es una expresión. El
único rojo del módulo es el suyo: una línea periódica cuya fecha pasó sin
comunicarse, y sube al panel en «La organización», junto a las partes interesadas.

### A quién: sólo partes interesadas vigentes

`SincronizarDestinatarios` filtra los ids por `ParteInteresada::vigentes()` antes de
tocar la pivote, porque `sync()` no filtra nada, y pasa `organizacion_id` a mano,
porque `sync()` no rellena columnas extra. Lo que no es una parte registrada va en
`destinatarios_otros`. Los nombres de las restricciones de la pivote **van a mano**:
los generados pasaban de 63 caracteres, PostgreSQL los recortó y la clave foránea y
el índice único acabaron llamándose igual.

### La prueba es una evidencia, opcional

`evidencia_id`, como en el cumplimiento de un compromiso: la prueba cuenta además
como evidencia del control. Un adjunto propio se descartó por no montar subida,
descarga y pivote para lo que una evidencia ya hace.

### Lo que este módulo declara que no hace

- **No comprueba que se registre todo lo recibido.** El acta lo dice: la e) es lo
  que se apuntó.
- **No envía nada.** Registra que se comunicó; no manda el correo.
- **Lo recibido no se ata a la mejora o la no conformidad que abra.** Si una queja
  acaba en una, se abre desde su registro y se apunta en la respuesta.
- **Dos verbos y no tres**: un plan de comunicación no se firma.

**El permiso es `plan_comunicacion.*` y no `comunicacion.*`**, aunque cubre las dos
pantallas: el prefijo de un permiso tiene que casar con un `href` de
`lib/navegacion.ts` para que la página de la cuenta sepa nombrarlo, y lo vigila
`PermisosDeLaCuentaTest`. Con `comunicacion` no casaba con ninguno.
