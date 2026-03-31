import { formatCurrency } from '../lib/formatters';

export default function HorizontalBarChart({ items = [] }) {
  const rows = items.slice(0, 6);
  const maxValue = rows.reduce((highest, item) => Math.max(highest, Number(item.total || 0)), 0);

  if (rows.length === 0) {
    return <p className="muted-line">Sem despesas categorizadas nesta competencia.</p>;
  }

  return (
    <div className="bar-chart">
      {rows.map((item) => {
        const total = Number(item.total || 0);
        const width = maxValue > 0 ? Math.max(8, (total / maxValue) * 100) : 8;

        return (
          <div key={item.name} className="bar-row">
            <div className="bar-row__label">
              <strong>{item.name}</strong>
              <span>{formatCurrency(total)}</span>
            </div>
            <div className="bar-row__track">
              <div className="bar-row__fill" style={{ width: `${width}%` }} />
            </div>
          </div>
        );
      })}
    </div>
  );
}

