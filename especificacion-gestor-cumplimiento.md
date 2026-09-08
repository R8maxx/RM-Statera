# Gestor de cumplimiento ISO 27001:2022 + ENS (RD 311/2022)

Especificación funcional del proyecto. Describe **qué** tiene que hacer el sistema y **cómo se modela el dominio**. No decide stack, framework ni infraestructura: esa decisión está pendiente y se toma aparte.

---

## 0. Contexto

Avanza Software Diagram está certificándose simultáneamente en:

- **ISO/IEC 27001:2022** — sistema de gestión de seguridad de la información (SGSI).
- **ENS, RD 311/2022** — Esquema Nacional de Seguridad, objetivo **categoría BÁSICA**.

Ambos procesos se llevan hoy en hojas de cálculo y documentos sueltos. El trabajo se duplica porque una misma evidencia sirve a controles de los dos marcos pero se mantiene por separado.

**Objetivo del sistema**: una herramienta única que gestione el ciclo completo de cumplimiento de ambos marcos, con mapeo cruzado entre ellos, de forma que cada evidencia, tarea y documento se registre una sola vez y cuente para todos los marcos donde aplique.

**Fase actual**: uso interno de Avanza.
**Fase futura**: producto vendible a otros clientes, que podrán perseguir categoría BÁSICA, MEDIA o ALTA y otros marcos.

---

## 1. Principios de diseño (invariantes)

Estos ocho puntos condicionan el esquema de datos. Romper cualquiera de ellos obliga a rehacer el modelo más adelante.

1. **`organizacion_id` en toda tabla de datos propios desde el primer día.** Aunque solo haya una organización durante meses. Retrofitear multi-tenancy es una refactorización cara y arriesgada.

2. **Separación estricta entre catálogo normativo y datos de organización.** El catálogo (marcos, controles, medidas, refuerzos, mapeos) es global y compartido entre todos los tenants. Todo lo demás pertenece a una organización. No mezclar en las mismas tablas.

3. **El catálogo va como datos, no como código.** Nada de enums, constantes o clases con los controles hardcodeados. Tablas con versión del marco: ISO 27001 ya tiene una enmienda de 2024 y el ENS tendrá revisiones.

4. **La aplicabilidad se deriva, no se selecciona.** El usuario valora las cinco dimensiones de seguridad; el sistema calcula la categoría y de ahí el conjunto de medidas y refuerzos exigibles. Nunca marcar controles a mano.

5. **Cargar el Anexo II completo aunque solo se use el subconjunto de básica.** El coste marginal es un seed más largo. El coste de cargarlo a medias es rehacer el modelo cuando llegue un cliente de categoría media.

6. **La relación evidencia ↔ requisito es N:M.** Una misma captura de pantalla puede probar un control ISO y tres medidas ENS.

7. **Los estados llevan histórico, no solo valor actual.** El auditor no pregunta "¿está implantado?", pregunta "¿desde cuándo?". Toda transición de estado se registra con fecha y autor.

8. **La herramienta entra en el alcance del propio SGSI.** Contendrá el inventario, las vulnerabilidades detectadas y las evidencias. Autenticación fuerte, cifrado en reposo, backups verificados y traza inmutable no son opcionales ni aplazables.

---

## 2. Modelo de dominio

### 2.1 Catálogo normativo (global, compartido, versionado)

**`marcos`**
| Campo | Notas |
|---|---|
| `codigo` | `ISO27001-2022`, `ENS-RD311-2022` |
| `nombre`, `version`, `fecha_vigencia` | |
| `estado` | `vigente`, `derogado` |

**`requisitos`** — tabla única para cláusulas ISO, controles del Anexo A y medidas del ENS.
| Campo | Notas |
|---|---|
| `marco_id` | |
| `codigo` | `6.1.2`, `A.5.15`, `op.acc.4`, `mp.eq.2` |
| `tipo` | `clausula` \| `control` \| `medida` |
| `parent_id` | jerarquía (`op` → `op.acc` → `op.acc.4`) |
| `titulo`, `descripcion`, `orden` | |

**`refuerzos`** — específico ENS. Cada medida puede tener refuerzos `R1`, `R2`, `R3`… con descripción propia.

**`aplicabilidad_ens`** — matriz que indica, para cada medida, qué se exige en cada categoría.
| Campo | Notas |
|---|---|
| `requisito_id` | |
| `categoria` | `basica` \| `media` \| `alta` |
| `exigencia` | `no_aplica` \| `aplica` \| `R1` \| `R2` … |
| `dimension_moduladora` | nullable; algunas medidas del marco operacional se modulan por el nivel de una dimensión concreta (típicamente disponibilidad o trazabilidad) en lugar de por la categoría global. El motor debe soportar ambos casos. |

**`atributos_iso`** — los cinco atributos que la ISO 27002:2022 asigna a cada control. Sirven para filtrar y agrupar en la interfaz.
`tipo_control` (preventivo/detectivo/correctivo), `propiedades_seguridad` (C/I/D), `conceptos_ciberseguridad` (identificar/proteger/detectar/responder/recuperar), `capacidades_operativas`, `dominios_seguridad`.

**`mapeos`** — la pieza que justifica todo el proyecto.
| Campo | Notas |
|---|---|
| `requisito_origen_id`, `requisito_destino_id` | |
| `tipo_correspondencia` | `equivalente` \| `parcial` \| `relacionado` |
| `nota` | qué cubre y qué no |

**`perfiles_cumplimiento`** + **`perfil_requisitos`** — perfiles ENS (serie CCN-STIC 890, incluido el de requisitos esenciales). Son una **vista filtrada** del catálogo, no un catálogo aparte.

### 2.2 Datos de organización (por tenant)

Todas las tablas de esta sección llevan `organizacion_id`.

**`organizaciones`** — nombre, CIF, sector, si es sujeto obligado ENS o proveedor del sector público.

**`sistemas`** — un SGSI ISO o un sistema ENS. Es la unidad de alcance y de certificación.
`nombre`, `descripcion`, `marco_id`, `estado`, `alcance_declarado`, `exclusiones_justificadas`.

**`valoracion_dimensiones`** — por sistema, una fila por dimensión.
`dimension` (`C` confidencialidad, `I` integridad, `D` disponibilidad, `A` autenticidad, `T` trazabilidad), `nivel` (`na` \| `bajo` \| `medio` \| `alto`), `justificacion`.
De aquí se **deriva** `categoria` del sistema: la más alta de las cinco.

**`activos`**
`codigo`, `nombre`, `tipo` (tipología MAGERIT: servicios, datos, software, hardware, comunicaciones, soportes, equipamiento auxiliar, instalaciones, personal), `responsable_id`, `ubicacion`, `proveedor_id`, `etiqueta_qr`, `estado_ciclo_vida`, valoración propia en las cinco dimensiones.

**`activo_dependencias`** — grafo dirigido: un servicio depende de una aplicación, que depende de una instancia, que depende de una base de datos. Necesario para propagar la valoración y para el BIA.

**`riesgos`**
`activo_id`, `amenaza`, `vulnerabilidad`, `probabilidad`, `impacto_por_dimension`, `riesgo_intrinseco`, `salvaguardas_aplicadas` (referencia a implantaciones), `riesgo_residual`, `decision` (`mitigar` \| `aceptar` \| `transferir` \| `evitar`), `aprobado_por`, `fecha_aprobacion`, `fecha_revision`.

> El ENS de categoría básica admite un análisis de riesgos informal, pero **ISO 27001 exige metodología formal con criterios de aceptación y aprobación por dirección**. El módulo se dimensiona según ISO.

**`documentos`**
`codigo`, `titulo`, `tipo` (`politica` \| `norma` \| `procedimiento` \| `registro` \| `acta`), `version`, `estado` (`borrador` \| `en_revision` \| `aprobado` \| `obsoleto`), `autor_id`, `aprobador_id`, `fecha_aprobacion`, `fecha_proxima_revision`, `fichero`.
**`documento_lecturas`** — acuse de lectura por usuario y fecha (lo pide la cláusula 7.3 de ISO y `org.2` del ENS).

**`evidencias`**
`titulo`, `tipo` (`captura` \| `log` \| `informe` \| `contrato` \| `registro` \| `certificado`), `fichero_o_url`, `fecha_obtencion`, `fecha_caducidad`, `responsable_id`, `periodicidad_renovacion`.

**`tareas`**
`titulo`, `origen` (`hallazgo` \| `riesgo` \| `brecha_implantacion` \| `incidente` \| `revision_direccion`), `responsable_id`, `fecha_limite`, `estado`, `prioridad`, `coste_estimado`.

**`personas`** — `nombre`, `puesto`, `fecha_alta`, `fecha_baja`, `roles_ens`.
Roles ENS obligatorios y su separación: **responsable de la información**, **responsable del servicio**, **responsable de seguridad**, **responsable del sistema**, **administrador de la seguridad del sistema**. El sistema debe **impedir** que responsable de seguridad y responsable del sistema recaigan en la misma persona.

**`proveedores`** — `nombre`, `servicio_prestado`, `criticidad`, `certificaciones` (ISO 27001, conformidad ENS, categoría), `clausulas_seguridad_contrato`, `fecha_evaluacion`, `proxima_evaluacion`, `ubicacion_datos`, `es_subencargado_rgpd`.

**`incidentes`** — `fecha_deteccion`, `fecha_inicio`, `clasificacion` (taxonomía y peligrosidad de la CCN-STIC 817), `dimensiones_afectadas`, `activos_afectados`, `impacto`, `acciones_contencion`, `notificado_ccn_cert` + fecha, `notificado_aepd` + fecha, `fecha_cierre`, `leccion_aprendida`, `no_conformidad_id`.

**`bia_servicios`** / **`planes_continuidad`** / **`pruebas_continuidad`** — `rto`, `rpo`, `impacto_por_periodo`, plan asociado, registro de pruebas con fecha y resultado.

**`auditorias`** — `tipo` (`interna` \| `externa` \| `autoevaluacion`), `marco_id`, `alcance`, `fecha`, `auditor`, `entidad_certificadora`, `resultado`.
**`hallazgos`** — `auditoria_id`, `requisito_id`, `tipo` (`nc_mayor` \| `nc_menor` \| `observacion` \| `oportunidad_mejora`), `descripcion`.

**`no_conformidades`** — `origen`, `descripcion`, `analisis_causa_raiz`, `accion_correctiva`, `responsable_id`, `fecha_prevista`, `fecha_cierre`, `eficacia_verificada`, `fecha_verificacion`.

**`indicadores`** + **`mediciones`** — `nombre`, `formula_o_fuente`, `objetivo`, `periodicidad`, serie histórica de valores.

**`revisiones_direccion`** — `fecha`, entradas obligatorias de la cláusula 9.3, salidas y decisiones, `acta_documento_id`.

**`eventos_auditoria`** — log inmutable del propio sistema: `usuario_id`, `entidad`, `entidad_id`, `accion`, `valor_anterior`, `valor_nuevo`, `ip`, `fecha`. Sin update ni delete.

### 2.3 La tabla de unión: `implantaciones`

Es el centro del modelo. Une un requisito del catálogo global con una organización.

| Campo | Notas |
|---|---|
| `organizacion_id`, `sistema_id`, `requisito_id` | |
| `aplica` | booleano |
| `justificacion` | obligatoria si `aplica = false` |
| `exigencia_calculada` | qué nivel/refuerzo se exige según la categoría del sistema |
| `estado` | `no_iniciado` \| `planificado` \| `en_progreso` \| `implantado` \| `no_aplica` |
| `nivel_madurez` | escala L0–L5 del CCN, usada en el informe INES |
| `responsable_id`, `fecha_objetivo`, `notas` | |

**`implantacion_transiciones`** — `estado_anterior`, `estado_nuevo`, `fecha`, `usuario_id`, `nota`.
**`implantacion_evidencias`** — N:M con `evidencias`.
**`implantacion_documentos`** — N:M con `documentos`.
**`implantacion_tareas`** — N:M con `tareas`.

Esta tabla contesta las tres preguntas de cualquier auditoría: qué aplica, cómo se cumple y dónde está la prueba.

**La Declaración de Aplicabilidad (SoA) de ISO y la Declaración de Aplicabilidad del ENS son dos consultas distintas sobre esta misma tabla.** Nunca documentos mantenidos a mano.

---

## 3. Motor de categorización ENS

Es una función pura, no un formulario:

```
entrada:  valoración de las 5 dimensiones (na | bajo | medio | alto)
          + perfil de cumplimiento opcional
paso 1:   categoría del sistema = máximo de las 5 dimensiones
paso 2:   consultar aplicabilidad_ens por categoría
paso 3:   aplicar modulación por nivel de dimensión donde corresponda
paso 4:   si hay perfil, intersecar con perfil_requisitos
salida:   conjunto de medidas aplicables con su nivel de refuerzo exigido
```

Al recalcularse (porque cambia una valoración), el sistema **no borra** implantaciones existentes: marca las que dejan de aplicar y crea las nuevas en `no_iniciado`, avisando de la diferencia.

---

## 4. Módulos funcionales

### 4.1 Contexto y alcance
Cuestiones internas y externas (cláusula 4.1), partes interesadas y sus requisitos (4.2), alcance declarado con exclusiones justificadas (4.3). Para el ENS: identificación y categorización del sistema.
Incluir el **cambio climático** como cuestión a considerar en 4.1/4.2 — lo introdujo la enmienda de 2024 y el auditor lo pregunta.

### 4.2 Inventario de activos
Alta manual, importación desde CSV/Excel e importadores automáticos (AWS vía API). Valoración en cinco dimensiones, grafo de dependencias, generación de etiquetas QR, ciclo de vida y baja con registro de borrado seguro.

### 4.3 Riesgos
Metodología configurable, catálogo de amenazas, cálculo de riesgo intrínseco y residual, vinculación de salvaguardas a implantaciones, aceptación formal con firma de dirección, reevaluación periódica con histórico comparable.

### 4.4 Aplicabilidad y control del cumplimiento
Vista del catálogo filtrada por marco, estado, responsable, tema o atributo. Vista de mapeo cruzado que muestra, para cada control ISO, sus medidas ENS correspondientes y su estado. Generación de SoA y DdA.

### 4.5 Documentación
Jerarquía política → normas → procedimientos → registros. Versionado, flujo de aprobación, publicación, acuse de lectura, aviso de revisión vencida, plantillas base personalizables por organización.

### 4.6 Evidencias
Repositorio con vinculación N:M a implantaciones, caducidad y recordatorio de renovación, responsable asignado, previsualización.

### 4.7 Plan de acción
Tareas con origen trazable, responsable, plazo, prioridad y estado. Vista Kanban y vista calendario. Alertas de vencimiento.

### 4.8 Personas
Roles ENS con validación de incompatibilidades, checklist de alta y baja, formación y concienciación con registro de asistencia, acuerdos de confidencialidad.

### 4.9 Proveedores y terceros
Evaluación inicial, cláusulas de seguridad contractuales, reevaluación periódica, registro de conformidad ENS del proveedor cuando aplique, tratamiento de servicios en la nube (`op.nub`).

### 4.10 Incidentes
Registro y clasificación, cálculo de plazos de notificación con cuenta atrás visible, notificación al CCN-CERT, enlace a no conformidad y a lecciones aprendidas.

### 4.11 Continuidad
BIA por servicio con RTO/RPO, planes asociados, calendario y registro de pruebas con resultado.

### 4.12 Auditorías
Programa anual de auditoría interna, checklists generadas desde el catálogo, registro de hallazgos vinculados al requisito concreto, seguimiento de auditorías externas y de la autoevaluación ENS.

### 4.13 No conformidades y acciones correctivas
Análisis de causa raíz, acción, responsable, plazo, cierre y **verificación de eficacia** (esto último es lo que más se olvida y lo que el auditor comprueba).

### 4.14 Métricas
Indicadores con objetivo y periodicidad, series históricas, cuadro de mando con: porcentaje de implantación por marco, evidencias caducadas, tareas vencidas, riesgos por encima del umbral, no conformidades abiertas, nivel de madurez medio.

### 4.15 Revisión por la dirección
Generador de acta que recoge automáticamente las entradas obligatorias de la cláusula 9.3 (estado de acciones previas, cambios de contexto, desempeño, no conformidades, resultados de auditoría, riesgos, oportunidades de mejora) y registra las salidas como tareas.

### 4.16 Calendario de obligaciones
Vista unificada de todo lo periódico: revisión por dirección (anual), auditoría interna (anual), reevaluación de riesgos, revisión documental, formación, pruebas de continuidad, reevaluación de proveedores, informe INES (anual), auditoría de seguimiento ISO (anual), renovación de conformidad ENS (**bienal** — no coincide con el ciclo de 3 años de ISO), caducidad de evidencias.

### 4.17 Conformidad
Dos flujos distintos:
- **Categoría básica**: autoevaluación → Declaración de Conformidad → publicación del distintivo.
- **Categoría media y alta**: auditoría por entidad acreditada por ENAC → Certificación de Conformidad.

Modelar ambos; implementar solo el primero por ahora.

### 4.18 Informes y exportación
Exportación a Word y PDF de SoA, DdA, plan de adecuación, informe de estado, acta de revisión, informe de auditoría interna. Formato pensado para entregar al auditor.

### 4.19 Usuarios, roles y permisos
RBAC por organización. El responsable de seguridad ve todo; un técnico ve sus tareas y las implantaciones a su cargo; un auditor externo tiene un rol de solo lectura con acceso limitado al alcance auditado.

---

## 5. Contenido del catálogo a cargar (seed)

**ISO/IEC 27001:2022**
- Cláusulas 4 a 10 (contexto, liderazgo, planificación, soporte, operación, evaluación del desempeño, mejora), incluida 6.3 planificación de cambios.
- Anexo A: 93 controles en 4 temas — organizativos (37), personas (8), físicos (14), tecnológicos (34).
- Los cinco atributos de la ISO 27002:2022 por control.

**ENS (RD 311/2022)**
- Anexo I: reglas de categorización.
- Anexo II: las medidas de los tres marcos, con refuerzos y matriz de aplicabilidad por categoría:
  - `org.*` — marco organizativo: política, normativa, procedimientos, proceso de autorización.
  - `op.*` — marco operacional: `op.pl` planificación, `op.acc` control de acceso, `op.exp` explotación, `op.ext` servicios externos, `op.nub` servicios en la nube, `op.cont` continuidad, `op.mon` monitorización.
  - `mp.*` — medidas de protección: `mp.if` instalaciones, `mp.per` personal, `mp.eq` equipos, `mp.com` comunicaciones, `mp.si` soportes, `mp.sw` aplicaciones, `mp.info` información, `mp.s` servicios.
- Perfiles de cumplimiento específicos (serie CCN-STIC 890, incluido el de requisitos esenciales).

**Mapeos ISO ↔ ENS**, con tipo de correspondencia y nota de cobertura parcial.

**Guías CCN-STIC de referencia** para enlazar desde cada medida: 801 roles, 802 auditoría, 803 valoración, 804 implantación, 805 política, 806 plan de adecuación, 807 criptología, 808 verificación de conformidad, 809 declaración y certificación, 815 métricas e indicadores, 817 gestión de ciberincidentes, 824 informe del estado de seguridad. Y el catálogo CPSTIC (CCN-STIC 105) para productos.

---

## 6. Requisitos no funcionales

Derivados del principio 8: la herramienta forma parte del alcance del SGSI.

- Autenticación con segundo factor obligatorio para roles con permisos de escritura.
- Cifrado en reposo de la base de datos y del almacén de ficheros. Cifrado en tránsito.
- Traza inmutable de toda operación sobre entidades de cumplimiento.
- Backups cifrados con restauración probada y periodicidad documentada.
- Política de retención y borrado alineada con RGPD para los datos de personas.
- Control de acceso por organización aplicado a nivel de consulta, no solo de interfaz.
- Registro de sesiones y bloqueo por inactividad.

---

## 7. Fases de entrega

**Fase 1 — sustituir las hojas de cálculo**
Catálogo cargado, motor de categorización, inventario de activos con cinco dimensiones, implantaciones con estados e histórico, evidencias, tareas. Con esto ya se gana el mapeo cruzado, que es el problema principal hoy.

**Fase 2 — el papel formal**
Riesgos con metodología, documentos con flujo de aprobación y acuse de lectura, generación de SoA, DdA y plan de adecuación.

**Fase 3 — el ciclo vivo**
Incidentes, continuidad, auditoría interna, no conformidades, revisión por la dirección, métricas y calendario de obligaciones.

**Fase 4 — producto**
Multi-organización real, onboarding, importadores, informes exportables para auditor, personalización de marca.

---

## 8. Fuera de alcance por ahora

Facturación y suscripciones. Onboarding self-service. Panel de superadministración. White-labeling. Integraciones con SIEM o escáneres de vulnerabilidades. Aplicación móvil. Flujos de auditoría formal ENS de categoría media y alta (se modelan, no se implementan). NIS2 — la Directiva 2022/2555 se transpondrá mediante la Ley de Coordinación y Gobernanza de la Ciberseguridad, todavía en tramitación; el modelo de marcos debe permitir añadirla sin cambios estructurales, pero no se carga aún.

---

## 9. Glosario

| Término | Significado |
|---|---|
| **SGSI** | Sistema de Gestión de la Seguridad de la Información (ISO 27001) |
| **SoA** | Statement of Applicability / Declaración de Aplicabilidad (ISO) |
| **DdA** | Declaración de Aplicabilidad (ENS) |
| **ENS** | Esquema Nacional de Seguridad, RD 311/2022 |
| **CCN** | Centro Criptológico Nacional |
| **CCN-STIC** | Serie de guías técnicas del CCN (serie 800 para el ENS) |
| **CPSTIC** | Catálogo de Productos y Servicios de Seguridad TIC (CCN-STIC 105) |
| **INES** | Informe Nacional del Estado de Seguridad, reporte anual al CCN |
| **MAGERIT** | Metodología de análisis y gestión de riesgos de la Administración |
| **ENAC** | Entidad Nacional de Acreditación |
| **NC** | No conformidad (mayor o menor) |
| **BIA** | Business Impact Analysis, análisis de impacto en el negocio |
| **RTO / RPO** | Tiempo objetivo de recuperación / punto objetivo de recuperación |
| **Dimensiones** | Confidencialidad, Integridad, Disponibilidad, Autenticidad, Trazabilidad |

---

## 10. Nota para quien implemente

El stack no está decidido. Antes de proponer tecnología, confirmar la decisión.

Si hay que empezar por algo, empezar por el **catálogo y el motor de categorización**: son la parte más específica del dominio, la que más se equivoca si se improvisa, y de la que cuelga todo lo demás. El resto son CRUD con reglas de negocio encima.
