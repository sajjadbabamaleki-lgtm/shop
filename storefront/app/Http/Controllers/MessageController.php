<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Support\Messaging\Thread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The customer's half of §11 — «پیام‌های من».
 *
 * Behind `auth:customer`, and that is not the same standard the order page
 * holds itself to. An order is reachable with the number and the telephone it
 * was placed under, because somebody who has just bought something should not
 * have to make an account to see where it is. A thread is different: it is a
 * history, it can carry photographs of a house or a receipt, and it goes on
 * for as long as the shop keeps it. A session flag proving one order is not
 * enough to open all of that.
 *
 * Every route resolves the conversation **through the signed-in customer's own
 * relation**, never by id alone, so editing the number in the address finds
 * nothing rather than somebody else's conversation.
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        $conversations = Conversation::where('customer_id', $customer->id)
            ->with(['order', 'participants'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return view('shop.messages', [
            'conversations' => $conversations,
            'customer' => $customer,
        ]);
    }

    public function show(Request $request, Conversation $conversation, Thread $thread): View
    {
        $this->mustBeTheirs($request, $conversation);

        $thread->markRead($conversation, Conversation::CUSTOMER, $request->user('customer')->id);

        return view('shop.message', [
            'conversation' => $conversation->load(['messages.attachments', 'order']),
        ]);
    }

    /**
     * Start a thread.
     *
     * The order, when there is one, is looked up through the branch-scoped
     * model **and** checked against this customer: `order_id` arrives in a
     * form, and a form field is a number somebody can change.
     */
    public function store(Request $request, Thread $thread): RedirectResponse
    {
        $customer = $request->user('customer');

        $input = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'order' => ['nullable', 'string', 'max:40'],
            'files' => ['nullable', 'array', 'max:'.Thread::MAX_FILES],
            'files.*' => ['file', 'mimes:'.implode(',', Thread::ALLOWED), 'max:'.Thread::MAX_KB],
        ], [], $this->names());

        $order = null;

        if (($input['order'] ?? '') !== '') {
            $order = Order::where('number', $input['order'])
                ->where('customer_id', $customer->id)
                ->first();

            if ($order === null) {
                return back()->withInput()->withErrors(['order' => 'سفارشی با این شماره برای شما پیدا نشد.']);
            }
        }

        $conversation = $thread->start(
            $customer,
            $input['subject'],
            $input['body'],
            $order,
            $request->file('files') ?? []
        );

        return redirect()
            ->to(storefront_route('account.message', $conversation))
            ->with('status', 'پیامت فرستاده شد. پاسخ همین‌جا می‌آید.');
    }

    public function reply(Request $request, Conversation $conversation, Thread $thread): RedirectResponse
    {
        $this->mustBeTheirs($request, $conversation);

        $input = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:'.Thread::MAX_FILES],
            'files.*' => ['file', 'mimes:'.implode(',', Thread::ALLOWED), 'max:'.Thread::MAX_KB],
        ], [], $this->names());

        $thread->say(
            $conversation,
            Conversation::CUSTOMER,
            $request->user('customer')->id,
            $input['body'],
            $request->file('files') ?? []
        );

        return redirect()->to(storefront_route('account.message', $conversation));
    }

    /**
     * A file, streamed rather than linked.
     *
     * It lives on the private disk, so there is no URL that serves it without
     * coming through here — and here checks that the person asking is in the
     * thread the file was attached to.
     */
    public function file(Request $request, Conversation $conversation, MessageAttachment $attachment): StreamedResponse
    {
        $this->mustBeTheirs($request, $conversation);

        abort_unless($attachment->message->conversation_id === $conversation->id, 404);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    /** This customer's own thread, or nothing. */
    private function mustBeTheirs(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->customer_id === $request->user('customer')?->id, 404);
    }

    /** @return array<string, string> */
    private function names(): array
    {
        return ['subject' => 'موضوع', 'body' => 'متن پیام', 'files' => 'پیوست', 'files.*' => 'پیوست', 'order' => 'شماره سفارش'];
    }
}
