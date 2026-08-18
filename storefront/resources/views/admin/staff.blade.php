@extends('layouts.admin')

@section('title', 'کارکنان')

{{--
    Who works here. Three rules the screen enforces and StaffController
    explains: only branch roles can be granted, nobody edits their own row, and
    an account is never deleted — a person is removed from a shop, and their
    name is still on last month's orders.
--}}

@section('content')
@if (session('password'))
    <p class="vp-adm-note is-good">
        رمز این حساب فقط همین یک بار نشان داده می‌شود:
        <span class="vp-adm-pw">{{ session('password') }}</span>
    </p>
@endif

<div class="vp-adm-stack">

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">کارکنان {{ $branch->name }}</h2>
        </div>

        @if ($staff->isEmpty())
            <p class="vp-adm-empty">هنوز کسی به این شعبه وصل نیست.</p>
        @else
            <table class="vp-admin-table">
                <thead><tr><th>نام</th><th>ایمیل</th><th>نقش</th><th></th></tr></thead>
                <tbody>
                @foreach ($staff as $membership)
                    @php $isMe = $membership->user_id === auth()->id(); @endphp
                    <tr>
                        <td>
                            {{ $membership->user?->name }}
                            @if ($isMe)<span class="vp-adm-sub">خودت</span>@endif
                        </td>
                        <td><bdi dir="ltr">{{ $membership->user?->email }}</bdi></td>
                        <td>
                            @if ($isMe)
                                {{-- Nobody changes their own role: it is the one edit that
                                     can lock the last manager out of their own shop. --}}
                                {{ $membership->role?->name }}
                            @else
                                <form class="vp-adm-inline" method="post" action="{{ route('admin.staff.update', $membership) }}">
                                    @csrf
                                    <label class="visually-hidden" for="vp-role-{{ $membership->id }}">نقش</label>
                                    <select id="vp-role-{{ $membership->id }}" name="role" class="vp-adm-cell">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->slug }}" @selected($membership->role_id === $role->id)>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="vp-adm-mini">ثبت</button>
                                </form>
                            @endif
                        </td>
                        <td>
                            @unless ($isMe)
                                <form method="post" action="{{ route('admin.staff.remove', $membership) }}">
                                    @csrf
                                    <button type="submit" class="vp-adm-mini is-bad">حذف از شعبه</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">افزودن کارمند</h2>
        </div>

        <p class="vp-adm-empty">اگر این ایمیل قبلاً حساب داشته باشد، همان حساب به این شعبه وصل می‌شود.</p>

        <form class="vp-adm-form" method="post" action="{{ route('admin.staff.store') }}">
            @csrf

            <div class="vp-adm-form-row">
                <div class="vp-adm-form">
                    <label for="sf-name">نام</label>
                    <input id="sf-name" name="name" value="{{ old('name') }}" required maxlength="120">
                </div>
                <div class="vp-adm-form">
                    <label for="sf-email">ایمیل</label>
                    <input id="sf-email" type="email" name="email" value="{{ old('email') }}" required maxlength="160" dir="ltr">
                </div>
            </div>

            <label for="sf-role">نقش</label>
            <select id="sf-role" name="role">
                @foreach ($roles as $role)
                    <option value="{{ $role->slug }}">{{ $role->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="vp-adm-apply">افزودن</button>
        </form>
    </section>
</div>
@endsection
