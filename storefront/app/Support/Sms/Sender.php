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
     * @param  list<string>  $args  the message's own values, in the order the
     *                              provider's approved pattern expects them
     */
    public function send(string $phone, string $message, array $args = []): void;
}
