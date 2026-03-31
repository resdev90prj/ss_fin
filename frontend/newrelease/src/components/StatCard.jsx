import SensitiveValue from './SensitiveValue';
import { Card } from './ui/card';
import { cn } from '../lib/utils';

const toneMap = {
  neutral: 'border-slate-200/80 bg-white',
  positive: 'border-emerald-200/80 bg-emerald-50/70',
  danger: 'border-rose-200/80 bg-rose-50/70',
  warning: 'border-amber-200/80 bg-amber-50/70',
  accent: 'border-blue-200/80 bg-blue-50/70',
};

export default function StatCard({
  label,
  value,
  hint,
  tone = 'neutral',
  privateValue = false,
  hidden = false,
  icon = null,
  featured = false,
  className = '',
}) {
  return (
    <Card
      className={cn(
        'stat-card relative overflow-hidden rounded-[28px] p-5 shadow-none',
        toneMap[tone] || toneMap.neutral,
        featured && 'min-h-[220px] bg-slate-950 text-white shadow-glow',
        className,
      )}
    >
      {featured ? (
        <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/40 to-transparent" />
      ) : null}

      <div className="flex h-full flex-col justify-between gap-6">
        <div className="flex items-start justify-between gap-4">
          <div className="space-y-2">
            <span className={cn('block text-xs font-semibold uppercase tracking-[0.18em]', featured ? 'text-slate-300' : 'text-slate-500')}>
              {label}
            </span>
            <strong className={cn('block text-2xl font-extrabold tracking-tight md:text-3xl', featured ? 'text-white' : 'text-slate-950')}>
              {privateValue ? <SensitiveValue hidden={hidden}>{value}</SensitiveValue> : value}
            </strong>
          </div>
          {icon ? (
            <div className={cn(
              'flex h-11 w-11 items-center justify-center rounded-2xl',
              featured ? 'bg-white/10 text-white' : 'bg-slate-950 text-white',
            )}>
              {icon}
            </div>
          ) : null}
        </div>

        {hint ? (
          <p className={cn('text-sm leading-6', featured ? 'text-slate-300' : 'text-slate-600')}>
            {hint}
          </p>
        ) : null}
      </div>
    </Card>
  );
}
