@extends('layouts.public')

@section('public.content')

<div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8" v-cloak>
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="mx-auto">
            <logo-default></logo-default>
        </div>
        <h2 class="mt-6 text-center text-3xl leading-9 font-bold text-gray-900">
            Sign in to your account
        </h2>
        <p class="mt-2 text-center text-sm leading-5 text-gray-600 max-w">
            Or
            <a href="{{ route('register') }}"
                class="font-medium text-orange-600 focus:outline-none focus:underline transition ease-in-out duration-150">
                start your 14-day free trial
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        @if($errors->any())
        <div>
            <alert-danger class="mb-3 shadow" title="Whoops!" text="These credentials do not match our records">
            </alert-danger>
        </div>
        @endif

        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <form action="{{ route('login') }}" method="POST">

                @csrf

                <form-input id="email" name="email" type="email" value="{{ old('email') }}" required
                    label="Email address"></form-input>

                <form-input id="password" name="password" type="password" required label="Password"></form-input>

                <div class="mt-6 flex items-center justify-between">

                    <form-checkbox id="remember" name="remember" label="Remember me"></form-checkbox>

                    <div class="text-sm leading-5">
                        <a href="{{ route('password.request') }}"
                            class="font-medium text-orange-600 hover:text-orange-500 focus:outline-none focus:underline transition ease-in-out duration-150">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <div class="mt-6">
                    <button-primary text="Sign in" type="submit"></button-primary>
                </div>
            </form>

            <div class="mt-4">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm leading-5">
                        <span class="px-2 bg-white text-gray-500">
                            Or continue with
                        </span>
                    </div>
                </div>

                <div>
                    <button-github route="{{ route('github.redirect', ['action' => 'login'])}}" class="mt-3">
                    </button-github>
                </div>
            </div>
        </div>
    </div>

    @endsection
