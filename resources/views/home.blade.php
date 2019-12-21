@extends('layouts.app')

@section('content')
<div class="container px-12 pt-6">
    <div class="shadow w-full bg-white mx-auto py-3">
        <modal-simple />
        <div>
            <illustration-hologram height="300px" width="300px" />
        </div>
        <div class="text-center">
            <h2 class="text-gray-700 font-semibold">
                Welcome!
            </h2>
            <h3 class="text-gray-700">
                You are on the sigma starting page.
            </h3>
        </div>
    </div>
</div>
@endsection
