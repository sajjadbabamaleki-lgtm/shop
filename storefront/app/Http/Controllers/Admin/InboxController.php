<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Support\Messaging\Thread;
use App\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The shop's half of §11 — «پیام‌ها», the inbox.
 *
 * No branch condition anywhere in this file. Conversation is branch-scoped, so
 * a manager in Shiraz who types a Tehran thread's id into the address gets a
 * 404 from the model layer, the same way an order does — and the same way it
 * has to be here, because a thread carries a customer's telephone number and
 * whatever they attached.
 *
 * **Opening a thread marks it read for the person who opened it, not for
 * everybody.** The cursor is on the participant row, so two members of staff
 * working the same inbox each see what they personally have not read. A single
 * flag on the conversation would mean the second person is told there is
 * nothing new because the first one looked.
 */
class InboxController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $user = $request->user();

        $conversations = Conversation::query()
            ->with(['customer', 'order', 'participants'])
            ->when($q !== '', function (Builder $builder) use ($q): void {
                // Folded on both sides, like every other search here.
                $needle = '%'.fold_persian($q).'%';

                $builder->where(function (Builder $inner) use ($needle): void {
                    $inner->where(Search::fold('subject'), 'ilike', $needle)
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where(Search::fold('name'), 'ilike', $needle)
                            ->orWhere(Search::fold('phone'), 'ilike', $needle))
                        ->orWhereHas('order', fn (Builder $o) => $o->where(Search::fold('number'), 'ilike', $needle));
                });
            })
            ->when(in_array($status, [Conversation::OPEN, Conversation::CLOSED], true),
                fn (Builder $b) => $b->where('status', $status))
            ->when($request->query('unread') === '1',
                fn (Builder $b) => $b->unreadByStaff($user->id))
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.inbox', [
            'conversations' => $conversations,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'unread' => $request->query('unread') === '1',
            ],
            'unreadTotal' => Conversation::unreadByStaff($user->id)->count(),
        ]);
    }

    public function show(Request $request, Conversation $conversation, Thread $thread): View
    {
        $thread->markRead($conversation, Conversation::STAFF, $request->user()->id);

        return view('admin.conversation', [
            'conversation' => $conversation->load(['messages.attachments', 'customer', 'order']),
        ]);
    }

    public function reply(Request $request, Conversation $conversation, Thread $thread): RedirectResponse
    {
        $input = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:'.Thread::MAX_FILES],
            'files.*' => ['file', 'mimes:'.implode(',', Thread::ALLOWED), 'max:'.Thread::MAX_KB],
        ], [], ['body' => 'متن پاسخ', 'files' => 'پیوست', 'files.*' => 'پیوست']);

        $thread->reply($conversation, $request->user(), $input['body'], $request->file('files') ?? []);

        return redirect()->route('admin.conversation', $conversation)->with('status', 'پاسخ فرستاده شد.');
    }

    /**
     * Close a thread, or open it again.
     *
     * Closing is not deleting and not hiding: the thread stays, the customer
     * can still read it, and a reply from them re-opens it — `Thread::say()`
     * does that, so a question asked after «بسته شد» lands somewhere somebody
     * is looking rather than in a folder nobody opens.
     */
    public function status(Request $request, Conversation $conversation): RedirectResponse
    {
        $to = $request->validate([
            'status' => ['required', Rule::in([Conversation::OPEN, Conversation::CLOSED])],
        ])['status'];

        $conversation->update(['status' => $to]);

        return redirect()->route('admin.conversation', $conversation)
            ->with('status', $to === Conversation::CLOSED ? 'گفت‌وگو بسته شد.' : 'گفت‌وگو دوباره باز شد.');
    }

    /** Streamed from the private disk; the branch scope is the check. */
    public function file(Conversation $conversation, MessageAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->message->conversation_id === $conversation->id, 404);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }
}
