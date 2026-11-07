<script setup lang="ts">
import { Link, useRemember } from "@inertiajs/vue3";

const note = useRemember("", "note");
</script>

<template>
  <div>
    <h1 data-testid="component">Remember</h1>
    <input v-model="note" data-testid="note" />
    <Link data-testid="link-dump" href="/dump">Dump</Link>
  </div>
</template>
