import { Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { money } from '../format';

type Point = { day: string; total: number | string };

export default function RevenueChart({ series, currency }: { series: Point[]; currency: string }) {
  const data = series.map((row) => ({
    day: String(row.day).slice(5),
    total: Number(row.total),
  }));

  if (data.length === 0) {
    return <p className="py-10 text-center text-sm text-ink-700">No paid orders in this window yet.</p>;
  }

  return (
    <div className="h-56">
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data}>
          <XAxis dataKey="day" tick={{ fontSize: 12 }} />
          <YAxis tick={{ fontSize: 12 }} width={48} />
          <Tooltip formatter={(value) => money(Number(value ?? 0), currency)} />
          <Line type="monotone" dataKey="total" stroke="#2563eb" strokeWidth={2} dot={false} />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
