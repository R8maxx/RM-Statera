---
paths:
  - app/Domain/Plataforma/**
  - routes/plataforma.php
  - resources/js/pages/plataforma/**
  - app/Http/Controllers/Plataforma/**
  - app/Http/Middleware/SoloPlataforma.php
  - app/Http/Middleware/SuscripcionVigente.php
  - app/Http/Requests/AltaOrganizacionRequest.php
  - app/Http/Requests/GuardarPlanRequest.php
  - app/Http/Requests/CambiarSuscripcionRequest.php
  - app/Http/Resources/OrganizacionPlataformaRecurso.php
---

# La plataforma

Es el punto 41, y abre el tramo «vendible». Hasta aquí una organización sólo
nacía en `DesarrolloSeeder`, y su primer responsable de seguridad no tenía
camino de entrada: `InvitarCuenta` exige que ya haya alguien dentro que invite.
Con un cliente real eso no se puede hacer entrando en la base.

César decidió cuatro cosas, y el resto se sigue de ellas:

- **Quien administra la plataforma vive en `users`**, con una marca. Usa la
  misma entrada, el mismo segundo factor, las mismas sesiones y la misma
  invitación que cualquiera. No hay un guard aparte.
- **Ve la ficha comercial de cada cliente y nada de lo que guarda dentro.**
  Entrar como soporte será en sólo lectura, y sólo por una ventana que abre el
  propio cliente (punto 44).
- **El cobro se modela y no se cobra**: plan y suscripción por organización
  (punto 43), sin pasarela.
- **Cuando la suscripción vence, la organización pasa a sólo lectura** tras un
  periodo de gracia. Nunca pierde datos.

## El administrador

**`users.es_plataforma`, con un `CHECK` de un solo sentido**:
`NOT (es_plataforma AND organizacion_id IS NOT NULL)`. Es de un sentido porque
`organizacion_id` ya es `nullOnDelete`, y una cuenta de cliente puede quedarse
sin organización sin volverse administradora. La columna no es `fillable`: se
pone con `forceFill`, para que nadie se la conceda por asignación masiva.

**Sólo nace desde la consola** (`plataforma:administrador`). Quien puede crear
administradores es quien tiene el servidor, que es la misma frontera que ya
protege las copias y el catálogo. Recibe una invitación por el broker
`invitaciones`, igual que una cuenta de cliente, y `AceptarInvitacion` tiene una
rama para él porque no hay tenant donde escribir su traza.

**No tiene rol de spatie.** Los permisos viven en el «team» de una organización,
y él no pertenece a ninguna. Por eso:

- `/plataforma/*` va con `SoloPlataforma` y no con `can:`.
- `SoloPlataforma` exige el segundo factor **también para leer**, igual que
  `/cuentas`. `ExigirDosFactores` no sirve aquí: decide por los permisos de
  escritura del rol, y el administrador no tiene rol, así que lo dejaría pasar.
- `HandleInertiaRequests` le pone en `auth.permisos` una sola marca,
  `plataforma.gestionar`. **No es un permiso de la base**: es la llave con la que
  `lib/navegacion.ts` le pinta el grupo «Plataforma». Quien autoriza es el
  middleware.
- En cualquier ruta de cliente recibe 403 por el `can:`. `/inicio` le manda a
  `/plataforma/organizaciones`, y el logotipo del layout también.

## La traza de la plataforma

**`eventos_plataforma`, y no `eventos_auditoria`.** Aquélla está bajo RLS y
cada fila tiene un tenant dueño, así que la entrada de un administrador o su
alta no tendrían dónde escribirse. Es la misma idea sin tenant:

- La base le quita a `statera_app` `UPDATE`, `DELETE` y `TRUNCATE`, igual que en
  `eventos_auditoria`.
- El `CHECK` de `accion` se construye desde `AccionPlataforma::cases()` en la
  migración, así que **un caso nuevo necesita una migración que lo amplíe**.
- **La columna se llama `organizacion_afectada_id` y no `organizacion_id`, a
  propósito.** No es una fila de datos propios de nadie, y con ese nombre
  `RlsDeclaradaTest` exigiría una política que aquí no significa nada.

**Lo que un administrador hace dentro de un tenant va a las dos trazas.** Dar de
alta una organización deja `organizacion_alta` en la de la plataforma y el
`creado` de `Organizacion` en la del cliente, que tiene que poder ver desde
cuándo existe sin pedírnoslo. Ese `creado` se escribe a mano, porque
`Organizacion::booted()` sólo registra `updated` (`organizacion.md`).

`RegistrarSesion` desvía a la traza de la plataforma la entrada, la salida y el
intento fallido de un administrador. Antes no se anotaban en ningún sitio,
porque la cuenta no tenía organización.

## El alta de una organización

**`AltaOrganizacion` es la única receta para crear un tenant.** Deja hecho lo
mínimo para que el cliente entre:

- la fila;
- los roles de su «team»;
- la invitación de su primer responsable de seguridad.

Todo lo demás lo hace el propio cliente: la ficha completa, el sistema y la
valoración. La aplicabilidad se deriva de lo que él valora y no la decide nadie
por él (invariante 4).

- **No cruza organizaciones.** Crea la fila y entra en ella con
  `paraOrganizacion()`, igual que un comando que visita los tenants de uno en
  uno. `comoMantenimiento()` sigue prohibido en una petición web, y no hace
  falta.
- **Comprueba que el catálogo ENS esté importado** antes de escribir nada. Sin
  él, el cliente se quedaría con un sistema al que no se le puede exigir nada.
- **El correo sale después de confirmar la transacción.** `InvitarCuenta` admite
  `enviar: false` para eso. Si el alta se deshace, nadie recibe un enlace a una
  cuenta que no existe.

## Lo que se lee desde la plataforma

Sólo `organizaciones`, `users` y `eventos_plataforma`: las tablas que ya
estaban, o que nacen, fuera de las tres capas. **Ningún recuento de una tabla
del tenant**, ni sistemas, ni riesgos, ni evidencias, por dos motivos:

- exigiría `comoMantenimiento()`;
- enseñaría el SGSI de un cliente que no ha abierto la puerta.

`{organizacion}` se resuelve con el binding implícito y sin acotar. Aquí eso es
lo correcto: `organizaciones` no tiene scope ni RLS, y a esas rutas sólo llega
la plataforma.

## El plan y la suscripción (punto 43)

**Se modelan y no se cobran.** Un plan no lleva precio. Lo que el producto
necesita saber de él es qué límites pone y cuántos días de gracia da antes de
pasar a sólo lectura.

**La suscripción vive en `organizaciones` y no en una tabla aparte**, en las
columnas `plan_id`, `suscripcion_inicia_en`, `suscripcion_vence_en` y
`suscripcion_referencia_externa`. Es el desvío principal respecto al plan, y
el motivo es el aislamiento:

- Una tabla `suscripciones` con `organizacion_id` necesitaría RLS, porque así
  lo exige `RlsDeclaradaTest`.
- Con RLS, la plataforma no podría listar las suscripciones de todos sus
  clientes sin `comoMantenimiento()`.
- Cada cliente tiene una sola suscripción, así que es un atributo de la raíz, y
  la raíz ya está fuera de las tres capas.

**El histórico sí va aparte (invariante 7)**, en `transiciones_suscripcion`.
Es inmutable por privilegios y lleva `organizacion_afectada_id`, igual que
`eventos_plataforma`.

**Un cambio de plan queda en tres sitios**:

- en el histórico;
- en la traza de la plataforma;
- en la traza del tenant. Ésta la escribe `Organizacion::booted()` sola, y por
  eso `CambiarSuscripcion` guarda **dentro de `paraOrganizacion()`**: una
  escritura que cruzara la frontera la tumbaría RLS al dejar el evento.

**El plan y las fechas no están en `$fillable`.** Así la ficha del cliente
(`GuardarFichaOrganizacion`, que hace `update($datos)`) no puede cambiárselos
aunque los mande en la petición, y hay un test que lo comprueba.

**`plan_id` nulo es «sin plan»**: sin límites y sin vencimiento. Es el estado
de toda organización anterior a este punto y el del uso interno. Sin `CHECK`
entre el inicio y el vencimiento, a propósito: un contrato ya vencido se
registra con su fecha pasada, y el inicio es el día en que se le puso el plan
en Statera.

### El estado se deriva

`EstadoSuscripcion::de()` lo saca de las fechas, igual que `EstadoCuenta`:

| Estado | Cuándo | Qué pasa |
|---|---|---|
| `Vigente` | hasta `vence_en`, o siempre si no vence | Se trabaja con normalidad. |
| `EnGracia` | hasta `vence_en + dias_gracia` | Se escribe, con una franja de aviso. |
| `SoloLectura` | después | No se escribe. |

**Sólo lectura no es perder nada.** `SuscripcionVigente` va justo detrás de
`EstablecerContextoOrganizacion` y corta toda petición que no sea segura. Se
sigue entrando, viendo y descargando: la SoA ya entregada y las evidencias que
vio el auditor son del cliente.

**La cuenta propia queda fuera del corte**: `logout`, `perfil/*` y `user/*`
(contraseña, segundo factor y passkeys). Un impago no puede dejar a alguien
sin poder proteger su cuenta. La lista va por camino porque esas rutas son de
Fortify y del paquete de passkeys, no nuestras.

`HandleInertiaRequests` comparte `suscripcion` **sólo en gracia o en sólo
lectura**, y `AppLayout` la pinta como franja encima del contenido. El rojo se
reserva para sólo lectura: en gracia todavía se puede trabajar.

### Los límites

`LimitesDelPlan`, comprobado en el `after()` de dos `FormRequest`, para que el
error salga junto al campo:

- **Sistemas** (`GuardarSistemaRequest`): sólo al dar de alta. Cuenta por el
  scope de la organización.
- **Cuentas** (`GuardarCuentaRequest`): al invitar, y también al quitarle el rol
  de auditor a alguien, porque eso añade un asiento.

**El auditor externo no ocupa asiento.** Se le da acceso para que audite, y
cobrárselo al cliente sería castigarle por dejarse auditar. **Una invitación
pendiente sí lo ocupa.** Quien administra la plataforma no cuenta nunca,
porque no tiene organización.

**Un plan no se borra: se retira con `activo`.** Deja de salir al dar de alta y
quien ya lo tiene lo conserva. El desplegable de la ficha ofrece los activos y,
además, el plan que ya tiene esa organización.

## Desvíos respecto al plan

- **`DesarrolloSeeder` no pasa por `AltaOrganizacion`.** El seeder necesita
  cuentas con contraseña conocida y activas, y la receta las invita. Lo que sí
  hace es sembrar un administrador sintético, `plataforma@statera.test`, con la
  contraseña de desarrollo.

## Lo que la plataforma declara que no hace todavía

- **No cobra.** `suscripcion_referencia_externa` es el hueco para una
  pasarela, y nada lo lee.
- **No avisa del vencimiento por correo**, ni a la plataforma ni al cliente. La
  franja sale al entrar, y nada más.
- **Superar un límite no quita nada.** Si se baja de plan a uno con menos
  cuentas de las que ya hay, nadie se desactiva: simplemente no se puede añadir
  otra.
- **No hay baja de organizaciones**, ni desactivación desde la plataforma.
  `organizaciones.activa` sigue sin lector.
- **No hay alta de administradores desde la web**, ni lista de administradores.
- **La plataforma no reenvía la invitación del responsable.** Si caduca, el
  cliente no tiene a nadie dentro que la reenvíe. Por ahora se arregla dando de
  alta otra vez con otro correo, o desde la consola.
