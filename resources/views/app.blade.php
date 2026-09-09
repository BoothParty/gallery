<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @viteReactRefresh
    @php
        $isPartyPage = str_starts_with(data_get($page, 'component', ''), 'party/');
        $stylesheet = $isPartyPage ? 'resources/css/app.css' : 'resources/css/admin.css';
        /**
         * The host's own visits would skew every metric of their gallery, they
         * being the one who reloads it all evening, so the tracker only goes
         * out to the guests.
         */
        $tracksVisit = $isPartyPage
            && ! data_get($page, 'props.canEdit')
            && filled(config('services.umami.script_url'))
            && filled(config('services.umami.website_id'));
    @endphp
    @vite([$stylesheet, 'resources/js/app.tsx'])
    @if ($tracksVisit)
        <script defer src="{{ config('services.umami.script_url') }}"
            data-website-id="{{ config('services.umami.website_id') }}"></script>
    @endif
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
