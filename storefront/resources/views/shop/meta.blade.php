{{--
    Canonical, Open Graph and schema.org, for one product.

    Included from a page's `meta` section; `$facts` is the array
    `App\Support\Seo\PageFacts` built, and `$canonical` the address this page
    should be indexed under.

    **The JSON-LD is the half that matters to an aggregator** — it is the only
    place on this page where a price is a number with a currency beside it
    rather than Persian digits with «تومان» after them. `JSON_UNESCAPED_UNICODE`
    so a Persian name stays readable in the source, and `JSON_UNESCAPED_SLASHES`
    so the URLs do too; neither changes what a parser reads.

    `</script>` cannot appear inside it — every string here comes from the
    catalogue and is encoded — but `JSON_HEX_TAG` makes that a fact rather than
    an assumption, because a product description is typed by a person in the
    panel.
--}}
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:type" content="product">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $facts['name'] ?? config('app.name') }}">
<meta property="og:description" content="{{ $facts['description'] ?? '' }}">
<meta property="og:url" content="{{ $canonical }}">
@if (! empty($facts['image'][0]))
    <meta property="og:image" content="{{ $facts['image'][0] }}">
@endif
<meta name="twitter:card" content="summary_large_image">

<script type="application/ld+json">
{!! json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>
