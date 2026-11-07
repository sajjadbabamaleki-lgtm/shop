<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * اسنپ‌پی refused a return address on www with 1051, because only the bare
 * domain is registered with them. See RedirectWwwToCanonicalHost.
 */
class WwwGoesToTheCanonicalHostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://vikyplus.ir']);
    }

    public function test_a_page_on_www_moves_to_the_bare_domain_with_its_path_and_query(): void
    {
        $this->get('https://www.vikyplus.ir/products?brand=nike')
            ->assertStatus(301)
            ->assertRedirect('https://vikyplus.ir/products?brand=nike');
    }

    public function test_a_post_on_www_is_not_redirected(): void
    {
        $response = $this->post('https://www.vikyplus.ir/checkout/callback/snapppay', []);

        $this->assertNotSame(301, $response->getStatusCode());
    }

    public function test_other_hosts_are_left_alone(): void
    {
        foreach (['https://vikyplus.ir/robots.txt', 'https://vikyplus.liara.run/robots.txt'] as $url) {
            $this->assertNotSame(301, $this->get($url)->getStatusCode(), $url);
        }
    }
}
