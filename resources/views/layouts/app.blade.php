<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    @include('common.head')
</head>

<body class="min-h-full h-full">
    <div id="app" class="min-h-full h-full bg-gray-100 flex">

        <div class="w-64 bg-10 bg-primary min-h-full inline-block w-92">
            @include('common.sidebar')
        </div>

        <div class="inline-block flex-grow">

            <nav class="top-0 text-gray-500 shadow bg-white inline-block w-full">
                @include('common.navbar')
            </nav>

            <main class="mx-auto container px-4 inline-block">
                @yield('content')
            </main>
        </div>
    </div>

    @if(config('app.env') == 'local')
    <script src="http://localhost:35729/livereload.js"></script>
    @endif
</body>

</html>
