<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A one-time code, sent to a phone number.
 *
 * Everything about how long it lives and how many guesses it survives is here
 * rather than spread through the controller, so the answer to "how long is a
 * code good for" is one place and not three.
 */
class LoginCode extends Model
{
    /** Digits in a code. Five is what Iranian shops send and what fits a glance. */
    public const LENGTH = 5;

    /** How long a code is good for. */
    public const LIVES_FOR_SECONDS = 120;

    /** How long before the same number may ask for another. */
    public const RESEND_AFTER_SECONDS = 90;

    /** Wrong guesses before a code is spent. */
    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['phone', 'code_hash', 'expires_at', 'attempts', 'consumed_at', 'ip'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * A code that has not been used, has not expired, and has guesses left.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    /**
     * Five digits, never starting the string with a zero-padded look-alike:
     * `random_int` over the whole range and then padded, so 00042 is as likely
     * as 99999 and the code is uniform. `random_int` rather than `rand` because
     * this is a credential.
     */
    public static function freshCode(): string
    {
        return str_pad(
            (string) random_int(0, (10 ** self::LENGTH) - 1),
            self::LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }

    public function isSpent(): bool
    {
        return $this->consumed_at !== null
            || $this->expires_at->isPast()
            || $this->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * When this number may ask for another code, or null if it may now.
     */
    public static function nextSendAllowedAt(string $phone): ?Carbon
    {
        $last = self::where('phone', $phone)->latest('id')->first();

        if ($last === null) {
            return null;
        }

        $ready = $last->created_at->addSeconds(self::RESEND_AFTER_SECONDS);

        return $ready->isFuture() ? $ready : null;
    }
}
