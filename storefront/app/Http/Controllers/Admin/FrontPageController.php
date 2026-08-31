<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchOffer;
use App\Models\FrontPagePlacement;
use App\Models\Product;
use App\Support\FrontPage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Choosing what the front page shows.
 *
 * «پرفروش ترین ها و موارد داخل تخفیف پله ایرو ما خودمون دستی اد میکنیم» — so
 * the five bands that show products are chosen here rather than in a file. The
 * screen is the shop's own materials, like the rest of this panel.
 *
 * Not branch-scoped: the front page is the shop's, and `catalogue.manage` is
 * the permission that already means «may decide what the catalogue looks like
 * to everybody», which is exactly what this is. A franchise manager cannot
 * rearrange the chain's front page for the same reason they cannot rename its
 * products.
 */
class FrontPageController extends Controller
{
    public function edit(FrontPage $frontPage): View
    {
        return view('admin.front-page', [
            'bands' => collect(FrontPage::BANDS)->map(fn (array $band, string $key) => [
                'key' => $key,
                'label' => $band['label'],
                'max' => $band['max'],
                'placements' => $frontPage->placements($key),
                // A band nobody has touched shows the file's list and says so,
                // rather than looking empty when the page is full.
                'isDefault' => $frontPage->chosen($key) === [],
                'defaults' => $frontPage->slugs($key),
                'captioned' => $band['captioned'] ?? false,
                'captionLabel' => $band['caption_label'] ?? null,
                'note' => $band['note'] ?? null,
            ])->values(),

            // The stepped sale's own switch — see `sale()` below for why this
            // is a count of rows rather than a setting.
            'saleIsOn' => $this->saleIsOn(),

            /*
             * Everything sellable, by name. Drafts are left out: a band filters
             * on `purchasable()` anyway, so a draft chosen here would simply
             * not appear and the screen would be lying about what it had done.
             */
            'products' => Product::query()
                ->where('status', 'active')
                ->orderBy('title')
                ->get(['id', 'title', 'slug']),
        ]);
    }

    public function add(Request $request, string $band, FrontPage $frontPage): RedirectResponse
    {
        $this->assertBand($band);

        $input = $request->validate([
            'product' => ['required', 'integer', 'exists:products,id'],
            // Only a captioned band offers the field, and only it stores one.
            // 120 is the column; anything near it is already far longer than
            // the three words this line is drawn for.
            'caption' => ['nullable', 'string', 'max:120'],
        ], [], ['product' => 'محصول', 'caption' => 'جمله بالای نام']);

        $captioned = FrontPage::BANDS[$band]['captioned'] ?? false;

        $max = FrontPage::BANDS[$band]['max'];
        $current = FrontPagePlacement::where('band', $band)->count();

        if ($current >= $max) {
            /*
             * A band with one slot is a choice, not a queue: «پیشنهاد روز» has
             * room for one shoe and the useful answer to picking a second is to
             * put it there, not to refuse. Bands with several slots refuse,
             * because there the shopkeeper knows which one they meant to drop.
             */
            if ($max === 1) {
                FrontPagePlacement::where('band', $band)->delete();
            } else {
                return back()->withErrors([
                    'product' => "این بخش جا برای {$max} محصول دارد. اول یکی را حذف کن.",
                ]);
            }
        }

        FrontPagePlacement::updateOrCreate(
            ['band' => $band, 'product_id' => $input['product']],
            [
                'position' => (int) FrontPagePlacement::where('band', $band)->max('position') + 1,
                'caption' => $captioned ? ($input['caption'] ?? null) : null,
            ],
        );

        return back()->with('status', 'اضافه شد.');
    }

    /**
     * The stepped sale, on or off.
     *
     * **This is the control that did not exist**, and its absence is what the
     * client photographed: the sale was seeded with a four-week window, the
     * window closed on its own, and the only way to reopen it was a deploy.
     * «بازه حراج پله ای نباید اوتومات بسته بشه باید همچیزش دستی باشه» — so
     * here is the hand.
     *
     * On clears both window columns, which the two rules that read them treat
     * as "no bound in that direction", so the sale runs until somebody comes
     * back here. Off closes it now. Nothing else changes: `price` is what a
     * customer is charged and `compare_at_price` is the struck-through figure,
     * and neither is in either statement — what moves is whether the shop is
     * *advertising* a cut.
     *
     * **Every branch.** `BranchOpener` copies the window to a franchise when it
     * opens, so a switch that touched only the central branch would leave the
     * chain half on. The campaign is the chain's, like the front page this
     * screen edits, which is also why neither is branch-scoped.
     *
     * Only rows that carry a real discount are touched. On any other row the
     * window is already ignored — no `compare_at_price` above `price` and there
     * is nothing to advertise — so writing to it would change nothing and would
     * overwrite a date somebody set on purpose.
     */
    public function sale(Request $request): RedirectResponse
    {
        $on = $request->input('state') === 'on';

        $this->discountedOffers()->update([
            'promotion_starts_at' => null,
            'promotion_ends_at' => $on ? null : now(),
        ]);

        return back()->with('status', $on ? 'حراج پله‌ای روشن شد.' : 'حراج پله‌ای خاموش شد.');
    }

    /**
     * Whether anything is actually being advertised as discounted right now.
     *
     * Read off the offers rather than kept as a setting, on purpose: a setting
     * would be a second answer to «is there a sale on?», and the page does not
     * read it — it reads the offers. Two answers is how a screen comes to say
     * «روشن» over a shop that is showing no cut at all.
     */
    private function saleIsOn(): bool
    {
        return BranchOffer::query()->acrossAllBranches()->promoted()->exists();
    }

    /**
     * Every branch's discounted offers.
     *
     * `acrossAllBranches()` and not a bare query: an offer is branch-scoped, no
     * branch is bound on this screen — it is the chain's front page, not a
     * shop's — and a scoped query with nothing bound correctly returns nothing.
     * Without this the switch would have reported «خاموش» over a running sale
     * and then silently updated no rows at all.
     *
     * @return Builder<BranchOffer>
     */
    private function discountedOffers(): Builder
    {
        return BranchOffer::query()
            ->acrossAllBranches()
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price');
    }

    public function remove(FrontPagePlacement $placement): RedirectResponse
    {
        $placement->delete();

        return back()->with('status', 'حذف شد.');
    }

    /**
     * Up or down, by swapping with the neighbour.
     *
     * Swapping rather than renumbering the whole band: two rows change, the
     * rest keep the numbers they had, and nothing depends on the positions
     * being contiguous.
     */
    public function move(Request $request, FrontPagePlacement $placement): RedirectResponse
    {
        $up = $request->input('direction') === 'up';

        DB::transaction(function () use ($placement, $up) {
            $neighbour = FrontPagePlacement::query()
                ->where('band', $placement->band)
                ->where('position', $up ? '<' : '>', $placement->position)
                ->orderBy('position', $up ? 'desc' : 'asc')
                ->lockForUpdate()
                ->first();

            if ($neighbour === null) {
                return;
            }

            [$placement->position, $neighbour->position] = [$neighbour->position, $placement->position];

            $placement->save();
            $neighbour->save();
        });

        return back()->with('status', 'جابه‌جا شد.');
    }

    /**
     * Back to the page's own default.
     *
     * Deleting the rows rather than writing the file's list into them: the
     * default is a thing that can change with the design, and a band that
     * copied it once would go on showing last year's answer.
     */
    public function reset(string $band): RedirectResponse
    {
        $this->assertBand($band);

        FrontPagePlacement::where('band', $band)->delete();

        return back()->with('status', 'به حالت پیش‌فرض برگشت.');
    }

    private function assertBand(string $band): void
    {
        abort_unless(array_key_exists($band, FrontPage::BANDS), 404);
    }
}
