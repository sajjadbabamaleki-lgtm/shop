@extends('layouts.store')
@section('title', 'تماس با '.$tenant->storeName())

@section('content')
    <article class="pane" style="margin-top:14px;max-width:60ch">
        <h1>تماس با {{ $tenant->storeName() }}</h1>

        <dl>
            @if($tenant->phone)
                <dt class="faint">تلفن</dt><dd class="num">{{ $tenant->phone }}</dd>
            @endif
            @if($tenant->address)
                <dt class="faint">نشانی</dt><dd>{{ $tenant->address }}</dd>
            @endif
            @if($tenant instanceof \App\Domains\Marketplace\Models\Vendor && $tenant->instagram)
                <dt class="faint">اینستاگرام</dt><dd>{{ $tenant->instagram }}</dd>
            @endif
            @if($tenant instanceof \App\Domains\Franchise\Models\Franchise)
                <dt class="faint">ساعت کاری</dt><dd>{{ $tenant->setting('working_hours') ?? '—' }}</dd>
            @endif
        </dl>
    </article>
@endsection
