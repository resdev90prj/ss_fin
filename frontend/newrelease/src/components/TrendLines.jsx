import {
  Area,
  AreaChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { formatCurrency } from '../lib/formatters';

export default function TrendLines({ items = [] }) {
  if (items.length === 0) {
    return <p className="muted-line">Sem historico suficiente para montar a evolucao.</p>;
  }

  const rows = items.map((item) => ({
    ...item,
    label: item.period,
  }));

  return (
    <div className="h-[340px]">
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={rows} margin={{ top: 10, right: 18, left: 8, bottom: 8 }}>
          <defs>
            <linearGradient id="incomeFill" x1="0" x2="0" y1="0" y2="1">
              <stop offset="5%" stopColor="#039855" stopOpacity={0.26} />
              <stop offset="95%" stopColor="#039855" stopOpacity={0.04} />
            </linearGradient>
            <linearGradient id="expenseFill" x1="0" x2="0" y1="0" y2="1">
              <stop offset="5%" stopColor="#e11d48" stopOpacity={0.22} />
              <stop offset="95%" stopColor="#e11d48" stopOpacity={0.03} />
            </linearGradient>
            <linearGradient id="installmentFill" x1="0" x2="0" y1="0" y2="1">
              <stop offset="5%" stopColor="#155eef" stopOpacity={0.18} />
              <stop offset="95%" stopColor="#155eef" stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" vertical={false} />
          <XAxis dataKey="label" tick={{ fill: '#475467', fontSize: 12 }} axisLine={false} tickLine={false} />
          <YAxis tickFormatter={(value) => formatCurrency(value)} tick={{ fill: '#475467', fontSize: 12 }} axisLine={false} tickLine={false} />
          <Tooltip
            formatter={(value) => formatCurrency(value)}
            contentStyle={{
              borderRadius: 18,
              borderColor: '#d0d5dd',
              boxShadow: '0 12px 28px rgba(15, 23, 42, 0.12)',
            }}
          />
          <Legend />
          <Area type="monotone" dataKey="incomes" stroke="#039855" fill="url(#incomeFill)" strokeWidth={3} name="Receitas" />
          <Area type="monotone" dataKey="expenses" stroke="#e11d48" fill="url(#expenseFill)" strokeWidth={3} name="Despesas" />
          <Area type="monotone" dataKey="installments_due" stroke="#155eef" fill="url(#installmentFill)" strokeWidth={3} name="Parcelas" />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
