import { toneClass } from '../lib/formatters';

export default function StatCard({ label, value, hint, tone = 'neutral', privateValue = false }) {
  return (
    <article className={`stat-card ${toneClass(tone)}`}>
      <span className="stat-card__label">{label}</span>
      <strong className={privateValue ? 'private-value' : ''}>{value}</strong>
      {hint ? <small>{hint}</small> : null}
    </article>
  );
}

