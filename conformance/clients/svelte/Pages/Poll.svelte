<script lang="ts">
  import { usePoll } from "@inertiajs/svelte";

  let { tick }: { tick: string } = $props();

  usePoll(400, { only: ["tick"] });
</script>

<div>
  <h1 data-testid="component">Poll</h1>
  <p data-testid="tick">{tick}</p>
</div>
