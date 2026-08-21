<?php

namespace App\Support\Messaging;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The only thing that writes a conversation — §12.
 *
 * There are three ways a line gets into a thread: a customer writes one, a
 * member of staff writes one, and the application writes one about an order.
 * All three go through here, so `last_message_at` cannot be forgotten by one
 * of them and the inbox cannot end up sorted by a column that is sometimes
 * right.
 *
 * **Everything is one transaction.** A message row with no `last_message_at`
 * update is a message nobody's inbox will surface; an attachment row whose
 * file never landed is a link to nothing. Both are the kind of half-write that
 * looks fine until somebody goes looking for a message they were told about.
 *
 * **Attachments go on the private disk.** `Storage::disk('local')`, not
 * `public` — see `MessageAttachment`. The file is written before the row so a
 * row never points at a file that is not there; a file with no row is
 * recoverable rubbish, a row with no file is a broken page.
 */
class Thread
{
    /** What a customer may attach, and how much of it. §13. */
    public const ALLOWED = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public const MAX_KB = 5120;

    public const MAX_FILES = 3;

    /**
     * Start a thread, with its first message.
     *
     * The subject is the customer's or the order's; a thread with neither
     * would be a row in an inbox with nothing to read before opening it.
     */
    public function start(Customer $customer, string $subject, string $body, ?Order $order = null, array $files = []): Conversation
    {
        return DB::transaction(function () use ($customer, $subject, $body, $order, $files): Conversation {
            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'order_id' => $order?->id,
                'subject' => $subject,
                'status' => Conversation::OPEN,
            ]);

            // The participant row is made before the first message so the
            // cursor `say()` sets has somewhere to go; `say()` then moves it
            // to the line it just wrote, because writing is reading.
            $conversation->participantFor(Conversation::CUSTOMER, $customer->id);

            $this->say($conversation, Conversation::CUSTOMER, $customer->id, $body, $files);

            return $conversation->refresh();
        });
    }

    /**
     * Add a line to an existing thread.
     *
     * Re-opens a closed one. A customer replying to a thread somebody marked
     * «بسته» has something more to say, and leaving it closed means the reply
     * lands where nobody is looking.
     */
    public function say(Conversation $conversation, string $party, ?int $partyId, string $body, array $files = []): Message
    {
        return DB::transaction(function () use ($conversation, $party, $partyId, $body, $files): Message {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'party' => $party,
                'party_id' => $partyId,
                'body' => $body,
            ]);

            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $this->attach($message, $file);
                }
            }

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                // A system line does not re-open a thread: the shop marking a
                // parcel delivered is not somebody asking for an answer.
                'status' => $party === Conversation::SYSTEM ? $conversation->status : Conversation::OPEN,
            ])->save();

            // Writing is reading: whoever just spoke is caught up **to their
            // own line**, which is the id of the message they just wrote.
            if ($partyId !== null && $party !== Conversation::SYSTEM) {
                $conversation->participantFor($party, $partyId)->update([
                    'last_read_message_id' => $message->id,
                    'last_read_at' => now(),
                ]);
            }

            return $message;
        });
    }

    /** A line the application wrote about an order, in the order's own thread. */
    public function note(Conversation $conversation, string $body): Message
    {
        return $this->say($conversation, Conversation::SYSTEM, null, $body);
    }

    /**
     * Mark everything written so far as read by this person.
     *
     * «So far» is the id of the newest line, not the clock: a message written
     * while this request was in flight keeps its own id and stays unread,
     * which is the honest answer — it was not on the page they just read.
     */
    public function markRead(Conversation $conversation, string $party, int $partyId): void
    {
        $conversation->participantFor($party, $partyId)->update([
            'last_read_message_id' => $conversation->latestMessageId(),
            'last_read_at' => now(),
        ]);
    }

    /** A member of staff answering joins the thread by doing so. */
    public function reply(Conversation $conversation, User $user, string $body, array $files = []): Message
    {
        return $this->say($conversation, Conversation::STAFF, $user->id, $body, $files);
    }

    private function attach(Message $message, UploadedFile $file): void
    {
        // Both read **before** the move: `store()` relocates the temporary
        // file, and asking it its own size afterwards is asking about a path
        // that is no longer there.
        $size = (int) $file->getSize();

        // `getMimeType()` guesses from the file's own bytes.
        // `getClientMimeType()` is whatever the browser was told to say, which
        // is to say whatever the sender chose — it decides how the page treats
        // the file, so it is not the one to trust.
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');

        $path = $file->store('attachments', 'local');

        MessageAttachment::create([
            'message_id' => $message->id,
            'path' => $path,
            // The name the person gave it, kept for the download and never
            // used as a path: a filename from a form is somebody else's text,
            // and `store()` names the file itself.
            'name' => mb_substr($file->getClientOriginalName(), 0, 160),
            'mime' => $mime,
            'size' => $size,
        ]);
    }
}
