<script lang="ts">
  import { WhenVisible } from "@inertiajs/svelte";

  let { lazy }: { lazy?: string } = $props();
</script>

<div>
  <h1 data-testid="component">WhenVisible</h1>
  <div style="height: 3000px"></div>
  <WhenVisible data="lazy">
    {#snippet fallback()}<p data-testid="fallback-lazy">Loading lazy</p>{/snippet}
    <p data-testid="lazy">{lazy}</p>
  </WhenVisible>
</div>
