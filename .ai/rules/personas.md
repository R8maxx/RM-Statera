---
paths:
  - app/Domain/Persona/**
  - resources/js/pages/personas/**
  - resources/js/pages/puestos/**
  - resources/js/pages/formacion/**
  - resources/js/components/persona/**
  - resources/js/components/puesto/**
  - resources/js/lib/organigrama.ts
---

# Las personas

§ 4.8, la cláusula 5.3 y `mp.per.*`. **El primero de los dos módulos que muerden
hoy**: en categoría básica ya son exigibles los deberes por escrito (`mp.per.2`),
la concienciación (`mp.per.3`) y la formación (`mp.per.4`), y hasta aquí no había
dónde registrarlos. Vive en `app/Domain/Persona/`, con seis tablas.

### `personas` no es `users`, y no se fusionan

Es la decisión que da forma al módulo. `users` son las **cuentas** de Statera
—quien entra, mira y cierra tareas— y `personas` es la **plantilla**: quien firma
un acuerdo, asiste a la formación y puede ser designado responsable de seguridad,
tenga o no cuenta. La mayoría no la tiene.

Por eso los responsables de activos, tareas, evidencias y objetivos **siguen
apuntando a `users` y no se migran**: asignar una tarea a quien no puede entrar a
cerrarla no sirve de nada. `personas.user_id` es el puente, nullable y único, y
esa unicidad es de toda la tabla y no por organización — una cuenta pertenece como
mucho a una persona, y dos organizaciones no comparten cuentas.

**La consecuencia, declarada y reescrita en el PDF**: el acuse de lectura sigue
registrando **usuarios de Statera**, y quien no tiene cuenta no puede acusar
recibo. `CoberturaAcuse` **no lee de `personas` a propósito**: hacerlo convertiría
a media organización en «pendiente de leer» para siempre, sin ninguna puerta por
la que dejar de estarlo, que es la clase de cifra inalcanzable que este producto
evita en todas partes.

**`activa` no es columna: es `fecha_baja IS NULL`.** Mismo criterio que `vigente`
en el análisis del contexto y que el ámbito derivado de una cuestión del DAFO.
Reincorporar a alguien es vaciar un campo y no acordarse de dos.

### La incompatibilidad del 5.3, que es lo que paga el módulo

La especificación no dice «avisar» ni «señalar»: dice que el sistema debe
**impedir** que el responsable de seguridad y el responsable del sistema recaigan
en la misma persona. Quien decide qué protección hace falta no puede ser quien
responde de haberla puesto.

**La regla vive en `DesignarRol` y no en un `CHECK`**, y el motivo es estructural:
es una condición **entre filas** —dos designaciones vigentes de la misma persona
en el mismo sistema— y un `CHECK` sólo ve una. Es el precedente exacto de
`RegistrarDependencia`, que rechaza los ciclos del grafo de activos porque el
`CHECK` de `activo_dependencias` sólo cubre el bucle de un salto. Y vive en el
dominio y no en el `FormRequest` porque vale igual para un importador y para el
seeder.

**Lo que sí impone la base es el titular único**: un índice único parcial sobre
`(sistema_id, rol) WHERE hasta IS NULL`, y sólo para los tres roles singulares.
Responsable de la información y responsable del servicio pueden ser varios —uno
por cada información tratada y por cada servicio prestado—, y exigirles unicidad
sería inventarse una restricción que la guía no pone. La guarda del dominio existe
para que el mensaje sea legible y no el nombre del índice, como la del cierre de
una auditoría.

**El administrador de la seguridad no entra en la incompatibilidad**, aunque sea
tentador: la guía lo pone bajo la dirección del responsable de seguridad, no en
conflicto con él, y en una organización pequeña es habitual que coincidan.

**Por sistema y no por organización**, que es donde la incompatibilidad significa
algo y como CCN-STIC 801 reparte los roles: una persona puede ser responsable de
seguridad de un sistema y responsable del sistema de otro sin perder separación de
funciones.

**Las designaciones llevan vigencia y no se borran.** «¿Desde cuándo es
responsable de seguridad?» es literalmente la pregunta del auditor (invariante 7),
y «¿hasta cuándo?» es la otra mitad. Revocar pone `hasta`; vigente es
`hasta IS NULL`.

**Y `RolEns` NO son los roles de `Domain\Autorizacion\Enums\Rol`.** Aquéllos
deciden quién puede tocar qué dentro de Statera; éstos son cargos de la
organización, se designan por escrito y el auditor pide el nombramiento. Una
persona puede ser responsable de seguridad del sistema sin tener cuenta, y quien
tiene el rol `ResponsableSeguridad` de la aplicación puede no ser quien lo es de
verdad. El aviso ya estaba escrito en la cabecera de `Permiso` antes de que este
módulo existiera.

### La formación: convocar y asistir son dos cosas distintas

Quien no está en la lista no fue convocado; quien está con `asistio = false` fue
convocado y no fue, y **ése es el que un auditor pregunta**. Sin esa diferencia,
«formación impartida al 100 % de los convocados» saldría siempre. Por eso quien
sale de la convocatoria se borra de la pivote en vez de marcarse a `false`.

**`/formacion` es pantalla propia y no un bloque de la ficha de una persona**, por
el mismo motivo que la checklist de una auditoría: lo que se registra es una
sesión con veinte convocados, y marcar veinte asistencias exige marcado en bloque.
Al revés —apuntar veinte sesiones desde cada ficha— son veinte peticiones y veinte
oportunidades de dejarlo a medias.

**`TipoAccionFormativa` son dos casos y no un campo libre** porque `mp.per.3` y
`mp.per.4` son medidas distintas: concienciar es recordar lo que todo el mundo
tiene que saber y formar es enseñar a hacer algo a quien lo tiene que hacer. Una
organización puede cumplir una y no la otra.

**Los doce meses de vigencia son una convención del producto y no de la norma.**
El ENS dice «periódicamente» y no pone número; doce meses es el ciclo con el que
ya trabajan la revisión por la dirección, la auditoría interna y el informe INES.
Vive en `Persona::MESES_DE_VIGENCIA_FORMATIVA`, y la factory lo lee de ahí para
que cambiar la cadencia no deje en verde un test que prueba lo contrario.

### El IND-03 pasó de manual a calculado

`CalculoIndicador::PersonalFormado` — activas con al menos una asistencia en los
últimos doce meses, sobre el total de activas. **El numerador sale de restar**
`sinFormacionReciente()` del total, no de una segunda consulta con la condición
contraria, que sería la misma regla escrita dos veces.

Sin marco (`admiteMarco()` → `false`), como las evidencias y los activos: la
plantilla es de la organización entera y sirve a los dos marcos (invariante 6).

**Y el `CHECK` de `indicadores.calculo` necesitó migración**, con la lista escrita
a mano en las dos direcciones: construirla desde el enum haría que `migrate:fresh`
admitiera cualquier caso nuevo sin migración y ningún test se pondría rojo. Su
`down()` borra los indicadores del cálculo nuevo **a través de
`ContextoOrganizacion::comoMantenimiento()`**, y eso no es adorno: una migración no
tiene petición ni usuario, así que RLS deniega por defecto y `DB::table(...)
->delete()` afecta a **cero filas sin fallar** — el `ALTER TABLE` de la línea
siguiente muere con «is violated by some row». Es el segundo sitio del producto
que atraviesa las tres capas, y el primero fue el recuento del importador.

**El hueco del indicador manual lo ocupa la satisfacción de las partes
interesadas**, que es honesto: es justo la entrada 9.3.2 e) que el acta de la
revisión por la dirección declara que se aporta fuera de Statera.

### Las dos checklists

`pasos_persona` es el patrón de las subtareas de una tarea: la lista se guarda
entera en una sola ruta, el orden va implícito en la posición del array,
`hecho_en` **no se vuelve a sellar** si ya estaba marcado, lo que no viene se borra
y un `id` que no es de esa persona se trata como un paso nuevo.

**Las dos se guardan por separado**, y eso sí es de aquí: la de alta y la de baja
se rellenan con meses de diferencia y por gente distinta, y una sola ruta haría que
guardar la de salida borrara la de entrada si el cliente se dejara un campo.

**Marcar todos los pasos no da de baja a nadie**, igual que marcar todas las
subtareas no cierra una tarea: la baja es una fecha y se pone al editar la persona.

### Tres verbos: el noveno de supervisión

`personas.ver`, `personas.gestionar` y **`personas.designar`**. Dar de alta a
alguien, apuntar su formación y marcar su checklist es trabajo del técnico;
designar al responsable de seguridad de un sistema es un nombramiento que la
organización firma y que el auditor pide por escrito. Es la misma familia que
`sistemas.valorar`, `riesgos.aceptar`, `documentos.aprobar`, `contexto.aprobar`,
`no_conformidades.verificar`, `objetivos.aprobar` y `revision_direccion.aprobar`.

### El único rojo del módulo

**La salida sin cerrar**: alguien que se fue con la checklist de baja a medias es
un acceso que puede seguir vivo, y es el hermano exacto de
`Activo::esperaBorradoSeguro()` — la herramienta no corrige el dato, lo pone
delante. Sólo se mira en quien ya no está: una checklist de salida sin empezar en
alguien que sigue trabajando no es una laguna, es que todavía no toca.

**No estar formado no va en rojo**, ni no tener acuerdo: son la distancia que
queda, y el quinto principio del producto. Y el anillo del panel es de los **roles
ENS designados** y no del personal formado, porque aquél tiene denominador estable
—los sistemas por los cinco roles— y mide cuánto está decidido, que es el caso del
anillo del inventario; el porcentaje de formados sube al impartir una sesión y baja
solo al pasar doce meses, así que castigaría por tener plantilla nueva.

### `resolveChildRouteBinding()`, por cuarta vez en el producto

`scopeBindings()` deduce la relación pluralizando el nombre del parámetro **en
inglés** —`designacion` → `designacions`— y aquí el dominio se nombra en español.
Sin escribirlo a mano, `/personas/{persona}/designaciones/{designacion}` responde
500 y de paso deja de acotar: el nombramiento de otra persona se revocaría desde
ésta. Los precedentes son `Documento`, `Indicador` y `Objetivo`, y **lo cazó un
test de aislamiento y no una revisión**, igual que las tres veces anteriores.
`acuerdo` y `paso` no hacen falta: su plural inglés coincide con el español, que es
justo lo que hace este fallo difícil de ver leyendo las rutas.

**Y el parámetro de una sesión es `{accion}` y no `{accion_formativa}`.** El
binding implícito empareja por **nombre de parámetro**, así que con
`{accion_formativa}` y un argumento `$accion` Laravel inyecta un modelo vacío y la
escritura muere con un «null value in column». No lanza: escribe mal.

### Lo que este módulo declara que no hace todavía

- **No sustituye a `users`**, y no lo pretende. Ver arriba.
- **No comprueba que un nombramiento esté firmado** por quien tiene potestad, ni
  que la persona designada reúna la competencia que `mp.per.1` pide —que en básica
  está en `no_aplica`—. Va impreso en la DdA.
- **No gestiona bajas automáticas**: dar de baja a alguien no cierra sus
  designaciones ni revoca su cuenta. La checklist de salida es lo que lo recuerda,
  y el rojo del módulo es no haberla cerrado.
- **No comprueba que la plantilla esté completa**, ni que todo puesto tenga
  caracterización. Desde los puestos, la caracterización al menos **tiene dónde
  escribirse** y se puede contar quién no la tiene; comprobarla sigue sin
  hacerse.
- ~~**No entra en el calendario de obligaciones**~~. **Entra desde el § 4.16**, y
  era la primera `Fuente` que hacía falta de verdad: la formación que toca este
  año no la cubría ni `Fuente::Documento` ni las tareas. Lo que la fuente pinta es
  `Persona::formacionCaducada()` / `formacionPorCaducar()`, y **la fila es la
  persona y no la sesión**: lo que vence es que a alguien le toca renovar, no la
  convocatoria de marzo.

  Lo que **sigue sin hacer** es avisar de quien **nunca** ha recibido formación:
  no hay fecha que pintar y `fecha_alta + 12` sería inventarle un plazo. Ésos
  salen donde ya salían, en `sinFormacionReciente()` y en el panel — así que el
  calendario es un **subconjunto** del panel y nunca al revés, y hay un test que
  lo fija.

---

## Los puestos y los datos de la persona

Lo que el § 4.8 dejó fuera y una organización real necesita para usarlo: quién es
cada persona, qué puesto ocupa y cómo se ordena la plantilla.

### El nombre completo lo calcula PostgreSQL

`personas` gana `nif`, `nombre_pila`, `apellido1`, `apellido2`, `telefono`,
`telefono_fijo`, `direccion` y `fecha_nacimiento`. Y **`nombre` no desaparece ni
cambia de significado**: sigue siendo el nombre completo que se muestra, se
ordena y se busca —lo leen dieciséis sitios, y en `PersonaRecurso` es columna
ordenable, campo de búsqueda y `ordenPorDefecto()`—, pero pasa a **derivarse** de
las partes con una **columna generada `STORED`**.

Tiene que ser columna de SQL y no accesor de PHP porque se ordena y se busca con
índice; y no puede escribirse al lado de sus partes porque sería el mismo dato en
dos sitios que pueden discrepar, que es lo que el repositorio ya evita con
`activa`, con `vigente` y con el ámbito de una cuestión del DAFO.

Tres cosas que costaron:

1. **`concat_ws` NO sirve**: PostgreSQL rechaza la columna con «generation
   expression is not immutable» —acepta `VARIADIC "any"` y su salida depende de
   la función de salida de cada tipo—. La expresión va con `coalesce` + `||` +
   `regexp_replace`, y el `regexp_replace` **no es adorno**: sin él, un apellido
   vacío deja el hueco doble —«Ana  Prat»— que era justo lo que `concat_ws`
   evitaba saltándose los nulos.
2. **No hay `ALTER COLUMN … SET GENERATED` para una expresión.** Lo que existe
   desde PG 17 reescribe la de una columna que **ya** es generada. De plana a
   generada hay que renombrar y crear al lado.
3. **Tras el `INSERT`, Eloquent sólo recupera el `id`**, así que una persona
   recién creada llegaba **sin `nombre`** y `DesignarRol` moría con un `TypeError`
   que no menciona la columna. `Persona` relee la fila en `created`, y va en el
   modelo y no en cada llamador porque vale igual para el seeder, una factory y un
   importador.

**Sin migración de datos, y es deliberado.** El `RENAME` deja el nombre completo
de siempre en `nombre_pila`, los apellidos nacen nulos y la generada reproduce el
mismo texto byte a byte. Partir «María del Carmen de la Fuente Gómez» es una
heurística que se equivoca, y equivocarse aquí cambia el nombre impreso en un
nombramiento firmado.

**Protección de datos, que aquí se implementa y no se comenta.** NIF, fecha de
nacimiento, teléfonos y domicilio son datos personales en una herramienta que
está en el alcance de su propio SGSI. Ninguno entra en la búsqueda libre ni en el
CSV; sólo el NIF llega a la tabla, **oculto por defecto**. Es único por
organización —índice parcial, los nulos no chocan— y se normaliza a mayúsculas y
sin separadores, o «12345678z» y «12345678-Z» serían dos documentos distintos.
**No se valida la letra**: un NIE y un pasaporte no la tienen, y rechazarlos sería
impedir dar de alta a alguien que trabaja aquí. Y `RegistraTraza` guardará sus
valores anteriores en `eventos_auditoria`: es correcto para la trazabilidad e
implica que el log pasa a contener datos personales, y eso hay que saberlo antes.

### La jerarquía vive en el puesto, no en la persona

`puestos` —con `reporta_a_id`— y `asignaciones_puesto` entre medias. El
organigrama de personas sale de cruzarlo con quién ocupa cada puesto, así que es
**un solo árbol con dos lecturas** y no dos que puedan discrepar; y que alguien
entre o se vaya **no lo mueve**, que es lo que envejece a un organigrama de
personas en dos semanas.

La asignación **lleva vigencia y no se borra**, patrón literal de
`designaciones_rol`: «¿desde cuándo ocupa ese puesto?» es la pregunta del auditor
(invariante 7). Índice único parcial sobre `persona_id` y **no** sobre
`puesto_id` — varias personas ocupan «Técnico de sistemas» a la vez. Cambiar de
puesto **cierra el anterior el día antes**, en la misma transacción: dos
asignaciones que se solapan un día harían que «qué puesto ocupaba el 3 de marzo»
tuviera dos respuestas.

**El ciclo no cabe en un `CHECK`**: es una condición entre filas, y contra un
grafo con un bucle la CTE del organigrama no devuelve un resultado raro, **no
termina**. El `CHECK` tapa el bucle de un salto; el resto lo rechaza
`AsignarSuperior`, precedente exacto de `RegistrarDependencia`, y está en el
dominio porque vale igual para un importador. La CTE arrastra además la ruta en un
`ARRAY` como red de seguridad.

**La CTE necesita `::text` en las dos ramas.** La base devuelve `varchar(255)` y
la recursiva una concatenación sin límite, y PostgreSQL exige que los tipos casen:
«recursive query column N has type character varying(255) in non-recursive term».
Lo cazó un test, no una lectura.

**La migración de datos es la que podía perder información en silencio.** Sin
petición no hay contexto, RLS deniega por defecto y un `INSERT … SELECT` escribe
**cero filas sin error** — y el `DROP COLUMN personas.puesto` de la línea
siguiente sí funciona. Va por `ContextoOrganizacion::comoMantenimiento()`, tercera
aparición en el repositorio, y **comprueba el recuento** antes de dejar que la
transacción se cierre. Los códigos salen de
`row_number() OVER (PARTITION BY organizacion_id ORDER BY titulo)`, así que el
único por organización se cumple por construcción y dos organizaciones con el
mismo texto acaban en dos filas distintas.

**Y la asignación se cierra el día de la baja para quien ya no está.** La columna
de texto no distinguía las dos cosas —guardaba el último puesto de todo el mundo,
estuviera o no—, pero una asignación vigente sobre alguien que se fue es falsa:
lo pinta ocupando su puesto en el organigrama y deja el puesto fuera de
«vacantes», que es justo lo que hay que ver para cubrirlo. **Se vio en el
diagrama**, no en un test.

Dos cosas más del `down()`, las dos aprendidas rompiéndolo: **vacía las filas que
el `up()` insertó** —si no, un `rollback` seguido de un `migrate` choca con el
índice único—, y **restaura desde la asignación más reciente y no desde la
vigente**, porque la columna original guardaba el último puesto hubiera o no baja
y restaurar sólo las vigentes se llevaba por delante el puesto de quien ya no
está.

**El organigrama son tres rutas hermanas**, no un conmutador de cliente: la
decisión ya tomada para `/tareas`, porque el enlace que alguien pega en un correo
tiene que abrir la vista que estaba mirando.

| Ruta | Qué enseña |
|---|---|
| `/puestos/organigrama` | Lista sangrada. **La vista por defecto** |
| `/puestos/organigrama/grafo` | Diagrama de cajas, sólo los puestos |
| `/puestos/organigrama/grafo-personas` | El mismo diagrama con los ocupantes dentro |

**La lista sigue siendo la de por defecto aunque haya diagrama**, y no por
antigüedad: es la única de las tres que se recorre entera con el teclado y que
cabe en 375 px sin arrastrar. Un lienzo de nodos no hace ninguna de las dos
cosas, así que es la alternativa y no el sustituto — DESIGN.md § 11. Las dos
vistas de diagrama lo dicen debajo y enlazan a la lista.

**El scroll vive en la caja y no en la página**, que es lo que permite tener un
diagrama sin romper la regla de no desplazar la página en horizontal: en móvil se
navega arrastrando dentro del lienzo, y el minimapa se oculta por debajo de `sm`
porque a esa anchura estorba más que orienta.

**El mismo payload para las tres vistas**, ocupantes incluidos: son cinco campos
por nodo, y ahorrarlos en la que no los pinta obligaría a tres consultas y a que
el conmutador cambiara de datos además de de forma. Quien decide qué se enseña es
el componente.

Tres cosas del diagrama que no se ven leyéndolo:

1. **La raíz sintética.** `d3-hierarchy` sólo sabe colocar un árbol, y una
   organización puede tener varias raíces —las tiene mientras el organigrama se
   monta—. Se cuelgan todas de una falsa, se coloca el conjunto y la falsa se
   descarta. De paso recoge los puestos cuyo superior ya no existe, que es lo que
   deja un borrado: verlos arriba es lo que permite arreglarlos.
2. **El tamaño de la caja vive en `lib/organigrama.ts` y no en el componente**,
   porque **la disposición depende de él**: d3 separa los hermanos por el ancho
   que se le diga, y un componente que pintara cajas más anchas que las
   declaradas las solaparía.
3. **El encuadre se pide en `onNodesInitialized` y no con `fit-view-on-init`.**
   Aquél corre antes de que el lienzo tenga su tamaño definitivo, y en una ventana
   estrecha deja el árbol medio fuera. Se vio a 500 px.

**Nada se arrastra ni se conecta en el lienzo.** La jerarquía se cambia en la
ficha del puesto, que es donde `AsignarSuperior` comprueba los ciclos; dejar mover
nodos aquí prometería que el organigrama se edita arrastrando.

> **El falso positivo que costó un rato, y que vale para cualquier animación de
> este producto.** Al montar el organigrama pareció que las filas salían
> congeladas a `opacity: 0`, y de ahí se dedujo un fallo de herencia de variantes
> de `motion-v` que **no existe**: se llegó a anotar que
> `components/activo/GrafoDependencias.vue` lo tenía, y es falso — ese bloque se
> pinta perfectamente.
>
> Lo que pasaba era la **forma de medir**. Las animaciones van por
> `requestAnimationFrame`, que **no avanza mientras la ventana no pinta** —una
> ventana tapada, minimizada o recién abierta por automatización—. Un
> `getComputedStyle` en ese momento devuelve el estado `initial` de todo:
> `opacity: 0` en las filas, en el `<main>` del layout y en el filete de la
> cabecera. Parece un fallo y es una foto tomada antes de que empiece la película.
>
> **Cómo comprobarlo de verdad**: muestrear en el tiempo —llega a `opacity: 1` en
> menos de 250 ms— o mirar una captura, no un `getComputedStyle` suelto. Si el
> `<main>` también sale a cero, no hay un fallo en el componente: no está
> pintando nadie.
>
> Del episodio sí queda una preferencia, no una corrección: el escalonado de una
> lista se declara **en el padre con `:variants`** y los hijos sólo nombran su
> variante, que es lo que hacen `TiraIndicadores` y otros seis sitios. Con
> objetos en `initial`/`animate` también funciona —`GrafoDependencias` lo hace—,
> pero tener un solo patrón para lo mismo vale más que el matiz de escalonar por
> nivel en vez de por fila.

**Sin verbo de permiso nuevo**: se reutilizan `personas.ver` y
`personas.gestionar`. Es el mismo módulo, y un `puestos.*` habría que acordarse de
añadirlo a mano en las listas literales de `Rol::permisos()` para `Tecnico` y
`Auditor`, que es la trampa que `RolesTest` existe para cazar.

Con `puestos.competencias` relleno, **`mp.per.1` pasa a tener dónde escribirse** y
el indicador «puestos sin caracterizar» se puede calcular. **Sigue sin hacerse**:
comprobar que la plantilla esté completa, que el organigrama lo esté, y que quien
ocupa un puesto reúna la competencia que ese puesto pide.
