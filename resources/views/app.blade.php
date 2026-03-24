<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
    @if(config('umami.script_url') && config('umami.website_id'))
        <script async src="{{ config('umami.script_url') }}" data-website-id="{{ config('umami.website_id') }}"></script>
    @endif
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>