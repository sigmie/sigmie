@extends('layouts.public')

@section('public.content')

<div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8" v-cloak>
    <div class="sm:mx-auto sm:w-full sm:max-w-md pb-4">
        <div class="mx-auto">
            <a href="{{ route("landing") }}">
                <logo-default></logo-default>
            </a>
        </div>

        @if($errors->any())
        <div class="pt-4">
            <alert-danger class="mb-3 shadow" title="Whoops!" text="These credentials do not match our records">
            </alert-danger>
        </div>
        @endif

        @if(Session::has('status'))
        <div class="pt-4">
            <alert-success class="mb-3 shadow" title="Nice!" text="Check your email for a link to reset your password">
            </alert-success>
        </div>
        @endif
    </div>

    <container-white class="mx-auto py-6 px-4 w-full max-w-md flex flex-col w-full">
        <password-form token="{{ $token }}" method="POST" action="{{ route('password.update') }}"></password-form>
    </container-white>
    @endsection
