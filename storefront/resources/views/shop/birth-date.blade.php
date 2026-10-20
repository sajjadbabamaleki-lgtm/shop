{{--
    A birth date, as three Persian selects: day, month, year.

    `$birth` is the stored date or null. What is posted is three numbers;
    `App\Support\Calendar\BirthDate` reads them back into one date and refuses
    a day that does not exist, ۳۱ اسفند included.
--}}
@php
    [$by, $bm, $bd] = $birth ? \App\Support\Calendar\Jalali::parts($birth) : [null, null, null];
    $by = (int) old('birth_year', $by);
    $bm = (int) old('birth_month', $bm);
    $bd = (int) old('birth_day', $bd);
    $thisYear = \App\Support\Calendar\Jalali::thisYear();
@endphp
<fieldset class="vp-birth">
    <legend class="vp-birth-legend">تاریخ تولد</legend>
    <div class="vp-birth-row">
        <select name="birth_day" aria-label="روز تولد" @if ($birthRequired ?? false) required @endif>
            <option value="">روز</option>
            @for ($d = 1; $d <= 31; $d++)
                <option value="{{ $d }}" @selected($bd === $d)>{{ fa_number($d) }}</option>
            @endfor
        </select>
        <select name="birth_month" aria-label="ماه تولد" @if ($birthRequired ?? false) required @endif>
            <option value="">ماه</option>
            @foreach (\App\Support\Calendar\Jalali::MONTHS as $m => $monthName)
                <option value="{{ $m }}" @selected($bm === $m)>{{ $monthName }}</option>
            @endforeach
        </select>
        <select name="birth_year" aria-label="سال تولد" @if ($birthRequired ?? false) required @endif>
            <option value="">سال</option>
            @for ($y = $thisYear; $y >= 1300; $y--)
                <option value="{{ $y }}" @selected($by === $y)>{{ strtr((string) $y, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']) }}</option>
            @endfor
        </select>
    </div>
</fieldset>
