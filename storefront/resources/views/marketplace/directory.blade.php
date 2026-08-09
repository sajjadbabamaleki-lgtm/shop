@extends('layouts.site')
@section('title', 'فروشندگان')

@section('content')
    <h1 style="margin-top:20px">فروشندگان ویکی‌پلاس</h1>

    <form method="get" class="pane pane--tight row">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="نام فروشگاه">
        <button class="btn btn--gold">بگرد</button>
    </form>

    <div class="grid grid--3" style="margin-top:18px">
        @forelse($vendors as $vendor)
            {{-- The one direction that exists: the parent links out to the mini
                 store. Nothing on the mini store links back. --}}
            <a class="pane pane--tight" href="{{ \App\Domains\Tenancy\StoreUrl::home($vendor) }}">
                <h3>{{ $vendor->name }}</h3>
                <span class="faint">{{ collect([$vendor->province, $vendor->city])->filter()->join('، ') ?: '—' }}</span>
                <div class="faint num">{{ $vendor->offers_count }} محصول</div>
            </a>
        @empty
            <p class="pane muted">فروشنده‌ای یافت نشد.</p>
        @endforelse
    </div>

    {{ $vendors->links() }}
@endsection
