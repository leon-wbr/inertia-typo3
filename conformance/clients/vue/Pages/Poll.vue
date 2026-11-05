<script setup lang="ts">
import { usePoll } from "@inertiajs/vue3";

defineProps<{ tick: string }>();
usePoll(400, { only: ["tick"] });
</script>

<template>
  <div>
    <h1 data-testid="component">Poll</h1>
    <p data-testid="tick">{{ tick }}</p>
  </div>
</template>
