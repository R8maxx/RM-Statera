# Administración de la plataforma: lo que falta para operar con clientes reales

## Contexto

La plataforma ya da de alta, cobra en modelo, avisa, da de baja y entra como soporte (puntos 41-47). Para operar con clientes reales faltan estas piezas:

- quién administra y con qué permisos;
- cómo se rescata a un cliente que se queda sin responsable o sin segundo factor;
- qué hizo cada administrador, con una traza consultable para nuestro propio auditor;
- qué requiere atención hoy;
- la salud del servicio;
- la exportación y el borrado de un cliente que se va.

César pidió hacerlo todo, con tres decisiones:

- **Dos perfiles de plataforma.**
  - **Administración** puede todo.
  - **Gestión comercial** gestiona clientes, planes, suscripciones y el cuadro de mando. No entra como soporte, no rescata cuentas, no da de baja ni ve la salud del servicio.
- **Rescatar una cuenta va con dos personas.** Restablecer el segundo factor o designar un nuevo responsable se *solicita* con la verificación escrita, y lo *ejecuta* otro administrador. Si sólo hay un administrador de perfil Administración, puede ejecutarlo él mismo, pero se avisa por correo a todos los responsables del cliente.
- **Exportar desde la web, borrar sólo por consola.** El borrado exige doble confirmación y un plazo mínimo de baja, y respeta lo que no se puede borrar.

**El aislamiento sigue igual**: nada nuevo usa `comoMantenimiento()` desde la web, y lo que toca datos de un cliente entra por `paraOrganizacion()`. **La única puerta nueva que atraviesa las capas es la purga del punto 57**, por consola y con una función SQL tan estrecha como `depurar_traza_de_persona`.

## Paso 0

Copiar este plan al repositorio como `plan-administracion-plataforma.md`, en la raíz junto a `PRODUCT.md`, y añadirle una fila por punto en `CLAUDE.md` según se cierre cada uno. Leer antes de tocar código:

- `.ai/rules/plataforma.md`, `aislamiento.md`, `cuentas.md`, `copias.md`, `personas.md`, `tests.md`, `migraciones.md`, `routing.md` y `recursos.md`;
- `DESIGN.md` §14 para las pantallas.

---

## 48. Los perfiles de la plataforma

- **Migración**: `users.perfil_plataforma`, nulo o `administracion` / `comercial`, con un `CHECK` construido desde el enum. Todo administrador existente pasa a `administracion`.
- **`Enums/PerfilPlataforma`**: el método `capacidades()` devuelve una lista de `CapacidadPlataforma`.
- **`Enums/CapacidadPlataforma`**: `clientes.ver`, `clientes.gestionar`, `planes.gestionar`, `soporte.entrar`, `cuentas.rescatar`, `clientes.baja`, `clientes.exportar`, `administradores.gestionar`, `traza.ver` y `salud.ver`.
  - Comercial tiene `clientes.ver`, `clientes.gestionar`, `planes.gestionar` y `traza.ver`.
  - Administración las tiene todas.
- **Autorización**:
  - `SoloPlataforma` sigue siendo la puerta del grupo.
  - Cada ruta lleva además el middleware nuevo `CapacidadDePlataforma:<capacidad>` (alias `plataforma`), con respuesta 403.
  - `User::puedeEnPlataforma(CapacidadPlataforma)`.
- **`HandleInertiaRequests`** pone en `auth.permisos` `plataforma.<capacidad>` por cada capacidad del perfil, en lugar de la marca única `plataforma.gestionar`.
  - `lib/navegacion.ts` filtra cada entrada por la suya.
  - Hay que actualizar a la vez `navegacionPara()`, que detecta si se está dentro de una organización por los permisos `.ver`. Ojo: las capacidades `plataforma.clientes.ver` y `plataforma.traza.ver` también acaban en `.ver`, así que esa detección tiene que excluir lo que empieza por `plataforma.`.
- **`plataforma:administrador --perfil=`**, con `administracion` por defecto.
- **`SesionDeSoporte` y `AccesoDeSoporte`** exigen `soporte.entrar`.
- **Tests**: la matriz perfil × ruta descubre las rutas de `routes/plataforma.php` en vez de enumerarlas. Un test de una ruta sin capacidad debe ponerse rojo.

## 49. Los administradores, desde la web

`/plataforma/administradores` (`administradores.gestionar`):

- **Lista**: perfil, segundo factor, último acceso, organización si es de alguna y estado.
- **Invitar** (`AltaAdministrador`, con perfil).
- **Cambiar el perfil.**
- **Quitar el acceso a la plataforma**: `es_plataforma=false` y perfil nulo. Si no es de ninguna organización, además se desactiva con `DesactivarCuenta`.
- **Reglas** en `Excepciones/AdministracionNoPermitida`:
  - nadie se quita a sí mismo;
  - no se queda la plataforma sin ningún administrador de perfil Administración activo (`AdministradoresDePlataforma::quedaOtro()`, calcado de `ResponsablesDeSeguridad`).
- **Confirmar la contraseña** (`password.confirm` de Fortify) antes de invitar, cambiar perfil o quitar acceso.
- **Traza**: `AccionPlataforma` gana `AdministradorPerfilCambiado` y `AdministradorRetirado`, con su migración de `CHECK`.

## 50. La traza de la plataforma, consultable

- **`/plataforma/traza`** (`traza.ver`), con `EventoPlataformaRecurso` sobre la capa de recursos:
  - filtros por acción, administrador, organización y rango de fechas;
  - orden por fecha;
  - exportación CSV con lo que ya hay (`lib/csv.ts`).
- El detalle JSON se pinta legible y **sin secretos**.
- La ficha del cliente enlaza aquí con el filtro de su organización.

## 52. Rescatar cuentas con dos personas: segundo factor y nuevo responsable

**Tabla `solicitudes_plataforma`**, con `organizacion_afectada_id` y sin RLS, como el resto de datos de plataforma:

- `tipo`, con `CHECK`: `restablecer_segundo_factor` o `designar_responsable`;
- `cuenta_id`, la cuenta afectada (nulo si el responsable es nuevo);
- `datos` (JSONB): nombre y correo del responsable nuevo;
- `verificacion`: texto obligatorio que dice cómo se comprobó quién lo pedía;
- `solicitada_por` y `solicitada_en`;
- `estado`, con `CHECK`: `pendiente`, `ejecutada`, `rechazada` o `caducada`;
- `resuelta_por`, `resuelta_en` y `motivo_rechazo`.

Las dos transiciones, pedir y resolver, quedan en la fila (invariante 7) y en `eventos_plataforma`. **Una solicitud pendiente caduca a las 72 horas**, y el estado se deriva de la fecha, como `EstadoCuenta`.

**Dominio en `app/Domain/Plataforma/Rescate/`:**

- **`SolicitarRescate`**: valida que la cuenta sea de esa organización, con la consulta de `users` acotada a mano.
- **`ResolverRescate`**:
  - exige `cuentas.rescatar` y que quien ejecuta no sea quien pidió;
  - la excepción es un único administrador de perfil Administración. Entonces se permite, y se marca `sin_segunda_persona` en el evento y en el correo.
- **`RestablecerSegundoFactor`**:
  - borra `two_factor_secret`, `two_factor_recovery_codes` y `two_factor_confirmed_at`, y las filas de `passkeys`;
  - rota el `remember_token` y borra las sesiones, igual que hace `DesactivarCuenta`;
  - escribe el evento en la traza del cliente dentro de `paraOrganizacion()`;
  - manda correo a la cuenta y a los responsables;
  - la próxima vez que escriba, `ExigirDosFactores` la mandará a activarlo.
- **`DesignarResponsable`**:
  - si la cuenta ya existe en la organización, `CambiarRol` a responsable de seguridad, con el administrador como quien lo hace;
  - si no, `InvitarCuenta` con ese rol, dentro de `paraOrganizacion()`;
  - correo a los responsables que haya;
  - **nunca a una cuenta de la plataforma**: para eso está `UnirAdministrador`.

**Interfaz:**

- En la ficha del cliente: «Solicitar restablecer el segundo factor» por cuenta, y «Designar nuevo responsable».
- **`/plataforma/solicitudes`**: pendientes y resueltas, con «Ejecutar» y «Rechazar».

## 53. El cuadro de mando de la plataforma

**`/plataforma`** (`clientes.ver`) pasa a ser la casa: `/inicio` lleva aquí a quien administra sin organización. Tarjetas con cifra y enlace a la lista filtrada:

- suscripciones que vencen en 30 días, en gracia y en sólo lectura;
- clientes que superan su plan;
- invitaciones caducadas sin aceptar (anteriores a la validez del broker);
- ventanas de soporte abiertas;
- solicitudes de rescate pendientes;
- **sólo con `salud.ver`**: el estado de la última copia y de la última verificación (punto 55).

Lo calcula `Plataforma/CuadroDeMando` leyendo sólo tablas fuera de RLS. Los filtros nuevos en `OrganizacionPlataformaRecurso` (vence pronto, en gracia, sólo lectura, de baja, sobre su plan) son los destinos de los enlaces.

## 54. La ficha comercial del cliente

- **Columnas nuevas en `organizaciones`, sólo de la plataforma y fuera de `$fillable`**: `contacto_facturacion_nombre`, `contacto_facturacion_email`, `contacto_facturacion_telefono` y `notas_comerciales`.
- **«Editar ficha comercial»** (`clientes.gestionar`): nombre, razón social, CIF, sector y contacto.
  - Se guarda dentro de `paraOrganizacion()`, para que la traza del cliente registre que la plataforma cambió su razón social.
  - Con `GuardarFichaComercial` y su `FormRequest`. El CIF sigue siendo único.
- El cliente sigue editando lo suyo en `/organizacion`, y ahí ve el contacto de facturación sin poder cambiarlo.

## 55. La salud del servicio

**`/plataforma/salud`** (`salud.ver`):

- **Copias**:
  - la última copia, con `HacerCopia::copias()`;
  - la última verificación, leyendo el JSON más reciente de `bases/` en el disco `copias`, con un lector nuevo `Copia/UltimaVerificacion`;
  - en rojo si la copia tiene más de 26 horas o la verificación más de 8 días o falló.
- **Colas**:
  - trabajos fallidos de `failed_jobs`, con la cola, la clase del job, la primera línea de la excepción y la fecha. **Nunca el payload**, que puede llevar correos;
  - el número de trabajos pendientes por cola, de Redis.
- **Horizon**: el gate `viewHorizon` pasa a ser `esPlataforma() && puedeEnPlataforma(SaludVer)`, y la pantalla enlaza al panel.

## 56. Exportar un cliente

Botón «Exportar todos los datos» en la ficha (`clientes.exportar`), con su job en cola `ExportarOrganizacion`:

- usa el middleware `ConContextoDeOrganizacion` y pasa escalares, sin `SerializesModels`;
- tiene su propio `failed()`.

**El contenido del ZIP:**

- **Un NDJSON por modelo de dominio con `organizacion_id`.** Los modelos se descubren con el mismo recorrido que `modelosDelDominio()` de `tests/Pest.php`, que se sube a una clase de dominio `Plataforma/Exportacion/ModelosDelCliente`. Se recorren **con Eloquent y no con SQL en bruto**, para que los datos personales cifrados (punto 35) salgan en claro y respeten `$hidden`.
- **Las cuentas** (`users` de esa organización): sin contraseña ni secretos.
- **La traza del cliente.**
- **Los ficheros de `evidencias`, `documentos` y `adjuntos`**, bajo su prefijo de organización, con las rutas que recogió la exploración.
- **Un `manifiesto.json`**: fecha, versión del esquema, recuentos y huella SHA-256 de cada fichero.

**Dónde se guarda:**

- en el disco `adjuntos`, que no tiene Object Lock, en `exportaciones/{org}/{ulid}.zip`;
- con la tabla `exportaciones_organizacion` (`organizacion_afectada_id`, `solicitada_por`, estado, ruta, tamaño, huella, `generada_en`, `caduca_en` a 7 días);
- descarga por ruta firmada y con la capacidad;
- un comando diario borra las caducadas.

**Hay que comprobar antes que `ext-zip` está en la imagen.** Si no, se usa un tar.gz con `PharData`, sin dependencia nueva.

## 57. El borrado definitivo, sólo por consola

**Empieza con una prueba (spike) en `statera_test`**, para confirmar lo que la exploración dejó como inferencia: la cascada desde `organizaciones` choca con el `REVOKE DELETE` de `eventos_auditoria` y `transiciones_suscripcion`, y con los disparadores de `auditoria_puntos` y `hallazgos`. El diseño previsto, que se ajusta a lo que diga la prueba:

- **Función SQL `purgar_organizacion(p_organizacion bigint)`**:
  - `SECURITY DEFINER`, del migrador, con `search_path` fijado;
  - `EXECUTE` sólo para el rol que use el comando;
  - **sólo actúa si la organización lleva de baja al menos `config('plataforma.dias_baja_para_purgar', 90)`**;
  - pone una variable de sesión `app.purgando = <id>` que los disparadores de inmutabilidad respetan, ampliándolos en la misma migración, y borra en orden.
- **Comando `organizaciones:purgar {organizacion}`**:
  - `--dry-run` para ver qué se borraría;
  - confirmación interactiva tecleando el CIF;
  - exige una exportación generada después de la baja, o `--sin-exportacion` explícito;
  - borra los ficheros de `adjuntos` y los borradores de `documentos`, y **cuenta lo que Object Lock retiene** (evidencias y documentos emitidos), que caducará con el ciclo de vida del bucket;
  - suprime las cuentas de cliente de esa organización, y a los administradores que fueran miembros sólo los desvincula;
  - deja `organizacion_purgada` en `eventos_plataforma`, con los recuentos. Su `organizacion_afectada_id` pasa a nulo, y el nombre y el CIF quedan en el detalle.
- **Las copias** dejan de contener al cliente cuando caducan (`conservar_dias`). El comando lo dice.

**Si la prueba demuestra que no se puede sin abrir más de lo razonable**, el punto se reduce a una seudonimización de la organización con la misma estructura que la de personas, y se documenta el porqué.

---

## Orden y entregas

**El 51 lo ocupó otro trabajo** que se hizo a la vez: la organización elige y cambia de plan (`/organizacion/plan`). Por eso el rescate de cuentas es el 52 y lo demás se desplaza un número.

**48 → 49 → 50 → 52 → 53 → 54 → 55 → 56 → 57**, un commit por punto. Los perfiles van primero porque todo lo demás cuelga de una capacidad. La purga va la última porque es la única destructiva y empieza con una prueba.

## Ficheros críticos

- **Nuevos**: `app/Domain/Plataforma/{Enums/PerfilPlataforma,Enums/CapacidadPlataforma,AdministradoresDePlataforma,Rescate/*,CuadroDeMando,GuardarFichaComercial,Exportacion/*}`, `app/Domain/Copia/UltimaVerificacion.php`, `app/Http/Middleware/CapacidadDePlataforma.php`, controladores en `app/Http/Controllers/Plataforma/` y páginas en `resources/js/pages/plataforma/{administradores,traza,solicitudes,salud}/`.
- **Se modifican**:
  - `routes/plataforma.php`, con una capacidad en cada ruta;
  - `app/Http/Middleware/HandleInertiaRequests.php` y `resources/js/lib/navegacion.ts`;
  - `app/Http/Controllers/PerfilController.php` (`/inicio`);
  - `app/Providers/HorizonServiceProvider.php`;
  - `app/Domain/Plataforma/Console/CrearAdministradorCommand.php`;
  - `DesarrolloSeeder`: un administrador de perfil Comercial, una solicitud pendiente y un cliente que supera su plan.
- **Migraciones**: perfiles, columnas comerciales, `solicitudes_plataforma`, `exportaciones_organizacion`, `CHECK` de `AccionPlataforma` ampliado en cada punto (`NOT VALID`), y la función de purga con sus disparadores.

## Verificación

**Por punto**:

- Pest acotado al módulo;
- `composer analyse` sin errores;
- Pint;
- `composer types` y `npm run type-check`;
- al cerrar el punto, la suite entera una sola vez, sin otra a la vez.

**Tests clave**:

- matriz perfil × ruta (48);
- no quitarse a sí mismo y no dejar la plataforma sin Administración (49);
- la traza filtra bien y nunca enseña secretos (50);
- en el rescate (52):
  - quien pide no ejecuta, salvo administrador único, con aviso;
  - la solicitud caduca;
  - restablecer borra el secreto, las passkeys y las sesiones;
  - designar no admite cuentas de plataforma;
  - todo queda en las dos trazas;
- las cifras del cuadro de mando con el escenario del seeder (53);
- la plataforma edita la ficha comercial y el cliente no edita el contacto (54);
- la salud se pone roja con una copia vieja y oculta los payloads (55);
- en la exportación (56):
  - el ZIP contiene un NDJSON por modelo descubierto;
  - los datos personales salen en claro;
  - no sale ninguna contraseña ni secreto;
  - no aparece nada de otra organización;
- en la purga (57):
  - se niega antes del plazo;
  - `--dry-run` no toca nada;
  - después de purgar no queda ninguna fila con ese `organizacion_id`;
  - las demás organizaciones quedan intactas.

**Recorrido en el navegador al final**, con el seeder, como Administración y como Comercial:

1. Comprobar qué ve cada perfil en el lateral y el 403 en lo que no le toca.
2. Pedir y ejecutar un rescate entre dos administradores.
3. Recorrer el cuadro de mando hasta las listas filtradas.
4. Exportar un cliente y abrir el ZIP.
5. Ver la salud del servicio.
6. Purgar en simulación (`--dry-run`) a «Cliente de baja».
