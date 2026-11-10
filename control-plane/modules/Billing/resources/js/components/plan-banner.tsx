import { Button } from '@/components/falak';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Rocket } from 'lucide-react';
import { billingOf } from '../types';

export function PlanBanner() {
    const { props, url } = usePage<SharedData>();
    const billing = billingOf(props);

    if (!billing || billing.operator || billing.active || url.startsWith('/billing')) return null;

    return (
        <div role="status" className="border-primary/30 bg-surface-2 text-fg border-b px-4 py-2 text-sm">
            <div className="mx-auto flex max-w-[1200px] flex-wrap items-center gap-x-3 gap-y-2">
                <Rocket className="text-primary size-4 shrink-0" aria-hidden />
                <p className="mr-auto min-w-0">
                    <span className="font-medium">{billing.plan ? 'Your plan has expired.' : 'Get your own space.'}</span>{' '}
                    <span className="text-fg-muted">
                        {billing.plan ? 'Renew to start your apps again.' : 'Pick a plan to deploy n8n and your apps with one click.'}
                    </span>
                </p>
                <Button asChild size="sm" variant="primary">
                    <Link href="/billing">{billing.plan ? 'Renew' : 'Choose a plan'}</Link>
                </Button>
            </div>
        </div>
    );
}
