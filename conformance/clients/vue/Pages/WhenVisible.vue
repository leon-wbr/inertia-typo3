<script setup lang="ts">
import { WhenVisible } from "@inertiajs/vue3";

defineProps<{ lazy?: string }>();
</script>

<template>
  <div>
    <h1 data-testid="component">WhenVisible</h1>
    <div style="height: 3000px" />
    <WhenVisible data="lazy">
      <template #fallback><p data-testid="fallback-lazy">Loading lazy</p></template>
      <p data-testid="lazy">{{ lazy }}</p>
    </WhenVisible>
  </div>
</template>
