@extends('layout')

@section('content')
<div class="h-screen flex overflow-hidden bg-gray-100">

    <sidebar ref="sidebar"> </sidebar>

    <div class="flex flex-col w-0 flex-1 overflow-hidden">

        <spinner ref="spinner"></spinner>

        <navbar v-cloak user-id={{ $user->id }} avatar-url="{{ $user ?? ''->avatar_url }}"></navbar>

        <main id="main" class="flex-1 relative z-0 overflow-y-auto py-6 focus:outline-none" tabindex="0">
            <bar ref="bar"></bar>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">

                @yield('app.content')
            </div>
        </main>
    </div>
</div>

@include('common.logout')

@endsection
