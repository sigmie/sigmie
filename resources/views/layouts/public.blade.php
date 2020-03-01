@extends('layout')

@section('content')

<div class="min-h-full relative pb-20">

    <main class="mx-auto container m-0 w-256 max-w-full">

        @yield('public.content')

    </main>


    <footer class="bottom-0 absolute text-sm w-full h-20">
        @include('common.footer')
    </footer>

</div>
@endsection
