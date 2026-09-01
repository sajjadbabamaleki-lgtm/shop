<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductComment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Where a customer's comment is read before the shop prints it.
 *
 * The queue is half the feature and not an extra: `ProductCommentController`
 * writes every comment `PENDING` and nothing on the storefront publishes one,
 * so without this screen every sentence a customer writes sits in a table
 * nobody opens. That is the same failure as the enquiries next door, and it is
 * why both screens exist.
 *
 * **Platform-scoped, not branch-scoped.** A comment is about the shoe and the
 * shoe is the same shoe at every branch, so `product_comments` carries no
 * branch column and there is no tenant to resolve here.
 *
 * Waiting first, oldest first — somebody working through a queue wants the one
 * that has been waiting longest, and a comment nobody has read is the only
 * thing on this screen that is anybody's job.
 */
class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        if (! array_key_exists((string) $status, ProductComment::LABELS)) {
            $status = null;
        }

        return view('admin.comments', [
            'comments' => ProductComment::query()
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                /*
                 * Waiting first, then oldest first. Postgres sorts booleans
                 * false before true, so «is it waiting» descending is what
                 * puts the queue at the top — the same shape as the enquiry
                 * screen's own work list.
                 */
                ->orderByRaw('(status = ?) desc', [ProductComment::PENDING])
                ->orderBy('created_at')
                ->with(['customer', 'product'])
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'waiting' => ProductComment::query()->waiting()->count(),
        ]);
    }

    public function update(Request $request, ProductComment $comment): RedirectResponse
    {
        $input = $request->validate([
            'status' => ['required', Rule::in(array_keys(ProductComment::LABELS))],
        ]);

        $comment->update([
            'status' => $input['status'],
            // When the decision was made, and cleared again if it goes back
            // to the queue — so the column never claims somebody approved
            // something that is waiting to be read. The shop sorts its
            // published comments on this, so it is a date the page shows and
            // not only a record.
            'approved_at' => $input['status'] === ProductComment::PUBLISHED ? now() : null,
        ]);

        return back()->with('status', 'وضعیت نظر به‌روز شد.');
    }
}
