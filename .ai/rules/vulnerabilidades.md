---
paths:
  - app/Domain/Vulnerabilidad/**
  - resources/js/pages/vulnerabilidades/**
  - app/Http/Controllers/VulnerabilidadController.php
  - app/Http/Resources/VulnerabilidadRecurso.php
---

# Las vulnerabilidades (invariante 8, A.8.8, `op.exp.4`)

Es el punto 30, y el último de los que quedaban. El invariante 8 decía desde el
principio que la herramienta «contiene las vulnerabilidades», y llevaba una nota
debajo que lo desmentía: lo único que había era `riesgos.vulnerabilidad`, un texto
libre del escenario de MAGERIT —la condición que hace creíble la amenaza—, y no un
hallazgo técnico con severidad, activos afectados y plazo. `op.exp.4` es exigible
desde la categoría básica.

## Cuatro decisiones

**La severidad se deriva del CVSS cuando lo hay**, con los tramos cualitativos de
la especificación de FIRST (CVSS v3.1, § 5):

| Puntuación | Severidad |
|---|---|
| 0 | informativa (la «ninguna» de FIRST) |
| 0,1–3,9 | baja |
| 4,0–6,9 | media |
| 7,0–8,9 | alta |
| 9,0–10 | crítica |

La regla está **dos veces a propósito**: en `Severidad::desdeCvss()` y en el
`CHECK` `vulnerabilidades_cvss_severidad_check`, así que no hay camino —importador,
seeder, un `forceFill`— que guarde una puntuación con otra severidad. Sin
puntuación —un boletín del fabricante, un hallazgo de auditoría— se declara. El
formulario la enseña en vivo con la misma tabla, y el `FormRequest` admite la coma
decimal.

**El plazo es política de la organización**: días por severidad en su ficha
(`organizaciones.plazo_vulnerabilidad_*_dias`), 7/30/90/180 por defecto y entre 1 y
730. **Esos números son práctica habitual y no norma**: ni ISO ni el ENS fijan
ninguno, y así se dice en la ficha. Una informativa no tiene plazo.

**Aceptar es de supervisión** (`vulnerabilidades.aceptar`). No corregir a sabiendas
es asumir un riesgo, en la línea de `riesgos.aceptar`. No tiene ruta propia: es una
transición más, y `CambiarEstadoVulnerabilidad` comprueba el permiso antes de
dejarla pasar. La ficha ni siquiera ofrece el botón a quien no lo tiene.

**Cerrar exige verificación escrita**, y guarda quién la hizo. Mitigada es que se
aplicó el arreglo; cerrada es que alguien comprobó que la vulnerabilidad ya no
está. Es la distancia entre la acción correctiva y la verificación de eficacia de
la 10.2, y lo que más se olvida.

## La máquina de estados

```
abierta → en_remediacion → mitigada → cerrada
   ↘ aceptada (supervisión, con motivo)
   ↘ falso_positivo (con motivo)
cerrada / aceptada / falso_positivo → abierta (con motivo)
mitigada → en_remediacion (con motivo: no pasó la verificación)
```

- **Sin borrado**: una vulnerabilidad que no lo era es un falso positivo con su
  motivo, que es lo que un auditor quiere poder leer.
- **Salir de aceptada o de cerrada borra su firma.** Una aceptación reabierta no
  sigue aceptada, y un cierre reabierto no sigue verificado. Los `CHECK` exigen
  firma y texto en esos dos estados.
- **El plazo sólo corre en abierta y en remediación** (`correPlazo()`). Una mitigada
  ya cumplió el plazo aunque le falte la verificación, y ésa se cuenta aparte
  (`sinVerificar`).

## `fecha_limite` es una copia, como la reevaluación de un proveedor

`PlazoRemediacion` la deriva de la fecha de detección y de los días de su
severidad. Se guarda porque el calendario la consulta por rango. La recalculan tres
sitios: el alta y la edición en el controlador, y `GuardarFichaOrganizacion` cuando
cambia la política (`todas()`).

## Las costuras

- **`Fuente::Vulnerabilidad`** en el calendario y en el correo, sólo las que siguen
  sin arreglo. Icono `Bug`, el del menú.
- **`OrigenTarea::Vulnerabilidad`** con pivote `vulnerabilidad_tarea`. **La tarea
  hereda el plazo de remediación** si no se le da otra fecha: es la que la
  organización ya se comprometió a cumplir.
- **Un rojo en el panel**, el plazo vencido. Lo demás es pendiente: críticas
  abiertas, mitigadas sin verificar y aceptadas, que es riesgo asumido. La
  tarjeta va en «El ciclo», junto a incidentes, con el reparto por severidad de
  las vivas. **Y el rojo cae en esa pestaña porque `/vulnerabilidades` está en
  `AlertasDelPanel::VISTAS`**: estar en `FUENTES` sólo lo cuenta. Ver `panel.md`.
- **El informe de estado** lo imprime como un registro más.
- **La ficha del activo** lista las vivas **y las aceptadas**, que no se corrigen
  pero siguen en el activo: sin ellas, la ficha decía «ninguna» de un servidor
  con una crítica aceptada encima (lo vio el recorrido como auditor). El aviso de fuera de
  soporte (`Obsolescencia`) ofrece registrarlo como vulnerabilidad, con el activo
  marcado y el título propuesto.
- **El aviso al registrarla** distingue la que llega ya vencida —detectada hace
  más de lo que da su severidad—: un «hay que remediarla antes del» con una
  fecha pasada se leía como una errata.
- **FK opcionales** a proveedor —el arreglo llega de fuera—, a riesgo —el escenario
  que la hace creíble— y a incidente —se descubrió por uno, o llegó a explotarse,
  que es `ClasificacionIncidente::Vulnerable` visto desde el otro lado—.

## El alcance del auditor

**`Vulnerabilidad` lleva `AcotadoPorAlcance` aunque no tiene `sistema_id`**: es de
los activos donde está, como un riesgo, y por ellos de sus sistemas. Una sin
activos no se ve desde un alcance, porque no se sabe dónde está; la ficha lo
avisa.

## Lo que este módulo declara que no hace todavía

- **No se integra con escáneres**, que está fuera de alcance, ni importa CSV. Todo
  hallazgo se registra a mano.
- **No consulta ninguna base de CVE.** El CVE se valida por forma
  (`CVE-AAAA-NNNN`), no porque exista, y la puntuación la escribe quien la registra.
- **Sólo CVSS v3.1.** El vector se guarda como texto y no se interpreta, así que
  un vector de la v4 entra, pero la severidad sigue los tramos de la v3.1.
- **Aceptar no tiene caducidad.** Una aceptación no obliga a revisarla pasado un
  tiempo; si hace falta, se reabre a mano.
- **Una vulnerabilidad no crea ni actualiza un riesgo.** El enlace es informativo:
  el riesgo sigue valorándose en su módulo.
