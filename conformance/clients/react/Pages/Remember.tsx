import { Link, useRemember } from "@inertiajs/react";

export default function Remember() {
  const [note, setNote] = useRemember("", "note");

  return (
    <div>
      <h1 data-testid="component">Remember</h1>
      <input data-testid="note" value={note} onChange={(event) => setNote(event.target.value)} />
      <Link data-testid="link-dump" href="/dump">
        Dump
      </Link>
    </div>
  );
}
