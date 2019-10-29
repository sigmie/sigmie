@extends('layouts.app')

@section('content')


<div class="h-full mx-auto m-16">
    <form class="mx-auto flex container w-4/5 sm:w-3/5 md:w-2/4 lg:w-1/3 xl:w-1/3 text-gray-700 h-auto" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="container flex justify-center w-auto block border-gray-200 border rounded bg-white px-4">
            <div class="row">
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10">
                    <h1 class="pt-4 pb-3 text-xl">Login</h1>
                </div>
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-2">
                    <div class="box">
                        <div class="mx-auto">
                            <label class="pb-2 block" for="email">{{ __('E-Mail Address') }}</label>
                            <input id="email" type="email" class="bg-white focus:outline-none focus:shadow-outline border border-gray-300 rounded-lg py-2 px-4 block w-full appearance-none leading-normal @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pb-6">
                    <div class="box my-4">
                        <div class="mx-auto">
                            <label class="pb-2 block" for="password">{{ __('Password') }}</label>

                            <input id="password" type="password" class="bg-white focus:outline-none focus:shadow-outline border border-gray-300 rounded-lg py-2 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                            @if (Route::has('password.request'))
                            <a class="text-gray-600 py-1" href="{{ route('password.request') }}">
                                {{ __('Forgot Your Password?') }}
                            </a>
                            @endif

                            @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 bg-gray-300 px-10">
                    <div class="float-left box w-full py-4">
                        <div class="container">
                            <div class="row m-0">
                                <div class="col-md-6 col-lg-6 col-sm-6 col-xs-6 p-0 pr-2">
                                    <div class="w-full">
                                        <button type="submit" class="bg-transparent text-gray-500 py-2 px-4 rounded w-full font-semibold uppercase">
                                            {{ __('Register') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-6 col-sm-6 col-xs-6 p-0 pl-2">
                                    <div class="w-full">
                                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white py-2 px-4 rounded uppercase w-full float-right font-semibold">
                                            {{ __('Continue') }}
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