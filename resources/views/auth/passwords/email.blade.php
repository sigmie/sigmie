@extends('layouts.public')

@section('public.content')


@if($errors->any())
<div>
    <alert-danger class="mb-3 shadow" title="Whoops!" text="These credentials do not match our records" />
</div>
@endif

<div class="min-h-screen flex flex-col justify-center py-12 w-full" v-cloak>
    <container-white class="mx-auto py-4 w-full max-w-md">

        <form action="{{ route('password.email') }}" method="POST" class="flex w-full px-4">

            @csrf

            <div class="w-3/5 pr-3">
                <form-input placeholder="john@yahoo.com" id="email" name="email" type="email" value="{{ old('email') }}"
                    required />
            </div>

            <div class="w-2/5">
                <button-primary text="Send reset link" type="submit" />
            </div>
        </form>
    </container-white>
</div>
@endsection
