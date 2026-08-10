<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A shopper. One account across the main store, every franchise storefront and
 * the marketplace (spec §21) — which is why there is no branch column here.
 * What is per-branch is the cart and the order, and those point at this row.
 */
class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'phone', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Phone numbers are stored in one shape so that uniqueness means
     * something. 0912 345 6789, +98 912 345 6789 and 0098…, which a customer
     * will type interchangeably, are all one account.
     *
     * Iranian mobile numbers are 09xxxxxxxxx: eleven digits, leading zero.
     * Persian and Arabic-Indic digits are folded to ASCII first, because a
     * form filled on a Persian keyboard sends ۰۹۱۲…, and a number that does
     * not fold is a duplicate account nobody can explain.
     */
    public static function normalisePhone(string $phone): string
    {
        $digits = strtr($phone, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        // Strip the country code in either of the forms it arrives in.
        $digits = preg_replace('/^(?:0098|98)(9\d{9})$/', '$1', $digits);

        // A bare 9xxxxxxxxx gets its leading zero back.
        return preg_match('/^9\d{9}$/', $digits) ? '0'.$digits : $digits;
    }

    /**
     * Written through the attribute so nothing can store an unnormalised
     * number by going around the helper.
     */
    public function setPhoneAttribute(string $value): void
    {
        $this->attributes['phone'] = self::normalisePhone($value);
    }
}
