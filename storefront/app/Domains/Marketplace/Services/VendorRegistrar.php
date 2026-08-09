<?php

namespace App\Domains\Marketplace\Services;

use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Tenancy\Services\StoreProvisioner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vendor registration and approval (§2).
 *
 * Registration is a workflow, not a form submission. A new seller is 'pending'
 * and has no shop: `isStorefrontLive()` is false, so its mini store 404s and
 * its offers reach no customer. Approval is what publishes it, and that is
 * also the moment its subdomain is issued and its owner is given the role over
 * it — three things that must happen together or the seller ends up with a
 * panel and no shop, or a shop and no way into the panel.
 */
class VendorRegistrar
{
    public function __construct(private readonly StoreProvisioner $provisioner) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function register(User $owner, array $attributes): Vendor
    {
        return DB::transaction(function () use ($owner, $attributes) {
            $vendor = Vendor::create([
                'slug' => $attributes['slug'] ?? $this->uniqueSlug($attributes['name']),
                'name' => $attributes['name'],
                'legal_name' => $attributes['legal_name'] ?? null,
                'national_id' => $attributes['national_id'] ?? null,
                'economic_code' => $attributes['economic_code'] ?? null,
                'owner_user_id' => $owner->id,
                'status' => config('marketplace.auto_approve_vendors') ? 'approved' : 'pending',
                'about' => $attributes['about'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'email' => $attributes['email'] ?? $owner->email,
                'province' => $attributes['province'] ?? null,
                'city' => $attributes['city'] ?? null,
                'address' => $attributes['address'] ?? null,
                'instagram' => $attributes['instagram'] ?? null,
                'telegram' => $attributes['telegram'] ?? null,
                'website' => $attributes['website'] ?? null,
            ]);

            // The owner is a member of their own shop from the first moment,
            // so the panel is reachable while approval is pending — they have
            // products to prepare.
            $vendor->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);
            $owner->assignRole('vendor-owner', $vendor);

            if ($vendor->status === 'approved') {
                $this->publish($vendor);
            }

            return $vendor;
        });
    }

    public function approve(Vendor $vendor, ?string $note = null): Vendor
    {
        return DB::transaction(function () use ($vendor, $note) {
            $vendor->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'status_note' => $note,
            ])->save();

            $this->publish($vendor);

            return $vendor;
        });
    }

    public function reject(Vendor $vendor, string $reason): Vendor
    {
        $vendor->forceFill(['status' => 'rejected', 'status_note' => $reason])->save();

        return $vendor;
    }

    /**
     * Suspension takes the shop down but leaves the panel standing: the seller
     * still has orders to fulfil and money owed, and cutting them off from
     * those is a different decision from cutting off new sales.
     */
    public function suspend(Vendor $vendor, string $reason): Vendor
    {
        $vendor->forceFill(['status' => 'suspended', 'status_note' => $reason])->save();

        return $vendor;
    }

    private function publish(Vendor $vendor): void
    {
        $vendor->approved_at ??= now();
        $vendor->save();

        $this->provisioner->provisionSubdomain($vendor);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $n = 2;

        while (Vendor::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
