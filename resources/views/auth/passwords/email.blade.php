@extends('layouts.public')

@section('public.content')


<div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8" v-cloak>
    <div class="sm:mx-auto sm:w-full sm:max-w-md pb-6">
        <div class="mx-auto pb-2">
            <a href="{{ route("landing") }}">
                <logo-default />
            </a>
        </div>

        @if($errors->any())
        <div class="pt-4">
            <alert-danger class="shadow" title="Whoops!" text="These credentials do not match our records" />
        </div>
        @endif

        @if(Session::has('status'))
        <div class="pt-4">
            <alert-success class="shadow" title="Nice!"
                text="Check your email for a link to reset your password" />
        </div>
        @endif
    </div>

    <container-white class="mx-auto py-6 px-4 w-full max-w-md flex flex-col w-full">
        <form action="{{ route('password.email') }}" method="POST" class="flex flex-col w-full px-4">
            <span class="text-gray-500 pb-6">
                Enter the email associated with your account and you will get a link to reset your password.
            </span>
            @csrf
            <div class="pb-6">
                <form-input label="Email address" placeholder="john@yahoo.com" id="email" name="email" type="email"
                    value="{{ old('email') }}" required />
            </div>

            <div>
                <button-primary text="Send" type="submit" />
            </div>
        </form>
    </container-white>
</div>
@endsection
