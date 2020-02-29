@extends('layouts.public')

@section('public.content')

<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8" v-cloak>
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="mx-auto">
            <logo-default />
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
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <form action="{{ route('login') }}" method="POST">

                @csrf

                <div>
                    <form-input id="email" name="email" type="email" value="{{ old('email') }}" required label="Email address" />
                </div>

                <div class="mt-6">
                    <form-input id="password" name="password" type="password" required label="Password" />
                </div>

                <div class="mt-6 flex items-center justify-between">

                    <div>
                        <form-checkbox id="remember" name="remember" label="Remember me" />
                    </div>

                    <div class="text-sm leading-5">
                        <a href="{{ route('password.request') }}"
                            class="font-medium text-orange-600 hover:text-orange-500 focus:outline-none focus:underline transition ease-in-out duration-150">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <div class="mt-6">
                    <span class="block w-full rounded-md shadow-sm">
                        <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-500 focus:outline-none focus:orange-indigo-700 focus:shadow-outline-indigo active:bg-orange-700 transition duration-150 ease-in-out">
                            Sign in
                        </button>
                    </span>
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

                <div class="mt-3">
                    <span class="w-full inline-flex rounded-md shadow-sm">
                        <a href="{{ route('github.redirect', ['action' => 'login'])}}"
                            class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md bg-white text-sm leading-5 font-medium text-gray-500 hover:text-gray-400 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue transition duration-150 ease-in-out">
                            <svg class="h-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    {{-- <router-view :form-action="'{{ route('login') }}'" forgot-route="{{ route('password.request') }}"
    :errors="{{ $errors->toJson() }}"
    :old="{{ json_encode(Session::getOldInput()) }}" :app="{{ json_encode($app) }}">
    </router-view> --}}

    @endsection
