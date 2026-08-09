@extends('layouts.store')
@section('title', $tenant->storeName())

@section('content')
    <section class="pane" style="margin-top:14px">
        <h1>{{ $tenant->storefrontSettings()['tagline'] ?? 'به فروشگاه '.$tenant->storeName().' خوش آمدید' }}</h1>
        @if($tenant->about)
            <p class="muted" style="max-width:60ch">{{ \Illuminate\Support\Str::limit($tenant->about, 260) }}</p>
        @endif
        <a class="btn btn--gold" href="{{ route('store.catalog') }}">دیدن همه محصولات</a>
    </section>

    <section style="margin-top:28px">
        <div class="row row--between">
            <h2>تازه‌ها</h2>
            <a class="faint" href="{{ route('store.catalog') }}">همه</a>
        </div>

        @include('store.partials.product-grid', ['products' => $products, 'prices' => $prices])
    </section>
@endsection
