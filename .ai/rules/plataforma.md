---
paths:
  - app/Domain/Plataforma/**
  - routes/plataforma.php
  - resources/js/pages/plataforma/**
  - app/Http/Controllers/Plataforma/**
  - app/Http/Middleware/SoloPlataforma.php
  - app/Http/Middleware/CapacidadDePlataforma.php
  - app/Http/Middleware/SuscripcionVigente.php
  - app/Http/Middleware/SoporteSoloLectura.php
  - app/Http/Requests/AbrirSoporteRequest.php
  - app/Http/Requests/AltaOrganizacionRequest.php
  - app/Http/Requests/GuardarPlanRequest.php
  - app/Http/Requests/CambiarSuscripcionRequest.php
  - app/Http/Resources/OrganizacionPlataformaRecurso.php
  - app/Http/Resources/EventoPlataformaRecurso.php
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

## La ventana de soporte (punto 44)

**La abre el cliente y no la plataforma.** César lo decidió así porque es lo
que un auditor ENS o ISO acepta sin discusión: el acceso de un tercero lo
autoriza el dueño de los datos, por un tiempo y queda registrado. El
responsable de seguridad la abre desde `/organizacion` (`organizacion.gestionar`
y segundo factor) para un plazo de entre una hora y siete días, y la cierra
cuando quiere.

**Es una fecha y no un interruptor**: `organizaciones.soporte_hasta`. Vive en la
raíz por lo mismo que la suscripción: la plataforma tiene que saber qué
clientes tienen la puerta abierta sin cruzar RLS. La ventana se cierra sola al
pasar el plazo, aunque nadie se acuerde. Abrir y cerrar quedan en la traza del
tenant, porque `Organizacion::booted()` registra el cambio de la columna.

**Entrar es fijar el contexto, no saltárselo.** La clave
`SesionDeSoporte::CLAVE` guarda en la sesión la organización.
`EstablecerContextoOrganizacion` tiene una rama para quien administra la
plataforma:

- sin clave, olvida el contexto;
- con clave y la ventana abierta, hace `establecer()` sobre esa organización;
- con la ventana cerrada o caducada, limpia la sesión y le manda a
  `/plataforma`.

**Se comprueba en cada petición**, así que cerrar la puerta echa a quien esté
dentro en su siguiente paso. **Y se comprueba que sea la misma ventana**: la
sesión guarda también el `soporte_hasta` con el que se entró
(`SesionDeSoporte::CLAVE_VENTANA`). Lo encontró la revisión de seguridad: con
sólo la organización, si el cliente cerraba y volvía a abrir entre dos
peticiones, quien estaba dentro seguía por la ventana nueva sin pasar por la
entrada, es decir, sin evento en la traza y sin correo al cliente. Las tres capas siguen aplicando, y nada de esto
usa `comoMantenimiento()`.

**Sólo lectura, con dos cerrojos:**

1. **`Gate::before`, en `AppServiceProvider`.** A quien administra la
   plataforma, con contexto puesto, le concede los permisos `.ver` y le niega
   todos los demás de forma explícita. Son exactamente los del auditor, cosa
   que `RolesTest` garantiza. Sin contexto, devuelve `null` y la decisión cae a
   spatie, que no le da nada.
2. **`SoporteSoloLectura`**, en el grupo `web` justo detrás del contexto. Corta
   cualquier petición que no sea segura, venga por donde venga: una ruta sin
   `can:`, un permiso mal clasificado o un endpoint que nadie revisó. Sólo deja
   escribir lo suyo: `logout`, `plataforma/*`, `perfil*` y `user/*`.

**El soporte no ve `/cuentas` ni `/organizacion`**, que no tienen permiso
`.ver`. Tampoco puede cerrar él la ventana: la puerta es del cliente.

**Al entrar se avisa por correo** a los responsables de seguridad
(`EntradaDeSoporte`, en cola y con escalares). La entrada y la salida quedan en
las dos trazas, con dos verbos nuevos en `AccionAuditada`: `soporte_entrada` y
`soporte_salida`.

**Los props compartidos leen la organización del contexto**, y no la de la
cuenta. `organizacion` y `auth.permisos` salían de `$usuario->organizacion`, y
para el soporte eso es nulo. `soporte` alimenta la franja del layout, que lleva
el botón de salir siempre a mano.

## Desvíos respecto al plan

- **`DesarrolloSeeder` no pasa por `AltaOrganizacion`.** El seeder necesita
  cuentas con contraseña conocida y activas, y la receta las invita. Siembra
  `plataforma@statera.test` (administrador sin organización),
  `plataforma.miembro@statera.test` (administradora y técnica de la
  organización de pruebas) y, en `escenariosDePlataforma()`, un cliente por
  cada estado: vigente con soporte abierto, por vencer, en gracia, en sólo
  lectura, de baja y sin estrenar. Cada uno tiene su `responsable@<cliente>.test`.
  Todas las cuentas usan la contraseña de desarrollo, salvo la del cliente sin
  estrenar, que está invitada. Las fechas son relativas a hoy, así que el
  escenario no caduca.

## Lo que la plataforma declara que no hace todavía

- **No cobra.** `suscripcion_referencia_externa` es el hueco para una
  pasarela, y nada lo lee.
- **No se registra cada página que ve el soporte**, sólo cuándo entra y cuándo
  sale. Como el auditor externo, que tampoco deja rastro de lectura.
- **Superar un límite no quita nada.** Si se baja de plan a uno con menos
  cuentas de las que ya hay, nadie se desactiva, pero no se puede añadir otra.
  La ficha de plataforma lo dice en un aviso **neutro**, encima de la franja de
  estado, y no en rojo como al principio: pasarse del límite no es un error,
  igual que un dato sin completar (DESIGN.md § 9). El formulario de la
  suscripción lo anticipa en «Lo que sale de aquí» antes de guardar, con los
  límites y la gracia que `planesActivos()` manda de cada plan.
- **La baja no borra los datos.** Borrar de verdad a un cliente que se va es un
  proceso destructivo aparte, con sus plazos de conservación, y no existe.
- **No hay alta de administradores desde la web**, ni lista de administradores.

## Lo que destapó el recorrido en el navegador

Tres fallos que ningún test de servidor veía. Los tres se corrigieron en el
mismo commit.

1. **El lateral del administrador enseñaba el SGSI entero.** `navegacionPara()`
   sólo filtraba las entradas con `permiso:`, y casi ninguna lo lleva, porque
   cualquier rol de cliente las ve. Ahora una entrada sin `permiso` exige estar
   dentro de una organización, es decir, tener algún `.ver`.
2. **Un prop de página con el nombre de uno compartido lo pisa.** La ficha de
   plataforma mandaba `organizacion` y `suscripcion`, y el layout pintaba esa
   organización como la activa y un aviso de vencimiento falso. `/organizacion`
   hacía lo mismo con `suscripcion` y `soporte`. Ahora se llaman `cliente` y
   `contrato` en la ficha de plataforma, y `contrato` y `accesoSoporte` en
   `/organizacion`. **Los nombres de los props compartidos (`auth`,
   `organizacion`, `suscripcion`, `soporte`) no se usan como props de página.**
   `organizacion/Editar` ya mandaba `organizacion` antes de este punto, y se
   queda así porque es la misma organización.
3. **`/` llevaba fijo a `/panel`.** Al cerrar sesión se volvía ahí, Laravel
   guardaba `/panel` como destino y el administrador entraba directo a un 403.
   Ahora `/` lleva a `/inicio`, que decide por cuenta.

«Primeros pasos» ofrecía botones de alta a quien sólo lee, sea el auditor o el
soporte, y llevaban a un 403. Ahora cada paso declara su permiso, y sin él la
tarjeta lo explica en lugar de pintar el botón (punto 45).

## El administrador que además es de una organización (punto 45)

César pidió contar con ello «por si acaso»: **una sola cuenta** que administra
la plataforma y trabaja en una organización con su rol. El `CHECK` del punto
41 se fue. Lo que lo sustituye es de dominio:

- **En su organización es un usuario más.** El contexto se fija por la rama
  normal de `EstablecerContextoOrganizacion` y decide spatie, con su rol. Sólo
  va por la rama de soporte si hay clave de soporte en la sesión.
- **En cualquier otra sólo entra como soporte, y en lectura.**
  `SesionDeSoporte::activo()` es la única pregunta: plataforma, con contexto,
  y el contexto no es el suyo. La usan `Gate::before`, `SoporteSoloLectura` y
  los props compartidos, para que los tres digan lo mismo. **Entrar como
  soporte en la suya se rechaza** (`SoporteNoPermitido::esLaSuya`).
- **No ocupa asiento** (`LimitesDelPlan` filtra `es_plataforma`): a los
  administradores del programa no se les cobra.
- **No puede ser auditor externo.** Ese rol lleva `acceso_hasta`, y al caducar,
  `EstadoCuenta` dejaría la cuenta entera fuera, plataforma incluida. Lo
  impiden `InvitarCuenta` y `CambiarRol`.
- **El cliente no puede dejarle fuera de Statera.** «Desactivar» a una cuenta
  de la plataforma la **saca de la organización**: quita el rol del «team», el
  alcance y el vínculo con su persona, y pone `organizacion_id` a nulo. La
  traza se escribe antes de soltar la organización, porque después no tendría
  dónde escribirse. El cliente la ve marcada como «De la plataforma» en su
  lista de cuentas y en su ficha.
- **Cómo llega a una organización: sólo por la plataforma, nunca por el
  cliente.** Lo une `UnirAdministrador`, por uno de estos dos caminos:
  - al dar de alta una organización con su correo como responsable. Es la
    única regla de correo que le deja pasar (`CorreoDeCuenta::libre()`);
  - con `plataforma:administrador correo "Nombre" --organizacion=ID --rol=…`.

  **Un cliente que invita su correo recibe el error genérico de correo en
  uso.** La primera versión le unía ahí mismo, y la revisión de seguridad lo
  tumbó con razón: cualquier responsable podía meter a un administrador en su
  organización sin que éste lo aceptara, y la respuesta le confirmaba que el
  correo era de la plataforma. `InvitarCuenta` volvió a como estaba.
- **Una cuenta de cliente puede pasar a administradora**: si
  `plataforma:administrador` recibe su correo, la promueve
  (`AltaAdministrador::promover`) en lugar de crear otra, y conserva su
  organización y su rol. La busca por el proveedor del guard, igual que el
  login.
- **Sólo puede ser de una organización**, porque `users.organizacion_id` es una
  columna y no una relación. Ser de varias es otro modelo de cuentas.

**La plataforma reenvía la invitación de un cliente** desde su ficha: hacía
falta para el primer responsable, que si deja caducar el enlace no tiene a
nadie dentro que se lo reenvíe. El parámetro de ruta es `{cuentaId}` y no
`{cuenta}`, porque ése lo resuelve el binding global acotado al contexto, que
desde la plataforma no hay. Se acota en el controlador con
`where('organizacion_id', …)`.

## Los avisos de vencimiento y la baja (punto 46)

### Los avisos

`suscripciones:avisar`, a diario a las 07:15 y con `--dry-run`. Al responsable
de seguridad de cada cliente le avisa en cinco hitos (`HitoSuscripcion`): 30, 7
y 1 días antes, al entrar en gracia y al pasar a sólo lectura. Quien administra
la plataforma recibe un resumen de a quién se avisó.

- **Por días de calendario y no por horas.** El aviso de las 07:15 del día
  anterior tiene que decir «mañana» aunque falten cuarenta horas.
- **Sólo el hito en que está hoy.** Si el planificador estuvo parado una semana,
  nadie necesita recibir a la vez «faltan 30» y «faltan 7».
- **Cada aviso sale una vez**: `avisos_suscripcion` guarda el hito y el
  vencimiento, con clave única. Al renovar, el vencimiento cambia y los avisos
  vuelven a empezar.
- **Y cada aviso queda en la traza de la plataforma** (`aviso_vencimiento_enviado`),
  con el hito, el vencimiento y los correos a los que fue, en `detalle`.
  `avisos_suscripcion` sirve para no repetir; la traza sirve para que la ficha
  del cliente conteste «¿le avisamos?». **También se anota el aviso que no tenía
  a quién llegar**, porque el cliente no tenía un responsable activo: es el aviso
  que más conviene ver. `EventoPlataforma::resumen()` lo convierte en la frase de
  la ficha. Como el comando escribe varios en el mismo instante, la ficha ordena
  también por `id`.
- **No necesita contexto**: todo lo que lee está fuera de RLS. Se salta las
  organizaciones de baja y las que no tienen plan o vencimiento.

### La baja

**Un estado que se deshace, nunca un borrado** (`BajaOrganizacion`). Lleva
`baja_en`, `motivo_baja` y `activa` a falso; `activa` existía desde la primera
migración sin que nadie la leyera. Se escribe dentro del contexto del cliente,
así que su traza dice desde cuándo y por qué estuvo de baja.

**Lo que hace la baja:**

- **Nadie de la organización entra.** El login lo dice en
  `RechazarCuentaNoVigente`, y a quien ya estaba dentro `CuentaVigente` le saca
  en la siguiente petición.
- **Quien además administra la plataforma sigue entrando a ella**, pero sin esa
  organización: `EstablecerContextoOrganizacion` no fija contexto para una
  organización de baja.
- **Se cierra la ventana de soporte**, y no se puede abrir otra ni entrar.
- **Los procesos diarios se la saltan**: `avisos:enviar`, `indicadores:medir` y
  `suscripciones:avisar`. **`personas:seudonimizar` no se la salta, a
  propósito**: la retención del RGPD sigue corriendo aunque el cliente se haya
  ido.

**Reactivar** lo deshace todo menos la ventana de soporte, que la vuelve a
abrir el cliente si la quiere.

### Dos fallos más que se cerraron aquí

- **`/organizacion` mandaba un prop `organizacion`** que pisaba al compartido,
  y el lateral perdía el logo justo en la pantalla donde se sube. Ahora se
  llama `ficha`, y `FichaTest` comprueba que el compartido sigue llevando el
  logo.
- **Las etiquetas QR leían la organización de la cuenta**, y dentro del
  soporte salían sin QR. Ahora leen la del contexto.

### La organización contra la que se valida es la del contexto (punto 47)

Veintitrés `FormRequest` acotaban sus `exists` y `unique` con
`$this->user()?->organizacion_id`, y `CuentaController::index` contaba con lo
mismo. Hasta el punto 44 era lo mismo que el contexto. Desde que una cuenta de
la plataforma puede mirar una organización que no es la suya, ya no.

Ahora todos usan `DeLaOrganizacionActiva::organizacionActiva()`, que lee
`ContextoOrganizacion::idObligatorio()`. Es `idObligatorio()` y no `id()`
porque un `where` con nulo es un `whereNull`. **Un `FormRequest` nuevo que
acote por organización usa el trait**, no la cuenta.

**`GuardarFotoPerfil` se queda con la de la cuenta, a propósito**: la foto es
de la persona y no de la organización que se está mirando.

## Los perfiles (punto 48)

**Dos perfiles, `users.perfil_plataforma`**: Administración, que puede todo, y
Gestión comercial, que lleva clientes, planes, suscripciones y la traza. Un
`CHECK` ata la marca al perfil: `es_plataforma` si y sólo si hay perfil. Así no
existe un administrador sin perfil, que no podría hacer nada sin saber por qué.

**Cada ruta de `/plataforma` exige una `CapacidadPlataforma`** con el
middleware `plataforma:<capacidad>` (`CapacidadDePlataforma`), y el perfil
decide cuáles tiene (`PerfilPlataforma::capacidades()`). `PerfilesTest` recorre
las rutas y se pone rojo con una que no lleve ninguna. La única excepción
declarada es salir del soporte, porque quien está dentro tiene que poder salir
siempre.

**Al cliente le llegan como `plataforma.<capacidad>`** en `auth.permisos`, y
`lib/navegacion.ts` y las fichas deciden con ellas qué pintar. Ojo con una
trampa: `plataforma.clientes.ver` acaba en `.ver`, así que `navegacionPara()`
excluye el prefijo `plataforma.` al deducir si se está dentro de una
organización.

`AccesoDeSoporte` exige además `soporte.entrar` en el dominio, no sólo en la
ruta.

## Los administradores desde la web (punto 49)

`/plataforma/administradores` (`administradores.gestionar`): lista, invitar,
cambiar el perfil y retirar. Las reglas viven en `AdministradoresDePlataforma`,
calcadas de `ResponsablesDeSeguridad`:

- **nadie se cambia ni se retira a sí mismo**;
- **la plataforma nunca se queda sin alguien de Administración activo**. Una
  cuenta desactivada no cuenta.

**Retirar no borra.** Quita la marca y el perfil. Si la cuenta no es de ninguna
organización, además se desactiva con `DesactivarCuenta`. Si es de una (punto
45), sigue siendo usuario suyo.

**Cada acción pide la contraseña de quien la hace en la misma petición**
(`current_password`), como `CerrarSesionesRequest`, y no con
`password.confirm`, que vuelve con un `GET` y aquí todo es `POST` o `PUT`.

**`{administrador}` es un entero** que se busca sólo entre las cuentas de la
plataforma: el id de una cuenta de cliente responde 404.

**`ConsultasDeUsuarioAcotadasTest` acepta ahora `'es_plataforma', true` como
acotación.** Una consulta de quienes administran no lista a los usuarios de
ningún cliente, y los administradores no tienen `organizacion_id` por el que
acotar.

Convertir en administradora una cuenta de cliente que ya existe sigue siendo
cosa de la consola. Desde la web, el correo tiene que estar libre.

## La traza consultable (punto 50)

`/plataforma/traza` (`traza.ver`, de los dos perfiles), con `EventoPlataformaRecurso`
sobre la capa de recursos. Filtra por acción, por quién, por cliente y por
fechas, y se exporta a CSV con lo que ya trae `DataTable`. La ficha del cliente
enlaza con su filtro puesto.

**El detalle sale siempre por `EventoPlataforma::detalleLegible()`**, que se
salta una lista fija de claves secretas (`password`, `token`, los del segundo
factor) aunque algún día alguien las escriba ahí por error. Lo que se ve en
pantalla y lo que se exporta a CSV es lo mismo.

**Y un cerrojo más en el soporte, que encontró la revisión de seguridad:**
`EstablecerContextoOrganizacion` comprueba `soporte.entrar` en cada petición, y
no sólo al entrar. A quien bajan a Gestión comercial estando dentro de un
cliente se le acaba el acceso en su siguiente paso.
