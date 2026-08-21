<?php

namespace App\Support\Basalam;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The half of the move that talks to Basalam.
 *
 * It speaks to `openapi.basalam.com`, the gateway Basalam documents and its own
 * SDK uses, rather than to the shop's web pages. That is worth stating because
 * the obvious way to move a marketplace stall is to scrape it, and scraping
 * this one would be building on sand: the storefront is a JavaScript
 * application, so the markup a fetch returns is not the markup a visitor sees,
 * and any selector written against today's build is a guess with a shelf life.
 * The gateway is a published contract with a schema.
 *
 * Nothing here writes to the database and nothing here knows what a Product is.
 * It fetches, retries, and hands back decoded arrays — which is what makes the
 * mapping testable without a network and the fetching testable without a
 * database.
 */
class Client
{
    public function __construct(
        private readonly string $baseUri,
        private readonly ?string $token,
        private readonly int $pauseMs = 350,
        private readonly int $retries = 4,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('services.basalam');

        return new self(
            rtrim((string) $config['base_uri'], '/'),
            $config['token'] ? (string) $config['token'] : null,
            (int) $config['pause_ms'],
            (int) $config['retries'],
        );
    }

    /**
     * One page of the stall's products.
     *
     * The list is the cheap call and carries only what a listing needs — id,
     * title, price, stock, one photograph. Everything else is on the product
     * itself, which is why the fetch does two passes rather than one.
     *
     * @return array{items: list<array<string, mixed>>, total: int, pages: int}
     */
    public function vendorProducts(int $vendorId, int $page = 1, int $perPage = 50): array
    {
        $body = $this->get("/v1/vendors/{$vendorId}/products", [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return [
            'items' => $body['data'] ?? [],
            'total' => (int) ($body['total_count'] ?? 0),
            'pages' => (int) ($body['total_page'] ?? 1),
        ];
    }

    /**
     * One product, whole: description, every photograph, the category, and the
     * variations with their own stock and prices.
     *
     * @return array<string, mixed>
     */
    public function product(int $productId): array
    {
        return $this->get("/v1/products/{$productId}");
    }

    /**
     * A photograph's bytes.
     *
     * Kept here rather than in the command because it wants the same retry and
     * the same pause as everything else: an import is a few hundred image
     * requests and they are the part most likely to be throttled.
     */
    public function download(string $url): string
    {
        $response = $this->request()->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Downloading {$url} answered {$response->status()}.");
        }

        $this->pause();

        return $response->body();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $response = $this->request()->get($this->baseUri.$path, $query);

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException(
                "Basalam answered {$response->status()} for {$path}. BASALAM_TOKEN is missing, expired, "
                .'or does not carry the scope this endpoint needs.'
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException("Basalam answered {$response->status()} for {$path}.");
        }

        $this->pause();

        return $this->decode($response, $path);
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(array_filter([
                // The stall is this shop's own, so identify the caller rather
                // than pretending to be a browser. If Basalam ever wants to
                // rate-limit or block this, they should be able to.
                'User-Agent' => 'VikyPlus-Importer/1.0 (+https://vikyplus.ir)',
                'Authorization' => $this->token ? 'Bearer '.$this->token : null,
            ]))
            ->timeout(30)
            /*
             * Exponential, and only for the failures that are worth repeating.
             * A 429 or a 5xx is the far end asking for a moment; a 404 is an
             * answer, and retrying it four times is just four more requests
             * for somebody else's server to refuse.
             */
            ->retry($this->retries, 2000, function (\Throwable $e, PendingRequest $request) {
                $status = $e instanceof RequestException
                    ? $e->response->status()
                    : 0;

                return $status === 0 || $status === 429 || $status >= 500;
            }, throw: false);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response, string $path): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException("Basalam answered {$path} with something that is not JSON.");
        }

        return $body;
    }

    /**
     * A pause between calls rather than a burst.
     *
     * Not a rate limiter — there is one caller and it is a console command, so
     * a sleep is the whole mechanism and a token bucket would be furniture.
     */
    private function pause(): void
    {
        if ($this->pauseMs > 0 && ! app()->runningUnitTests()) {
            usleep($this->pauseMs * 1000);
        }
    }
}
