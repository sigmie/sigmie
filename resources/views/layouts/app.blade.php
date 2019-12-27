@extends('layout')

@section('content')


<div class="flex h-full bg-primary-background">

    <div class="w-64 bg-10 bg-primary min-h-full inline-block w-92">
        @include('common.sidebar')
    </div>

    <div class="inline-block flex-grow">

        <nav class="top-0 text-gray-500 shadow bg-white inline-block w-full">
            @include('common.navbar')
        </nav>

        <main class="min-w-full container px-4 inline-block">
            @yield('app.content')
        </main>
    </div>
</div>
@endsection
