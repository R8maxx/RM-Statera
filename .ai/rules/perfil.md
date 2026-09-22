---
paths:
  - resources/js/pages/perfil/**
  - app/Domain/Autorizacion/**
  - app/Domain/Usuario/**
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

Lo que no se hace es enumerar los cuarenta y tres permisos en cuarenta y tres
filas con su palomita: **eso es la fila de ceros del inventario otra vez**. Se
agrupan por módulo, diecisiete filas, y los módulos que no se ven enteros se
resumen en una frase.

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
> roles del § 4.19 llevan el `.ver` de los diecisiete módulos, así que nadie
> tiene un módulo oculto. Es la **tercera** vez que este mismo hecho aparece en
> este documento: ya estaba anotado en la guarda del bloque de riesgos de la
> ficha de un activo —«la guarda no la ejerce nadie»— y en la de las fuentes del
> panel —«no la hace innecesaria: la hace **no ejercida**»—. Aquí igual: la rama
> existe, hay un test que la ejercita revocándole un módulo entero al rol, y otro
> que fija que hoy nadie la activa. El día que haya un rol más estrecho se sabrá
> por ese test y no porque una pantalla empiece a decir algo nuevo.

**Sin verbo de permiso nuevo y sin segundo factor**, como el resto de `/perfil` y
como el acuse de lectura de un documento: se escribe sobre uno mismo. Y el bloque
no abre ninguna ruta de escritura — quién tiene qué rol es el § 4.19.

### Lo que esta pantalla declara que no hace todavía

- **No corrige la orientación EXIF.** La extensión `exif` no está en la imagen y
  añadirla es tocar el `Dockerfile`. Una foto hecha de lado con el móvil se
  guarda de lado; se gira antes de subirla.
- **No da de alta cuentas, ni asigna roles, ni deja ver el perfil de otro.** Eso
  es el § 4.19, que sigue sin pantalla: existen los enums, el seeder y las
  políticas, y ninguna interfaz.
- **La foto es de la cuenta y no de la persona.** `personas` no tiene retrato, así
  que el organigrama de `/puestos/organigrama/grafo-personas` sigue sin caras —
  y ponerlas sería ampliar el § 4.8, no esto.
- **No hay perfil de la organización.** Razón social, nombre comercial, domicilio
  fiscal y logos no existen en el modelo, y `organizaciones.url_base_etiquetas`
  —de la que dependen los QR **ya impresos** del parque de activos— sólo se puede
  poner por seeder o tocando la base. Es el trabajo siguiente y es el que de
  verdad falta.
