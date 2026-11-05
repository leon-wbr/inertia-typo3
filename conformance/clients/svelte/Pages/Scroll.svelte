<script lang="ts">
  import { InfiniteScroll } from "@inertiajs/svelte";

  let { items }: { items: { data: { id: number; name: string }[] } } = $props();
</script>

<div>
  <h1 data-testid="component">Scroll</h1>
  <InfiniteScroll data="items">
    {#each items.data as item (item.id)}
      <div data-testid="entry" style="height: 120px">{item.name}</div>
    {/each}
  </InfiniteScroll>
</div>
