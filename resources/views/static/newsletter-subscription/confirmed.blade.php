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
                <h1 class="font-display font-semibold text-primary text-4xl sm:text-5xl mb-8 leading-none">Your
                    newsletter subscription has been confirmed.</h1>
                <p class="text-xl sm:text-2xl text-primary leading-normal"> Now you can close this page and return to
                    whatever you were doing, and i will keep you up to date with the Sigma project.
                </p>
            </div>
        </div>
    </main>

</div>
@endsection
