---
paths:
  - app/Domain/Conformidad/**
  - resources/js/pages/conformidad/**
  - resources/js/components/conformidad/**
  - app/Domain/Documento/Contenido/DeclaracionConformidadEns.php
---

# La conformidad con el ENS

§ 4.17. **El flujo de categoría básica entero**: autoevaluación cerrada →
Declaración de Conformidad firmada → distintivo publicado. Vive en
`app/Domain/Conformidad/`, con dos tablas —`conformidades` y su histórico
`conformidad_transiciones`— y un sexto documento calculado,
`TipoDocumento::DeclaracionConformidadEns`. La vía de media y alta —auditoría
ENAC → Certificación— **se modela y no se implementa**: la columna `via`, la
entidad y el número de certificado existen con su `CHECK`, e `IniciarDeclaracion`
la rechaza con un mensaje que lo dice.

### Una fila por declaración, y la categoría congelada

**No es una columna de `sistemas`.** La conformidad se renueva cada dos años y el
auditor pregunta por la anterior (invariante 7). Y la categoría de un sistema se
deriva y no se guarda (invariante 4), pero lo que se declara es la categoría **del
día en que se inició**: `conformidades.categoria` la congela, igual que
`auditoria_puntos.exigencia_congelada`. Revalorar el sistema después no toca lo
declarado; la DdC lo avisa en sus limitaciones si ya no coincide.

**Básica se declara y media y alta se certifican, y lo dice la base**:
`conformidades_via_categoria_check` es `(via = 'declaracion') = (categoria =
'basica')`, en las dos direcciones.

**`caducada` no es un estado.** Se deriva de `vigente_hasta`
(`Conformidad::haCaducado()`, los scopes `enVigor()` y `caducadas()`). Guardarlo
exigiría un comando nocturno, y el día que fallara una declaración vencida se
enseñaría como vigente. Es el único rojo del módulo, y lo pone
`Conformidad::tono()`, no el enum.

**Dos índices únicos parciales y no uno**: uno en preparación por sistema, y uno
vigente —declarada o publicada— por sistema. La renovación se prepara **con la
anterior todavía en vigor**, y `RegistrarDeclaracion` retira la anterior en la
misma transacción, con la nota «Sustituida por…».

### Las puertas de `IniciarDeclaracion`

Las enumera **una sola clase**, `RequisitosDeDeclaracion`, y la leen la ficha —que
enseña los bloqueos antes de que nadie pulse— y la acción —que se niega si la
lista no está vacía—. Son los bloqueos que harían **falsa** la declaración:

- sistema bajo el ENS y con las cinco dimensiones valoradas;
- categoría básica;
- una **autoevaluación cerrada**, y la última: declarar desde la del año pasado
  habiendo una más reciente sería escoger la que salió mejor. Una auditoría
  interna cerrada no cuenta;
- con checklist y **sin puntos pendientes**: pendiente no es conforme;
- que **no respalde ya la declaración vigente**: renovar es volver a comprobar,
  no volver a firmar lo mismo. La primera versión lo permitía —la ficha ofrecía
  «Iniciar la renovación» recién declarada— y lo destapó el recorrido en el
  navegador, no un test;
- **sin no conformidades mayores abiertas**. «Cerrada» es
  `EstadoNoConformidad::esCerrada()`, que incluye la anulada. Las menores y las
  observaciones no bloquean: se declaran con su plan de tratamiento.

### La firma no se da aquí

**Dos permisos y ninguno de supervisión** (`conformidad.ver`,
`conformidad.gestionar`). La firma existe y es la de la Declaración de
Conformidad, que se aprueba en `/documentos` con `documentos.aprobar`; duplicarla
sería pedir dos firmas para el mismo papel. El Técnico gestiona y no firma, porque
no tiene `documentos.aprobar`.

`RegistrarDeclaracion` sólo **ata** la versión emitida, y comprueba que sea
exactamente la suya: tipo DdC, mismo sistema, emitida y vigente, y **emitida
después de iniciar la declaración**. La fecha de la declaración es la de la firma
(`aprobada_en`) y la vigencia se congela ahí, bienal, con `Obligacion\Cadencia`.

**Esa última comprobación va en SQL y no en PHP, y no es un capricho.**
`documento_versiones.emitida_en` es `timestamptz` y `conformidades.created_at` no;
las dos se escriben con la hora de Madrid sin desfase, así que Eloquent devuelve
la primera como UTC y la segunda como hora local, y en PHP quedan separadas dos
horas. En la base se leen igual. El desplegable de versiones del controlador usa
el mismo filtro. **Y no se compara con `aprobada_en`**, que es una fecha: una
versión firmada la misma mañana en que se inició quedaría «antes».

**Registra además el cumplimiento de `ens.conformidad`** si la organización asumió
esa obligación para el sistema, con `documento_id` apuntando a la serie de la DdC y
`cubre_hasta` igual a la vigencia. No se duplica si ya hay uno de ese documento en
esa fecha. Por eso la DdC **no lleva `periodicidad_revision_meses`**: el
vencimiento sale del compromiso, y con las dos cosas el calendario pintaría dos
chips para el mismo plazo.

### El distintivo se registra, no se sirve

Lo publica la organización en su web junto a la declaración (CCN-STIC 809). Aquí
se guarda la URL —sólo `http(s)://`, lo comprueban el `FormRequest`, el dominio y
un `CHECK`—, la fecha y una evidencia opcional. **No hay ninguna ruta pública**:
la herramienta entra en el alcance del SGSI (invariante 8) y una página sin sesión
sería la primera puerta de fuera hacia dentro. Fue decisión expresa al construir
el módulo; si algún día hace falta una página de verificación, es una decisión de
seguridad y se toma aparte.

### El documento

`Contenido/DeclaracionConformidadEns` implementa `GeneradorDocumento` con
`ArmaContenidoComun`, como el acta. Lee **la conformidad viva del sistema** —la
que está en preparación si la hay; si no, la vigente— y nunca la categoría del
sistema hoy. Sin conformidad iniciada, `DocumentoNoGenerable::sinDeclaracionIniciada()`.

Tres fuentes nuevas del cuerpo: `declaracion_formal`, `ficha_autoevaluacion` y
`resultado_autoevaluacion`. **`declaracion_formal` está en
`SIEMPRE_RECALCULADOS`**, como la ficha de portada: quién declara, qué sistema, qué
categoría y sobre qué autoevaluación es identificación, y una DdC editada a mano
para decir «categoría media» es exactamente lo que un auditor no puede aceptar.

**La tabla de resultados va en texto y no en badges.** «No conforme» y «no
conformidad mayor» gastan el rojo en la aplicación y el documento no lo tiene
entre sus tonos (`EsquemaCuerpo::TONOS_BADGE`); en gris se igualarían a una medida
fuera de muestra.

La serie la crea `PrepararDocumentoDeclaracion`, **una por sistema y reutilizada en
cada renovación** —la de 2028 es la v2 de la de 2026, y su control de versiones
enseña la anterior con su huella—, y nace **pública**: se cuelga en internet junto
al distintivo.

### Lo que este módulo no hace todavía

- **La vía de certificación** (media y alta), modelada y sin camino que la rellene.
- **Ninguna cifra en el panel.** Una declaración caducada es un rojo de verdad y no
  sale en `AlertasDelPanel`; se ve en `/conformidad` y en el calendario, a través
  del compromiso `ens.conformidad` si está asumido.
- **No comprueba la antigüedad de la autoevaluación**: se puede declarar sobre una
  cerrada hace dos años si es la última. La ficha enseña su fecha de cierre; no lo impide.
