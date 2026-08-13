<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Checkout\SettleOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The branch's orders.
 *
 * No branch condition anywhere in this file: Order is branch-scoped, so a
 * Shiraz manager quoting a Tehran order's number gets a 404 from the model
 * layer rather than a page. That is §18's "changing an id in the URL must not
 * work", enforced where it cannot be forgotten.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $orders = Order::query()
            ->when(
                array_key_exists((string) $status, Order::statusLabels()),
                fn ($q) => $q->where('status', $status),
            )
            ->latest('placed_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders', [
            'orders' => $orders,
            'status' => $status,
            'statuses' => Order::statusLabels(),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.order', ['order' => $order->load('items', 'customer')]);
    }

    /**
     * Move an order on.
     *
     * Paying and cancelling are not status edits — they move stock, so they go
     * through SettleOrder, which is the only thing besides the checkout that
     * touches a shelf. Shipping and delivering are just where the parcel is.
     */
    public function update(Request $request, Order $order, SettleOrder $settle): RedirectResponse
    {
        $to = $request->validate([
            'status' => ['required', Rule::in([Order::PAID, Order::SHIPPED, Order::DELIVERED, Order::CANCELLED])],
        ])['status'];

        $back = redirect()->route('admin.order', $order);

        if (! $this->allowed($order, $to)) {
            return $back->withErrors(['status' => "از «{$order->statusLabel()}» نمی‌شود به این وضعیت رفت."]);
        }

        match ($to) {
            Order::PAID => $settle->paid($order),
            Order::CANCELLED => $settle->cancelled($order, "Cancelled in the panel by {$request->user()->name}."),
            default => $order->update(['status' => $to]),
        };

        return $back->with('status', 'وضعیت سفارش به‌روز شد.');
    }

    /**
     * Which moves make sense from where.
     *
     * Written out rather than inferred, because "any status to any status" is
     * how an order that was already paid gets marked paid again and sells the
     * same stock twice.
     */
    private function allowed(Order $order, string $to): bool
    {
        return match ($order->status) {
            Order::PLACED => in_array($to, [Order::PAID, Order::CANCELLED], true),
            Order::PAID => in_array($to, [Order::SHIPPED, Order::CANCELLED], true),
            Order::SHIPPED => $to === Order::DELIVERED,
            default => false,
        };
    }
}
