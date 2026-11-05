<script lang="ts">
  import { Link, useRemember } from "@inertiajs/svelte";

  const state = useRemember({ note: "" }, "note");
</script>

<div>
  <h1 data-testid="component">Remember</h1>
  <input data-testid="note" bind:value={state.note} />
  <Link data-testid="link-dump" href="/dump">Dump</Link>
</div>
