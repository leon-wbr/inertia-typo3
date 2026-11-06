<script setup lang="ts">
import { useForm, usePage } from "@inertiajs/vue3";

const page = usePage();
const form = useForm({ name: "" });
</script>

<template>
  <div>
    <h1 data-testid="component">Form</h1>
    <input v-model="form.name" data-testid="name" />
    <button data-testid="submit" @click="form.post('/_c/form')">Submit</button>
    <button data-testid="submit-bag" @click="form.post('/_c/form', { errorBag: 'contact' })">
      Submit with an error bag
    </button>
    <p data-testid="error-name">{{ form.errors.name ?? "" }}</p>
    <pre data-testid="errors">{{ JSON.stringify(page.props.errors ?? {}) }}</pre>
    <pre data-testid="flash">{{ JSON.stringify(page.flash ?? {}) }}</pre>
  </div>
</template>
