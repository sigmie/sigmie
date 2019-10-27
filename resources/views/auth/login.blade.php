@extends('layouts.app')

@section('content')


<div class="h-full mx-auto m-16">
    <form class="mx-auto flex container w-11/12 sm:w-2/3 md:w-3/5 lg:w-1/3 xl:w-1/3 text-gray-700 h-auto" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="container flex justify-center border-gray-300 border-2 shadow rounded py-12 px-4">
            <div class="row">
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <div class="box mb-4">
                        <div class="mx-auto w-56">
                            <label class="pb-3 block" for="email">{{ __('E-Mail Address') }}</label>
                            <input id="email" type="email" class="bg-white focus:outline-none focus:shadow-outline border border-gray-300 rounded-lg py-2 px-4 block w-full appearance-none leading-normal @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <div class="box my-4">
                        <div class="mx-auto w-56">
                            <label class="pb-3 block" for="password">{{ __('Password') }}</label>

                            <input id="password" type="password" class="bg-white focus:outline-none focus:shadow-outline border border-gray-300 rounded-lg py-2 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                            @if (Route::has('password.request'))
                            <a class="btn btn-link" href="{{ route('password.request') }}">
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

                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <div class="box w-56 mx-auto">
                        <div class="float-left">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                {{ __('Login') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection