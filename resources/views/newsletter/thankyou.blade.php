@extends('layout')

@section('content')

<div class="min-h-full relative pb-20"
    style="background-image: linear-gradient(30deg, #AEA9EF 8%, #CEC3EE 51%, #F5EAF3 100%);">

    <main class="mx-auto container">

        <a href="{{ route('landing') }}" class="float-right mt-10 cursor-pointer mr-10 sm:mr-10 md:mr-0 lg:mr-0">
            <icon-x height="25px" />
        </a>

        <div class="max-w-3xl mx-auto px-6 py-8 sm:py-16 md:py-24">
            <div class="mt-20">
                <h1 class="font-display font-semibold text-primary text-4xl sm:text-5xl mb-8 leading-none">Thanks! Now
                    check your email.</h1>
                <p class="text-xl sm:text-2xl text-primary leading-normal"> You should get a confirmation email soon,
                    open it up and <strong class="text-primary font-bold">click the confirm email button</strong> so we
                    can keep you up to date. </p>
            </div>
        </div>
    </main>

</div>
@endsection
