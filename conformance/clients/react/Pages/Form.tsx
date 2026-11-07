import { useForm, usePage } from "@inertiajs/react";

export default function Form() {
  const page = usePage();
  const form = useForm({ name: "" });

  return (
    <div>
      <h1 data-testid="component">Form</h1>
      <input
        data-testid="name"
        value={form.data.name}
        onChange={(event) => form.setData("name", event.target.value)}
      />
      <button data-testid="submit" onClick={() => form.post("/_c/form")}>
        Submit
      </button>
      <button data-testid="submit-bag" onClick={() => form.post("/_c/form", { errorBag: "contact" })}>
        Submit with an error bag
      </button>
      <p data-testid="error-name">{form.errors.name ?? ""}</p>
      <pre data-testid="errors">{JSON.stringify(page.props.errors ?? {})}</pre>
      <pre data-testid="flash">{JSON.stringify(page.flash ?? {})}</pre>
    </div>
  );
}
