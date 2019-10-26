@extends('layouts.app')

@section('content')


<div class="h-full mx-auto">
    <form class="mx-auto flex container w-2/3" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="container flex justify-center border border-gray-600">
            <div class="row">
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">

                    <div class="col-md-6">
                        <div class="mx-auto w-56">
                            <label for="email">{{ __('E-Mail Address') }}</label>
                            <input id="email" type="email" class="@error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <div class="mx-auto w-56">
                        <label for="password">{{ __('Password') }}</label>

                        <input id="password" type="password" class="@error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                        @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                </div>
                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <div class="mx-auto w-56">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                        <label class="form-check-label" for="remember">
                            {{ __('Remember Me') }}
                        </label>
                    </div>
                </div>

                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <div class="mx-auto w-56">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Login') }}
                        </button>

                        @if (Route::has('password.request'))
                        <a class="btn btn-link" href="{{ route('password.request') }}">
                            {{ __('Forgot Your Password?') }}
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection