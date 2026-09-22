---
paths:
  - app/Domain/Riesgo/**
  - resources/js/pages/riesgos/**
  - resources/js/components/riesgo/**
---

# El análisis de riesgos

Vive en `app/Domain/Riesgo/`. Abre la fase 2, y es lo que cobra el grafo de dependencias del
inventario: el impacto de un riesgo sale de la valoración **efectiva** de sus activos, así que un
riesgo sobre una base de datos valorada «bajo» que sostiene un servicio esencial se puntúa contra
«alto». Una hoja de cálculo no hace eso.

**El catálogo de amenazas de MAGERIT es catálogo global**, en `catalogo/magerit-amenazas.yaml` y
cargado por el mismo `catalogo:importar`, que pasa a reconocer **tres** claves raíz —`marco`,
`mapeos` y `amenazas`—. Sin `organizacion_id` y sin RLS (invariante 2): «E.1 Errores de los
usuarios» no es un hecho de nadie en particular. Lo que sí es enum es `GrupoAmenaza`, porque los
cuatro grupos son la **estructura** del catálogo y no su contenido — mismo reparto que
`TipoRequisito` frente a `requisitos`. Y no entra en `requisitos` con un `tipo` nuevo: una amenaza no
se implanta, no tiene fila en `aplicabilidad_ens` y `GeneradorImplantaciones` tendría que aprender a
excluirla.

Van 56 amenazas con los códigos originales, **descripciones redactadas para Statera** —misma
disciplina que con ISO 27002— y `revisado: false`: las dimensiones de cada amenaza están asignadas
por criterio y no contrastadas celda a celda contra el Libro II, así que se usan como **sugerencia** y
nunca para descartar nada.

**La metodología es tabla por organización, no `config/`.** El precedente de `config/obsolescencia.php`
empuja en la otra dirección y su propio comentario dice por qué: aquello son hechos del mundo, iguales
para todos los clientes. Los criterios de aceptación de riesgo los fija la dirección de cada
organización (ISO 27001, 6.1.2 a) y el auditor pide el papel firmado. Y hay un segundo motivo que pesa
más: en `config/` un despliegue cambiaría la escala **retroactivamente para todo el histórico** y sin
dejar constancia.

**Cadena de dos eslabones, y gana el primero que exista:** `metodologias_riesgo` →
`MetodologiaDeFabrica`. Igual que la narrativa de los documentos, y por lo mismo: que no haga falta
materializar una fila para poder registrar el primer riesgo. **`GuardarMetodologia` borra la fila
cuando coincide con la de fábrica** —«no lo he tocado» y «no hay fila» tienen que ser lo mismo—, con
una excepción: una fila **aprobada** no se borra aunque coincida, porque ahí ya no dice «no lo he
tocado», dice «lo he mirado y lo firmo», y esa firma es lo que pide el auditor.

**El riesgo residual lo declara una persona; lo derivado se enseña al lado y no lo sobrescribe
nunca.** Es el precedente exacto de `ValoracionEfectiva`. El invariante 4 —«la aplicabilidad se
deriva, no se selecciona»— **no aplica aquí**, y conviene tenerlo escrito: aquél es una derivación
*legal*, con una respuesta correcta en el BOE, y dejar elegir a mano deja a alguien fuera de
conformidad sin enterarse. El residual no tiene BOE: **no existe ninguna función publicada** de
(`estado`, `nivel_madurez`) a riesgo residual, cualquiera que inventáramos sería una opinión de la
herramienta disfrazada de cálculo, y ISO 6.1.3 f) exige que lo apruebe el propietario del riesgo, que
no puede aprobar lo que dedujo la máquina.

Lo que sí hace la herramienta es **señalar la contradicción**: `Riesgo::residualSinRespaldo()` marca
el riesgo cuyo residual declarado baja del intrínseco sin una sola salvaguarda implantada. Es el
mismo papel que hace `Activo::esperaBorradoSeguro()` con un equipo retirado sin constancia de borrado
— no corrige el dato, lo pone delante.

**`riesgo_valoraciones` es histórico al estilo de `documento_versiones`, no de
`implantacion_transiciones`.** Una transición registra un delta sobre un campo; una reevaluación es un
juicio nuevo y entero sobre seis valores correlacionados, emitido contra una metodología que puede
haber cambiado. La especificación pide «histórico **comparable**», y comparar marzo con octubre exige
saber con qué escala se midió marzo. De ahí las dos `jsonb` congeladas: `escala` —sin ella un 12 de
marzo es un número sin unidades— y `salvaguardas` —sin ella la fila **miente** en cuanto una
implantación cambie de estado, que es el mismo motivo por el que el `.docx` se construye
`desdeInstantanea()`—. Cuál es la vigente lo marca un **índice único parcial**, como el borrador de un
documento.

**Riesgo ↔ activo es N:M, contra la letra de §2.2.** «Robo de un portátil» es UN riesgo sobre treinta
portátiles: con clave singular, o se crean treinta riesgos —y el indicador de § 4.14 cuenta treinta
donde hay una cosa que decidir, que es el argumento aritmético que dejó las subtareas fuera de
`tareas`— o se apunta a uno arbitrario y los otros veintinueve son invisibles. Y §2.2 ya se corrigió
una vez por lo mismo, con la tabla única de documentos.

**Y se recorre en los dos sentidos.** `Riesgo::activos()` contesta «sobre qué pesa» y
`Activo::riesgos()` contesta «a qué está expuesto»; sin la segunda, el inventario decía cuánto vale
una cosa y qué se cae con ella, pero no contra qué hay que protegerla. En la tabla de activos va como
**recuento** y no como nivel máximo: el nivel se lee con la escala congelada de cada valoración y eso
no es algo que SQL pueda comparar entre filas sin mentir; quién está por encima del umbral lo
contesta el filtro `riesgo_sobre_umbral`, que delega en `Riesgo::scopeSobreUmbral()` —el mismo que
cuenta el registro— en vez de reescribir la condición. Los dos van por `whereHas` y no por `join`,
por lo mismo que el alcance: un activo con tres riesgos saldría tres veces y la paginación contaría
mal.

Y **el bloque de la ficha no se manda si quien mira no tiene `riesgos.ver`**. Conectar dos módulos
abre una puerta lateral al registro del otro sin que nadie la decida; el frontend decide qué pinta y
nunca qué autoriza.

**Las salvaguardas apuntan a `implantaciones` y no a `requisitos`.** Es la diferencia entre «el ENS
pide cifrado» y «lo tenemos puesto en este sistema», y es lo que hace que un mismo control valga a la
vez de prueba de cumplimiento y de tratamiento de un riesgo **sin registrarlo dos veces**.

---

## Comandos del análisis de riesgos

```sh
php artisan catalogo:importar catalogo/magerit-amenazas.yaml   # las 56 amenazas
php artisan catalogo:importar --dry-run                        # el diff las nombra aparte
```

El seeder de desarrollo deja cuatro riesgos, y cada uno enseña una cosa distinta: uno por encima del
umbral con el **residual sin respaldo**, uno **aceptado** y con la reevaluación vencida, uno **sin
valorar** y uno con **amenaza libre** y decisión de transferir. La metodología se queda a propósito en
la de fábrica y sin aprobar, que es el estado real de partida de cualquier cliente: guardarla
escondería justamente el aviso que hay que ver.

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **Las bandas de `NivelRiesgo` salen de los umbrales, no de quintiles de la escala.** `MuyAlto` es el
  umbral crítico, `Alto` es por encima del de aceptación, y los tres de abajo reparten en tercios la
  zona aceptable. Eso es lo que hace que `porEncimaDelUmbral()` y `NivelRiesgo::sobreUmbral()` digan
  siempre lo mismo, **por construcción y no por coincidencia**. Con quintiles, un riesgo podía salir
  «muy alto» —badge rojo— estando dentro del apetito declarado, y entonces la tabla y el indicador del
  panel discreparían sobre la misma fila. Con umbrales muy bajos alguna banda queda vacía, y es
  correcto: la organización ha decidido que casi nada le resulta aceptable y la herramienta no le
  inventa grados que no ha pedido.

- **El trigger de `riesgo_valoraciones` deja apagar `vigente` en una fila aceptada.** Es la única
  excepción a la inmutabilidad y hace falta sí o sí: jubilar la anterior es el primer paso de toda
  reevaluación, y una valoración aceptada hace un año es justo la que hay que jubilar al volver a mirar
  el riesgo. Un trigger que lo bloqueara dejaría un riesgo aceptado **sin poder revaluarse nunca**, que
  es lo contrario de lo que pide § 4.3. Se compara el registro entero con `vigente` neutralizado en vez
  de enumerar columnas: una columna nueva quedaría fuera de la lista y sería editable sin que nadie lo
  notara.

- **`nota_aceptacion` es columna propia, y no se reutiliza `nota`.** Las escriben dos personas en dos
  momentos: `nota` es el razonamiento de quien valoró y `nota_aceptacion` es lo que dijo quien firmó
  —«aceptado en el comité del 3 de marzo»—. Con una sola columna, firmar pisaría el razonamiento, que
  es justo lo que el auditor quiere leer al lado de la firma.

- **`riesgos.aceptar` es el tercer verbo de permiso del producto**, junto a `sistemas.valorar`. Un
  técnico registra riesgos y los puntúa; firmar que la organización convive con una exposición es de
  dirección, y no es un matiz de permisos: es la razón entera por la que ISO 6.1.3 f) pide la
  aprobación del propietario del riesgo. Cubre también definir la metodología, porque fijar el apetito
  es decidir de antemano qué se va a poder aceptar. **Y en `Rol::permisos()` hay que acordarse a mano**:
  `ResponsableSeguridad` usa `Permiso::cases()` y se entera solo, pero `Tecnico` y `Auditor` son listas
  literales y olvidarlas no rompe nada — el módulo simplemente no aparece.

- **`Riesgo::scopeSobreUmbral()` resuelve el umbral él mismo cuando no se le pasa**, aunque un scope que
  pide un servicio no sea bonito. El indicador del panel y el filtro de la tabla invocan los scopes por
  nombre y **sin argumentos** —`$consulta->{$scope}()`, `Filtro::porScope()`—, así que un parámetro
  obligatorio obligaría a escribir la condición una segunda vez para el filtro. Y esa es exactamente la
  duplicación que deja el panel diciendo 12 y la tabla enseñando 9.

- **`CalculoRiesgo::bandas()` devuelve el tono y el icono dentro de cada banda.** El cliente no los
  deduce de un mapa propio: es la misma regla que con los estados —el mismo tono significa cosas
  distintas según el módulo—, y un mapa nivel→color en `MatrizRiesgo.vue` sería la sexta copia del
  vocabulario que `lib/tonos.ts` existe para centralizar.

- **La matriz de riesgo es rejilla CSS, no SVG**, aunque sea una gráfica. `DESIGN.md` §9 admite las dos
  —«SVG y CSS a mano sobre los tokens»— y aquí gana CSS por un motivo concreto: tiene que caber a 375 px
  sin scroll horizontal, y una rejilla se encoge con su contenedor mientras que un `viewBox` escala el
  texto hasta hacerlo ilegible. Es lo que ya hace `BarraSegmentada`; `AnilloProgreso` es SVG porque allí
  hay un arco que dibujar. **El número sólo se pinta en las celdas señaladas**: pintarlo en las
  veinticinco convierte el mapa de calor en una tabla de multiplicar, que es justo lo que la cuadrícula
  evita tener que leer.

- **Los enums de riesgo no llevan `#[TypeScript]`, y es deliberado.** `NivelRiesgo`, `DecisionRiesgo` y
  `GrupoAmenaza` viajan serializados como `ValorEtiquetado` —valor, etiqueta, tono, icono—, igual que
  `EstadoTarea`, y las pantallas declaran interfaces locales para lo que el controlador serializa a
  mano. El atributo lo llevan los que cruzan **como tipo**: `Permiso`, `Rol`, `Fuente`, `Filtro`.
