@extends('layouts.admin')

@section('title', 'جست‌وجو')

{{--
    §14's results.

    Groups rather than one mixed list: «VP-100294» and «نیوبالانس ۵۳۰» are not
    the same kind of answer, and ranking them against each other would mean
    inventing a score nobody asked for. Each group shows the first few and says
    nothing about how many more there are — a count on a truncated list invites
    somebody to believe the number is the answer.
--}}

@section('content')
<form class="vp-adm-filters" method="get" action="{{ route('admin.search') }}" role="search">
    <label class="visually-hidden" for="vp-search-q">جست‌وجو در پنل</label>
    <input id="vp-search-q" class="vp-adm-search" type="search" name="q" value="{{ $q }}"
           placeholder="شماره سفارش، نام محصول، کد کالا، تلفن مشتری یا کد تخفیف" autofocus>
    <button type="submit" class="vp-adm-apply">جست‌وجو</button>
</form>

@if (! $enough)
    <section class="vp-adm-card">
        <p class="vp-adm-empty">
            @if ($q === '')
                چیزی بنویس تا در سفارش‌ها، محصولات، مشتری‌ها و کدهای تخفیف بگردم.
            @else
                دست‌کم دو حرف لازم است.
            @endif
        </p>
    </section>
@elseif ($groups === [])
    <section class="vp-adm-card">
        <p class="vp-adm-empty">چیزی با «{{ $q }}» پیدا نشد.</p>
    </section>
@else
    <div class="vp-adm-grid">
        @foreach ($groups as $group)
            <section class="vp-adm-card">
                <div class="vp-adm-card-head">
                    <h2 class="vp-adm-card-title">{{ $group['label'] }}</h2>
                </div>

                <ul class="vp-adm-list">
                    @foreach ($group['rows'] as $row)
                        <li>
                            <a href="{{ $row['url'] }}">{{ $row['title'] }}</a>
                            <span class="vp-adm-sub">{{ $row['note'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
@endsection
