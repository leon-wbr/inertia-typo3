<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Support;

/**
 * Why PropsResolver leaves a prop out of the initial response.
 *
 * @internal
 */
enum PropExclusion
{
    /** Optional and deferred props: never sent with the page. */
    case IgnoredOnFirstLoad;

    /** Props that opted in to deferral, like scroll props. */
    case Deferred;

    /** Once props the client already has. */
    case AlreadyOnClient;
}
