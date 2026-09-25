---
paths:
  - resources/js/pages/cuentas/**
  - resources/js/pages/auth/AceptarInvitacion.vue
  - app/Domain/Usuario/**
  - app/Http/Controllers/CuentaController.php
  - app/Http/Controllers/InvitacionController.php
  - app/Http/Middleware/CuentaVigente.php
  - app/Http/Middleware/BloqueoPorInactividad.php
  - app/Http/Middleware/EscribeLoSuyo.php
  - app/Domain/Autorizacion/EscrituraPropia.php
  - app/Domain/Autorizacion/Concerns/AcotadoPorAlcance.php
---

# Las cuentas (§ 4.19)

Es el punto 28. Hasta aquí una cuenta sólo se creaba desde el `DesarrolloSeeder`,
así que nadie salvo las tres cuentas sintéticas podía usar la herramienta. Los
enums de rol y de permiso ya existían, igual que el seeder y la separación por
organización con los *teams* de spatie. Lo que faltaba era todo lo demás: dar de
alta, cambiar de rol, quitar la entrada y las dos frases del § 4.19 que ninguna
lista de permisos expresa, el auditor «limitado al alcance auditado» y el técnico
que «ve sus tareas y las implantaciones a su cargo».

## Tres decisiones que lo hacen pequeño

**El estado de una cuenta no se guarda: se deriva.** `EstadoCuenta::de()` lo saca
de cuatro fechas de `users`: `invitada_en`, `activada_en`, `desactivada_en` y
`acceso_hasta`. Caducar es que `acceso_hasta` ya pasó, así que nadie tiene que
cambiar una columna a las cero horas. Es la decisión de la conformidad del
§ 4.17, «caducar es una fecha y no un estado». El orden de `de()` es el de
precedencia: una cuenta desactivada lo está aunque además haya caducado.

**Desactivar, nunca borrar.** La fila es autora, responsable y firmante en todo el
histórico. Borrarla dejaría a nulo el `usuario_id` de la traza, que es
`nullOnDelete`. `DesactivarCuenta` cierra además las tres puertas por las que la
cuenta seguiría dentro:

- las sesiones abiertas de la tabla `sessions`;
- el «recordarme», porque cambia el `remember_token`;
- la invitación pendiente, porque borra el token.

**La traza se escribe a mano, y `User` no lleva `RegistraTraza`.** `users` se crea
desde seeders, desde tests y desde otras organizaciones sin contexto, y
`eventos_auditoria` está bajo RLS. Con el trait, crear una cuenta de otra
organización en un test reventaría con un error de privilegios. Cada acción
llama a `RegistroTraza::evento()`, que pasa por la misma puerta que los tres
verbos de fila. Para lo que no es una fila hay cuatro verbos nuevos en
`AccionAuditada`: `rol_cambiado` (el rol vive en una pivote de spatie sin modelo),
`inicio_sesion`, `cierre_sesion` e `intento_fallido`.

## La invitación

**Nadie escribe la contraseña de otro.** La cuenta nace con una contraseña
aleatoria de 64 caracteres que nadie conoce y sin `activada_en`, así que hay dos
cerrojos. El correo lleva un enlace a `/invitacion/{token}`.

**Tiene su broker y su tabla**: `invitaciones` e `invitacion_tokens`, en
`config/auth.php`. No reutiliza `/reset-password` de Fortify por dos motivos:

- aquel valida contra el broker `users`, que caduca en una hora, y una invitación
  tiene que durar lo que tarda alguien en leer el correo (siete días);
- con la tabla compartida, pedir una contraseña nueva pisaría una invitación
  pendiente.

`invitacion_tokens` no lleva `organizacion_id`, por lo mismo que la de Fortify: se
consulta antes de saber de qué organización es nadie. `RlsDeclaradaTest` no la
mira, porque sólo mira tablas con esa columna.

**Aceptar no abre sesión: manda al login.** Así la primera entrada pasa por la
misma tubería que todas: el rechazo de cuentas no vigentes, el segundo factor y
el registro de la sesión.

**El nombre y el correo sólo se escriben al invitar.** Después son de la propia
cuenta y se cambian en `/perfil`. `GuardarCuentaRequest` los prohíbe en la
edición, porque reescribir el correo de otro es quedarse con su cuenta pidiendo
una contraseña nueva.

## Quién no entra, y por dónde se le cierra

- **`RechazarCuentaNoVigente`**, un paso de la tubería de login de Fortify
  (`FortifyServiceProvider::authenticateThrough`).
  - Va delante del segundo factor. Sin eso, una cuenta desactivada con 2FA
    llegaría a la pantalla del código, que es decirle que la contraseña era
    buena.
  - **Sólo habla si la contraseña es la buena.** Con la mala, el mensaje es el de
    siempre (`auth.failed`): decir «desactivada» a quien prueba correos es decirle
    cuáles existen.
  - Busca la cuenta con el proveedor del guard y no con `User::query()`.
- **`CuentaVigente`**, en el grupo `web` delante del contexto. Cubre los dos
  caminos que no pasan por la tubería: la sesión que ya estaba abierta y **el
  login con passkey**, que tiene su propio controlador.
- **El último responsable de seguridad no se va.** `ResponsablesDeSeguridad::quedaOtro()`
  cuenta los que pueden entrar de verdad —no desactivados, aceptados y sin
  caducar—. `CambiarRol` y `DesactivarCuenta` lo impiden, no avisan. Tampoco se
  desactiva ni se quita el rol a sí mismo quien hace la petición.

## El alcance del auditor

**Por sistema y con fecha de fin**, que es la decisión de César. Un auditor sin
sistemas o sin fecha no se da de alta (`AlcanceDeCuenta`, y otra vez en el
`FormRequest` para que el error salga junto al campo). Al cambiar a otro rol, los
dos se borran.

**Es una cuarta capa encima de las tres y no quita ninguna.** Vive en
`ContextoOrganizacion::acotarASistemas()`, la fija `EstablecerContextoOrganizacion`
justo después de la organización (`cuenta_sistemas` está bajo RLS) y la aplica el
scope global del trait `AcotadoPorAlcance`. No choca con la prohibición de
`withoutGlobalScopes()`, y RLS no puede hacerlo porque dentro de una organización
no distingue a nadie.

**Cómo se acota cada modelo lo decide el modelo** (`acotarAlAlcance()`):

- **Por `sistema_id`**, por defecto: implantación, auditoría, valoración,
  designación de rol y conformidad.
- **Por `sistema_id`, dejando pasar la fila sin sistema**: documento, incidente
  y compromiso. Un documento sin sistema es de toda la organización —la política,
  el acta—, y el auditor lo necesita para auditar cualquier sistema. Un incidente
  sin atribuir no se le esconde, porque es justo lo que está sin clasificar.
- **Por una relación, con `whereHas`**: activo (por sus sistemas), riesgo (por
  sus activos) y evidencia (por sus implantaciones). No se repite la lista: la
  consulta de la relación ya lleva el scope en el modelo del otro lado. Una
  evidencia se ve si prueba **algo** de su sistema (invariante 6).
- **Sistema**: por su propio `id`.

**Lo que queda sin acotar, a propósito**: contexto, partes interesadas, política,
revisión por la dirección, objetivos, indicadores, mejoras, personas, puestos y
formación. Es el SGSI entero y no un sistema.

**Sin filas, el alcance no acota**, y eso es un agujero con nombre: un auditor
anterior al § 4.19, o uno al que se le borró su único sistema —la fila se va en
cascada con él—, ve la organización entera. Ninguna cuenta nueva puede quedar
así, así que no se cierra el paso en silencio: se **señala**, en rojo, en la
lista de cuentas y en la ficha (`ResumenCuentas::auditoresSinAlcance()`). Lo
destapó el recorrido: el auditor sembrado era de antes y no tenía sistema.

**`AlcanceDelAuditorTest` descubre en vez de enumerar**: todo modelo de
`app/Domain/*/Models/` cuya tabla tenga `sistema_id` tiene que usar el trait.
`CuentaSistema` es la excepción declarada: es la tabla que define el alcance.

## El técnico escribe lo suyo

**Lee todo y escribe lo suyo**, que es como se decidió leer la frase del § 4.19.
Sigue viendo la organización entera: la evidencia compartida con la medida de
otro, la tarea de la que depende la suya. Lo que no puede es mover una tarea o
una implantación que está a cargo de otra persona. **Lo que no tiene responsable
es de todos**: una implantación recién generada por el motor nace sin nadie.

**`EscribeLoSuyo` es un middleware en el grupo de escritura**, y no el
`authorize()` de cada `FormRequest` como decía el plan. La mitad de esas rutas no
tienen `FormRequest` —borrar una tarea, desvincular una evidencia—, y en el grupo
una ruta nueva hereda la regla sin que nadie se acuerde. Mira el registro
principal de la ruta: la tarea si la hay y, si no, la implantación. Responde 403
y no 404, porque la tarea existe y se ve.

- **Las acciones masivas** no tienen registro en la ruta y filtran en su
  controlador: se saltan las ajenas y se dice cuántas, igual que las que no
  admiten la transición.
- **En el tablero** la tarjeta ajena llega sin transiciones y sin
  `descartable`. Sin eso, el técnico la arrastraría, el servidor diría 403 y la
  tarjeta volvería sola: un gesto que parece funcionar y no funciona. Sin
  destinos tampoco se arrastra (`canDrag`), el menú no se pinta y el asa se
  queda invisible —no se quita, porque ocupa el hueco que alinea el título con
  los badges—.
- **Las fichas** reciben `puedeEscribir`/`puedeGestionar` y `aCargoDeOtro`: dicen
  de quién es en lugar de esconder los botones sin más. De paso, **el auditor deja
  de ver en esas dos fichas botones que le respondían 403**: antes se pintaban
  para cualquiera. En la de implantación, además, los campos salen deshabilitados
  y el bloque de evidencias sin «Adjuntar» ni «Desvincular» (`editable`), porque
  un formulario que se deja rellenar y no tiene botón de guardar es una trampa.
- **«Responsable: yo» por defecto** (`EmpiezaPorLoMio`). La primera visita de la
  sesión, sin filtros en la URL, redirige a la misma tabla con el filtro puesto,
  **si tiene algo a su cargo**: sin nada, entraría a una tabla vacía, que fue lo
  que enseñó el recorrido en implantaciones.
  **Si se quita, no vuelve**: una tabla que se re-filtra sola cada vez que alguien
  limpia los filtros es una tabla que no se puede ver entera. No es una
  restricción, es un punto de partida.

## A quién se le puede encargar algo

**`CuentasAsignables` decide quién sale en el desplegable de «Responsable»**:
quien tiene el permiso de gestionar el módulo, no está desactivado y no tiene el
acceso caducado. Lo destapó el recorrido del técnico por vulnerabilidades: la
lista eran todas las cuentas de la organización, y ofrecía al auditor externo
como responsable de remediar, que no puede escribir y que no debe, porque audita
lo que se hace. El `FormRequest` lo comprueba con la misma clase, así que no se
cuela por la petición.

**El responsable que ya estaba se conserva** aunque haya dejado de cumplirlo:
sin él, editar la ficha vaciaría el campo en silencio.

**Sólo lo usan vulnerabilidades y proveedores.** Hay otros veintiún controladores
con su `User::query()` propio —la mayoría sin excluir tampoco a los desactivados—
y no se han tocado: es una decisión de alcance pendiente, no un olvido.

## La sesión

`BloqueoPorInactividad`, con `seguridad.inactividad_minutos` (30 por defecto,
`INACTIVIDAD_MINUTOS`). La marca es de la aplicación y vive en la sesión; no
depende de `SESSION_LIFETIME`, que cuenta cualquier cosa que llegue a la cookie.
**En la suite está a cero** (`phpunit.xml`), porque muchos tests viajan en el
tiempo entre dos peticiones de la misma sesión. `SesionesTest` lo enciende a
mano.

`RegistrarSesion` escribe dentro de la organización de la cuenta con
`paraOrganizacion()`: al entrar todavía no hay contexto, porque el middleware que
lo fija corrió antes de que hubiera usuario. El intento fallido sólo se registra
cuando el correo es de una cuenta; un correo que no existe no tiene a quién
anotárselo.

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **La tubería de login de Fortify está copiada en `FortifyServiceProvider`** para
  meter un paso. Es la misma lista y el mismo orden que
  `AuthenticatedSessionController::loginPipeline()`. Si Fortify la cambia, esto se
  queda con la vieja sin avisar: **hay que mirarla al actualizar el paquete.**
- **`{cuenta}` se resuelve con `Route::bind` en `routes/web.php`**, acotado a la
  organización del contexto, porque `User` no tiene scope global. Usa
  `id() ?? 0` y no `id()`: un `where` con nulo es un `whereNull` y encontraría
  justo las cuentas sin organización. Corre antes que `auth`, así que tampoco
  puede reventar sin contexto.
- **`/cuentas` va detrás del segundo factor también para leer**, que es la
  excepción del producto. Lo que se lee es a quién habría que robarle la cuenta.
- **`cuentas.gestionar` no tiene `.ver`**, igual que `organizacion.gestionar`, y
  por el mismo motivo: un `.ver` se lo daría al auditor por `RolesTest`. Tiene
  entrada en `lib/navegacion.ts` con `permiso:`, así que el sidebar sólo la
  enseña a quien la puede abrir.

- **Un permiso nuevo en `Permiso` no llega solo a las organizaciones que ya
  existen.** `SembrarRoles` sólo corre al dar de alta una organización y desde el
  seeder, y la suite siembra en cada alta, así que **los tests no lo ven**. Lo
  destapó el recorrido en el navegador: el responsable recibía un 403 en
  `/cuentas`. Lo arregla `2026_09_28_090400_sembrar_permiso_de_cuentas`, que
  vuelve a pasar `SembrarRoles` por todas. **El módulo que añada el siguiente
  permiso necesita su migración igual**, y ninguno de los anteriores la llevaba:
  hasta aquí sólo había la base de desarrollo, que se resembraba a mano.

## Lo que este módulo declara que no hace todavía

- **No acota al auditor en tareas, no conformidades, mejoras ni hallazgos
  sueltos.** Son del SGSI entero o cuelgan de varios orígenes, y una tarea no
  tiene sistema. Un auditor de un sistema ve el plan de acción de toda la
  organización. Los hallazgos se llegan desde su auditoría, que sí está acotada.
- **Desactivar no reasigna nada.** Las tareas, implantaciones y riesgos que tenía
  a su cargo siguen a su nombre, y nada avisa de que el responsable ya no entra.
- **No hay pantalla de traza general.** La ficha de una cuenta enseña sus veinte
  eventos más recientes. La traza entera sigue sin leerse desde la interfaz.
- **Un solo rol por cuenta.** `syncRoles` deja uno; el modelo de spatie admitiría
  más, y ninguna pantalla los pinta.
- **El sondeo cuenta como actividad**: una pestaña con una recarga periódica
  mantiene viva la sesión.
- **El login con passkey no pasa por `RechazarCuentaNoVigente`**: entra, y
  `CuentaVigente` lo saca en la petición siguiente.
- **La caducidad de la invitación es fija**, siete días, en `config/auth.php`.
