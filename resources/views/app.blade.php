<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">

    {{-- El navegador pinta el chrome con el color del tema activo, no con blanco. --}}
    <meta name="theme-color" content="#fbfcfd" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#14191f" media="(prefers-color-scheme: dark)">

    {{--
        El tema se aplica antes del primer pintado para no provocar un fogonazo.
        Tres estados: `claro`, `oscuro` y —cuando no hay nada guardado— lo que
        diga el sistema. Guardar sólo «claro/oscuro» destruía la preferencia del
        sistema en cuanto alguien tocaba el interruptor una vez.

        Con sesión abierta manda el de la cuenta, que se guarda en el servidor y
        sigue a la persona de un navegador a otro; el del navegador queda para
        las pantallas de acceso, donde todavía no se sabe quién es nadie. Se
        copia al navegador para que el acceso de mañana salga ya en ese tema.
    --}}
    <script>
        (function () {
            try {
                const cuenta = @json(auth()->user()?->tema?->value);

                if (cuenta === 'sistema') {
                    localStorage.removeItem('statera.tema');
                } else if (cuenta) {
                    localStorage.setItem('statera.tema', cuenta);
                }

                const guardado = cuenta ?? localStorage.getItem('statera.tema');
                const preferencia = guardado === 'claro' || guardado === 'oscuro' ? guardado : 'sistema';
                const oscuro = preferencia === 'oscuro'
                    || (preferencia === 'sistema' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', oscuro);
                document.documentElement.dataset.tema = preferencia;
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="min-h-screen bg-background font-sans text-foreground">
    @inertia
</body>
</html>
