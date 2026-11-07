<script setup lang="ts">
import { Deferred } from "@inertiajs/vue3";

import Debug from "../Debug.vue";

defineProps<{ slow?: string; other?: string; query?: string }>();
</script>

<template>
  <Deferred data="slow">
    <template #fallback><p data-testid="fallback-slow">Loading slow</p></template>
    <template #rescue><p data-testid="rescue-slow">Slow failed</p></template>
    <p data-testid="slow">{{ slow }}</p>
  </Deferred>
  <Deferred data="other">
    <template #fallback><p data-testid="fallback-other">Loading other</p></template>
    <p data-testid="other">{{ other }}</p>
  </Deferred>
  <Deferred data="query">
    <template #fallback><p data-testid="fallback-query">Loading query</p></template>
    <p data-testid="query">{{ query }}</p>
  </Deferred>
  <Debug />
</template>
