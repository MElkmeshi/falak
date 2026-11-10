import { Button, Tag } from '@/components/falak';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppIcon, Card, type StoreApp } from '../../components/space-bits';
import { SpaceLayout } from '../../components/space-layout';
import { type Plan, type Space, formatMemory } from '../../types';

interface Props {
    subscription: { plan: Plan; status: 'active' | 'expired' } | null;
    space: Space | null;
    store: StoreApp[];
    app_count: number;
}

export default function Store({ subscription, space, store, app_count }: Props) {
    const [deploying, setDeploying] = useState<string | null>(null);
    const ready = space?.status === 'ready' && subscription?.status === 'active';
    const full = subscription !== null && app_count >= subscription.plan.max_apps;

    const deploy = (slug: string) => {
        setDeploying(slug);
        router.post(`/billing/apps/${slug}`, {}, { onFinish: () => setDeploying(null) });
    };

    const blocker = !subscription
        ? { text: 'Choose a plan first', href: '/billing', cta: 'See plans' }
        : subscription.status !== 'active'
          ? { text: 'Your plan has expired', href: '/billing', cta: 'Renew' }
          : !ready
            ? { text: 'Your space is still being set up. Apps can be added in about a minute.', href: '/home', cta: 'Check progress' }
            : full
              ? {
                    text: `You are using all ${subscription.plan.max_apps} apps of your plan. Delete one or upgrade to add more.`,
                    href: '/billing',
                    cta: 'Upgrade',
                }
              : null;

    return (
        <SpaceLayout title="App store" description="Ready-made apps. One click, and each gets its own secure web address.">
            {blocker && (
                <Card className="mb-6 flex flex-wrap items-center justify-between gap-3 p-4">
                    <p className="text-sm">{blocker.text}</p>
                    <Button variant="primary" asChild>
                        <Link href={blocker.href}>{blocker.cta}</Link>
                    </Button>
                </Card>
            )}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {store.map((app) => (
                    <Card key={app.slug} className="flex flex-col gap-3">
                        <div className="flex items-center gap-3">
                            <AppIcon app={app} size={36} />
                            <p className="font-semibold">{app.name}</p>
                            {app.slug === 'n8n' && (
                                <Tag tone="accent" className="ml-auto">
                                    Popular
                                </Tag>
                            )}
                        </div>
                        <p className="text-fg-muted line-clamp-3 text-sm">{app.description}</p>
                        <div className="mt-auto pt-1">
                            {app.fits ? (
                                <Button
                                    variant={app.slug === 'n8n' ? 'primary' : 'secondary'}
                                    className="h-10 w-full"
                                    onClick={() => deploy(app.slug)}
                                    loading={deploying === app.slug}
                                    disabled={blocker !== null || deploying !== null}
                                >
                                    Deploy {app.name}
                                </Button>
                            ) : (
                                <div className="space-y-2">
                                    <p className="text-fg-muted text-xs">
                                        Needs{' '}
                                        {app.min_memory_mb ? `more than ${formatMemory(subscription?.plan.memory_mb ?? 0)} memory` : 'a bigger plan'}
                                        {app.needs_plan ? `: the ${app.needs_plan} plan or bigger.` : '.'}
                                    </p>
                                    <Button className="h-10 w-full" asChild>
                                        <Link href="/billing">Upgrade plan</Link>
                                    </Button>
                                </div>
                            )}
                        </div>
                    </Card>
                ))}
            </div>
        </SpaceLayout>
    );
}
