import { WhenVisible as InertiaWhenVisible } from "@inertiajs/react";

interface Props {
  lazy?: string;
}

export default function WhenVisible({ lazy }: Props) {
  return (
    <div>
      <h1 data-testid="component">WhenVisible</h1>
      <div style={{ height: 3000 }} />
      <InertiaWhenVisible data="lazy" fallback={<p data-testid="fallback-lazy">Loading lazy</p>}>
        <p data-testid="lazy">{lazy}</p>
      </InertiaWhenVisible>
    </div>
  );
}
