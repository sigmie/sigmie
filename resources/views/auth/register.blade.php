@extends('layouts.public')

@section('additional-js')
<script src="https://js.stripe.com/v3/" type="application/javascript"></script>
@endsection


@section('additional-css')
<style>
    .StripeElement {
        box-sizing: border-box;
        padding: 0.25rem 1rem 0.25rem 1rem;
        background-color: #f7fafc;
        border-radius: 0.25rem;
        line-height: 1.5;
        padding: 0.4rem 1rem 0.4rem 1rem;
    }

    .StripeElement--focus {
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
    }

    .StripeElement--invalid {
        background-color: 'red';
        border-color: #f7fafc;
    }

    .StripeElement--webkit-autofill {
        background-color: 'red';
        background-color: #f7fafc !important;
    }
</style>
@endsection()

@section('public.content')

<router-view :form-action="'{{ route('register') }}'" :terms-route="'{{ route('terms') }}'"
    :privacy-route="'{{ route('privacy') }}'" :errors="{{ $errors->toJson() }}"
    :old="{{ json_encode(Session::getOldInput()) }}" :app="{{ json_encode($app) }}">
</router-view>

@endsection
