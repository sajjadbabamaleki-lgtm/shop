<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FrontPagePlacement;
use App\Models\Product;
use App\Support\FrontPage;
use Illuminate\Contracts\View\View;
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
            ])->values(),

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
        ], [], ['product' => 'محصول']);

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
            ['position' => (int) FrontPagePlacement::where('band', $band)->max('position') + 1],
        );

        return back()->with('status', 'اضافه شد.');
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
