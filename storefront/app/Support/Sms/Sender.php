<?php

namespace App\Support\Sms;

/**
 * Something that can put a text message on a phone.
 *
 * One method and no provider in sight, because the provider is not decided
 * yet: the shop needs an account, a service line registered to the company and
 * an approved pattern before a single real message can be sent, and none of
 * that is code. The flow it feeds — `AccountController` — is finished and
 * working today against `LogSender`, and the day a key arrives the only thing
 * that changes is which class this resolves to.
 *
 * Implementations must not throw for an ordinary refusal by the provider —
 * a wrong number, no credit. Sign-in is what is on the other side of this
 * call, and a 500 in front of somebody trying to buy shoes is worse than a
 * message that did not arrive. Log it and return.
 *
 * **The message is given twice: once written out, once as its parts.** An
 * Iranian provider will not send an unapproved transactional text, so the real
 * senders do not post a sentence at all — they name a pattern registered with
 * the provider and hand it the values to drop into it. `$message` is the whole
 * sentence, for a driver that sends text; `$args` is the same information as
 * data, in the order the pattern expects, for a driver that sends a pattern.
 *
 * Both are passed so that no driver has to reconstruct one from the other. A
 * pattern sender digging a six-digit code back out of a Persian sentence with
 * a regular expression would work until somebody rewords the sentence, and
 * then it would send a blank code to a real telephone with nothing anywhere
 * going red — the exact shape of silent failure this shop keeps paying for.
 */
interface Sender
{
    /**
     * The shopper's one-time sign-in code. One value: the code.
     */
    public const CODE = 'code';

    /**
     * «somebody signed in to the panel», to the owner. Three values.
     */
    public const ALERT = 'alert';

    /**
     * **`$purpose` exists because a pattern is per-message, not per-shop.**
     *
     * A provider approves a *sentence* with numbered blanks in it, and this
     * shop sends two sentences with different numbers of blanks — a code has
     * one, the sign-in alert has three. One registered pattern cannot carry
     * both: sent through the code's pattern, the alert's three values fill one
     * blank and the message that arrives is wrong, with nothing going red. So
     * each message says which of its shop's patterns it is, and a driver that
     * sends patterns looks the id up per purpose. A driver that sends the
     * sentence ignores this entirely.
     *
     * @param  list<string>  $args  the message's own values, in the order the
     *                              provider's approved pattern expects them
     * @param  self::CODE|self::ALERT  $purpose  which of the shop's messages
     *                                           this is
     */
    public function send(string $phone, string $message, array $args = [], string $purpose = self::CODE): void;
}
