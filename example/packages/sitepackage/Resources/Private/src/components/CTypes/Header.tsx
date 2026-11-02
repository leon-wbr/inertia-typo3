export default function Header({ data }: { data: Record<string, unknown> }) {
  return <h2 className="text-3xl font-bold">{String(data.header ?? "")}</h2>;
}
