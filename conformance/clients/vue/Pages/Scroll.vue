<script setup lang="ts">
import { InfiniteScroll } from "@inertiajs/vue3";

defineProps<{ items: { data: { id: number; name: string }[] } }>();
</script>

<template>
  <div>
    <h1 data-testid="component">Scroll</h1>
    <InfiniteScroll data="items">
      <div v-for="item in items.data" :key="item.id" data-testid="entry" style="height: 120px">{{ item.name }}</div>
    </InfiniteScroll>
  </div>
</template>
