@extends('layouts.site')
@section('title', 'ورود')

@section('content')
    <form method="post" action="{{ route('login') }}" class="pane" style="max-width:420px;margin:40px auto">
        @csrf
        <h1>ورود</h1>

        @if($errors->any())
            <div class="notice notice--bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        <div class="field">
            <label for="email">ایمیل</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="field">
            <label for="password">گذرواژه</label>
            <input id="password" type="password" name="password" required>
        </div>
        <label class="row" style="gap:6px"><input type="checkbox" name="remember" value="1" style="width:auto"> مرا به خاطر بسپار</label>

        <button class="btn btn--gold" style="margin-top:12px">ورود</button>
        <p class="faint" style="margin-top:14px">
            فروشنده‌اید و هنوز حساب ندارید؟ <a href="{{ route('vendors.register') }}">ثبت‌نام فروشنده</a>
        </p>
    </form>
@endsection
