@yield('tail-js')

@yield('tail-css')

@if(config('app.env') == 'local')
{{-- <script src="http://localhost:8098"></script> --}}
@routes

@endif

<script src="{{ mix('js/app.js') }}" defer></script>
<script src="{{ mix('js/manifest.js') }}" defer></script>
<script src="{{ mix('js/vendor.js') }}" defer></script>
