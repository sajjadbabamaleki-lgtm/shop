<?php

namespace App\Domains\Tenancy\Contracts;

/**
 * What the rest of the application is allowed to know about a mini store's
 * owner, whether that owner is a marketplace vendor or a franchise branch.
 *
 * Keeping this narrow is the point. Anything that switches on `instanceof
 * Vendor` inside a view or a route is a place where the two domains have
 * started to bleed into each other, which is exactly what the architecture
 * document asks us to avoid.
 */
interface Tenant
{
    /** 'vendor' or 'franchise'. Matches the polymorphic type in store_domains. */
    public function tenantKind(): string;

    public function tenantId(): int;

    /** The path segment a mini store answers on when no hostname is bound. */
    public function tenantSlug(): string;

    /** The shop's name as a customer reads it. */
    public function storeName(): string;

    /** Whether customers may reach this store at all right now. */
    public function isStorefrontLive(): bool;

    /** Presentation bag: accent colour, tagline, hero image. */
    public function storefrontSettings(): array;
}
