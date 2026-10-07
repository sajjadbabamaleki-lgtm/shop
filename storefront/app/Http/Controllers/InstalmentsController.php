<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Payments\Gateways;
use App\Support\Payments\SnappPay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * «این سفارش اقساطی می‌شود؟» — asked of اسنپ‌پی, from the page, after it has
 * already been drawn.
 *
 * **Why this is a request of its own rather than part of the order page.**
 * SnappPay require their `eligible` service to be called, to be called again
 * on every change of amount, and to decide the button — «از هر گونه پیاده‌سازی
 * دستی در سمت خود خودداری فرمایید». They also require the title and
 * description it returns to be printed unchanged, because the second of those
 * is the sentence a shopper actually decides on («۴ قسط ماهیانه ۶۲۷٬۰۰۰
 * تومان») and only they can compute it.
 *
 * Doing that inside the order page's render would put one of their round trips
 * in front of every shopper looking at an order, on a machine already measured
 * at thirteen times slower than a development one, where the page costs about
 * 1.4 seconds before anything of theirs is added. So the page ships without
 * it, the button arrives a moment later, and a shopper whose browser never
 * asks simply sees the card button — which is the failure this can afford.
 *
 * **The credentials never leave the server**, which is the other reason this
 * is not simply a fetch from the browser to SnappPay: the four of them
 * authenticate the shop, not the shopper.
 */
class InstalmentsController extends Controller
{
    /**
     * What the button should say, or that there should be no button.
     *
     * Guarded by the same session proof the order page and the pay route ask
     * for, and for a plainer reason than theirs: the amount is the thing being
     * asked about, so an open endpoint would let anybody read what any order
     * is worth.
     */
    public function show(Request $request, Order $order, Gateways $gateways): JsonResponse
    {
        if (! $request->session()->get("order.{$order->number}")) {
            throw new AccessDeniedHttpException('این سفارش برای این نشست نیست.');
        }

        $lender = $gateways->named('snapppay');

        // A shop with no instalment gateway configured answers the same way a
        // refused amount does: no button. The page asks either way, because
        // whether the shop has one is not the page's business.
        if (! $lender instanceof SnappPay) {
            return response()->json(['eligible' => false]);
        }

        $answer = $lender->eligibleFor((int) $order->grand_total);

        return response()->json([
            'eligible' => $answer['eligible'],
            // Printed exactly as they sent them, which is the requirement —
            // and why neither has a fallback here. A missing sentence is
            // SnappPay's to explain; inventing one would be the thing the
            // document forbids.
            'title' => $answer['title'],
            'description' => $answer['description'],
        ]);
    }
}
