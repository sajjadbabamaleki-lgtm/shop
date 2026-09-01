<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * What a customer thought of a shoe.
 *
 * «همچنین یه جایی برای کامنت های مرتبط با اون کفش میخوایم», open to «فقط کسی
 * که خریده».
 *
 * So there are two gates and they are different questions. `auth:customer` on
 * the route says somebody is signed in; `boughtIt()` says this account has
 * actually owned this shoe. Neither implies the other, and the second is the
 * one the client asked for.
 *
 * Nothing written here reaches the shop. Every comment is stored `PENDING` and
 * waits for `/admin/comments` — a form that publishes on submission is a form
 * that publishes whatever somebody types into it, and this one sits on a page
 * every visitor can read.
 */
class ProductCommentController extends Controller
{
    /**
     * Statuses in which the shoe is really theirs.
     *
     * Not `PLACED`: an unpaid order is an intention, and a basket somebody
     * filled in and abandoned would otherwise buy the right to write on the
     * page. Not `CANCELLED` either, for the same reason from the other end.
     *
     * Shipped and delivered are included and paid is the floor — the shop has
     * the money and the shoe is on its way, which is when somebody starts
     * having an opinion about the size.
     *
     * @var list<string>
     */
    private const OWNED = [Order::PAID, Order::SHIPPED, Order::DELIVERED];

    public function store(Request $request, Product $product): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (! self::boughtIt($customer, $product)) {
            // Not a 403. The page renders the form only for somebody who
            // bought the shoe, so arriving here means a stale page or a
            // second tab — and the honest answer to that is the sentence,
            // not an error screen.
            return back()->withErrors([
                'body' => 'فقط کسی که این کفش را خریده می‌تواند دربارهٔ آن بنویسد.',
            ])->withInput();
        }

        $input = $request->validate([
            // A floor as well as a ceiling: «خوب بود» is not a comment
            // anybody reads, and the shop is asking for the paragraph a
            // shopper wanted to find. The ceiling is what a text column and
            // a page can carry without one person owning the screen.
            'body' => ['required', 'string', 'min:10', 'max:1500'],
            // The stars. Required from the form even though the column is
            // nullable — the column is nullable for the comments written
            // before stars existed, and inventing a score for those would be
            // putting a number in somebody's mouth. Nothing written from here
            // on may skip it.
            'rating' => ['required', 'integer', 'between:1,5'],
        ], [], ['body' => 'نظر', 'rating' => 'امتیاز']);

        /*
         * One comment per person per shoe, and a second is an edit of the
         * first — which is why the table can carry a unique index at all.
         *
         * It goes back to `PENDING` on every edit, and `approved_at` is
         * cleared with it. A comment that could be rewritten after approval
         * would make the queue a formality: approve a sentence about the
         * leather, come back and replace it with anything.
         */
        ProductComment::updateOrCreate(
            ['product_id' => $product->id, 'customer_id' => $customer->id],
            [
                'body' => $input['body'],
                'rating' => $input['rating'],
                'status' => ProductComment::PENDING,
                'approved_at' => null,
            ],
        );

        /*
         * Its own flash key and not `status`. The heart on this same page
         * flashes `status` — «به لیست علاقمندی اضافه شد» — and the comments
         * band would print it under «نظر خریداران» as though the shop were
         * answering something nobody asked.
         */
        return back()
            ->with('comment_status', 'نظر شما ثبت شد و پس از بررسی منتشر می‌شود.')
            ->withFragment('vp-pdp-talk');
    }

    /**
     * Has this account owned this shoe?
     *
     * **Across every branch, deliberately.** Orders are branch-scoped, and
     * rightly — Shiraz's orders are not Tehran's to read. But a comment is not
     * branch-scoped: it is about the shoe, and the shoe is the same shoe at
     * every branch. Somebody who bought a pair at a franchise and is now
     * reading the main store's page for it has still bought that pair, and
     * scoping this would tell them they had not.
     *
     * So the isolation is set aside for exactly one question, on rows already
     * narrowed to this customer's own id, and nothing about somebody else's
     * order is read or shown.
     *
     * A null customer is a no rather than an exception: the view asks this for
     * every visitor, most of whom are not signed in.
     */
    public static function boughtIt(?Customer $customer, Product $product): bool
    {
        if ($customer === null) {
            return false;
        }

        return OrderItem::query()
            ->whereIn('order_id', Order::query()
                ->acrossAllBranches()
                ->select('id')
                ->where('customer_id', $customer->id)
                ->whereIn('status', self::OWNED))
            ->whereIn('variant_id', $product->variants()->select('id'))
            ->exists();
    }
}
