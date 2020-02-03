@if(config('app.env') == 'local')
<script src="http://localhost:35729/livereload.js"></script>
<script src="{{ mix('js/app.js') }}" defer></script>
<script src="{{ mix('js/manifest.js') }}" defer></script>
<script src="{{ mix('js/vendor.js') }}" defer></script>
@else
<script src="{{ asset('js/app.js') }}" defer></script>
<script src="{{ asset('js/manifest.js') }}" defer></script>
<script src="{{ asset('js/vendor.js') }}" defer></script>
@endif

@include('common.analytics')
