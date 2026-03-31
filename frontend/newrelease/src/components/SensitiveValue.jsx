import { cn } from '../lib/utils';

export default function SensitiveValue({ hidden = false, className, children }) {
  return (
    <span className={cn('private-value', hidden && 'private-value--hidden', className)}>
      {children}
    </span>
  );
}

