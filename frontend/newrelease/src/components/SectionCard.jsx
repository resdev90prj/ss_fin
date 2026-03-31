import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { cn } from '../lib/utils';

export default function SectionCard({ title, subtitle, action, children, className = '', contentClassName = '' }) {
  return (
    <Card className={cn('section-card', className)}>
      {(title || subtitle || action) ? (
        <CardHeader className="flex flex-col gap-4 border-b border-slate-100/80 pb-5 md:flex-row md:items-start md:justify-between">
          <div className="space-y-1">
            {title ? <CardTitle className="text-xl md:text-2xl">{title}</CardTitle> : null}
            {subtitle ? <CardDescription className="max-w-2xl text-sm leading-6">{subtitle}</CardDescription> : null}
          </div>
          {action ? <div className="shrink-0">{action}</div> : null}
        </CardHeader>
      ) : null}
      <CardContent className={cn(title || subtitle || action ? 'pt-5' : 'pt-6', contentClassName)}>
        {children}
      </CardContent>
    </Card>
  );
}
