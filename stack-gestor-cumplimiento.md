# Statera — Stack técnico

Gestor de cumplimiento ISO 27001:2022 + ENS (RD 311/2022).

Documento de decisiones técnicas. Complementa a `especificacion-gestor-cumplimiento.md`, que describe el **qué**. Este describe el **con qué** y, sobre todo, el **por qué**, para que las decisiones no se reabran cada dos semanas.

Fecha de las decisiones: septiembre de 2026.

---

## 0. Nombre, marca y titularidad

### El nombre

**Statera.** Del latín: la balanza romana, el instrumento que pesa y contrasta. La metáfora encaja con lo que hace el producto — poner dos marcos normativos en la misma balanza y determinar el valor de lo que se ha implantado.

Criterios que cumplió y que otros candidatos no:

- Se pronuncia y se escribe igual en español y en inglés.
- Se escribe correctamente después de oírla una vez.
- Tiene significado real, no es una sílaba inventada.
- No lleva la raíz `nex-`, la más saturada del naming tecnológico.
- Sin colisiones en el sector: no existe ningún producto GRC ni de cumplimiento con ese nombre. Los usos existentes de "Statera" están en defensa, biofarma y finanzas, todos en clases distintas.

Pendiente antes de registrar: comprobación en TMview de OEPM y EUIPO, clases 9 y 42.

### Arquitectura de marca

| Nivel | Marca |
|---|---|
| Casa / proveedor | RM Technology |
| Producto | Statera |

- El producto se llama **Statera** a secas. Nunca "RM Statera" ni "RM - Statera".
- El respaldo va en letra pequeña: "un producto de RM Technology", en el pie de la web, el "acerca de" y la documentación.
- Motivo: RM Technology no aporta reconocimiento a un comprador del sector del cumplimiento, y el prefijo alarga el nombre sin dar confianza. Además, un nombre limpio permite que el producto se escinda o se venda sin arrastrar la marca personal.

### Identidad visual

**Statera tiene identidad propia, separada de la de RM Technology.**

El sistema de RM Technology (símbolo de chip, wordmark en Poppins, degradados morados) comunica marca técnica y de desarrollo. Statera le habla a auditores, responsables de seguridad y compradores del sector público, donde el degradado morado resta credibilidad.

Dirección para Statera: paleta contenida y sobria, tipografía legible en documentos densos, y la balanza como símbolo — da un logo geométrico y limpio con muy poco esfuerzo.

### Titularidad

El proyecto es **personal de César**, no de Avanza Software Diagram.

En España, el artículo 97.4 de la Ley de Propiedad Intelectual atribuye al empresario los derechos de explotación de un programa creado por un asalariado *en el ejercicio de sus funciones o siguiendo instrucciones del empresario*, salvo pacto en contrario. Lo determinante no es de quién sea la idea, sino si se desarrolló dentro de las funciones del puesto. Como César lidera la certificación ISO de Avanza, la separación tiene que mantenerse explícita desde el principio:

- Repositorio en la cuenta personal de GitHub, no en la organización de Avanza.
- Dominio y marca a nombre de César o de RM Technology.
- Desarrollo en tiempo y equipo propios.
- **Nada de datos reales de Avanza** en seeds, fixtures ni demos. Solo datos sintéticos.
- Si Avanza usa la herramienta, es una relación cliente-proveedor, aunque sea a coste cero, y conviene dejarlo por escrito.

### Nomenclatura interna

La marca corta arriba, los nombres descriptivos debajo:

- **Statera Core** — catálogo normativo y motor de categorización.
- **assay engine** — validación de evidencias y cálculo del estado de cumplimiento. Nombre interno; no necesita registro y ahí la metáfora del ensayo funciona sin pegas.

---

## 1. Resumen

| Capa | Elección |
|---|---|
| Lenguaje / framework | PHP 8.4 + Laravel 13 |
| Base de datos | PostgreSQL 17 |
| Capa de vista | Inertia 3 + Vue 3 + TypeScript |
| Estilos | Tailwind CSS |
| Componentes | shadcn-vue (sobre Reka UI) |
| Tablas | TanStack Table v9 (adaptador Vue) |
| Generación de PDF | Gotenberg 8.x en contenedor |
| Colas | Redis + Laravel Horizon |
| Almacenamiento de evidencias | S3 con versionado y Object Lock |
| Tests | Pest |
| Infraestructura | AWS (RDS, S3, ECS Fargate o EC2 con Docker) |

Laravel 13 es la versión mayor actual desde marzo de 2026 y requiere PHP 8.3–8.5. No hay release LTS vigente en Laravel; la política de soporte normal es suficiente.

---

## 2. Backend

### 2.1 Laravel 13

Es el stack del equipo y encaja bien con el problema: la mayor parte del sistema es CRUD con reglas de negocio, flujos de aprobación y generación documental. Nada aquí justifica salirse de él.

**Estructura de la lógica de dominio.** Toda la lógica vive en servicios y acciones, no en controladores ni en componentes de interfaz:

```
app/
  Domain/
    Catalogo/          marcos, requisitos, refuerzos, mapeos, importador
    Categorizacion/    motor ENS (función pura + tests)
    Implantacion/      estados, transiciones, recálculo
    Riesgos/
    Documentos/        generadores, plantillas
    Evidencias/
    ...
  Http/
    Controllers/       delgados: validan, delegan, devuelven Inertia
    Requests/          única fuente de verdad de la validación
    Resources/         definición de tablas y columnas para el frontend
```

La razón de esta disciplina es que la capa de presentación sea desechable. Si en la fase producto hace falta cambiarla, se cambia sin tocar el dominio.

### 2.2 PostgreSQL, no MySQL

Esta decisión no es de preferencia, es funcional:

- **CTEs recursivas** para la jerarquía de requisitos (`op` → `op.acc` → `op.acc.4`) y para el grafo de dependencias entre activos. En MySQL habría que resolverlo a mano o en PHP.
- **JSONB** con índices GIN para los cinco atributos ISO 27002, las entradas de la revisión por dirección y los valores anteriores del log de auditoría.
- **Row Level Security** como segunda barrera del aislamiento multi-tenant.
- **Tipos enumerados y restricciones CHECK** para los estados, que aquí son numerosos y tienen que ser estrictos.

### 2.3 Paquetes de backend

| Paquete | Para qué |
|---|---|
| `inertiajs/inertia-laravel` | puente con Vue |
| `spatie/laravel-query-builder` | filtros y ordenación desde la query string |
| `spatie/laravel-typescript-transformer` | genera tipos TS desde los DTOs de PHP |
| `spatie/laravel-medialibrary` | ficheros de evidencias y documentos |
| `gotenberg/gotenberg-php` (v2.x) | cliente oficial de Gotenberg 8.x |
| `laravel/fortify` | autenticación y 2FA |
| `laravel/horizon` | supervisión de colas |
| `pestphp/pest` | tests |
| `larastan/larastan` | análisis estático, nivel 6 mínimo |
| `laravel/pint` | formato |

No usar paquetes de multi-tenancy de terceros. El aislamiento se implementa a mano (ver sección 5): es poco código, y depender de un paquete para la frontera de seguridad más importante del sistema es mala idea.

---

## 3. Frontend

### 3.1 Inertia 3 + Vue 3 + TypeScript

Inertia evita construir una API pública y duplicar la autenticación. TypeScript no es opcional: el árbol de requisitos, la matriz de aplicabilidad y el estado de implantación son estructuras complejas y de larga vida.

Los tipos del frontend se **generan** desde PHP con `laravel-typescript-transformer`. Nunca se escriben a mano dos veces.

**Se usa la v3, no la v2.** Inertia v3 se publicó el 26 de marzo de 2026 y la v2 deja de recibir correcciones de errores el 26 de septiembre de 2026. Arrancar sobre v2 sería empezar ya fuera de soporte.

Lo que la v3 aporta a este proyecto:

- **`useHttp`** para peticiones que no son navegación: opciones de filtro, autocompletados de responsable o activo, consulta del grafo de dependencias. En v2 esto obligaba a meter Axios a mano.
- **Actualizaciones optimistas con rollback automático**, en el router, en `useForm` y en `useHttp`. Encaja con las acciones masivas sobre implantaciones y con el Kanban de tareas.
- **Cliente XHR propio con interceptores incorporados**; Axios deja de ser dependencia obligatoria. Una dependencia menos que auditar en una aplicación dentro del alcance del SGSI.
- **Plugin `@inertiajs/vite`**: resuelve páginas y configura SSR automáticamente, sin callbacks `resolve` ni `setup` en el punto de entrada.
- **Genéricos en el componente `Form`**, con errores y slot props tipados.
- **Soporte de enums en `Inertia::render()`**, y este dominio está lleno de enums de estado.
- **Layout props** en lugar de event bus o `provide`/`inject`.

Cambios de configuración a tener presentes: `page_paths` y `page_extensions` pasan a `pages.paths` y `pages.extensions` en `config/inertia.php`, y se han eliminado los traits de testing `Has`, `Matching` y `Debugging` (deprecados desde v1, sustituidos por `AssertableInertia`).

> ⚠️ **Aviso para cualquier asistente de código**: por defecto se genera código de v2 — `createInertiaApp({ resolve, setup })` y Axios como dependencia. Consultar la guía de actualización a v3 antes de escribir el punto de entrada o cualquier petición HTTP.

### 3.2 shadcn-vue + Tailwind

Los componentes se copian al repositorio en lugar de instalarse como dependencia. Eso significa que en la fase producto se reestilizan sin pelearse con una librería ni esperar a que un mantenedor acepte un PR.

Se descartó PrimeVue: ahorra semanas al principio, pero ata el aspecto del producto y su forma de hacer las cosas.

### 3.3 TanStack Table v9

Adaptador Vue. **Fijar versión exacta en `package.json`.**

v9 pasó a estable el 4 de agosto de 2026. Es un rediseño respecto a v8: las funcionalidades son opt-in y tree-shakables, y el estado se apoya en TanStack Store con reactividad de grano fino y suscripciones por porción de estado. La API cambia — `useReactTable` pasa a ser `useTable`, y el estado fluye por `table.state`, `table.store` y átomos por slice en lugar de `getState()`.

> ⚠️ **Aviso importante para cualquier asistente de código**: casi toda la documentación, ejemplos y respuestas que existen ahí fuera son de v8. Si no se especifica, se generará código v8 (`useReactTable`, `getState()`) que no funciona. Consultar siempre la guía de migración v8→v9 antes de escribir código de tabla.

Como las consultas son de servidor, se usa en modo manual (`manualPagination`, `manualSorting`, `manualFiltering`). Lo que se aprovecha de la librería:

- modelo de definición de columnas, unificado en todos los módulos
- filas expandibles y sub-filas — el árbol del catálogo ENS y las dependencias de activos
- visibilidad, orden, anclado y redimensionado de columnas — la vista de la SoA tiene más de doce
- selección de filas para acciones masivas

shadcn-vue trae su `data-table` construido sobre TanStack Table, así que no son dos decisiones sino una.

### 3.4 La capa de recursos genérica

**Hay que construirla antes del primer módulo.** Sin Filament nadie regala el CRUD, y hay diecinueve módulos que son mayoritariamente tablas y formularios. Escribirlos uno a uno produce cuatro dialectos distintos para el sexto.

Consta de:

1. Una clase `Resource` en PHP que declara columnas, filtros, acciones y permisos, y que el controlador serializa a Inertia.
2. Un componente `DataTable` en Vue que consume esa definición, sobre TanStack Table.
3. Componentes de formulario que envuelven `useForm` de Inertia y muestran errores desde los `FormRequest`.

Presupuesto estimado: una semana. Se amortiza en el tercer módulo.

---

## 4. Generación documental: Gotenberg

Gotenberg es una API en contenedor sobre Chromium y LibreOffice, con interfaz `multipart/form-data`. Cliente oficial: `gotenberg/gotenberg-php`, cuya rama v2.x corresponde a Gotenberg 8.x.

### Reglas de uso

- **Fijar una versión concreta, no el tag `8`.** Entre las versiones 8.8.0 y 8.9.1 la conversión a PDF/A y PDF/UA estuvo rota desde las rutas de Chromium. Usar 8.9.1 o superior y anclarla.
- **Nunca exponerlo.** Gotenberg descarga las URLs que se le pasen; si es alcanzable desde fuera es un SSRF de manual. Red interna de Docker, sin publicar puerto, sin acceso a internet.
- **Enviar el HTML, no una URL.** Se manda `index.html` más los assets en el mismo multipart. Evita el problema de autenticación, evita el SSRF y hace el resultado reproducible.
- **Cabecera y pie como ficheros HTML aparte**, con código de documento, versión, fecha de aprobación, clasificación y "página X de Y".
- **PDF/A-3b** para todo documento de cumplimiento archivable. Es el formato de conservación a largo plazo, y estos registros hay que guardarlos años.
- **Siempre en cola.** Una SoA de 93 controles con evidencias tarda; nunca en el ciclo de petición.
- **Almacenar el PDF generado, no regenerarlo.** Esto es lo importante: la SoA que se entrega al auditor se guarda en S3 con su hash y se registra como versión del documento. Si se regenera seis meses después el contenido habrá cambiado y no se puede demostrar qué se firmó. Generación bajo demanda solo para borradores marcados como tales.
- El módulo LibreOffice convierte `.docx` a PDF. Útil si un cliente sube su política en Word.

### Arquitectura

Una clase por tipo de documento (SoA, DdA, plan de adecuación, acta de revisión, informe de auditoría interna), cada una devolviendo vista Blade + datos. Un servicio común renderiza, llama a Gotenberg, calcula el hash, sube a S3 y registra la versión.

---

## 5. Multi-tenancy

Base de datos única, tres capas de aislamiento:

1. **`organizacion_id`** en toda tabla de datos propios, presente desde la primera migración aunque solo haya una organización.
2. **Global scope de Eloquent** que filtra por la organización activa. Prohibido `withoutGlobalScopes()` fuera de comandos de mantenimiento explícitos, y con test que lo verifique.
3. **Row Level Security en PostgreSQL** con la organización activa en una variable de sesión.

Tres capas parece excesivo hasta que un scope mal quitado filtra datos de un cliente a otro. En una herramienta que contiene el inventario de activos y las vulnerabilidades de sus clientes, esa fuga es un incidente grave.

El **catálogo normativo no lleva `organizacion_id`**: es global y compartido.

---

## 6. Almacenamiento de evidencias

S3 con:

- **Versionado activado** y **Object Lock en modo compliance** sobre el bucket de evidencias. Esto es funcionalidad, no infraestructura: permite demostrar ante un auditor que un fichero no se ha alterado desde su fecha de obtención.
- **Cifrado con KMS**, clave gestionada.
- **Hash SHA-256 almacenado en base de datos** junto al fichero, calculado en el momento de la subida.
- Acceso solo mediante URLs firmadas de corta duración. Bucket privado.

---

## 7. Colas y trabajos programados

Redis + Horizon. Colas separadas por tipo de carga:

- `documentos` — generación de PDF, la más lenta
- `importadores` — inventario desde AWS, CSV, catálogo
- `notificaciones` — avisos de vencimiento
- `default`

Trabajos programados: recálculo de vencimientos, avisos de evidencia caducada, avisos de revisión documental, comprobación de tareas fuera de plazo, recordatorios del calendario de obligaciones.

---

## 8. Seguridad de la propia aplicación

La herramienta forma parte del alcance del SGSI. Contiene el inventario, las vulnerabilidades detectadas y las evidencias. No es un requisito aplazable.

- **2FA obligatorio** para todo rol con permisos de escritura (Fortify). SSO/SAML queda para fase producto.
- **Cifrado en reposo** de RDS y S3; TLS en tránsito.
- **Log de auditoría inmutable**: tabla escrita por un usuario de base de datos sin permisos de `UPDATE` ni `DELETE` sobre ella. Si la aplicación puede borrar su log de auditoría, no es un log de auditoría.
- **Autorización** con políticas de Laravel por modelo, además del scope de organización.
- **Bloqueo por inactividad** y registro de sesiones.
- **Retención y borrado** alineados con RGPD para los datos de personas.
- **Rol de auditor externo**: solo lectura, limitado al alcance auditado.

---

## 9. El seed del catálogo

No va en un seeder de Laravel a pelo.

- Ficheros **YAML versionados en el repositorio**, uno por marco y versión: `catalogo/iso27001-2022.yaml`, `catalogo/ens-rd311-2022.yaml`, `catalogo/mapeos-iso-ens.yaml`.
- Un comando de importación **idempotente** que sepa hacer diff: qué requisitos son nuevos, cuáles cambian de texto, cuáles desaparecen.
- Al importar una revisión del marco, el comando informa de cómo afecta a las implantaciones existentes de cada organización. No las modifica en silencio.

El contenido literal de los 93 controles del Anexo A y de las medidas del Anexo II con su matriz de aplicabilidad hay que extraerlo del BOE y de la norma. Es un trabajo aparte con su propio script.

---

## 10. Infraestructura

**Desarrollo**: Docker Compose con app, PostgreSQL, Redis, Gotenberg y MinIO (S3 local).

**Producción**: RDS PostgreSQL cifrado con backups verificados y restauración probada, S3, ElastiCache o Redis en contenedor, y ECS Fargate o una EC2 con Docker Compose para empezar. Gotenberg en la red privada, sin acceso a internet.

CI/CD con GitHub Actions: Pint, Larastan, Pest y build de assets en cada PR.

Nota: la infraestructura de esta aplicación acaba siendo evidencia de cumplimiento de la propia certificación. Configurarla bien tiene doble retorno.

---

## 11. Tests

Pest. Prioridad de cobertura, por orden:

1. **Motor de categorización ENS.** Es donde un fallo silencioso deja a un cliente fuera de conformidad sin que nadie se entere. Tests exhaustivos de la matriz completa, incluidas las medidas moduladas por nivel de dimensión.
2. **Aislamiento multi-tenant.** Un test que verifique que ninguna consulta cruza la frontera de organización.
3. **Transiciones de estado de implantación** y recálculo tras un cambio de valoración.
4. **Importador del catálogo**, incluida la idempotencia y el diff.
5. Resto de módulos: tests de funcionalidad sobre los flujos principales.

---

## 12. Descartado y por qué

| Opción | Motivo del descarte |
|---|---|
| **Filament** | Rápido para la fase 1, pero limita la UI y se mete en el modelo. Con un producto vendible como objetivo, rehacer la capa de presentación en la fase 4 sale más caro que construirla ahora. |
| **Django / Python** | Técnicamente equivalente y con mejor ecosistema documental, pero partiría el stack del equipo en dos para un proyecto de mantenimiento largo. |
| **MySQL** | Sin CTEs recursivas cómodas, sin JSONB indexable, sin RLS. Todo eso habría que hacerlo a mano. |
| **API separada + SPA** | Duplica autenticación y capa de serialización. Inertia lo evita. |
| **Microservicios** | No hay ningún eje de escalado que lo justifique. |
| **Event sourcing** | Tentador por la trazabilidad, pero la tabla de transiciones da el mismo resultado con una fracción de la complejidad. |
| **PrimeVue** | Ahorra tiempo inicial, ata el aspecto del producto. |
| **Paquetes de multi-tenancy de terceros** | Es la frontera de seguridad principal; se implementa y se testea a mano. |
| **MongoDB** | El dominio es relacional de principio a fin. |

---

## 13. Orden de arranque

Sin tocar la interfaz hasta el punto 4:

1. Esquema del catálogo + comando de importación idempotente desde YAML.
2. Motor de categorización ENS, con tests exhaustivos.
3. Generación de implantaciones desde el motor, con transiciones y recálculo.
4. Capa de recursos genérica (`Resource` + `DataTable` + formularios).
5. Primer módulo completo de punta a punta: inventario de activos.
6. Primer documento generado con Gotenberg: la SoA.

Al llegar al punto 6 el esqueleto está validado entero y el resto son módulos repitiendo un patrón probado.

---

## 14. Versiones a fijar

Estas no se dejan flotando:

```
php            ^8.4
laravel/framework  ^13.0
inertiajs/inertia-laravel  ^3.0
@inertiajs/vue3  ^3.0
@tanstack/vue-table  9.x  (versión exacta, no ^)
gotenberg/gotenberg  8.9.1+  (imagen anclada, no el tag "8")
gotenberg/gotenberg-php  ^2.0
postgres       17
```
