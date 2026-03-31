import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { formatCurrency } from '../lib/formatters';

export default function HorizontalBarChart({ items = [] }) {
  const rows = items.slice(0, 6);

  if (rows.length === 0) {
    return <p className="muted-line">Sem despesas categorizadas nesta competencia.</p>;
  }

  return (
    <div className="h-[320px]">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={rows} layout="vertical" margin={{ top: 8, right: 18, left: 12, bottom: 8 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" horizontal={false} />
          <XAxis type="number" tickFormatter={(value) => formatCurrency(value)} tick={{ fill: '#475467', fontSize: 12 }} axisLine={false} tickLine={false} />
          <YAxis
            type="category"
            dataKey="name"
            width={110}
            tick={{ fill: '#344054', fontSize: 12, fontWeight: 600 }}
            axisLine={false}
            tickLine={false}
          />
          <Tooltip
            cursor={{ fill: 'rgba(21, 94, 239, 0.06)' }}
            formatter={(value) => formatCurrency(value)}
            contentStyle={{
              borderRadius: 18,
              borderColor: '#d0d5dd',
              boxShadow: '0 12px 28px rgba(15, 23, 42, 0.12)',
            }}
          />
          <Bar dataKey="total" radius={[10, 10, 10, 10]}>
            {rows.map((item, index) => (
              <Cell
                key={item.name}
                fill={['#155eef', '#3b82f6', '#06b6d4', '#039855', '#d97706', '#e11d48'][index % 6]}
              />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
