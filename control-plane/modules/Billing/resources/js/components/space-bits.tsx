import { Button, ServiceIcon } from '@/components/falak';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { type ReactNode, useEffect } from 'react';

export type AppStatus = 'running' | 'starting' | 'failed' | 'stopped';

export interface SpaceApp {
    id: string;
    name: string;
    icon: string | null;
    url: string | null;
    status: AppStatus;
    error: string | null;
    updated_at: string | null;
}

export interface StoreApp {
    slug: string;
    name: string;
    description: string;
    icon: string | null;
    min_memory_mb: number | null;
    fits: boolean;
    needs_plan: string | null;
}

const STATUS: Record<AppStatus, { label: string; className: string }> = {
    running: { label: 'Running', className: 'bg-success-soft text-success' },
    starting: { label: 'Starting…', className: 'bg-info-soft text-info' },
    failed: { label: 'Didn’t start', className: 'bg-danger-soft text-danger' },
    stopped: { label: 'Stopped', className: 'bg-surface-3 text-fg-muted' },
};

export function AppStatusBadge({ status }: { status: AppStatus }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium', STATUS[status].className)}>
            {status === 'starting' ? <Loader2 className="size-3 animate-spin" aria-hidden /> : <span className="size-1.5 rounded-full bg-current" />}
            {STATUS[status].label}
        </span>
    );
}

export function AppIcon({ app, size = 32 }: { app: { icon: string | null; name: string }; size?: number }) {
    return <ServiceIcon name={app.icon ?? app.name.toLowerCase()} title={app.name} size={size} />;
}

export function usePoll(active: boolean, props: string, ms = 4000) {
    useEffect(() => {
        if (!active) return;
        const timer = window.setInterval(() => router.reload({ only: props.split(',') }), ms);

        return () => window.clearInterval(timer);
    }, [active, ms, props]);
}

export function Card({ className, children }: { className?: string; children: ReactNode }) {
    return <div className={cn('border-border bg-surface-1 rounded-xl border p-5', className)}>{children}</div>;
}

export function Meter({ label, used, limit, unit }: { label: string; used: number | null; limit: number; unit: string }) {
    const pct = used === null || limit <= 0 ? 0 : Math.min(100, Math.round((used / limit) * 100));

    return (
        <div className="space-y-1.5">
            <div className="flex items-baseline justify-between text-sm">
                <span className="text-fg-muted">{label}</span>
                <span className="text-fg font-medium">
                    {used === null ? '—' : used}
                    <span className="text-fg-muted font-normal">
                        {' '}
                        / {limit} {unit}
                    </span>
                </span>
            </div>
            <div
                className="bg-surface-3 h-2 overflow-hidden rounded-full"
                role="meter"
                aria-label={label}
                aria-valuenow={pct}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className={cn('h-full rounded-full', pct > 90 ? 'bg-danger' : pct > 75 ? 'bg-warning' : 'bg-primary')}
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

export function SettingUp({ status }: { status: string }) {
    const failed = status === 'error';

    return (
        <Card className="flex flex-col items-center gap-3 py-10 text-center">
            {failed ? (
                <>
                    <p className="text-lg font-semibold">Something went wrong setting up your space</p>
                    <p className="text-fg-muted max-w-md text-sm">We have been notified and will fix it. You don’t need to pay again.</p>
                </>
            ) : status === 'suspended' ? (
                <>
                    <p className="text-lg font-semibold">Your space is paused</p>
                    <p className="text-fg-muted max-w-md text-sm">Renew your plan to start it again. Your apps and data are kept.</p>
                    <Button variant="primary" asChild className="h-10 px-5">
                        <a href="/billing">Renew plan</a>
                    </Button>
                </>
            ) : (
                <>
                    <Loader2 className="text-primary size-8 animate-spin" aria-hidden />
                    <p className="text-lg font-semibold">Setting up your space…</p>
                    <p className="text-fg-muted max-w-md text-sm">
                        This usually takes about a minute. You can leave this page open; it updates by itself.
                    </p>
                </>
            )}
        </Card>
    );
}
