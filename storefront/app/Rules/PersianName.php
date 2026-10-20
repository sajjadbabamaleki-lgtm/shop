<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A person's name, in Persian letters only.
 *
 * «امکان نوشتن اسم به انگلیسی تغییر کنه فقط فارسی بتونن بنویسن». The name is
 * printed on the address label a courier reads and on the invoice, and it is
 * what a promotional text message would greet somebody with.
 *
 * Arabic-script letters (U+0600–U+06FF, which carries ی/ي and ک/ك both, so no
 * keyboard is refused), the zero-width non-joiner of «نیم‌فاصله», spaces and
 * nothing else — no Latin letters, no digits, no punctuation.
 */
class PersianName implements ValidationRule
{
    public const PATTERN = '/^[\x{0600}-\x{06FF}\x{200C}\s]+$/u';

    /** The same rule for the browser's `pattern` attribute, so a form says so before it posts. */
    public const HTML_PATTERN = '[؀-ۿ‌\s]+';

    public const MESSAGE = 'نام را فقط با حروف فارسی بنویس.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, trim($value)) || preg_match('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $value)) {
            $fail(self::MESSAGE);
        }
    }
}
