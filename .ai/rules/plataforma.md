---
paths:
  - app/Domain/Plataforma/**
  - routes/plataforma.php
  - resources/js/pages/plataforma/**
  - app/Http/Controllers/Plataforma/**
  - app/Http/Middleware/SoloPlataforma.php
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

## Desvíos respecto al plan

- **`DesarrolloSeeder` no pasa por `AltaOrganizacion`.** El seeder necesita
  cuentas con contraseña conocida y activas, y la receta las invita. Lo que sí
  hace es sembrar un administrador sintético, `plataforma@statera.test`, con la
  contraseña de desarrollo.

## Lo que este punto declara que no hace todavía

- **No hay baja de organizaciones**, ni desactivación desde la plataforma.
  `organizaciones.activa` sigue sin lector.
- **No hay alta de administradores desde la web**, ni lista de administradores.
- **La plataforma no reenvía la invitación del responsable.** Si caduca, el
  cliente no tiene a nadie dentro que la reenvíe. Por ahora se arregla dando de
  alta otra vez con otro correo, o desde la consola.
