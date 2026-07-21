{{-- De-serialized font loading.

     These three bunny.net stylesheets used to be @import-ed inside app.css.
     That forced a render-blocking waterfall: the browser had to download AND
     parse app.css before it could even discover the font CSS, then fetch that,
     then the font files. On PageSpeed's throttled-mobile run (≈4x slower CPU,
     slow 4G) every serial hop is magnified, which is why mobile scored worst.

     Loading them as parallel <link>s in the <head> lets the font CSS fetch
     alongside app.css instead of after it. The @import was removed from
     app.css so nothing double-loads. Included in every <head> that ships
     app.css. Two preconnects are the documented pattern: the anonymous one
     warms the stylesheet request, the crossorigin one warms the CORS font
     fetches. --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
<link href="https://fonts.bunny.net/css?family=instrument-serif:400,400i&display=swap" rel="stylesheet">
<link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet">
