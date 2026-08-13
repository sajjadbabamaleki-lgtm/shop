<!doctype html>
<html class="no-js" lang="fa" dir="rtl">

<head>
@include('partials.head')
</head>

<body class="shoe-shop vp-admin-body">

<main class="vp-admin-login">
    <div class="vp-shop-panel vp-track">
        <h1 class="vp-shop-title">ورود کارکنان</h1>
        <p class="vp-shop-count">این صفحه برای مدیران و کارکنان شعبه است، نه برای خرید.</p>

        @foreach ($errors->all() as $error)
            <p class="vp-note is-bad">{{ $error }}</p>
        @endforeach

        <form class="vp-checkout" method="post" action="{{ route('admin.login.store') }}">
            @csrf
            <div class="vp-field">
                <label for="lg-email">ایمیل</label>
                <input id="lg-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            </div>
            <div class="vp-field">
                <label for="lg-pass">رمز</label>
                <input id="lg-pass" type="password" name="password" required autocomplete="current-password">
            </div>
            <label class="vp-admin-remember"><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label>
            <button type="submit" class="vp-filter-apply vp-cart-go">ورود</button>
        </form>
    </div>
</main>

</body>

</html>
