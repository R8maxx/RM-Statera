---
paths:
  - app/Domain/Metrica/**
  - resources/js/pages/indicadores/**
  - resources/js/components/metrica/**
  - resources/js/components/grafica/**
---

# Los indicadores y las mediciones

§ 4.14 y la cláusula 9.1. El panel lleva desde el principio contando cosas —cumplimiento,
inventario, plan de acción, no conformidades, contexto— y **todas esas cifras son de hoy**. La 9.1 no
pide una cifra: pide qué se mide, con qué método, **cada cuánto**, quién lo mira y **contra qué
objetivo**. Sin la serie, «¿ha mejorado esto desde la última revisión?» —que es literalmente lo que
pregunta la 9.3— se contesta con un encogimiento de hombros.

Vive en `app/Domain/Metrica/`, con dos tablas: `indicadores` y `mediciones`.

**El contexto se llama `Metrica` y el modelo `Indicador`, y eso choca con
`App\Http\Resources\Panel\Indicador`**, que es otra cosa: aquél es «una cifra que pide acción, con el
camino para ir a verla», la baldosa que nació en el inventario y se generalizó a cualquier módulo con
tabla. Es el caso de `Contexto` frente a `ContextoOrganizacion` y se resuelve igual: **no se renombra
nada**, se anota, y el único fichero donde conviven —`RegistroIndicadores`— importa uno con alias.
Renombrar el VO tocaría seis resúmenes de panel, los tipos generados y `TiraIndicadores.vue` para
ganar cero. El **espacio de nombres** sí se eligió para no tartamudear: `Domain\Indicador\Models\Indicador`
era peor que `Domain\Metrica\Models\Indicador`, y § 4.14 se titula «Métricas».

### La medición se sella, y con su objetivo al lado

Es la decisión que da forma al resto. **«Calculado» no quiere decir «se consulta al mirarlo»**:
quiere decir que el sistema **propone** la cifra al cerrar el periodo y la guarda con su fecha. Una
serie que se recalcula reescribiría marzo en octubre — el argumento literal de
`riesgo_valoraciones.salvaguardas`, `documento_versiones.instantanea`, la exigencia congelada al
cerrar una auditoría y la instantánea del análisis del contexto. Cuatro precedentes, y éste es el
quinto sitio donde una consulta en vivo mentiría sobre el pasado.

**Y se sella con su objetivo dentro** (`mediciones.objetivo`). Sin él, subir el listón en marzo
reescribiría el veredicto de enero: lo que estuvo en objetivo pasaría a figurar como fallado y nadie
sabría por qué. El objetivo vigente vive en el indicador; el que se aplicó, en la fila. **Corregir una
cifra no mueve el objetivo**, y eso lo garantiza `RegistrarMedicion`: arreglar un dígito mal tecleado
en abril no puede cambiar contra qué se juzgó marzo.

**`mediciones.origen` no es una copia de `indicadores.origen`.** El del indicador es la política de
hoy; el de la fila es el hecho de cómo se obtuvo **aquélla**. Pasar un indicador de calculado a manual
no puede reescribir cómo se tomó la medición de marzo. Mismo reparto que la exigencia congelada en
`auditoria_puntos`.

**Y aun así no lleva trigger de inmutabilidad**, a diferencia de los cuatro registros que sí lo
llevan. Una medición no la firma nadie y no se entrega sola a un auditor; lo que se congela es el acta
de la revisión por la dirección que la cita. Blindarla aquí haría imposible corregir un dedazo en una
medición manual —el caso ordinario— sin proteger nada que no esté ya protegido. La frontera del módulo
es otra: **derivar en silencio, prohibido; corregir con autor y traza, permitido**.

### El cálculo es un catálogo cerrado, no una fórmula

§ 2.2 dice «formula_o_fuente» y la tentación es una columna de texto con `(implantadas / aplicables)
* 100` dentro y un intérprete detrás. No entra, por dos motivos y el segundo pesa más: un evaluador de
expresiones en una herramienta que está en el alcance de su propio SGSI es superficie de ataque a
cambio de nada, y **cada caso de `CalculoIndicador` llama al scope o al resumen que ya existe**
—`Implantacion::pendientes()`, `Evidencia::caducadas()`, `NoConformidad::pendientesDeVerificar()`—,
nunca reescribe la condición. Es lo mismo que hace `Filtro::porScope()`, y es lo que garantiza que el
indicador, la cifra del panel y la lista que sale al pulsarla digan el mismo número.

`CalculosTest` **se parametriza solo**: recorre `CalculoIndicador::cases()` y sella cada uno, así que
un cálculo nuevo entra sin que nadie toque el fichero. Y lo que comprueba no es que no lance, es que
la cifra **entra en la tabla**: un `Medida` con numerador y sin denominador lo rechaza un `CHECK`, y
ese rechazo aparecería meses después en el comando de las siete y media de la mañana.

**Las dos mitades de «formula_o_fuente» son dos columnas**, `calculo` y `formula_o_fuente`, cada una
obligatoria exactamente cuando la otra sobra. El indicador **manual** existe igual y no es una
concesión: «porcentaje de personal formado» no sale de esta base de datos mientras el § 4.8 no exista,
y un módulo de métricas que sólo admitiera lo que ya sabe contar dejaría fuera justo lo que cuesta
medir.

### Lo derivado y lo declarado

**El cumplimiento no es una columna**: sale de (`valor`, `objetivo`, `sentido`) cada vez que hace
falta. Guardarlo sería el mismo dato en dos sitios, como `vigente` en el análisis del contexto.

**`sentido` es obligatorio aunque `objetivo` sea opcional.** «Tareas vencidas ≤ 5» y «cobertura de
cifrado ≥ 90 %» se juzgan al revés, y sin la columna el veredicto sale invertido en la mitad de los
indicadores: un cuadro de mando que felicita por subir las no conformidades vencidas se deja de mirar
el mismo día.

**Cuatro veredictos y no dos.** «Sin objetivo» y «sin medir» no son «fuera de objetivo», que es el
argumento de `EstadoControl::PorConfirmar`: vigilar algo sin comprometerse a una cifra sigue siendo
seguimiento, y un periodo sin medir es una pregunta abierta. Colapsarlos daría un panel en rojo el día
que se crea el primer indicador.

**La regla está escrita dos veces y hay test.** `Indicador::scopeFueraDeObjetivo()` la aplica
PostgreSQL sobre miles de filas y `SentidoIndicador::alcanza()` decide el badge de una; no hay forma
de tener una sola. Es el caso de `ValoracionEfectiva`, que tiene dos entradas y un test que fija que
coinciden. Aquí es `Metricas/CumplimientoCoincideTest`, que recorre la matriz de los dos sentidos por
encima, por debajo y justo en el umbral.

### El rojo es de no medir, no de quedarse corto

**Ningún veredicto gasta rojo**, y conviene decir por qué, porque `NivelRiesgo::MuyAlto` sí lo gasta
con un argumento que parece el mismo. Estar por debajo de un objetivo **es la distancia que queda**:
pintarlo de alarma castiga por ponerse objetivos ambiciosos, que es exactamente lo que el quinto
principio del producto existe para impedir —«un indicador que castiga por apuntar lo que falta enseña
a no apuntarlo»—. Un riesgo por encima del umbral crítico no es una distancia: es una exposición que
la organización ya declaró inaceptable.

Lo que sí va mal de verdad es **un periodo que cerró sin medición** habiéndose comprometido a medirlo:
eso es la cláusula 9.1 sin hacer, y es lo primero que un auditor comprueba. Es el único rojo del
módulo y lo lleva la columna «Periodo», no el estado — mismo reparto que en tareas, donde el rojo es
del plazo y no del estado.

### Un periodo, no una fecha

`Periodicidad` **parte el calendario en cubos**, y por eso no se reutiliza `Evidencia\PeriodicidadRenovacion`
aunque la palabra sea la misma y cuatro casos coincidan: aquélla **suma meses a una fecha** —«esta
captura caduca el 3 de junio»— y ésta contesta «el 3 de marzo cae en el primer trimestre». Falta
`Bienal` a propósito: existe allí por la conformidad del ENS, y a esa cadencia no hay serie, hay dos
puntos. El motivo de fondo es la dirección de la dependencia: compartirla haría que `Domain\Metrica`
supiera de `Domain\Evidencia` y ofrecería «bienal» en un cuadro de mando.

De ahí que `mediciones` tenga **`periodo_inicio`, `periodo_fin` y `medida_en`**: a qué pertenece el
dato y cuándo se tomó no son lo mismo, y una medición de marzo apuntada en abril desordenaría la serie
si se ordenara por `created_at`. El par `(indicador, periodo_inicio)` es único: medir dos veces el
mismo periodo es **corregir, no acumular**.

**El formulario pide una fecha cualquiera, no dos extremos.** Dejar escribir los dos permitiría sellar
«del 3 de marzo al 7 de abril», que no es ningún trimestre, y la serie tendría puntos que no encajan
con los demás. Y **no se sella el periodo en curso**: una cifra a medias habría que corregirla al día
siguiente.

### El marco, y lo que no se deja acotar

`indicadores.marco_id` es opcional y sólo lo admiten los cuatro cálculos que cuelgan de
`implantaciones`. § 4.14 pide «porcentaje de implantación **por marco**» con esas palabras, y es la
pregunta del producto: ISO y el ENS avanzan a ritmos distintos y una sola cifra los promedia hasta que
no dice nada. Las evidencias, las tareas y los activos **no se acotan**: son de la organización entera
y sirven a los dos marcos a la vez (invariante 6), así que repartirlos los contaría dos veces o los
dejaría fuera de uno. Lo rechaza el `FormRequest` y lo declara `CalculoIndicador::admiteMarco()`.

### El comando, y dónde se rompe

```sh
php artisan indicadores:medir              # sella el periodo cerrado de cada indicador calculado
php artisan indicadores:medir --dry-run    # enseña la cifra que saldría, sin escribir
php artisan indicadores:medir --fecha=…    # toma otro día como «hoy», para cerrar antes
```

**Mismo cuidado que `avisos:enviar` y por lo mismo**: un comando programado no tiene petición ni
usuario, así que sin contexto el scope no devuelve nada y RLS deniega por defecto. **No falla, no ve
nada**, y una serie sin puntos es indistinguible de una organización que no mide. De ahí
`ContextoOrganizacion::paraOrganizacion()`, una organización cada vez. Nada de `withoutGlobalScopes()`.

Va **diario y no mensual**, aunque el periodo más corto sea el mes: el comando mira qué periodo ha
cerrado y sella sólo lo que falte, así que correrlo todos los días es idempotente y correrlo una vez
al mes deja la serie con un agujero en cuanto el planificador se pierda un día. Misma disciplina que
el importador del catálogo. Y **no toca los manuales ni los retirados**: sellar un cero en su nombre
sería inventarse la medición.

### La gráfica, y la librería que no entró

`CLAUDE.md` dejó la puerta abierta a `d3-scale` y `d3-shape` «el día que haya una serie histórica con
eje de tiempo». **Ese día llegó y la puerta sigue cerrada**, con motivo: el eje de esta serie no es
tiempo continuo, son **cubos etiquetados y equiespaciados** —«T1 2026», «T2 2026»— que impone
`Periodicidad`. Lo que `d3-scale` compra es elegir ticks legibles sobre un eje continuo, y aquí los
ticks vienen escritos de casa. La puerta se queda abierta para el día que haya una serie con fechas
irregulares.

`GraficaSerie.vue` es **SVG y no rejilla CSS**, al revés que `MatrizRiesgo` y `BarraSegmentada`: aquí
sí hay una línea que dibujar entre puntos, que es el caso de `AnilloProgreso`. Las etiquetas viven
**fuera** del SVG, en HTML, porque con `preserveAspectRatio="none"` el texto se deformaría con la caja
y a 375 px tienen que poder envolver. **La línea de objetivo va de puntos y no de color**: si sólo la
distinguiera el tono, con protanopía sería otra serie más.

**La escala arranca en cero salvo que la serie no lo toque nunca.** Una serie de madurez que va de 3,1
a 3,4 dibujada desde cero es una recta plana que no dice nada; una de porcentajes que empieza en su
mínimo exagera dos puntos hasta que parecen un despegue. Se recorta el eje sólo cuando lo primero no
distingue nada.

### `resolveChildRouteBinding()`, por segunda vez en el producto

`scopeBindings()` deduce la relación pluralizando el nombre del parámetro **en inglés** —`medicion` →
`medicions`— y aquí el dominio se nombra en español. Sin escribirlo a mano, `/indicadores/{indicador}/mediciones/{medicion}`
responde 500 con un «Call to undefined method» que no menciona ni la ruta ni la relación, y de paso
deja de acotar: la medición de otro indicador se borraría desde éste. El precedente exacto es
`Documento::resolveChildRouteBinding()`, y **lo cazó un test de aislamiento, no una revisión**, igual
que allí.

### Dos verbos y no tres

`indicadores.ver` e `indicadores.gestionar`. **En este módulo no hay nada que firmar**: una medición
es un dato que se toma, no una decisión que alguien aprueba, y quien está en el día a día es quien
sabe de dónde sale la cifra. Por eso el técnico define indicadores y los mide. El verbo de supervisión
de este ciclo es `objetivos.aprobar`, y llegó con la 6.2: comprometerse a una cifra sí se firma.

### Lo que este módulo declara que no hace todavía

- **No entra en el calendario de obligaciones.** El periodo que cierra sin medir es el rojo del
  módulo y hoy sólo se ve en la tabla, en el panel y en la ficha: `Aviso\Fuente` sigue con sus tres
  casos, así que ni el aviso diario ni la vista de mes lo recogen. Es trabajo aparte, y va declarado.
- **No comprueba que lo que se mide cubra lo que hay que medir.** La 9.1 a) pide determinar qué
  necesita seguimiento; Statera registra lo que se declare y no dice si falta algo.
- ~~**No vincula indicadores con objetivos de seguridad**~~. Lo hace desde la 6.2, y la pivote es la
  N:M que aquí se dejó anunciada: `indicador_objetivo`.
- **Una media se registra sin numerador**, con sólo el denominador al lado. Es correcto —«3,2 sobre 48
  requisitos valorados»— y por eso el `CHECK` es asimétrico: un numerador exige denominador, pero no
  al revés.
