---
paths:
  - app/Domain/Organizacion/**
  - resources/js/pages/organizacion/**
  - resources/js/components/organizacion/**
---

# La ficha de la organización

`/organizacion`. La raíz del tenant, que llevaba desde la primera migración **sin
ninguna pantalla**: `nombre`, `cif`, `sector`, dos banderas y `url_base_etiquetas`
sólo se podían tocar por seeder o entrando en la base. Y dos de esas columnas ya
decidían cosas que se imprimen — `url_base_etiquetas` gobierna los QR **ya pegados
en el parque de activos**, y las dos banderas del ENS deciden qué dice la DdA.

### La razón social, que es lo que paga la pantalla

Hasta aquí la portada de todo documento entregable imprimía `organizaciones.nombre`,
que es un nombre de pantalla —el del sidebar, el de los correos—. **Una Declaración
de Aplicabilidad la firma una persona jurídica**, y eso es la razón social.

**No se añade `nombre_comercial`.** `nombre` ya *es* el nombre comercial; una
columna aparte sería el mismo dato en dos sitios que pueden discrepar, que es lo que
el repositorio evita con `activa`, con `vigente` y con el ámbito derivado de una
cuestión del DAFO. Lo que cambia es la etiqueta del campo, no la columna — mismo
tratamiento que recibió `personas.nombre` al llegar los puestos.

**Un solo punto de cambio y cero churn de tests.** `ArmaContenidoComun:37` es el
único sitio donde `Organizacion` toca la portada, así que `nombreLegal()` se escribe
ahí y se propaga solo a la ficha de la primera página y a la cabecera de todas las
demás. Y **ningún test existente cambió**: `OrganizacionFactory` deja
`razon_social` a nulo a propósito —que es el estado de toda organización anterior a
esto—, así que el respaldo devuelve exactamente `nombre` y los `Contenido*Test` de
los seis documentos siguen afirmando lo que afirmaban.

Es `?:` y no `??`, en las dos puntas: una cadena vacía tampoco es una razón social.

**Las versiones ya emitidas no cambian**, porque su texto está congelado en
`instantanea` y la fila es inmutable por trigger. **Consecuencia declarada: dos
versiones del mismo documento pueden nombrar a la organización de forma distinta**,
y es correcto —cada una dice lo que era verdad el día que se firmó—, pero hay que
poder explicárselo a un auditor.

### El `??` que se convirtió en fallo al haber formulario

`GeneradorEtiquetas` resolvía la base con `?? config('app.url')`. Mientras esa
columna sólo la escribían los seeders nunca llegaba a ser cadena vacía; **con un
formulario delante, vaciar el campo manda `''`, que atraviesa el `??`** y produce
QR contra `/activos/3` — sin host, codificado en una pegatina que alguien imprime y
pega durante años.

Se tapa en dos capas porque una sola se olvida: `GuardarFichaOrganizacion`
normaliza toda cadena vacía a nulo, y el `??` pasó a `?:` **donde la regla tiene que
vivir**, para valer igual a un importador. Con test de regresión: `EtiquetasTest` ya
cubría la columna nula, y faltaba la cadena vacía.

La pantalla **enseña a dónde apuntará el QR** con la base que hay puesta, y avisa en
rojo cuando se cambia: las pegatinas ya impresas siguen apuntando a la anterior.

### La traza, y el no-op que casi se cuela

`Organizacion` **no lleva `RegistraTraza`**, a diferencia del resto del dominio, y
tiene dos piezas propias en su lugar. Las dos salieron de fallos reales:

1. **`RegistroTraza::escribir()` sale con un `return` en silencio** si el modelo no
   tiene `organizacion_id`, y ésta es la única tabla de datos propios que no la
   tiene. Poner el trait y quedarse ahí habría dado una pantalla que **parece**
   dejar traza sin dejar ninguna — la familia de `IconoTipo` y `tonos.ts`. Se
   resuelve con un accesor `organizacionId` que devuelve el propio `id`; no se
   persiste y no ensucia el diff, porque `organizacion_id` está en `IGNORADOS`.
2. **El trait registra también `created`, y eso reventó 34 tests.** El alta de una
   organización ocurre **antes de que exista contexto para ella**, así que la
   política de RLS de `eventos_auditoria` rechaza la inserción con «new row
   violates row-level security policy» — un error de privilegios que no menciona ni
   la traza ni la organización. Por eso el modelo registra **sólo `updated`**, a
   mano. No se pierde nada que importe: lo que el auditor pregunta es desde cuándo
   la razón social dice lo que dice.

De ahí una consecuencia que queda clavada en un test: **una organización sólo se
puede modificar desde su propio contexto**. Una escritura que cruce la frontera
intenta dejar evento en otro tenant y RLS la tumba, que es lo correcto — fallar
ruidosamente antes que guardar sin traza.

### Un solo verbo, y sin `.ver`

`organizacion.gestionar`, y sólo lo tiene `ResponsableSeguridad`. Es el único
permiso del producto **sin pareja de lectura**, y el motivo está en
`RolesTest:129-143`: ese test recorre `Permiso::cases()` y **exige que el Auditor
tenga todo permiso acabado en `.ver`**. Crear `organizacion.ver` se lo daría; con un
único verbo de escritura, la pantalla queda fuera del Auditor **por construcción** y
no por una lista que haya que recordar.

`Tecnico` y `Auditor` son listas literales y no hubo que tocarlas, que es
exactamente lo que `RolesTest` está ahí para cazar.

### Cómo se llega, y el prop que llevaba sin usarse

**Sin entrada en `lib/navegacion.ts`**, como la metodología de riesgo: ese fichero
es el mapa de **módulos** y esto es la ficha del tenant. La puerta es el desplegable
de organización del sidebar, que pintaba un ítem **`disabled`** con el nombre y
ahora enlaza aquí.

**Y se pinta sólo si el permiso está, leyendo `auth.permisos`** — que
`HandleInertiaRequests` comparte desde el principio y que **no leía nadie en el
cliente**. Éste es su primer uso, y es el que su propio comentario anunciaba: «el
frontend solo decide qué pinta, nunca qué autoriza». Sin esto, dos de los tres roles
verían un enlace que les devuelve 403 — que es lo que ya le pasa a
`/plantillas-documento`.

### Las tres columnas muertas

`sector`, `activa` y `leAplicaElEns()` estaban escritas y **no las leía nadie**.

- **`sector` se expone**: la especificación §2.2 lo nombra y es barato.
- **`activa` no se expone.** Nadie la lee, y un interruptor para desactivar tu
  propia organización desde tu propia pantalla es un pie de fábrica sin puerta de
  vuelta.
- **`leAplicaElEns()` gana su primer lector**: la pantalla lo usa para decir, en
  derivado y sin campo propio, si a la organización le aplica el ENS.

### Dos defectos de interfaz que esta pantalla destapó

Ninguno es de aquí; los dos se vieron **midiendo**, no mirando, y los dos estaban en
componentes compartidos:

1. **`CampoBase` no alineaba dos campos lado a lado** cuando uno llevaba ayuda y el
   otro no. La celda corta se estira a la altura de la larga —`align-items: stretch`
   es el valor por defecto— y la rejilla interna del campo repartía el hueco, así
   que el input del campo sin ayuda bajaba. Medido: **28 px** entre «CIF» y
   «Sector». Se arregla con `content-start`, que es la misma corrección que
   `SeccionFormulario` ya llevaba. Afectaba también a `/riesgos/metodologia`.
2. **El interruptor no comunicaba su estado por ningún canal.** `Switch.vue` venía
   de shadcn con las variantes `data-checked:` / `data-unchecked:`, que Tailwind v4
   compila a atributos **desnudos**, y Reka emite `data-state="checked"`: ninguna
   clase casaba, así que la pista salía transparente y el pulgar quieto — idéntico
   encendido y apagado, en las **seis** pantallas que lo usan. No lo cazaba ningún
   test porque es sólo CSS, y no salta a la vista porque un interruptor sin pista se
   lee como uno apagado.

   **Y ojo con cómo se comprueba**: Tailwind v4 usa la propiedad `translate`, no
   `transform`, así que `getComputedStyle(pulgar).transform` devuelve `none` aunque
   funcione. Por leer la propiedad equivocada estuve a punto de reescribir el
   componente de más.

### La marca del cliente

**Co-branding y no marca blanca**, que sigue fuera de alcance en los tres
documentos. Statera se queda arriba del panel y firma el pie de la portada; lo que
se añade es el logo de quien usa la herramienta, en su propia documentación.
`DESIGN.md` §2 lo declara antes de usarse: dónde va cada pieza, a qué tamaño y
dónde **no** entra —ni en el acceso, ni en los estados vacíos, ni en los correos—.

**Dos piezas**, como el propio Statera tiene `completo` y `simbolo`: el logo
horizontal para la portada del PDF y el desplegable de organización, y el símbolo
cuadrado para la cabecera de cada página, que mide 8 pt y donde un logo con el
nombre dentro no se lee. **Las dos opcionales y las dos degradan a lo que había**,
que es lo que permitió meter esto sin revisar ningún documento anterior.

#### El logo es marca, no contenido, y eso resuelve casi todo

`CuerpoDeFabrica` ya dice que el filete y la palabra «Statera» de la portada «son
la marca, no contenido». Un logo de cliente es lo mismo, así que **entra por CSS**:
una regla de `background-image` sobre `.portada`, generada por `AssetsDocumento`
igual que ya genera los `@font-face`.

La consecuencia es que **no se tocó nada del cuerpo**: ni `EsquemaCuerpo` —que
declara por escrito que no hay nodo de imagen ni de SVG, y sigue sin haberlo—, ni
`RenderizadorCuerpo`, ni `CuerpoDeFabrica`, ni `documento.css`, ni
`CuerpoRenderizadoTest`, que clava `<div class="portada__marca">Statera</div>` y
sigue siendo cierto. **La instantánea no engorda ni un byte**, que es justo lo que
aquel comentario temía de una imagen incrustada.

**Data URI y no fichero del multipart**, aunque base64 pese un tercio más: la
cabecera se renderiza en un contexto aparte que **no recibe los assets**, así que
el símbolo tiene que ir en línea sí o sí. Usar la misma vía para el logo deja un
solo mecanismo y un solo sitio donde se rompe. Con un SVG el sobrecoste son 661
bytes sobre los ~210 kB que la hoja ya pesa por las fuentes.

#### El SVG, y por qué aquí sí entra una dependencia

`enshrined/svg-sanitize`, la primera dependencia de seguridad del repositorio y una
excepción consciente al «la frontera se implementa a mano» del multi-tenancy: allí
la regla es del dominio y la sabemos nosotros; aquí es una lista blanca de XML
—entidades, `DOCTYPE`, espacios de nombres, `xlink:href`, CSS embebido, handlers
`on*`— que alguien mantiene mejor, y un XXE al parsear no se ve venir leyendo el
diff.

**Es la segunda barrera y no la única**, y conviene tenerlo escrito: el SVG sólo se
pinta como `background-image` y como `<img>`, y en esos contextos **ningún
navegador ejecuta scripts**. Lo que el saneado cubre es el día que alguien lo
incruste en el DOM y el fichero raro que llegue a Chromium al generar.

Se admite SVG aquí y **no** en la foto de perfil, y la diferencia es real: una foto
es un mapa de bits y no hay motivo para aceptar un documento XML; un logo
corporativo existe en vector y va a imprenta.

#### Lo que se le hace a la imagen, y lo que no

Un mapa de bits sale **PNG** —no WebP, al revés que el avatar: un logo es gráfico
plano, y PNG es sin pérdida, conserva alfa y es la opción aburrida para papel—,
escalado por el lado mayor y **sin agrandar**. Un SVG se guarda tal cual: escalarlo
sería perder lo único que aporta.

**No se recorta, no se recolorea y no se deforma**, en ninguno de los dos caminos.
Un logo de cliente no es nuestro para retocarlo, y en un documento firmado eso
importa. Si el símbolo llega apaisado, sale apaisado — hay test.

#### Lo que la marca declara que no hace

- **El `.docx` no lleva logo.** Ya declaraba que no traduce el lenguaje de color y
  forma; esto entra por la misma puerta.
- **El editor del cuerpo no lo previsualiza**, porque lee `documento.css` del disco
  con `?raw` y la regla se genera en ejecución.
- **No entra en el árbol de etiquetas del PDF**, por ir de fondo. Es correcto para
  algo decorativo cuya organización se nombra en texto en la misma portada.
- **No se comprueba que el logo sea legible** sobre fondo claro. El documento
  siempre lo es; un logo pensado para fondo oscuro se verá mal y la pantalla lo
  avisa, pero la herramienta no lo corrige.
- **No hay tematización por organización**: los colores siguen siendo los de
  Statera. Eso ya sería marca blanca.

### Lo que esta pantalla declara que no hace todavía

- **El domicilio no se imprime** en ningún documento. El membrete de la portada
  lleva hoy el logo y la razón social; la dirección postal, todavía no.
- **No se valida que el CIF exista** ni que la letra cuadre, por el mismo motivo que
  no se valida el NIF de una persona: un NIE o un identificador extranjero no siguen
  la misma regla, y rechazarlos impediría dar de alta a alguien real.
- **No se comprueba que la razón social sea la del registro mercantil.** Statera
  registra lo que se declare.
- **No hay alta ni baja de organizaciones**: esto edita la propia. Crear tenants es
  panel de superadministración, fuera de alcance en los tres documentos.
