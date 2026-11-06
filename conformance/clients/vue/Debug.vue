<script setup lang="ts">
// The DOM contract every client implements the same way (specs/support.ts).
import { Link, usePage } from "@inertiajs/vue3";
import { ref } from "vue";

const page = usePage();
const local = ref("");
</script>

<template>
  <div>
    <h1 data-testid="component">{{ page.component }}</h1>
    <p data-testid="url">{{ page.url }}</p>
    <pre data-testid="props">{{ JSON.stringify(page.props) }}</pre>
    <pre data-testid="flash">{{ JSON.stringify(page.flash ?? {}) }}</pre>
    <input v-model="local" data-testid="local" />
    <nav>
      <Link data-testid="link-dump" href="/dump">Dump</Link>
      <Link data-testid="link-other" href="/dump/other">Other</Link>
      <Link data-testid="link-prefetch" href="/dump/other" prefetch>Prefetch</Link>
      <Link data-testid="link-instant" href="/form" component="Form">Instant</Link>
      <Link data-testid="link-preserve" href="/dump/other" preserve-state preserve-scroll>Preserve</Link>
      <Link data-testid="link-once-other" href="/once/other">Once other</Link>
      <Link data-testid="link-fragment" href="/_c/fragment">Fragment</Link>
      <Link data-testid="link-external" href="/_c/external">External</Link>
      <Link data-testid="link-encrypted" href="/history/encrypted">Encrypted</Link>
      <Link data-testid="link-clear" href="/history/clear">Clear</Link>
      <Link data-testid="link-put" href="/_c/method" method="put" as="button">PUT</Link>
      <Link data-testid="link-flash" href="/_c/flash" method="post" as="button">Flash</Link>
      <Link data-testid="link-logout" href="/_c/logout" method="post" as="button">Logout</Link>
    </nav>
    <div style="height: 3000px" />
    <p id="section" data-testid="section">Section</p>
  </div>
</template>
