<script src="{{ mix('js/app.js') }}" defer></script>
<script src="{{ mix('js/manifest.js') }}" defer></script>
<script src="{{ mix('js/vendor.js') }}" defer></script>

@if(config('app.env') == 'local')
<script src="http://localhost:35729/livereload.js"></script>
@endif

@include('common.analytics')
