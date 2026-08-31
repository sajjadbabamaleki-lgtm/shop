<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Sms\Sender;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A text message every time somebody signs in to the panel.
 *
 * «میخوام یه سیستمی باشه وقتی هرکسی با هر عنوانی وارد پنل ادمین میشه یه اس ام
 * اس برای این شماره بره».
 *
 * **On the event, not in the controller.** `SessionController@store` is one
 * way into the panel and not the only one: a remember-me cookie signs somebody
 * in with no form posted at all, and a future console or SSO path would too.
 * `Illuminate\Auth\Events\Login` is what every one of them ends at, so this
 * cannot be walked around by adding a second door.
 *
 * **Staff only.** Shoppers have their own guard — `customer` — and every one
 * of them signing in is not what was asked for and would be hundreds of
 * messages a day. The guard name is the filter, and it is the same distinction
 * that keeps a shopper's session from satisfying a staff permission check.
 */
class TellTheOwnerSomebodySignedIn
{
    /**
     * How long the same person's second sign-in stays quiet.
     *
     * One act of signing in is one event, so this is not normally reached. It
     * is here for the cases that are not one act: a tab restored beside a
     * fresh login, a remember-me cookie landing at the same moment as a form
     * post. Short on purpose — two minutes swallows a duplicate and cannot
     * hide a second person arriving.
     */
    // Public so `sms:test` can say the number out loud rather than printing a
    // second copy of it that drifts.
    public const QUIET_SECONDS = 120;

    /**
     * **Nothing is injected, and that is the point.**
     *
     * This took `Sender` in its constructor, which defeated the whole of the
     * `try` below: `SmsServiceProvider` refuses to build a sender at all when
     * the driver is «log» in production, so the listener could not be
     * constructed, so the throw happened *before* anything could catch it —
     * and a staff sign-in 500d on a shop whose only fault was not having an
     * SMS account yet. Worse, it did so even with `SMS_ALERT_TO` empty, which
     * is meant to switch this feature off entirely.
     *
     * The sender is asked for inside the `try`, where a refusal to build one
     * is the same kind of event as a gateway timing out, and is handled the
     * same way.
     */
    public function __construct() {}

    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $to = trim((string) config('services.sms.alert_to'));

        if ($to === '') {
            return;
        }

        $user = $event->user;

        if (! Cache::add("signin-alert:{$user->getAuthIdentifier()}", true, self::QUIET_SECONDS)) {
            return;
        }

        $title = $this->title($user);
        $when = fa_date(now(), true);

        $message = "{$title} «{$user->name}» وارد پنل مدیریت ویکی پلاس شد.\n{$when}";

        try {
            // The parts as well as the sentence, because a provider that sends
            // an approved pattern rather than free text needs them in order —
            // see the Sender contract. Today's driver sends the sentence.
            app(Sender::class)->send($to, $message, [$title, (string) $user->name, $when], Sender::ALERT);
        } catch (Throwable $e) {
            // **A sign-in must never fail because a text message did.** The
            // Sender contract already says an ordinary refusal must not throw,
            // but a DNS failure or a timeout is not an ordinary refusal, and
            // locking the shop's staff out of their own panel because an SMS
            // gateway is down would be a far worse outage than a missing
            // notification. It goes in the log, where `sms:test` looks.
            Log::warning('The sign-in alert could not be sent.', [
                'user' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * What to call them, in the client's own words.
     *
     * The roles carry Persian names already — «مالک شرکت», «مدیر ارشد» — so
     * the message says which of them arrived rather than only a name. A
     * platform role first, because that is the one that describes the person
     * across the whole shop; then a branch role, which is «مدیر شعبه شیراز»
     * sort of information and still better than nothing; then a plain word,
     * because an account with no role at all can still reach the login form.
     */
    private function title(User $user): string
    {
        $platform = $user->roles->first();

        if ($platform !== null) {
            return $platform->name;
        }

        $branch = $user->branchRoles()->with('role')->first();

        return $branch?->role?->name ?? 'کاربر پنل';
    }
}
