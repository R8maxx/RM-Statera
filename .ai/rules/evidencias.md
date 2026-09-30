---
paths:
  - app/Domain/Evidencia/**
  - resources/js/pages/evidencias/**
---

# Las evidencias

## Lo que está contado en otro sitio

Una evidencia cruza medio producto (invariante 6: evidencia ↔ requisito es N:M), así
que sus reglas viven donde muerden:

- **Caducidad y periodicidad de renovación**, y los scopes `caducadas()` y
  `porCaducar()` que comparten el panel, el filtro de la tabla y el correo diario:
  `.ai/rules/obligaciones.md`, que es donde vive `Domain\Aviso` desde el § 4.16.
- **`Evidencia\PeriodicidadRenovacion` no es `Metrica\Periodicidad`**, y por qué no se
  comparten: `.ai/rules/metricas.md`.
- **Un adjunto no es una evidencia**, y dónde está la frontera: `.ai/rules/adjuntos.md`.
- **El disco `evidencias` lleva Object Lock**, y de ahí que `destroy()` deje el fichero
  a propósito — la diferencia con el disco de adjuntos está en `.ai/rules/adjuntos.md`.

## La ficha, la vigencia y la renovación

- **La vigencia se decide una vez, en `Evidencia\Vigencia`**, y la leen la columna de la tabla
  (`badge()`) y la ficha (`toArray()`, con el periodo en días para la barra). Antes cada una tenía
  su regla, y la ficha decía «Sin caducidad» de una evidencia semestral. El umbral de «por caducar»
  es `Vigencia::DIAS_DE_AVISO`, el mismo que usa por defecto `Evidencia::porCaducar()`.
- **Con periodicidad siempre hay caducidad**: la deriva `RegistrarEvidencia` al guardar. Lo que no
  pase por él —el seeder— tiene que escribirla, o la ficha dice «Sin caducidad».
- **Renovar es dar de alta otra, no editar** (`RegistrarEvidencia::renovar()`): el fichero no se
  reemplaza. La nueva hereda los vínculos con su nota; la anterior **conserva los suyos** —probó
  lo que probó durante su periodo— y apunta a la nueva con `renovada_por_id` (única, `SET NULL`).
  **`sinRenovar()` es lo que la saca de los avisos**: lo aplican `caducadas()`, `porCaducar()` y
  `CalendarioVencimientos`. Una consulta nueva de vencimientos de evidencias que no pase por
  esos scopes vuelve a encender el rojo de lo ya renovado.
- **Vincular desde la ficha de la evidencia** va por `POST /evidencias/{evidencia}/requisitos`, de
  varios en varios. No hay implantación en la ruta, así que `EscribeLoSuyo` no decide: el
  controlador salta y cuenta las ajenas del técnico, como el cambio de estado en bloque, y las
  candidatas ya llegan filtradas. **Quitar** usa la ruta de siempre,
  `DELETE /implantaciones/{implantacion}/evidencias/{evidencia}`, que sí lleva el middleware.
- **`GuardarEvidenciaRequest` distingue la edición por el nombre de la ruta**
  (`evidencias.update`), no por traer `{evidencia}`: la renovación también la trae y es un alta
  que exige su propio fichero o enlace.

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **`spatie/laravel-medialibrary` se retiró.** Venía instalado en el esqueleto con su migración `media` y no lo usaba nadie: ni un `HasMedia`, ni `config/media-library.php` publicado. Y su tabla `media` no lleva `organizacion_id`, así que meter ahí los ficheros de las evidencias las habría dejado fuera de las tres capas de aislamiento; incluirla exigía modelo propio, migración, RLS y global scope sobre una tabla que no controlamos. Las evidencias guardan `disco`, `ruta`, `mime`, `tamano` y `hash_sha256` en columnas propias sobre el disco `evidencias`, que es exactamente lo que ya describía el comentario de `config/filesystems.php`. Si algún día hacen falta conversiones de imagen o adjuntos de documentos, se vuelve a valorar entonces.
