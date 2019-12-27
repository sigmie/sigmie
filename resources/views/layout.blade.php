<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    @include('common.head')
</head>

<body class="min-h-full h-full bg-gray-100">

    @include('cookieConsent::index')

    <div id="app" class="min-h-full h-full relative">

        @yield('content')

    </div>


    @if(config('app.env') == 'local')
    <script src="http://localhost:35729/livereload.js"></script>
    @endif

    @include('common.analytics')
</body>

</html>
