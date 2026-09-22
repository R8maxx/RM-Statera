---
paths:
  - app/Domain/Mejora/**
  - resources/js/pages/mejoras/**
---

# Las oportunidades de mejora

Cláusula 10.1, «mejora continua», y la segunda mitad del capítulo 10. Es la última
entrada que le faltaba a la 9.3: con este módulo dentro, las siete entradas
obligatorias de la revisión por la dirección salen del producto.

Vive en `app/Domain/Mejora/`, con tres tablas: `mejoras`, `mejora_tarea` y
`mejora_transiciones`.

### Tabla propia, y el motivo es aritmético antes que conceptual

**No es una ampliación de `no_conformidades`.** «No conformidades abiertas» es a
la vez cifra del panel, cálculo de `CalculoIndicador` y entrada obligatoria de la
9.3; con las mejoras dentro, una idea apuntada contaría como un incumplimiento en
los tres sitios. Contar de más es el fallo caro y aquí se evita no dando la
ocasión — el mismo argumento que dejó las subtareas fuera de `tareas`.

Y la diferencia de fondo es la que hace la norma: **la 10.2 trata lo que incumple
y la 10.1 lo que se puede mejorar sin que nada incumpla**. Una tiene causa raíz y
verificación de eficacia porque algo falló; la otra no tiene nada que verificar.

Por eso la tabla es **mucho más corta**: sin `correccion_inmediata`, sin
`analisis_causa_raiz`, sin `fecha_verificacion` y sin `resultado_verificacion`.
Copiar esas cuatro columnas «por simetría» sería pedirle a quien apunta una idea
que declare la causa raíz de algo que no ha pasado.

### La bifurcación del hallazgo, cerrada por los dos lados

`TipoHallazgo::abreMejora()` y `admiteNoConformidad()` deciden a qué registro va
cada hallazgo, y **las dos puertas están en el dominio**: `RegistrarNoConformidad`
lanza `HallazgoNoTratable` si alguien intenta tratar una oportunidad de mejora
como no conformidad. Está en el dominio y no sólo en el `FormRequest` porque la
regla vale también para un importador — mismo criterio que el motivo de
`descartada` en tareas.

Y en la interfaz no se rechaza, **se redirige**: `/no-conformidades/crear?hallazgo=`
lleva a `/mejoras/crear?hallazgo=` cuando el tipo no corresponde, y al revés.
Dejar rellenar un formulario que el dominio va a rechazar al final es la forma más
cara de decir que no.

**Un hallazgo se trata una vez** en cada registro, y lo impone el índice único
sobre `mejoras.hallazgo_id`. En PostgreSQL los nulos son distintos entre sí, así
que deja pasar todas las mejoras sueltas que hagan falta — que son la mayoría: casi
ninguna mejora sale de una auditoría.

### El módulo sin rojo

**Es el único registro del producto sin `alertas()`**, y es la decisión que lo
define. Ninguna cifra de aquí va mal de verdad: una idea sin hacer no incumple
nada —la 10.1 pide mejorar de forma continua, no tener cero ideas pendientes— y
una mejora descartada es una decisión legítima. Pintar de rojo lo que alguien
apuntó voluntariamente es la forma más rápida de que deje de apuntarlo, que es el
quinto principio del producto.

Ni siquiera el plazo. `Mejora::sePasoDeFecha()` se llama así y no `haVencido()` a
propósito: nadie se comprometió a esa fecha —eso es un objetivo de la 6.2, que sí
lleva su rojo—, y el tono de la columna **rebaja `caducada` a `no_iniciado`**. Hay
un test que recorre el enum comprobando que ningún estado gasta rojo.

Lo que sí hay es `sinEmpezar`, que es la cifra honesta del registro: un buzón de
ideas al que nadie vuelve no es mejora continua.

### Dos verbos, y ninguno de supervisión

`mejoras.ver` y `mejoras.gestionar`. **Aquí no hay nada que firmar**, y es lo que
lo separa del registro de al lado: no hay eficacia que verificar porque no había
nada roto, y no hay compromiso que aprobar porque nadie se obligó. Cuando una
mejora se convierte en un compromiso, lo que nace es un **objetivo de la 6.2**, que
sí tiene su verbo. El técnico la gestiona entera, descartarla incluida.

**Una sola transición exige motivo: descartar.** Implantar no lo pide —lo que se
hizo lo cuentan sus tareas— y pedir un texto para cerrar lo que sí se hizo
convierte en trámite el único gesto del registro que da alegrías.

### Sin doble vínculo, y aquí el argumento es distinto

`VincularActuacionDeMejora` ata **un solo** extremo, como en objetivos y en el
contexto. Pero el motivo no es el mismo que allí: en un objetivo **no hay medida
detrás por construcción**, y aquí sí puede haberla —cuando la mejora viene de un
hallazgo con punto de checklist— y aun así no se ata.

El motivo: **una oportunidad de mejora no incumple la medida**. El plan de
adecuación lista lo que falta por implantar, y una medida que ya está implantada y
que además se puede hacer mejor no está pendiente de nada. Atar el vínculo la
metería en un plan que presupuesta brechas, que es la clase de cifra inflada que el
§ 4.13 tuvo que arreglar por el otro lado.

**La consecuencia, declarada:** el coste de una actuación de mejora no entra en el
presupuesto del plan de adecuación.

**`OrigenTarea::Mejora` es el noveno origen y el cuarto que no está en § 4.7.** No
se apunta a `NoConformidad`: el reparto por origen del plan de acción existe para
distinguir lo reactivo de lo voluntario, y colapsarlos haría que un plan lleno de
mejoras se leyera como una organización apagando fuegos.

### Lo que este módulo declara que no hace todavía

- **No entra en el calendario de obligaciones**, y aquí ni siquiera se plantea: lo
  que vence no vence, porque nadie se comprometió. Sus tareas sí tienen plazo y
  ésas ya pintan chip.
- **No comprueba que la mejora continua exista de verdad.** La 10.1 pide mejorar
  de forma continua; Statera registra lo que se declare y no dice si el registro
  lleva seis meses sin moverse.
- **No convierte una mejora en objetivo.** Cuando una mejora se asume como
  compromiso, el objetivo de la 6.2 se registra aparte y a mano. Automatizarlo
  crearía objetivos sin plazo, sin recursos y sin firma, que es lo que la 6.2 no
  admite.
- **No entra en ningún documento.** El acta de la revisión por la dirección es
  donde estas mejoras se leen, y llega con el § 4.15.
