@extends('layouts.homepage')

@section('content')

<div class="h-full mx-auto">
    <form class="mx-auto flex container w-84 text-gray-700 h-auto" method="POST" action="{{ route('password.email') }}">

        @csrf

        <div class="container flex justify-center w-auto block border-gray-200 border rounded bg-white px-4">

            <div class="row">

                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 border-b mb-2">
                    <h1 class="pt-5 pb-4 text-xl">New password</h1>
                </div>

                <input type="hidden" name="token" value="{{ $token }}">


                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 pb-1">
                    <div class="box mt-4">
                        <div class="mx-auto">
                            <label class="pb-1 block text-gray-600 font-normal text-sm" for="email">Email
                                address</label>
                            <input id="email" type="email"
                                class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal"
                                name="email" value="{{ $email ?? old('email') }}" required autocomplete="email"
                                autofocus>
                        </div>
                    </div>
                </div>

                @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror

                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 pb-1">
                    <div class="box mt-4">
                        <div class="mx-auto">
                            <label class="pb-1 block text-gray-600 font-normal text-sm" for="password">
                                Password
                            </label>
                            <input id="password" type="password"
                                class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal"
                                name="password" required autocomplete="new-password">

                            @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>
                    </div>
                </div>


                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 pb-6">
                    <div class="box mt-4">
                        <div class="mx-auto">
                            <label class="pb-1 block text-gray-600 font-normal text-sm" for="password-confirm">
                                Confirm password
                            </label>
                            <input id="password-confirm" type="password"
                                class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal"
                                name="password_confirmation" required autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 bg-gray-300 px-8">
                    <div class="float-left box w-full py-3">
                        <div class="w-full">
                            <button type="submit"
                                class="bg-blue-800 hover:bg-blue-900 text-white text-sm py-2 px-4 rounded uppercase w-full float-right font-semibold tracking-wide">
                                Reset password
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </form>
</div>

@endsection

@section('bottom-content')
<a href="">
    Don't have an account yet? Sign up Here
</a>
@endsection
