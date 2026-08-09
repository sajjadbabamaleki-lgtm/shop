@extends('layouts.site')
@section('title', 'پنل من')

@section('content')
    <h1 style="margin-top:20px">پنل من</h1>

    <div class="grid grid--3">
        @foreach($tenants as $tenant)
            <a class="pane" href="{{ route('panel.'.$tenant->tenantKind().'.dashboard', $tenant) }}">
                <h3>{{ $tenant->storeName() }}</h3>
                <span class="tag">{{ $tenant->tenantKind() === 'vendor' ? 'فروشنده' : 'نمایندگی' }}</span>
                @unless($tenant->isStorefrontLive())
                    <span class="tag tag--warn">در انتظار تأیید</span>
                @endunless
            </a>
        @endforeach

        @if($isPlatformStaff)
            <a class="pane" href="{{ route('panel.platform.dashboard') }}">
                <h3>مدیریت پلتفرم</h3>
                <span class="tag">فروشندگان، کمیسیون، تسویه</span>
            </a>
        @endif
    </div>

    @if($tenants->isEmpty() && ! $isPlatformStaff)
        <p class="pane muted">هنوز به هیچ فروشگاهی دسترسی ندارید.</p>
    @endif
@endsection
