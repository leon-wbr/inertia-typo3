<script lang="ts">
  import { page, useForm } from "@inertiajs/svelte";

  const form = useForm({ name: "" });
</script>

<div>
  <h1 data-testid="component">Form</h1>
  <input data-testid="name" bind:value={form.name} />
  <button data-testid="submit" onclick={() => form.post("/_c/form")}>Submit</button>
  <button data-testid="submit-bag" onclick={() => form.post("/_c/form", { errorBag: "contact" })}>
    Submit with an error bag
  </button>
  <p data-testid="error-name">{form.errors.name ?? ""}</p>
  <pre data-testid="errors">{JSON.stringify(page.props.errors ?? {})}</pre>
  <pre data-testid="flash">{JSON.stringify(page.flash ?? {})}</pre>
</div>
