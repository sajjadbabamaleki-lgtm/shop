# Marketplace and franchise — what was built, and where the spec landed

The client supplied `laravel_marketplace_franchise_architecture_EN.pdf` and asked
for two things on top of it: a marketplace where independent sellers list their
own goods, keep a professional profile, run their own sales panel and get a
mini website with **no route back to the parent store**; and a franchise
network built to the document's architecture. The platform takes 9%.

This file is the map between the two. Section numbers below are the PDF's.

---

## The shape

One Laravel application, modular monolith, as §1 asks. New code lives under
`app/Domains/`:

```
app/Domains/
  Identity/     roles, role assignments, the permission trait on User
  Marketplace/  vendors, offers, commission rules, ledger, settlements
  Franchise/    branches, assortment, local stock, settings, fulfilment
  Orders/       orders, order items, refunds, checkout, stock reservation
  Tenancy/      tenant context, resolver, routing, mini-store isolation
```

**`app/Models` is the Catalog domain.** The PDF lists `Catalog/` among the
domain folders; the catalogue core — brands, categories, products, variants,
media, inventory movements — already existed there with its invariants in the
database, and moving seven working files to satisfy a folder name would have
been churn for nothing. It is the platform-owned "Product Core" of §10 either
way.

## §2 — Multi-vendor marketplace

| The spec asks for | Where it is |
|---|---|
| Vendor registration, approval, profile | `Marketplace/Services/VendorRegistrar`, `vendors` table |
| Vendor dashboard: products, pricing, inventory, orders, reporting | `Http/Controllers/Panel/Vendor/*`, `Marketplace/Services/SalesReport` |
| Commission by vendor, category or product | `commission_rules`, `Marketplace/Services/CommissionResolver` |
| Vendor ledger and settlement workflow | `ledger_entries`, `settlements`, `Ledger`, `SettlementRunner` |
| A cart with items from several vendors | `Orders/Services/Checkout` — one order, lines per seller |
| Independent fulfilment/return/payout per item | `order_items.fulfillment_status`, `order_item_refunds`, `settlement_id` |

**The critical design rule is enforced, not just followed.** Vendor ownership
is on the item, and a check constraint refuses an item that claims a seller
kind without naming that seller:

```sql
CHECK ((seller_kind = 'platform'  AND vendor_id IS NULL     AND franchise_id IS NULL)
    OR (seller_kind = 'vendor'    AND vendor_id IS NOT NULL AND franchise_id IS NULL)
    OR (seller_kind = 'franchise' AND vendor_id IS NULL     AND franchise_id IS NOT NULL))
```

Two more constraints keep the money honest: `commission_amount + seller_payable
= line_total`, and a platform line may take no commission.

### The 9%

`config/marketplace.php` holds `default_commission_bps = 900`. Basis points,
integer arithmetic, never a float: commission is `line_total * bps / 10000`
truncated, and the seller takes the remainder, so the halves always add back to
the line exactly. `CommissionTest` checks that across awkward remainders.

The rate is resolved most-specific-first — offer override, product rule,
category rule, vendor rule, the rate on the vendor row, then the 9% default —
and **copied onto the order item at the moment of sale**. A rate renegotiated
next month does not rewrite last month's invoices.

## §3, §4, §5 — Franchise, mini stores, tenancy

A franchise is a separate model from a vendor because the two are separate
concepts, exactly as the document insists. The difference is enforced on the
franchise row: `price_override_mode` plus `max_discount_bps` /
`max_markup_bps` bound how far a branch may move a central price, and
`inventory_mode` decides whether it sells local stock, central stock or both.
A branch that has set no price follows ours and keeps following it when head
office reprices.

Shared database with tenant identifiers, as §5 recommends — not a database per
branch.

### The mini store, and the isolation the client asked for

> وقتی لینک مستقیم مینی وب‌سایتشون رو در اختیار کاربران می‌ذارن، هیچ راه ورودی
> به وبسایت مادر … نداشته باشه

Three layers, and the first is the one that matters:

1. **Route table.** `Domains/Tenancy/TenantRouting` chooses which routes exist
   for the request. On a mini store's own hostname the parent's route file is
   never registered — there is no route to the parent's home page, catalogue,
   sign-in or panel. A link back cannot be followed because the URL does not
   exist on that hostname, not because nothing happens to link to it.
2. **Layout.** `resources/views/layouts/store.blade.php` shares no partial with
   the parent's layout. The only branding is the seller's.
3. **Tripwire.** `GuardMiniStoreIsolation` scans mini-store HTML in local and
   testing and throws if a parent link appears. It does nothing in production —
   a customer must never see a stack trace over a stray link — so the automated
   test is what actually holds the line.

`MiniStoreIsolationTest` covers all three, including that the parent host keeps
its own routes and that a suspended seller's shop 404s rather than redirecting
into the parent.

**Addresses.** Subdomains are preferred and are issued automatically when a
store is published (`StoreProvisioner`). Hostnames resolve through the
`store_domains` table only — never by guessing a slug from the first label,
because vendor and franchise slugs are independent and a guess would eventually
pick the wrong one. Custom domains are supported and stay unusable until
verified. A path form, `/s/{vendor}` and `/f/{franchise}`, always works for
previews and before DNS exists; route names are identical either way, so
nothing that generates a link knows which form it is in.

**The cost, stated plainly:** `php artisan route:cache` compiles one route
table and this application has two. Route caching must stay off, or be run
per host into per-host files. For a store this size that is a fraction of a
millisecond per request, and it buys isolation that a template cannot.

### Isolation is four layers, not a column

§5's warning — "a tenant_id column alone is not enough" — is answered in query
scopes (`BelongsToTenant`, a global scope that returns *nothing* rather than
everything when the tenant kind mismatches), service checks
(`ResolvePanelTenant`, which runs **before** route-model binding so `{offer}`
resolves through a scoped query and another seller's id simply 404s), policies
(gates that require both the role over that store *and* membership of it), and
tests (`TenantIsolationTest`).

## §6 — Roles and permissions

The eight roles the document names are rows in `roles`; what each may do is a
map in `config/rbac.php`. An assignment carries the scope it was granted over,
so "Franchise Manager" is a fact about a person *and one branch*. Gates are
defined in a loop from the config, so no gate can be added that tests the role
and forgets the tenant.

## §7 — Inventory and fulfilment

Three shelves — central warehouse, vendor stock, branch stock — all with the
same invariants: non-negative, reserved never above on-hand, `sellable_stock`
generated by the database. `StockReservation` locks the row with `SELECT … FOR
UPDATE`, and the check constraint underneath catches any future code path that
forgets to. Every movement on all three shelves goes into the one
`inventory_movements` table, which gained a nullable owner rather than being
copied twice.

`FulfillmentRouter` implements the local-then-central routing of §7, bounded by
the branch's `inventory_mode`.

## §9 — Financial flow

There is **no balance column anywhere.** A vendor's payable is `SUM(amount)`
over its unsettled ledger entries. Entries are append-only — the model throws on
update and delete — so a correction is another entry, which is what makes a
dispute three months later answerable. Sales and commissions are written as
mirrored pairs so the platform's revenue reconciles against the vendors' payable
rather than both against the order table.

Drafting a settlement stamps `settlement_id` on the items and entries it
covers, so the live balance drops at draft time and no entry can be paid twice.

## §11 — The decisions the document says must be made first

Each was answered to get a working system. All are reversible; three are
commercial and should be confirmed.

| Question | Answer here | Where |
|---|---|---|
| One canonical catalogue, or independent product records? | **Canonical**, with vendor/franchise *offers* attached. A vendor may propose a product; it sits at `pending_review` until approved. | `vendor_offers`, `products.approval_status` |
| May a franchise change central prices? | **Within limits.** Default: up to 10% off, 0% up. Per branch, enforced by constraint and controller. | `franchises.price_override_mode` |
| One payment or separate flows? | **One checkout, one payment, one order**; the split is on the items. | `Orders/Services/Checkout` |
| Who owns shipping and returns for marketplace orders? | **The vendor**, per item. Returns go back to the shelf they were sold from. | `order_items`, `RefundService` |
| When is vendor revenue settleable? | **Delivered, plus a 7-day return hold.** Configurable. | `config/marketplace.php` |
| Local stock, central, or both for a branch? | **Configurable; hybrid by default.** | `franchises.inventory_mode` |
| Shared customer accounts across parent and mini stores? | **Yes** — one account, one login. Staff access comes from roles and membership. | `App\Models\User` |
| Custom domains as well as subdomains? | **Yes**, unusable until verified. | `store_domains` |
| Tax, invoicing, settlement, Iranian legal requirements | **Not addressed.** See below. | — |

## §12 — Phases: where this leaves us

- **Phase 1 (core)** — users, catalogue, orders, roles: done, except payment.
- **Phase 2 (marketplace)** — vendors, commission, ledger, settlements, vendor
  operations: done.
- **Phase 3 (franchise)** — tenancy, mini stores, subdomains, branch inventory
  and orders: done.
- **Phase 4** — central analytics beyond the platform dashboard, advanced
  fulfilment, financial automation: not started.

## What is deliberately not here

Saying this plainly matters more than the list above.

- **No payment gateway.** `Checkout::markPaid()` is the seam a gateway callback
  calls; it is idempotent and writes the ledger. Nothing talks to Zarinpal,
  IPG, or anything else yet.
- **No cart or customer checkout UI.** A mini store shows products and prices
  and hands the customer the seller's contact details. `Checkout` is fully
  built and tested behind that.
- **No tax or invoicing**, and none of the Iranian legal and payment-settlement
  requirements §11 ends on. That needs an answer from the client's accountant
  before it is worth writing.
- **The parent storefront is still not ported.** `download-version/` remains
  the design source of truth; see `HANDOFF.md`. The pages added here — seller
  directory, registration, sign-in, the panels — use the settled tokens
  (glass `rgba(16,17,17,0.034)`, the gold, the 24px corner, an ink foot rather
  than a drop shadow) rather than inventing a second visual language.
- **No image upload** for vendor logos and banners. The columns exist.
- **No vendor staff management screen.** The tables, roles and policies are
  there; only the UI is missing.

## Running it

```bash
cd storefront
composer install
cp .env.example .env && php artisan key:generate
# PostgreSQL: the check constraints do not exist on SQLite
php artisan migrate --seed
php artisan serve
```

The seeder builds a working network: a super admin, two sellers with different
commission rates, one branch, a small catalogue, and paid orders including one
basket split across two sellers. Every seeded row goes through the same
services the application uses, so none of it could have been written by a path
the app does not have. Sign in as `admin@vikyplus.ir` / `password`.

Mini stores are reachable at `/s/kafsh-ali`, `/s/charm-sara` and `/f/shiraz`
without DNS. With DNS they are `kafsh-ali.vikyplus.ir` and so on, and on those
hostnames the parent store does not exist.

Tests run against PostgreSQL for the same reason migrations do:

```bash
createdb vikyplus_test && php artisan test
```
