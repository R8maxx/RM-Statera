<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" href="/favicon.ico" sizes="any">

    {{-- El tema se aplica antes del primer pintado para no provocar un fogonazo blanco. --}}
    <script>
        (function () {
            try {
                const guardado = localStorage.getItem('statera.tema');
                const oscuro = guardado === 'oscuro'
                    || (guardado === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', oscuro);
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
