import { InfiniteScroll } from "@inertiajs/react";

interface Props {
  items: { data: { id: number; name: string }[] };
}

export default function Scroll({ items }: Props) {
  return (
    <div>
      <h1 data-testid="component">Scroll</h1>
      <InfiniteScroll data="items">
        {items.data.map((item) => (
          <div key={item.id} data-testid="entry" style={{ height: 120 }}>
            {item.name}
          </div>
        ))}
      </InfiniteScroll>
    </div>
  );
}
