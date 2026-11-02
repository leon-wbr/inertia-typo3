<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Event;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched by the page controller before a TYPO3 page is rendered as an
 * Inertia page. Listeners can pick another component and add or change
 * props, including deferred, optional and merge props.
 */
final class BeforeInertiaPageRenderedEvent
{
    /**
     * @param array<string, mixed> $page The page record (language overlay applied)
     * @param array<string, mixed> $props
     */
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly array $page,
        private string $component,
        private array $props,
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPage(): array
    {
        return $this->page;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function setComponent(string $component): void
    {
        $this->component = $component;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * @param array<string, mixed> $props
     */
    public function setProps(array $props): void
    {
        $this->props = $props;
    }

    public function addProp(string $key, mixed $value): void
    {
        $this->props[$key] = $value;
    }
}
