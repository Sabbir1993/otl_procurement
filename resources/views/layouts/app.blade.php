<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'OIL-PMS') · OIL-PMS</title>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="{{ asset('css/oilpms.css') }}?v={{ filemtime(public_path('css/oilpms.css')) }}">
</head>
<body>
<div id="app">
    @include('partials.sidebar')
    <div class="main">
        @include('partials.topbar')
        <div class="content" id="content">
            <div class="crumbs">@yield('crumbs')</div>
            <div class="ph">
                <div>
                    <h1>@if (View::hasSection('heading')) @yield('heading') @else @yield('title') @endif</h1>
                    @hasSection('subheading')<p>@yield('subheading')</p>@endif
                </div>
                <div class="sp"></div>
                <div class="acts">@yield('actions')</div>
            </div>
            @if (($shell['role'] ?? '') === 'audit')<div class="alert info" style="margin-bottom:10px"><div class="t"><b>Read-only role</b>Internal Audit / MD can view everything but cannot change data.</div></div>@endif
            <div id="pgbody">@yield('content')</div>
        </div>
    </div>
    @include('partials.mobile-bar')
</div>
<div id="toasts"></div>
@stack('modals')
<script>
window.OILPMS_TOASTS = @json(array_values(array_filter([session('toast'), $errors->any() && !session('toast') ? ['msg' => $errors->first(), 'kind' => 'bad'] : null])));
</script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
