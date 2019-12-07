    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    <style>
        .StripeElement {
            box-sizing: border-box;
            padding: 0.25rem 1rem 0.25rem 1rem;
            background-color: #edf2f7;
            border-radius: 0.25rem;
            line-height: 1.5;
            padding: 0.4rem 1rem 0.4rem 1rem;
        }

        .StripeElement--focus {
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }

        .StripeElement--invalid {
            background-color: 'red';
            border-color: #fa755a;
        }

        .StripeElement--webkit-autofill {
            background-color: 'red';
            background-color: #fefde5 !important;
        }
    </style>
