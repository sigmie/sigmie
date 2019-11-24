<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>

    @component('common.head')
    @endcomponent

</head>

<body class="min-h-full h-full">
    <div id="app" class="min-h-full h-full bg-gray-100">

        <main class="mx-auto container px-4">
            @yield('content')
        </main>

    </div>

    @if(config('app.env') == 'local')
    <script src="http://localhost:35729/livereload.js"></script>
    @endif
</body>

</html>