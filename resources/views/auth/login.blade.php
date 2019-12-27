@extends('layouts.public')

@section('public.content')

<router-view :form-action="'{{ route('login') }}'" forgot-route="{{ route('password.request') }}" :errors="{{ $errors->toJson() }}"
    :old="{{ json_encode(Session::getOldInput()) }}" :app="{{ json_encode($app) }}">
</router-view>

@endsection
