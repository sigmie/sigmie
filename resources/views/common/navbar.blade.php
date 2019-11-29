<div class="m-0 p-3 row flex h-full m-auto w-256">

    <div class="col-md-4 align-middle">
        <input id="search" name="search" type="search" class="align-middle">
    </div>

    <div class="col-md-4 col-md-offset-4 flex flex-col justify-center">
        <div class="flex justify-end">
                @auth
                <a href="{{ route('logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout
                </a>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    {{ csrf_field() }}
                </form>
                @else
                <a class="pl-4" href="{{ route('login') }}">Login</a>

                @if (Route::has('register'))
                <a class="pl-4" href="{{ route('register') }}">Register</a>
                @endif

                @endauth
        </div>
    </div>

</div>
