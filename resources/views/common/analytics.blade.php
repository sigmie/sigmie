@if (Cookie::get('sigma_cookie_consent') === "1")
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-154938828-1"></script>
<script>
    window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'UA-154938828-1');
</script>
@endif
