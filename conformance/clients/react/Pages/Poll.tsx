import { usePoll } from "@inertiajs/react";

interface Props {
  tick: string;
}

export default function Poll({ tick }: Props) {
  usePoll(400, { only: ["tick"] });

  return (
    <div>
      <h1 data-testid="component">Poll</h1>
      <p data-testid="tick">{tick}</p>
    </div>
  );
}
