<navbar :auth="@auth true @endauth @guest false @endguest" logout-action="{{ route('logout') }}" login-route="{{ route('login') }}"
    register-route="{{ route('register') }}" />
