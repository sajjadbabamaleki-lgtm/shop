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
 */
interface Sender
{
    public function send(string $phone, string $message): void;
}
