<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @viteReactRefresh
    @php
        $stylesheet = str_starts_with(data_get($page, 'component', ''), 'party/')
            ? 'resources/css/app.css'
            : 'resources/css/admin.css';
    @endphp
    @vite([$stylesheet, 'resources/js/app.tsx'])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>