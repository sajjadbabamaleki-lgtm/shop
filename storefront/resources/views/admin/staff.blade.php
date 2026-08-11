@extends('layouts.admin')

{{--
    Who works here. Three rules the screen enforces and StaffController
    explains: only branch roles can be granted, nobody edits their own row, and
    an account is never deleted — a person is removed from a shop, and their
    name is still on last month's orders.
--}}

@section('content')
@if (session('password'))
    <div class="vp-note is-good">
        رمز این حساب فقط همین یک بار نشان داده می‌شود:
        <code class="vp-password">{{ session('password') }}</code>
    </div>
@endif

<section class="vp-shop-panel">
    <h1 class="vp-shop-title">کارکنان {{ $branch->name }}</h1>

    @if ($staff->isEmpty())
        <p class="vp-shop-empty">هنوز کسی به این شعبه وصل نیست.</p>
    @else
        <table class="vp-admin-table is-wide">
            <thead><tr><th>نام</th><th>ایمیل</th><th>نقش</th><th></th></tr></thead>
            <tbody>
            @foreach ($staff as $membership)
                @php $isMe = $membership->user_id === auth()->id(); @endphp
                <tr>
                    <td>
                        {{ $membership->user?->name }}
                        @if ($isMe)<span class="vp-cart-meta">(خودت)</span>@endif
                    </td>
                    <td>{{ $membership->user?->email }}</td>
                    <td>
                        @if ($isMe)
                            {{ $membership->role?->name }}
                        @else
                            <form class="vp-admin-inline" method="post" action="{{ route('admin.staff.update', $membership) }}">
                                @csrf
                                <select name="role" class="vp-admin-pick">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->slug }}" @selected($membership->role_id === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="vp-admin-save">ثبت</button>
                            </form>
                        @endif
                    </td>
                    <td>
                        @unless ($isMe)
                            <form method="post" action="{{ route('admin.staff.remove', $membership) }}">
                                @csrf
                                <button type="submit" class="vp-admin-drop">حذف از شعبه</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>

<section class="vp-shop-panel vp-track">
    <h2 class="vp-shop-title">افزودن کارمند</h2>
    <p class="vp-shop-count">اگر این ایمیل قبلاً حساب داشته باشد، همان حساب به این شعبه وصل می‌شود.</p>

    <form class="vp-checkout" method="post" action="{{ route('admin.staff.store') }}">
        @csrf
        <div class="vp-field">
            <label for="st-name">نام</label>
            <input id="st-name" name="name" value="{{ old('name') }}" required maxlength="120">
        </div>
        <div class="vp-field">
            <label for="st-email">ایمیل</label>
            <input id="st-email" type="email" name="email" value="{{ old('email') }}" required maxlength="160">
        </div>
        <div class="vp-field">
            <label for="st-role">نقش</label>
            <select id="st-role" name="role" class="vp-admin-pick">
                @foreach ($roles as $role)
                    <option value="{{ $role->slug }}">{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="vp-filter-apply vp-cart-go">افزودن</button>
    </form>
</section>
@endsection
