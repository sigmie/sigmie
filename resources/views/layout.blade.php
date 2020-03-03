<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    @include('common.head')
</head>

<body class="min-h-full h-full bg-gray-50">

    <div id="app" class="min-h-full h-full relative">

        @yield('content')

    </div>

    @include('common.tail')
</body>

</html>
