---
paths:
  - app/Domain/Cambio/**
  - resources/js/pages/cambios-sgsi/**
  - app/Http/Controllers/CambioSgsiController.php
  - app/Http/Resources/CambioSgsiRecurso.php
---

# Los cambios del SGSI

Cláusula 6.3, «planificación de cambios». La norma pide una sola cosa: cuando la
organización decide cambiar su sistema de gestión, **el cambio se hace de forma
planificada**. Hasta este módulo la 6.3 tenía requisito en el catálogo, implantación
esperando y ningún sitio donde escribirse.

Vive en `app/Domain/Cambio/`, con tres tablas: `cambios_sgsi`, `cambio_sgsi_tarea` y
`cambio_sgsi_transiciones`. Rutas en `/cambios-sgsi`.

### Sólo el sistema de gestión, y es la frontera del módulo

Alcance, política, organización y roles, procesos del SGSI, recursos, documentación
(`AmbitoCambio`). **Los cambios técnicos no entran**: A.8.32 y `op.exp.5` son otra cosa,
se cuentan por decenas y con otro ritmo, y meterlos aquí ahogaría los tres o cuatro
cambios al año que la 6.3 quiere ver planificados. `op.exp.5` no aplica en básica; cuando
llegue un cliente de media, la gestión de cambios técnicos es **un registro aparte**, no
una ampliación de éste. Por eso el permiso es `cambios_sgsi.*` y no `cambios.*`: el
nombre corto queda libre para aquél.

### El esqueleto de mejoras, la firma de objetivos

Del registro de la 10.1 toma el código (`CS-{año}-NN`, con el `?::int` de `CodigoMejora`),
el histórico de transiciones y las actuaciones como tareas, **sin doble vínculo** con
implantaciones. De la 6.2 toma la regla que lo define: **un borrador se escribe como se
pueda; un compromiso, no**. Firma y plazo son obligatorios exactamente en `aprobado`,
`implantado` y `revisado`, con dos `CHECK`, y lo comprueba también
`CambiarEstadoCambio` porque la regla vale para un importador.

Los campos son lo que un auditor pregunta de un cambio planificado: `proposito`,
`consecuencias`, `integridad` (cómo sigue el SGSI en pie mientras dura) y `recursos`.
**Opcionales al escribir**: se rellenan antes de pedir la firma, no el día que se apunta.
La ficha dice cuántos faltan, y no bloquea la aprobación por ellos: decidir si un cambio
está bien planificado es de quien firma.

### Cinco estados, y por qué `revisado` es uno aparte

`propuesto → aprobado → implantado → revisado`, más `descartado`. **Implantar y revisar son
dos pasos** porque el propósito escrito al principio solo sirve si al final alguien mira
si se cumplió; revisar **exige ese texto**, que va a la columna `revision` además del
histórico (`CHECK` incluido). Descartar y toda vuelta atrás exigen nota
(`EstadoCambio::exigeNota()`), salvo volver al borrador, que suelta la firma entera.
**Reabrir no reescribe quién firmó**, como en objetivos.

### El verbo de supervisión

`cambios_sgsi.aprobar`. Cubre aprobar y **renunciar a un cambio ya aprobado**
(`EstadoCambio::exigeAprobar()`); descartar una propuesta la puede hacer quien la
escribió. Lo comprueba el controlador y no la ruta, porque la ruta de transición es una
sola. El técnico propone, planifica, implanta y revisa; no firma.

### Un solo rojo, y es de plazo

`RegistroCambios::alertas()` sólo cuenta los **aprobados con la fecha prevista pasada y
sin implantar**, y sube al panel (`AlertasDelPanel::FUENTES`, vista «ciclo»). Ningún
estado gasta rojo. Un propuesto con la fecha pasada dice «Fecha pasada» en gris: nadie se
ha comprometido todavía. Y un implantado sin revisar **no es alarma**: la 6.3 no fija
plazo para esa comprobación, y pintarla de rojo sería inventarse una obligación. Es un
pendiente.

### Lo que este módulo declara que no hace

- **No entra en el calendario de obligaciones**, por el mismo argumento que objetivos: sus
  tareas ya pintan chip, y una `Fuente` propia pintaría dos el mismo día.
- **No enlaza con la revisión por la dirección por clave foránea.** El acta ofrece el
  enlace `?origen=revision_direccion` y el origen es sólo etiqueta.
- **No entra en ningún documento generado**, tampoco en el informe de estado.
- **No comprueba que un cambio tocara lo que dice.** Si cambia la política, Statera no
  mira que haya una versión nueva de la política; lo cuentan sus actuaciones.
