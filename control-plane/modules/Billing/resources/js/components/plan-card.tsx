import { Tag } from '@/components/falak';
import { cn } from '@/lib/utils';
import { Check, Sparkles } from 'lucide-react';
import { type ReactNode } from 'react';
import { type Plan, formatCpus, formatMemory, priceOf } from '../types';

export function PlanCard({ plan, currency, action }: { plan: Plan; currency: string; action: ReactNode }) {
    const lines = [
        formatCpus(plan.cpus),
        `${formatMemory(plan.memory_mb)} RAM`,
        `${plan.disk_gb} GB disk`,
        `Up to ${plan.max_apps} apps`,
        `${plan.max_databases} database${plan.max_databases === 1 ? '' : 's'}`,
        'Free HTTPS subdomain',
    ];

    return (
        <div className={cn('bg-surface-1 relative flex flex-col gap-3 rounded-lg border p-4', plan.featured ? 'border-primary' : 'border-border')}>
            {plan.featured && (
                <Tag tone="accent" icon={<Sparkles className="size-3" />} className="absolute -top-2.5 left-4">
                    Best for n8n
                </Tag>
            )}
            <div>
                <h3 className="text-fg text-base font-medium">{plan.name}</h3>
                {plan.description && <p className="text-fg-muted text-sm">{plan.description}</p>}
            </div>
            <div className="text-fg text-2xl font-semibold">
                {priceOf(plan, currency)}
                <span className="text-fg-muted text-sm font-normal"> / {plan.period_days} days</span>
            </div>
            <ul className="text-fg-muted space-y-1 text-sm">
                {lines.map((line) => (
                    <li key={line} className="flex items-center gap-2">
                        <Check className="text-success size-3.5 shrink-0" aria-hidden />
                        {line}
                    </li>
                ))}
            </ul>
            {plan.runs.length > 0 && (
                <p className="text-fg-muted text-xs">
                    <span className="text-fg font-medium">One-click: </span>
                    {plan.runs.join(', ')}
                </p>
            )}
            <div className="mt-auto pt-1">{action}</div>
        </div>
    );
}
