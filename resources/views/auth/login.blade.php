@extends('layouts.homepage')

@section('content')

<div class="h-full mx-auto">
    <form class="mx-auto flex container w-84 text-gray-700 h-auto" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="container flex justify-center w-auto block border-gray-200 border rounded bg-white px-4">
            <div class="row">
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 border-b mb-2">
                    <h1 class="pt-5 pb-4 text-xl">Login</h1>
                </div>
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 pt-4">
                    <div class="box">
                        <div class="mx-auto">
                            <label class="pb-1 block text-gray-600 font-normal text-sm" for="email">Email</label>
                            <input id="email" type="email"
                                class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('email') bg-red-100 border border-red-400 text-red-700 @enderror"
                                name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                            {{-- @error('password') --}}
                            {{-- <strong>{{ $message }}</strong> --}}
                            {{-- @enderror --}}
                        </div>
                    </div>
                </div>
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 pb-6">
                    <div class="box mt-4">
                        <div class="mx-auto">
                            <label class="pb-1 block text-gray-600 font-normal text-sm" for="password">Password</label>

                            <input id="password" type="password"
                                class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror"
                                name="password" required autocomplete="current-password">

                            @if (Route::has('password.request'))
                            <a class="text-gray-500 text-sm py-1" href="{{ route('password.request') }}">
                                Forgot Your Password?
                            </a>
                            @endif

                        </div>
                    </div>
                </div>

                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 bg-gray-300 px-8">
                    <div class="float-left box w-full py-3">
                        <div class="container">
                            <div class="row m-0">
                                <div class="col-md-6 col-lg-6 col-sm-6 col-xs-6 p-0 pr-3">
                                    <a href="{{ route('register')}}"
                                        class="bg-transparent text-gray-600 text-sm rounded w-full font-semibold uppercase tracking-wide w-full text-center block h-full cursor-pointer px-4 py-2">
                                        Register
                                    </a>
                                </div>
                                <div class="col-md-6 col-lg-6 col-sm-6 col-xs-6 p-0 pl-3">
                                    <div class="w-full">
                                        <button type="submit"
                                            class="bg-blue-800 hover:bg-blue-900 text-white text-sm py-2 px-4 rounded uppercase w-full float-right font-semibold tracking-wide">
                                            Sign in
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
