import { formatCurrency } from '../lib/formatters';

function buildPath(values, width, height, padding) {
  if (values.length === 0) {
    return '';
  }

  const maxValue = Math.max(...values, 1);
  const stepX = values.length === 1 ? 0 : (width - padding * 2) / (values.length - 1);

  return values
    .map((value, index) => {
      const x = padding + stepX * index;
      const y = height - padding - (Number(value || 0) / maxValue) * (height - padding * 2);
      return `${index === 0 ? 'M' : 'L'} ${x} ${y}`;
    })
    .join(' ');
}

export default function TrendLines({ items = [] }) {
  const width = 560;
  const height = 220;
  const padding = 18;

  if (items.length === 0) {
    return <p className="muted-line">Sem historico suficiente para montar a evolucao.</p>;
  }

  const labels = items.map((item) => item.period);
  const incomes = items.map((item) => Number(item.incomes || 0));
  const expenses = items.map((item) => Number(item.expenses || 0));
  const installments = items.map((item) => Number(item.installments_due || 0));

  return (
    <div className="trend-card">
      <svg viewBox={`0 0 ${width} ${height}`} className="trend-svg" role="img" aria-label="Evolucao financeira">
        <path d={buildPath(incomes, width, height, padding)} className="trend-line trend-line--positive" />
        <path d={buildPath(expenses, width, height, padding)} className="trend-line trend-line--danger" />
        <path d={buildPath(installments, width, height, padding)} className="trend-line trend-line--accent" />
      </svg>

      <div className="trend-legend">
        <span><i className="dot dot-positive" />Receitas</span>
        <span><i className="dot dot-danger" />Despesas</span>
        <span><i className="dot dot-accent" />Parcelas</span>
      </div>

      <div className="trend-footer">
        {labels.map((label, index) => (
          <div key={label} className="trend-footer__item">
            <strong>{label}</strong>
            <span>{formatCurrency(incomes[index])}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

