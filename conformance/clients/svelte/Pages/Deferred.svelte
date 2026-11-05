<script lang="ts">
  import { Deferred } from "@inertiajs/svelte";

  import Debug from "../Debug.svelte";

  let { slow, other, query }: { slow?: string; other?: string; query?: string } = $props();
</script>

<Deferred data="slow">
  {#snippet fallback()}<p data-testid="fallback-slow">Loading slow</p>{/snippet}
  {#snippet rescue()}<p data-testid="rescue-slow">Slow failed</p>{/snippet}
  <p data-testid="slow">{slow}</p>
</Deferred>
<Deferred data="other">
  {#snippet fallback()}<p data-testid="fallback-other">Loading other</p>{/snippet}
  <p data-testid="other">{other}</p>
</Deferred>
<Deferred data="query">
  {#snippet fallback()}<p data-testid="fallback-query">Loading query</p>{/snippet}
  <p data-testid="query">{query}</p>
</Deferred>
<Debug />
