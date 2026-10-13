<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'OIL-PMS') · OIL-PMS</title>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="{{ asset('css/oilpms.css') }}?v={{ filemtime(public_path('css/oilpms.css')) }}">
</head>
<body>
<div id="app">@yield('content')</div>
<div id="toasts"></div>
<script>window.OILPMS_TOASTS = @json(array_values(array_filter([session('toast')])));</script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
