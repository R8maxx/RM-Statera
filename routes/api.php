<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Rutas de API
|--------------------------------------------------------------------------
|
| Statera no expone API pública: la interfaz va sobre Inertia, que comparte
| la sesión y la autorización de la capa web (§12 de stack-gestor-cumplimiento.md,
| "API separada + SPA" está descartada). Este fichero queda para endpoints
| internos puntuales (webhooks, health checks de integraciones).
|
*/
