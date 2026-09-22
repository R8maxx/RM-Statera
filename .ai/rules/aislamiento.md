---
paths:
  - app/Domain/**
  - app/Http/Middleware/**
  - app/Models/**
  - database/factories/**
  - database/seeders/**
---

# El aislamiento multi-tenant

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **`EstablecerContextoOrganizacion` va antes de `SubstituteBindings`**, y por eso `bootstrap/app.php` saca este último de su sitio por defecto y lo vuelve a poner detrás. El *route model binding* resuelve los modelos con una consulta de Eloquent que pasa por el scope de organización: sin contexto fijado, el scope no devuelve nada y **cualquier** ruta con `{sistema}` responde 404, también las propias. Hay un test que lo fija (`tests/Feature/Sistemas/CrudTest.php`), y falla si se revierte el orden.

- **`DatabaseSeeder` no usa `WithoutModelEvents`.** `PerteneceAOrganizacion` rellena `organizacion_id` en el evento `creating`; silenciar los eventos deja la columna a nulo y RLS rechaza la inserción con un error de privilegios que no dice nada de la causa.

- **Ninguna factory declara `organizacion_id` en su `definition()`**, y es la misma trampa por el otro
  lado. El trait rellena la columna **sólo si viene a nulo**, así que un valor por defecto en la factory
  lo cortocircuita: la fila nace con un tenant que no es el del contexto y el `WITH CHECK` de la
  política la rechaza. `SistemaFactory` lo hacía, era la única del repositorio que lo hacía, y tenía
  **nueve tests de riesgos en rojo desde el día que se escribieron** — el error habla de privilegios y
  no menciona la palabra «organización», así que pasó por una regresión de otra cosa durante semanas.
  En un `state` —`->de($organizacion)`— sí vale: ahí es una decisión explícita de quien escribe el
  test. Lo clava `FactoriesSinOrganizacionTest`.

- **La traza vive en `app/Domain/Traza/`, no en `Domain\Auditoria\`.** Lo que hay ahí es
  `eventos_auditoria`, el log inmutable de quién tocó qué dentro de Statera, y eso es una traza. El
  nombre `Auditoria` hizo falta para el módulo del § 4.12, que registra auditorías de verdad: con los
  dos en la misma carpeta habría un `RegistroAuditoria` a una «s» de distancia de un
  `RegistroAuditorias`. El servicio pasó a `RegistroTraza`; **`EventoAuditoria` conserva su nombre**,
  porque es el modelo de `eventos_auditoria` y la tabla se llama así en la § 2.2. Mismo criterio que
  `IndicadorInventario` → `Indicador`.

- **La aplicación se conecta a PostgreSQL como `statera_app`, no como `statera`.** El rol que crea `POSTGRES_USER` es superusuario, y PostgreSQL exime a los superusuarios y a los roles con `BYPASSRLS` de la seguridad a nivel de fila **incluso con `FORCE ROW LEVEL SECURITY`**. Conectarse con él dejaría la tercera capa del aislamiento presente en el esquema y sin ningún efecto, que es peor que no tenerla porque parece puesta. `statera_app` es `NOSUPERUSER NOBYPASSRLS` y propietario del esquema `public`; `statera` queda como rol administrativo. Lo crea `docker/postgres/init/02-crear-rol-de-aplicacion.sql` en el primer arranque del volumen.

- **`ContextoOrganizacion` se registra como `scoped`, no como `singleton`.** El worker de la cola es
  un proceso largo que atiende jobs de organizaciones distintas: con `singleton`, la organización del
  job A sigue puesta al empezar el job B. Y no basta con eso — `set_config(..., false)` es de SESIÓN
  y vive en la conexión que el worker reutiliza; quien la devuelve a «denegar por defecto» es el
  `finally` de `paraOrganizacion()`. **Hacen falta las dos cosas.**

- **Un job en cola fija su organización con `ConContextoDeOrganizacion`**, el middleware de job que
  hace en la cola lo que `EstablecerContextoOrganizacion` hace en HTTP. Sin él no hay petición, no
  hay usuario y por tanto no hay contexto: el scope no devuelve nada y RLS deniega por defecto, así
  que **el job no revienta, sencillamente no ve nada**. Tres consecuencias que no se ven leyendo el
  job: se pasan **escalares y nunca `SerializesModels`** —ese trait reconsulta el modelo al
  deserializar, *antes* de cualquier middleware, y muere con un `ModelNotFoundException` que no
  menciona la palabra «organización»—; **`failed()` NO pasa por el middleware** y necesita su propio
  envoltorio o la versión se queda «generando» para siempre; y la traza de auditoría registra sin
  autor, que es el motivo de que `documento_versiones.generada_por_id` exista.
  `tests/Feature/Documentos/ContextoEnColaTest.php` fija las cuatro cosas.

- **`MetodologiaVigente` se registra como `scoped`, no como `singleton`**, por lo mismo que
  `ContextoOrganizacion` y con un fallo aún más silencioso: la metodología resuelta es la de UNA
  organización, y un worker que la arrastrara al job siguiente valoraría los riesgos de un cliente con
  la escala de otro. No reventaría nada: devolvería números plausibles y equivocados.

- **`User` NO lleva `PerteneceAOrganizacion`, y toda consulta de usuarios se acota a mano.** La
  autenticación tiene que poder encontrar a alguien *antes* de saber de qué organización es, así que
  ese modelo se queda fuera de las tres capas: no hay scope global y no hay RLS. La consecuencia es
  que un `User::query()` inocente —el desplegable de responsables, la lista de destinatarios de un
  acuse— **lista a los usuarios de todos los clientes**, y no lo caza ningún test de aislamiento
  porque el modelo no está protegido en ninguna capa. Se acota con
  `->where('organizacion_id', …)`, y la organización se toma preferentemente de la fila que se está
  mirando —`$version->organizacion_id`— y no del contexto: bajo RLS es la misma, y así la consulta no
  depende de que alguien haya fijado el contexto antes. Ya había un caso de esto en el formulario de
  documentos, corregido al llegar el § 4.5.

- **Y acordarse no era el mecanismo: lo comprueba `ConsultasDeUsuarioAcotadasTest`.** Cuando el § 4.16
  fue a tocar el desplegable del calendario, había **doce** `User::query()` sin acotar repartidos por
  seis módulos —controlador y `Recurso` de cada uno: tareas, riesgos, activos, evidencias,
  implantaciones y revisiones de inventario—, mientras los otros diecinueve sitios sí lo hacían y uno
  de ellos lo llevaba comentado. Con treinta y un sitios en dos capas, la disciplina no basta. El test
  recorre `app/`, salta las líneas de comentario —`PlantillaDocumentoController` explica en su docblock
  por qué ahí no hace falta, y leer esa explicación como una infracción enseñaría a no escribirlas— y
  mira las cinco líneas siguientes a cada consulta buscando `organizacion_id`. Es la capa que faltaba:
  `RlsDeclaradaTest` y `FactoriesSinOrganizacionTest` interrogan al esquema, y aquí no hay esquema que
  interrogar porque la fuga no está en la base, está en la consulta.

  Usa `toBeTrue($mensaje)` y no `toContain`, **a propósito**: `toContain` es variádico en Pest y se
  traga el mensaje como una segunda aguja, con lo que el test falla siempre y por el motivo
  equivocado. Pasó al escribirlo.
