<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta http-equiv="X-UA-Compatible" content="ie=edge">

<title>Sigmie app</title>

<meta name="description" content="">
<meta name="keywords" content="elasticseach,cloud,infastructure,sigma,sigmie,search,php">
<meta name="author" content="nicoorfi@yahoo.com">

{{-- CSRF Token required by Laravel Echo --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@nicoorf">
<meta name="twitter:title" content="{{ config('app.name') }}">
<meta name="twitter:description" content="Awesome On-Site Search, running on your own Cloud infastructure.">
<meta name="twitter:image" content="{{ asset('img/twitter-card.png') }}">
<meta name="twitter:creator" content="@nicoorf">

{{-- Og --}}
<meta property="og:url" content="https://app.sigmie.com">
<meta property="og:type" content="article">
<meta property="og:title" content="{{ config('app.name') }}">
<meta property="og:description" content="Awesome On-Site Search, running on your own Cloud infastructure.">
<meta property="og:image" content="{{ asset('img/twitter-card.png') }}">

{{-- Favicon --}}
<link rel="apple-touch-icon" sizes="76x76" href="{{ asset('/apple-touch-icon.png')}}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('/favicon-32x32.png')}}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('/favicon-16x16.png')}}">
<link rel="manifest" href="{{ asset('/site.webmanifest') }}">
<link rel="mask-icon" href="{{ asset('/safari-pinned-tab.svg')}}" color="#5bbad5">
<meta name="msapplication-TileColor" content="#da532c">
<meta name="theme-color" content="#ffffff">

<title>{{ config('app.name', 'Laravel') }}</title>

<style>
    .gradient {
        background: linear-gradient(90deg, #d53369 0%, #daae51 100%);
    }
</style>

{{-- Fonts  --}}
<link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

{{-- Styles --}}
<link href="{{ mix('css/app.css') }}" rel="stylesheet">

@yield('head-js')

@yield('head-css')

<script>
    @yield('js-assign')
</script>
