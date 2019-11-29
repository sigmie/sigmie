<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    @include('common.head')
</head>

<body class="min-h-full h-full">
    <div id="app" class="min-h-full h-full bg-gray-100">

        <nav class="top-0 text-gray-500 shadow bg-white inline-block w-full h-12">
            @include('common.navbar')
        </nav>

        <main class="mx-auto container m-0 w-256 pt-10">
            @yield('content')
        </main>

    </div>

    @if(config('app.env') == 'local')
    <script src="http://localhost:35729/livereload.js"></script>
    @endif
</body>

</html>
