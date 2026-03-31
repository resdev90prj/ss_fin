import { cn } from '../../lib/utils';

export function Progress({ value = 0, className, indicatorClassName }) {
  return (
    <div className={cn('h-2.5 w-full overflow-hidden rounded-full bg-slate-200', className)}>
      <div
        className={cn('h-full rounded-full bg-primary transition-all', indicatorClassName)}
        style={{ width: `${Math.min(100, Math.max(0, Number(value || 0)))}%` }}
      />
    </div>
  );
}

