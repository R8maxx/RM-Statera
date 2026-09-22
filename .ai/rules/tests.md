---
paths:
  - tests/**
---

# Los tests que no hay que acordarse de ampliar

Seis tests **descubren** en vez de enumerar, así que cubren solos lo que traiga el módulo siguiente.
Nacieron de fallos que ya habían mordido o estaban a punto:

| Test | Qué convierte en rojo |
|---|---|
| `Organizacion/RlsDeclaradaTest` | Una tabla con `organizacion_id` **sin RLS activa, forzada y con política**. Pregunta a `pg_policies` en vez de enumerar modelos. Lleva la lista de las cuatro excepciones declaradas —`users` y las tres de spatie— y exige que quien deje de serlo salga de la lista. |
| `Organizacion/FactoriesSinOrganizacionTest` | Una factory que declare `organizacion_id` en su `definition()`. **Recorre los subdirectorios desde el § 4.1**: su `glob` miraba sólo el primer nivel, así que `NoConformidad/`, `Auditoria/`, `Riesgo/`, `Catalogo/` y `Contexto/` quedaban fuera — cinco de diecinueve, y justo las de los módulos más recientes. Un test que existe para impedir una recaída y que no mira donde se escribe el código nuevo da por cubierto lo que no cubre. |
| `Autorizacion/RolesTest` | Que al rol `Auditor` le falte un permiso `.ver`, o que le sobre uno de escritura. `Rol::permisos()` es lista literal para `Tecnico` y `Auditor`, y olvidarla no rompía nada. |
| `Diseno/TonosTest` | Un tono que el servidor emite y que no está en `lib/tonos.ts`. **`tono()` acaba en `?? neutro`: el badge sale gris y no falla nadie** — el mismo fallo que `IconoTipo` tenía y que `IconosTest` ya cubría. |
| `Metricas/CalculosTest` | Un caso de `CalculoIndicador` cuya rama revienta, o que devuelve una cifra que la tabla rechaza —numerador sin denominador, denominador a cero—. Recorre `cases()` y **sella cada uno de verdad**, así que lo que prueba no es que el `match` tenga la rama: es que el `CHECK` de PostgreSQL la acepta. Sin él, ese rechazo aparecería meses después dentro del comando de las 07:30 y sin nadie mirando. |
| `Panel/AlertasTest` | Un registro con `alertas()` que no esté en `AlertasDelPanel::FUENTES`. Recorre `app/Domain/` en vez de enumerar módulos, porque olvidar uno **no rompe nada**: su pestaña del panel deja de marcarse, que es el fallo que el punto existe para cerrar. Y si su `glob` deja de encontrar nada se pone rojo, que es la lección de `FactoriesSinOrganizacionTest`. |

Los dos de diseño y el de roles se apoyan en `enumsDelDominioCon()` (en `tests/Pest.php`), que recorre
`app/Domain/<Contexto>/Enums/` **y el propio contexto**, porque algunos enums están sueltos —`Aviso\Fuente`
lo está— y limitarse al subdirectorio dejaba fuera justo al que rompía la suite. `IconosTest` tenía dos
listas literales y ya no tiene ninguna.
