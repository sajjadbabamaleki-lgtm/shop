{{--
    The form both enquiry pages post.

    One partial rather than two copies: the wholesale page and the franchise
    page ask the same five questions, and the only difference is what two of
    the labels say. Two copies is how the two would end up disagreeing about
    whether the city is required.

    The fields are the checkout's own — `.vp-field`, `.vp-field-row` — so this
    reads as the same shop as the page somebody fills in to buy.
--}}
<form class="vp-checkout vp-enquiry" method="post" action="{{ $action }}">
    @csrf

    <div class="vp-field-row">
        <div class="vp-field">
            <label for="eq-name">نام و نام خانوادگی</label>
            <input id="eq-name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
        </div>
        <div class="vp-field">
            <label for="eq-phone">شماره موبایل</label>
            <input id="eq-phone" name="phone" value="{{ old('phone') }}" required maxlength="20" inputmode="tel" autocomplete="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹">
        </div>
    </div>

    <div class="vp-field-row">
        <div class="vp-field">
            <label for="eq-city">شهر</label>
            <input id="eq-city" name="city" value="{{ old('city') }}" maxlength="60">
        </div>
        <div class="vp-field">
            <label for="eq-org">{{ $organisationLabel }}</label>
            <input id="eq-org" name="organisation" value="{{ old('organisation') }}" maxlength="160">
        </div>
    </div>

    <div class="vp-field is-wide">
        <label for="eq-message">{{ $messageLabel }}</label>
        <textarea id="eq-message" name="message" rows="4" maxlength="1000">{{ old('message') }}</textarea>
    </div>

    <button type="submit" class="vp-filter-apply vp-cart-go">{{ $submit }}</button>
</form>
