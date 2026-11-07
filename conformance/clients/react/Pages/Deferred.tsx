import { Deferred as InertiaDeferred } from "@inertiajs/react";

import Debug from "../Debug";

interface Props {
  slow?: string;
  other?: string;
  query?: string;
}

export default function Deferred({ slow, other, query }: Props) {
  return (
    <>
      <InertiaDeferred
        data="slow"
        fallback={<p data-testid="fallback-slow">Loading slow</p>}
        rescue={<p data-testid="rescue-slow">Slow failed</p>}
      >
        <p data-testid="slow">{slow}</p>
      </InertiaDeferred>
      <InertiaDeferred data="other" fallback={<p data-testid="fallback-other">Loading other</p>}>
        <p data-testid="other">{other}</p>
      </InertiaDeferred>
      <InertiaDeferred data="query" fallback={<p data-testid="fallback-query">Loading query</p>}>
        <p data-testid="query">{query}</p>
      </InertiaDeferred>
      <Debug />
    </>
  );
}
