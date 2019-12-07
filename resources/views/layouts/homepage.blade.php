<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    @include('common.head')
</head>

<body class="min-h-full h-full bg-gray-100">
    <div id="app" class="min-h-full relative pb-20">

        <nav class="top-0 text-gray-500 shadow bg-white inline-block w-full h-12">
            @include('common.navbar')
        </nav>

        <main class="mx-auto container m-0 w-256">

            <router-view :old="{{ json_encode(Session::getOldInput()) }}"></router-view>

            <div class="w-full py-10">
                <img class="mx-auto"
                    src="https://res.cloudinary.com/markos-nikolaos-orfanos/image/upload/v1574659602/Group_2_fxapdw.png"
                    width="200">
            </div>

            @yield('content')

            <div class="pt-5 text-gray-500 text-center text-sm">
                @yield('bottom-content')
            </div>
        </main>

        <footer class="bottom-0 absolute text-sm w-full h-20">
            @include('common.footer')
        </footer>
    </div>


    @if(config('app.env') == 'local')
    <script src="http://localhost:35729/livereload.js"></script>
    @endif
</body>

</html>
