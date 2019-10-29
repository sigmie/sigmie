<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

</head>

<body class="min-h-full h-full">
    <div id="app" class="min-h-full h-full bg-gray-100">
        <nav class="top-0 text-gray-500 shadow bg-white">
            <div class="row">
                <div class="col-md-4 col-sm-4 col-xs-4">
                    <div class="h-full flex justify-center">
                        <a class="text-grey-darker text-center bg-grey-light px-4 py-2 m-2" href="{{ url('/') }}">
                            {{ config('app.name', 'Laravel') }}
                        </a>
                    </div>
                </div>

                <div class="col-md-4 col-sm-4 col-xs-4">


                </div>


                <div class="col-md-4 col-sm-4 col-xd-4">
                    <!-- Right Side Of Navbar -->
                    <div class="h-full flex justify-center">
                        <!-- Authentication Links -->
                        @guest
                        <a class="m-2 py-2 px-4 hover:text-gray-600" href="{{ route('login') }}">{{ __('Login') }}</a>
                        @if (Route::has('register'))
                        <a class="m-2 py-2 px-4 hover:text-gray-600" href="{{ route('register') }}">{{ __('Register') }}</a>
                        @endif
                        @else
                    </div>

                    <li class="nav-item dropdown">
                        <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                            {{ Auth::user()->name }} <span class="caret"></span>
                        </a>

                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                {{ __('Logout') }}
                            </a>

                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                @csrf
                            </form>
                        </div>
                    </li>
                    @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="mx-auto container px-4">
            @yield('content')
        </main>
    </div>
</body>

</html>