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

- **`EstablecerContextoOrganizacion` va antes de `SubstituteBindings`**, y por eso `bootstrap/app.php` saca este último de su sitio por defecto y lo vuelve a poner detrás. El *route model binding* resuelve los modelos con una consulta de Eloquent que pasa por el scope de organización: sin contexto fijado, el scope no devuelve nada y **cualquier** ruta con `{sistema}` responde 404, también las propias. Hay un test que lo fija (`tests/Feature/Sistemas/CrudTest.php`), y falla si se revierte el orden. Desde el punto 43 va entre los dos `SuscripcionVigente`: necesita saber de qué organización es la petición, y corta las escrituras de una suscripción en sólo lectura antes de resolver nada (`plataforma.md`).

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

- **La aplicación se conecta a PostgreSQL como `statera_app`, no como `statera`.** El rol que crea `POSTGRES_USER` es superusuario, y PostgreSQL exime a los superusuarios y a los roles con `BYPASSRLS` de la seguridad a nivel de fila **incluso con `FORCE ROW LEVEL SECURITY`**. Conectarse con él dejaría la tercera capa del aislamiento presente en el esquema y sin ningún efecto, que es peor que no tenerla porque parece puesta. `statera_app` es `NOSUPERUSER NOBYPASSRLS`; `statera` queda como rol administrativo. Lo crea `docker/postgres/init/02-crear-rol-de-aplicacion.sql` en el primer arranque del volumen.

- **Y `statera_app` no es dueña de nada (punto 32).** Hasta ahí corría también las migraciones y era propietaria de las 113 tablas, y un dueño puede devolverse cualquier privilegio: el `REVOKE UPDATE, DELETE` sobre `eventos_auditoria` paraba al código distraído, pero unas credenciales comprometidas lo deshacían con un `GRANT`, y con `ALTER TABLE … DISABLE TRIGGER` apagaban los cuatro triggers de inmutabilidad. Ahora el esquema y todo lo que contiene son de **`statera_migrador`**, que sólo usan las migraciones —`php artisan migrate --database=pgsql_migraciones`—, y `statera_app` recibe `SELECT, INSERT, UPDATE, DELETE` por `ALTER DEFAULT PRIVILEGES`, sin `TRUNCATE`, que vacía una tabla sin pasar por RLS. Tres consecuencias:
  - **Un `php artisan migrate` a secas falla** con «permission denied for schema public». Es el comportamiento correcto; el entrypoint y `composer setup` ya pasan la conexión.
  - **Los tests migran como dueño y corren como aplicación.** Lo hace `Tests\Concerns\RefrescaLaBase`, que sustituye a `RefreshDatabase` en `Pest.php`. Un test que corriera como dueño vería un `GRANT` salirle bien. `TrazaTest` comprueba que la aplicación no es dueña de ninguna tabla y que no puede conceder, truncar, crear ni apagar triggers.
  - **Un volumen anterior se convierte a mano, una vez**: el mismo script es idempotente y hace `REASSIGN OWNED BY statera_app TO statera_migrador` (la orden está en su cabecera).

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

- **La consulta no es la única puerta: también la validación.** `'exists:users,id'` a secas acepta
  el id de una cuenta de otro cliente como responsable, y el registro se guarda apuntándola. Había
  **siete** así —seis `FormRequest` y `ObligacionController::asumir()`— mientras veintitrés sitios ya
  usaban `Rule::exists('users', 'id')->where('organizacion_id', …)`. El mismo test las busca ahora en
  sus dos formas y exige el `where` en las cinco líneas siguientes. El resto de `exists:` no lo
  necesita: las demás tablas de datos propios tienen RLS y la validación pasa por ella.

- **La traza tiene una sola puerta de escritura, y es estrecha (punto 36).** `depurar_traza_de_persona()` es `SECURITY DEFINER` y es del migrador, así que la aplicación puede ejecutarla sin tener `UPDATE` sobre `eventos_auditoria`. Por eso lo que importa es lo que **no** puede hacer: quita claves de una lista fija escrita en la función, sólo de eventos `Persona` y `Adjunto` con los ids que recibe y sólo de la organización de la sesión, además de RLS. `search_path` va fijado, porque una función `SECURITY DEFINER` con el del llamador se secuestra creando una tabla con el mismo nombre en otro esquema. `SupresionTest` comprueba que no toca otra organización ni otra entidad con el mismo id. **Cualquier otra puerta así se escribe con esas mismas restricciones o no se escribe.**

- **Hay una cuarta capa, y no es de tenant: el alcance de la cuenta (§ 4.19).** El
  auditor externo ve sólo los sistemas que audita. Vive en
  `ContextoOrganizacion::acotarASistemas()`, la fija `EstablecerContextoOrganizacion`
  justo después de la organización y la aplica el scope global del trait
  `AcotadoPorAlcance`. **Se suma a las tres y no quita ninguna**: dentro de una
  misma organización RLS no distingue a nadie, así que no puede ir en PostgreSQL,
  y tampoco toca la prohibición de `withoutGlobalScopes()`. `olvidar()` la borra
  junto con la organización, y `comoMantenimiento()` la ignora. Que ningún modelo
  con `sistema_id` se quede sin ella lo comprueba `AlcanceDelAuditorTest`. El
  razonamiento entero, en `cuentas.md`.
