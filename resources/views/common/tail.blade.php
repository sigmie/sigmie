@yield('tail-js')

@yield('tail-css')

<script src="{{ mix('js/app.js') }}" defer></script>
<script src="{{ mix('js/manifest.js') }}" defer></script>
<script src="{{ mix('js/vendor.js') }}" defer></script>

@if(config('app.env') == 'local')

@routes

@endif
