<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * The saved products — «لیست علاقمندی».
     *
     * No branch column here either, and for the same reason the class comment
     * gives about the account itself: what somebody likes is theirs, not the
     * storefront's they were standing in when they saved it.
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * The same list as the products themselves, newest saved first.
     *
     * Ordered by the pivot's own `created_at` rather than the product's, so the
     * list reads in the order things were saved — which is the order the
     * shopper remembers putting them in.
     */
    public function wishlistProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlist_items')
            ->withTimestamps()
            ->orderByPivot('created_at', 'desc');
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
        $digits = preg_replace('/\D+/', '', latin_digits($phone)) ?? '';

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
