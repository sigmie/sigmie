<script src="{{ asset('js/app.js') }}" defer></script>
<script src="{{ asset('js/manifest.js') }}" defer></script>
<script src="{{ asset('js/vendor.js') }}" defer></script>

@if(config('app.env') == 'local')
<script src="http://localhost:35729/livereload.js"></script>
@endif

@include('common.analytics')
