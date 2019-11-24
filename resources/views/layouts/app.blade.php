<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>

    @component('common.head')
    @endcomponent

</head>

<body class="min-h-full h-full">
    <div id="app" class="min-h-full h-full bg-gray-100">
        <nav class="top-0 text-gray-500 shadow h-20 bg-white">
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

        <div class="w-64 bg-10 bg-indigo-900 min-h-full">
            fhe

        </div>

        <main class="mx-auto container px-4">
            @yield('content')
        </main>
    </div>

    @if(config('app.env') == 'local')
    <script src="http://localhost:35729/livereload.js"></script>
    @endif
</body>

</html>