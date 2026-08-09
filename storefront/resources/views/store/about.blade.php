@extends('layouts.store')
@section('title', 'درباره '.$tenant->storeName())

@section('content')
    <article class="pane" style="margin-top:14px;max-width:70ch">
        <h1>درباره {{ $tenant->storeName() }}</h1>
        @if($tenant->about)
            <p class="muted" style="white-space:pre-line">{{ $tenant->about }}</p>
        @else
            <p class="muted">این فروشگاه هنوز معرفی‌نامه‌ای ثبت نکرده است.</p>
        @endif

        @if($tenant->city || $tenant->province)
            <p class="faint">{{ collect([$tenant->province, $tenant->city])->filter()->join('، ') }}</p>
        @endif
    </article>
@endsection
