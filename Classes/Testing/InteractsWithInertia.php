<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Testing;

use LeonWbr\Inertia\Support\Header;
use Psr\Http\Message\ResponseInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * For TYPO3 functional tests (typo3/testing-framework): builds Inertia
 * requests and asserts on the page objects they return.
 *
 *     $response = $this->executeFrontendSubRequest($this->inertiaRequest('https://website.local/products'));
 *     $this->assertInertia($response, fn(AssertableInertia $page) => $page
 *         ->component('Products')
 *         ->has('products.data', 10));
 */
trait InteractsWithInertia
{
    /**
     * A GET request as the Inertia client sends it on a visit.
     *
     * @param array<string, string> $headers
     */
    protected function inertiaRequest(string $uri, string $version = '', array $headers = []): InternalRequest
    {
        $request = (new InternalRequest($uri))
            ->withHeader(Header::INERTIA, 'true')
            ->withHeader(Header::VERSION, $version);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * A partial reload of a component: only (or except) the given props.
     *
     * @param list<string> $only
     * @param list<string> $except
     * @param array<string, string> $headers
     */
    protected function inertiaReloadRequest(
        string $uri,
        string $component,
        array $only = [],
        array $except = [],
        string $version = '',
        array $headers = [],
    ): InternalRequest {
        $headers[Header::PARTIAL_COMPONENT] = $component;
        if ($only !== []) {
            $headers[Header::PARTIAL_ONLY] = implode(',', $only);
        }
        if ($except !== []) {
            $headers[Header::PARTIAL_EXCEPT] = implode(',', $except);
        }

        return $this->inertiaRequest($uri, $version, $headers);
    }

    /**
     * Asserts that the response is an Inertia page (JSON or first visit),
     * and runs the callback with it.
     *
     * @param (callable(AssertableInertia): mixed)|null $callback
     */
    protected function assertInertia(ResponseInterface $response, ?callable $callback = null): AssertableInertia
    {
        $page = AssertableInertia::fromResponse($response);

        if ($callback !== null) {
            $callback($page);
        }

        return $page;
    }
}
