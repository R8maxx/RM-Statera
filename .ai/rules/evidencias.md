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

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **`spatie/laravel-medialibrary` se retiró.** Venía instalado en el esqueleto con su migración `media` y no lo usaba nadie: ni un `HasMedia`, ni `config/media-library.php` publicado. Y su tabla `media` no lleva `organizacion_id`, así que meter ahí los ficheros de las evidencias las habría dejado fuera de las tres capas de aislamiento; incluirla exigía modelo propio, migración, RLS y global scope sobre una tabla que no controlamos. Las evidencias guardan `disco`, `ruta`, `mime`, `tamano` y `hash_sha256` en columnas propias sobre el disco `evidencias`, que es exactamente lo que ya describía el comentario de `config/filesystems.php`. Si algún día hacen falta conversiones de imagen o adjuntos de documentos, se vuelve a valorar entonces.
