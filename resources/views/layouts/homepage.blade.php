<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    @include('common.head')
</head>

<body class="min-h-full h-full">
    <div id="app" class="min-h-full h-full bg-gray-100 pb-10">

        <nav class="top-0 text-gray-500 shadow bg-white inline-block w-full h-12">
            @include('common.navbar')
        </nav>


        <main class="mx-auto container m-0 w-256 pt-20">

            <div class="w-full py-10">
                <img class="mx-auto"
                    src="https://res.cloudinary.com/markos-nikolaos-orfanos/image/upload/v1574659602/Group_2_fxapdw.png"
                    width="200">
            </div>
            @yield('content')

            <div class="pt-5 text-gray-500 text-center">
                @yield('bottom-content')
            </div>
        </main>

    </div>

    <footer class="absolute bottom-0 w-full h-20">
        <div class="flex flex-col justify-center h-full px-10">
            <div>
                <a class="inline-block pr-4 text-gray-500" href="">
                    ©2019 MOS - Sigma s.r.o.
                </a>
                <a class="inline-block pr-4 text-gray-500" href="{{route('terms-of-service')}}">
                    Terms of service
                </a>
                <a class="inline-block pr-4 text-gray-500" href="{{route('privacy-policy')}}">
                    Privacy policy
                </a>
            </div>
        </div>
    </footer>

    @if(config('app.env') == 'local')
    <script src="http://localhost:35729/livereload.js"></script>
    @endif
</body>

</html>
