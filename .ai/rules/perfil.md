---
paths:
  - resources/js/pages/perfil/**
  - app/Domain/Autorizacion/**
  - app/Domain/Usuario/**
  - resources/js/components/MenuCuenta.vue
  - resources/js/composables/useTema.ts
---

# Mi cuenta

`/perfil`. No es un módulo de los diecinueve y no tiene dominio propio salvo dos
acciones: es la pantalla de quien ha entrado —quién soy, cómo entro y qué puedo
hacer—. Era además **la única pantalla de escritura del producto que no estaba
construida con la capa de recursos**, y eso se veía justo en lo primero que se
mira: tres anchos de campo distintos en la misma columna.

### El fallo que no se veía

`useForm({ name: '', email: '' })` con el valor actual puesto sólo de
`placeholder`. Un `placeholder` **no es un valor**, así que quien abría la
pantalla y pulsaba «Guardar» mandaba los dos campos vacíos, y quien corregía
sólo el nombre mandaba el correo en blanco —y Fortify exige los dos—. Se veía
como un error de validación sobre un formulario que parecía relleno.

Ahora el nombre y el correo viajan en el prop `usuario` y el formulario arranca
con ellos. `PerfilTest` lo clava, porque es la clase de regresión que vuelve en
cuanto alguien toca el prop.

### La retícula, que es de donde salían los tres anchos

Fuera las `Card`: ninguna otra pantalla de escritura las usa. Cada bloque pasa a
ser una `SeccionFormulario` —la explicación a la izquierda, los campos a la
derecha—, y los campos quedan al mismo ancho **por construcción y no por acertar
con una clase**.

**No se usa `FormularioRecurso`**, y conviene decirlo porque es lo que uno
esperaría: esto no es un recurso, son cinco bloques independientes que escriben
cada uno contra su sitio —tres endpoints de Fortify, los del paquete de passkeys
y la ruta propia de la foto—. Se toma la retícula y no el contenedor, así que
tampoco hay `BarraAcciones` ni contador de obligatorios: cada bloque guarda lo
suyo con su propio botón. `usarSeccionObligatorios()` ya
contemplaba este caso por escrito: fuera de un `FormularioRecurso` devuelve cero
y no pinta nada.

**El orden pasa a ser de lectura** —identidad, contraseña, dos pasos, passkeys,
permisos— y antes empezaba por el segundo factor. No se pierde nada: **si falta
el segundo factor, su aviso rojo sube a una tira encima de las secciones**, que
es lo que ya hace el panel con lo suyo. Ordenar por lectura y sacar el rojo
arriba son la misma decisión.

### La foto: una columna, y el disco que sí deja borrar

**`users.foto_ruta`, y nada más.** Ni una tabla, ni un adjunto: un adjunto lleva
título, nota, quién lo subió y N:M con su anfitrión porque **documenta un
registro**, y una foto de perfil es un atributo de la cuenta que no documenta
nada. Tampoco lleva `disco`, `mime` ni `tamano`, porque los tres serían siempre
el mismo valor.

**En el disco `adjuntos`, bajo `avatares/{organizacion}/`. Es el único de los
tres sin Object Lock, y ése es exactamente el motivo.** Bajo Object Lock en modo
compliance, una foto subida por error **no se podría borrar nunca**; la cara de
alguien es un dato personal y quien ejerce su derecho de supresión no acepta «la
fila ya no está». Es el argumento que ya estaba escrito en `BorrarAdjunto`, y
aquí se hereda entero: borrar la foto **se lleva también el objeto**, y cambiarla
borra la anterior — después de que la fila apunte a la nueva, nunca antes.

**Todo lo que entra sale igual**: un WebP cuadrado de 256 px como mucho. Sin eso,
quien sube doce megas hechos con el móvil los hace descargar en cada pantalla,
porque el avatar vive en el chrome y el chrome se pinta siempre. Se **recorta**
al cuadrado central en vez de deformar, y **no se agranda**: estirar 64 px hasta
256 no añade información, añade peso y borrosidad.

**Con GD a pelo y sin dependencia nueva.** `gd` ya lo exige el lock, son cuarenta
líneas, e `intervention/image` traería un grafo entero para un `imagescale` — el
mismo criterio que dejó fuera a Chart.js y a GSAP.

**Aquí SÍ hay lista blanca de `mimes`**, al revés que en `SubirAdjuntoRequest`, y
la diferencia es real: un adjunto es «lo que haya que adjuntar» y esto es una
imagen que **vamos a decodificar nosotros**. Admitir cualquier cosa es pasarle a
GD un fichero que no sabe leer, y lo que ve quien lo sube es un 500 en vez de
«esto no es una imagen».

**La ruta que la sirve no admite decir de quién es**, y es la decisión que
sostiene el aislamiento de todo esto. `users` se queda **fuera de las tres
capas** —sin `PerteneceAOrganizacion`, sin scope global y sin RLS, y es una de
las cuatro excepciones que `RlsDeclaradaTest` lleva escritas—, porque la
autenticación tiene que encontrar a alguien antes de saber de qué organización
es. Así que `/perfil/foto/{usuario}` habría que acotarla a mano y **ningún test
de aislamiento se pondría rojo si alguien lo olvidara**. Sin parámetro no hay
nada que acotar. Se sirve como redirect a URL firmada de cinco minutos, igual
que un adjunto: nunca un enlace al bucket.

**La URL lleva sufijo de versión, y no es adorno.** Como la ruta es fija, sin él
el navegador sirve de su caché la foto vieja y cambiarla no se ve — un fallo que
aparece dos días después y en otra pantalla. El ULID del fichero ya es distinto
en cada subida, así que **sirve de versión tal cual** y no hace falta calcular
ningún hash.

**El respaldo son las iniciales, y cubre dos casos.** No hay foto, y **la hay y
no carga** — que pasa de verdad, porque la firma caduca a los cinco minutos.
`AvatarImage` de Reka conmuta solo al respaldo; un `<img>` a secas enseñaría el
icono de imagen rota del navegador. Las iniciales se calculaban en `AppLayout` y
ahora viven una sola vez, en `AvatarUsuario`.

**Y es decoración, dicho de frente**: no aparece en ninguna traza, ni en ningún
PDF, ni ayuda a ningún auditor. `DESIGN.md` §14 termina con «¿hay algo decorativo
que se pueda quitar? Quítalo», así que esto es una excepción declarada y no un
descuido — lo que compra es saber con qué cuenta se está dentro, que en un
producto con tres roles y una frontera de tenant no es trivial.

### Los permisos: se pinta también lo que NO se tiene

`PermisosDeLaCuenta` es sólo lectura y contesta **«¿por qué no me sale este
botón?»**, que es la única pregunta que alguien le hace a este bloque. De ahí la
decisión que le da forma: **una lista de sólo lo concedido no la contesta**, así
que cada módulo enseña sus verbos con los que faltan tachados y con su icono
—§11 no deja que la diferencia dependa del color—.

Lo que no se hace es enumerar los permisos uno a uno con su palomita —hoy son
cuarenta y siete—: **eso es la fila de ceros del inventario otra vez**. Se agrupan
por módulo, y los módulos que no se ven enteros se resumen en una frase.

> Sin cifra de filas a propósito. Decía «diecisiete» y el § 4.16 la dejó en veinte
> sin que nadie lo notara, que es lo que pasa con todo recuento escrito en prosa:
> `PermisosDeLaCuenta::porModulo()` agrupa por prefijo y se entera solo.

**El título y el icono no se escriben en PHP.** Viaja el `href` y el cliente lo
resuelve contra `lib/navegacion.ts`, que pasa a tener un **quinto lector** en vez
de una segunda lista que mantener sincronizada. Y eso abre un fallo silencioso
que hay que cerrar: `entradaDe()` devuelve `undefined` sin quejarse, así que un
permiso con un prefijo nuevo saldría **sin nombre y sin icono sin que fallara
nadie** — el mismo fallo que `IconosTest` y `TonosTest` existen para cerrar, por
cuarta vez. Lo clava `PermisosDeLaCuentaTest`, que **parsea `navegacion.ts` desde
PHP** porque el otro lado es TypeScript, igual que `EsquemaEnDosIdiomasTest`, y
se pone rojo si su patrón deja de casar.

**El reparto es «tengo algo de este módulo» y no «tengo su `.ver`».** Hoy son lo
mismo —ningún rol escribe sin leer— y si dejaran de serlo, un módulo con
escritura y sin lectura tiene que salir en la lista larga y no escondido en el
resumen.

> **Y de aquí salió un hallazgo: `sinAcceso` hoy no se pinta nunca.** Los tres
> roles del § 4.19 llevan el `.ver` de todos los módulos que tienen uno, así que
> nadie tiene un módulo oculto. Es la **tercera** vez que este mismo hecho aparece en
> este documento: ya estaba anotado en la guarda del bloque de riesgos de la
> ficha de un activo —«la guarda no la ejerce nadie»— y en la de las fuentes del
> panel —«no la hace innecesaria: la hace **no ejercida**»—. Aquí igual: la rama
> existe, hay un test que la ejercita revocándole un módulo entero al rol, y otro
> que fija que hoy nadie la activa. El día que haya un rol más estrecho se sabrá
> por ese test y no porque una pantalla empiece a decir algo nuevo.

**Sin verbo de permiso nuevo y sin segundo factor**, como el resto de `/perfil` y
como el acuse de lectura de un documento: se escribe sobre uno mismo. Y el bloque
no abre ninguna ruta de escritura — quién tiene qué rol es el § 4.19.

### El menú de la cuenta y lo que llegó con él

El desplegable de arriba a la derecha decía quién eras y poco más. Ahora contesta
tres preguntas que no son del trabajo sino de quien lo hace: **qué hay a mi
nombre**, **cómo está protegida mi cuenta** y **cuándo entré la última vez**.

**Se pide al abrirse (`/perfil/menu`, JSON) y no viaja con cada página.** Son
cinco consultas para un menú que casi nunca se abre; como prop compartido
correrían en cada navegación y en cada recarga parcial de una tabla. Lo que ya se
sabe —nombre, correo, segundo factor— sale de los props compartidos al instante.

**Cada cifra sale del scope que filtra la tabla a la que lleva**:
`Tarea::abiertas()`/`vencidas()`, `Documento::pendientesDeMiAcuse()` —nuevo, y
también filtro `por_leer` de la tabla de documentos— y `enRevision()`. Lo clava
«la cifra del menú es la del filtro de la tabla» en `MiCuentaTest`.
`pendientesDeMiAcuse()` lee la cuenta de la sesión porque `Filtro::porScope()` no
pasa parámetros; sin nadie autenticado no devuelve nada.

**La entrada anterior es la segunda más reciente**, no la última: la última es la
de la sesión actual (también la de «recordarme», que dispara el mismo evento). Va
con los intentos fallidos desde entonces, que es lo que delata un acceso ajeno.
Todo sale de la traza (`AccesosRecientes`): ya guarda IP y es inmutable, y un
registro propio sería una segunda copia que sí se podría tocar.

**Al auditor le dice hasta cuándo entra y qué sistemas ve.** Sin eso descubre que
su acceso caduca el día que deja de poder entrar.

**El tema pasó al menú y a la cuenta.** `users.tema` manda sobre `localStorage`:
`app.blade.php` lo aplica antes del primer pintado y `useTema()` lo adopta al
montarse, porque tras el login la navegación es de Inertia y la plantilla no se
vuelve a pintar. `useTema()` guarda en el servidor al elegir (`PUT /perfil/tema`,
sin navegar) y su `ref` es **de módulo**, no por llamada: con uno por componente,
el menú seguía marcando «Claro» después de elegir «Oscuro» en la paleta.

### Sesiones abiertas

`SesionesAbiertas` lee la tabla `sessions` del driver `database`: la sesión ES
esa fila. **El id de sesión no sale nunca hacia el cliente** —es el valor de la
cookie—; viaja su SHA-256 como `clave` y cerrar compara huellas entre las de la
propia cuenta. Lo clava `assertDontSee($id)`.

**Cerrar pide la contraseña en la misma petición** (`CerrarSesionesRequest`), y no
con `password.confirm`: ese middleware vuelve al destino con un `GET` y esto es
un `DELETE`. Sin contraseña, quien encuentre una sesión olvidada echa al dueño de
las demás. **Y cambia el `remember_token`**, como `DesactivarCuenta`: sin eso un
navegador con «recordarme» abre sesión nueva en su siguiente petición. Queda en
la traza como `cierre_sesion` con `sesiones_cerradas`.

En la suite `SESSION_DRIVER=array`, así que los tests insertan las filas a mano.

### Preferencias, contraseña y datos

- **Tres columnas y no un JSONB** (`tema`, `pagina_inicio`, `avisos_por_correo`),
  con `CHECK` desde listas escritas en la migración. Las lee el servidor —la
  plantilla, la redirección de entrada— y un `CHECK` sobre una clave de JSON no se
  mantiene. `User::$attributes` repite los valores por defecto: sin ellos, una
  cuenta recién creada revienta en `tema->value` en la misma petición.
- **`/inicio` es el `home` de Fortify** y redirige a la página elegida. «Mis
  tareas» es la tabla de tareas con el filtro de responsable, no otra pantalla.
- **Los avisos por correo son un solo interruptor**, porque el correo es uno: el
  resumen diario. Sólo se ofrece a quien lo recibe —el responsable de seguridad—,
  y la regla vive en `Aviso\DestinatariosDelResumen`, que usan el comando y la
  pantalla. De paso el comando dejó de mandárselo a cuentas desactivadas.
- **`password_cambiada_en` nace a nulo** en las cuentas que ya existían: no se sabe
  cuándo se puso su contraseña. La escriben los dos actions de Fortify y
  `AceptarInvitacion`.
- **La copia de tus datos (`/perfil/mis-datos`) es lo de la cuenta**: ficha,
  preferencias, accesos, acuses y firmas. No van tareas ni evidencias —son
  registros del SGSI en los que apareces— ni ningún secreto.
- **La pantalla no promete seudonimizar la cuenta**, porque no se hace: el punto
  36 alcanza a `personas`, y la cuenta se desactiva y conserva su nombre en lo que
  firmó.

### Lo que esta pantalla declara que no hace todavía

- **No corrige la orientación EXIF.** La extensión `exif` no está en la imagen y
  añadirla es tocar el `Dockerfile`. Una foto hecha de lado con el móvil se
  guarda de lado; se gira antes de subirla.
- **No da de alta cuentas, ni asigna roles, ni deja ver el perfil de otro.** Eso
  vive en `/cuentas` desde el punto 28, con sus reglas en `cuentas.md`. Aquí decía
  que existían «las políticas», y no existía ninguna: la autorización es por
  permiso en la ruta (`can:`) y no hay una sola clase `Policy` en el repositorio.
- **La foto es de la cuenta y no de la persona.** `personas` no tiene retrato, así
  que el organigrama de `/puestos/organigrama/grafo-personas` sigue sin caras —
  y ponerlas sería ampliar el § 4.8, no esto.
- **No hay perfil de la organización.** Razón social, nombre comercial, domicilio
  fiscal y logos no existen en el modelo, y `organizaciones.url_base_etiquetas`
  —de la que dependen los QR **ya impresos** del parque de activos— sólo se puede
  poner por seeder o tocando la base. Es el trabajo siguiente y es el que de
  verdad falta.
- **No hay preferencia de densidad de tablas en la cuenta.** Ya existe por tabla y
  por navegador en la vista guardada de `DataTable` (`recursos.md`), y duplicarla
  en la cuenta daría dos fuentes para lo mismo.
- **No hay avisos por tipo ni hora de envío por persona.** El resumen es uno y lo
  manda un comando programado a una hora para todos; repartirlo por persona es
  rehacer `avisos:enviar`.
- **No hay diálogo de atajos de teclado.** Hoy son dos (`⌘K` y `/`), y un diálogo
  para dos atajos no compensa la entrada en el menú.
