---
paths:
  - database/migrations/**
---

# Las migraciones

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **La migración del trigger usa `CREATE OR REPLACE FUNCTION`.** `migrate:fresh` tira las TABLAS, no
  las funciones, así que la función sobrevive a un refresco de la base y la segunda pasada chocaría.
  Lo descubrió la suite, no una revisión.
