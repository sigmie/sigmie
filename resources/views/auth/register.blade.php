@extends('layouts.homepage')

@section('additional-js')
<script src="https://js.stripe.com/v3/" type="application/javascript"></script>
@endsection


@section('additional-css')
<style>
    .StripeElement {
        box-sizing: border-box;
        padding: 0.25rem 1rem 0.25rem 1rem;
        background-color: #edf2f7;
        border-radius: 0.25rem;
        line-height: 1.5;
        padding: 0.4rem 1rem 0.4rem 1rem;
    }

    .StripeElement--focus {
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
    }

    .StripeElement--invalid {
        background-color: 'red';
        border-color: #fa755a;
    }

    .StripeElement--webkit-autofill {
        background-color: 'red';
        background-color: #fefde5 !important;
    }
</style>

@endsection()

@section('content')
<div class="h-full mx-auto container">
    <div class="row m-0">
        <div class="col-md-7 col-sm-12">
            <form method="POST" id="register-form" class="mx-auto flex container w-full text-gray-700 h-auto"
                action="{{ route('register') }}">
                @csrf
                <div class="container flex justify-center w-auto block border-gray-200 border rounded bg-white px-4">
                    <div class="row">

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10">
                            <h1 class="pt-5 pb-2 text-xl">Register</h1>
                        </div>
                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 border-t mt-3 pt-2">
                            <span class="text-xs text-gray-500">Basics</span>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">

                                    <label for="email" class="pb-1 block">Email</label>
                                    <input id="email" type="email"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror @error('email') is-invalid @enderror"
                                        name="email" value="{{ old('email') }}" required autocomplete="email">

                                    @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">

                                    <label for="password" class="pb-1 block">{{ __('Password') }}</label>
                                    <input id="password" type="password"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror @error('email') is-invalid @enderror"
                                        name="password">

                                    @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">

                                    <label for="password-confirm"
                                        class="pb-1 block">{{ __('Confirm Password') }}</label>
                                    <input id="password-confirm" type="password"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror @error('email') is-invalid @enderror"
                                        name="password_confirmation" required autocomplete="new-password">
                                    @error('password-confirm')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 border-t mt-6 pt-2">
                            <span class="text-xs text-gray-500">Billing</span>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">
                                    <label for="name" class="pb-1 block">Cardholder name</label>
                                    <input id="card-holder-name" type="text"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"
                                        name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                    @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">
                                    <label for="name" class="pb-1 block">Credit card</label>
                                    <div id="card-element"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror">
                                    </div>
                                    <div id="card-errors" role="alert"></div>
                                </div>
                            </div>
                        </div>

                        <input name="method" id="method-field" value="" type="hidden">

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 py-4">
                            <button id="register-button"
                                class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-2 px-4 rounded uppercase float-right font-semibold tracking-wide">
                                Register
                            </button>
                        </div>

                    </div>
            </form>
        </div>
    </div>
    <div class="col-md-5 col-sm-12 first-xs last-md">
        <div class="row m-0">
            {{-- @include('auth.register.cards.hobby') --}}
        </div>
    </div>
</div>
@endsection
