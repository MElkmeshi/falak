import { Button } from '@/components/falak';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { PlanCard } from '../../components/plan-card';
import { AppIcon, AppStatusBadge, Card, Meter, SettingUp, type SpaceApp, type StoreApp, usePoll } from '../../components/space-bits';
import { SpaceLayout } from '../../components/space-layout';
import { type Plan, type Space, formatMemory } from '../../types';

interface Props {
    subscription: { plan: Plan; status: 'active' | 'expired'; ends_at: string | null } | null;
    space: Space | null;
    usage: { memory_mb: number | null; disk_gb: number | null; running: boolean } | null;
    apps: SpaceApp[];
    store: StoreApp[];
    plans: Plan[];
}

export default function Home({ subscription, space, usage, apps, store, plans }: Props) {
    const { auth } = usePage<SharedData>().props;
    const firstName = auth.user?.name?.split(' ')[0] ?? 'there';
    const ready = space?.status === 'ready' && subscription?.status === 'active';
    const settingUp = subscription !== null && !ready;
    const n8n = store.find((app) => app.slug === 'n8n');
    const hasN8n = apps.some((app) => app.icon === 'n8n');
    const [deploying, setDeploying] = useState(false);

    usePoll(settingUp || apps.some((app) => app.status === 'starting'), 'space,subscription,apps,usage');

    const deployN8n = () => {
        setDeploying(true);
        router.post('/billing/apps/n8n', {}, { onFinish: () => setDeploying(false) });
    };

    if (!subscription) {
        return (
            <SpaceLayout
                title={`Welcome, ${firstName}!`}
                description="Pick a plan to get your own private space. You can deploy apps right after paying."
            >
                <div className="grid gap-4 pt-2 md:grid-cols-3">
                    {plans.map((plan) => (
                        <PlanCard
                            key={plan.id}
                            plan={plan}
                            currency="LYD"
                            action={
                                <Button className="h-10 w-full" variant={plan.featured ? 'primary' : 'secondary'} asChild>
                                    <Link href={`/billing?plan=${plan.slug}`}>Choose {plan.name}</Link>
                                </Button>
                            }
                        />
                    ))}
                </div>
            </SpaceLayout>
        );
    }

    return (
        <SpaceLayout title={`Hi, ${firstName}`} description={ready ? 'Your space is running.' : undefined}>
            <div className="space-y-6">
                {settingUp && space && <SettingUp status={subscription.status === 'expired' ? 'suspended' : space.status} />}
                {settingUp && !space && <SettingUp status="provisioning" />}

                {ready && apps.length === 0 && (
                    <Card className="flex flex-col items-start gap-4 p-6 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-4">
                            <AppIcon app={{ icon: 'n8n', name: 'n8n' }} size={44} />
                            <div>
                                <p className="text-lg font-semibold">Your space is ready. Launch your first app!</p>
                                <p className="text-fg-muted text-sm">n8n is ready in about a minute, with its own secure web address.</p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {n8n?.fits ? (
                                <Button variant="primary" className="h-10 px-5" onClick={deployN8n} loading={deploying}>
                                    Deploy n8n
                                </Button>
                            ) : null}
                            <Button className="h-10 px-5" asChild>
                                <Link href="/store">Browse apps</Link>
                            </Button>
                        </div>
                    </Card>
                )}

                {apps.length > 0 && (
                    <section className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold">My apps</h2>
                            <Link href="/apps" className="text-primary flex items-center gap-1 text-sm hover:underline">
                                Manage <ArrowRight className="size-3.5" aria-hidden />
                            </Link>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {apps.map((app) => (
                                <Card key={app.id} className="flex items-center gap-3 p-4">
                                    <AppIcon app={app} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">{app.name}</p>
                                        <AppStatusBadge status={app.status} />
                                    </div>
                                    {app.url && app.status === 'running' && (
                                        <Button variant="primary" asChild>
                                            <a href={app.url} target="_blank" rel="noreferrer">
                                                Open <ExternalLink className="size-3.5" aria-hidden />
                                            </a>
                                        </Button>
                                    )}
                                </Card>
                            ))}
                        </div>
                        {ready && !hasN8n && n8n?.fits && (
                            <Button onClick={deployN8n} loading={deploying}>
                                Add n8n
                            </Button>
                        )}
                    </section>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <h2 className="text-lg font-semibold">{subscription.plan.name} plan</h2>
                            <Link href="/billing" className="text-primary text-sm hover:underline">
                                {subscription.status === 'active' ? 'Renew or upgrade' : 'Renew now'}
                            </Link>
                        </div>
                        <p className="text-fg-muted text-sm">
                            {subscription.status === 'active' ? 'Active until' : 'Expired on'}{' '}
                            {subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString(undefined, { dateStyle: 'long' }) : '—'}
                        </p>
                        <p className="text-fg-muted text-sm">
                            {apps.length} of {subscription.plan.max_apps} apps used
                        </p>
                    </Card>
                    <Card className="space-y-4">
                        <h2 className="text-lg font-semibold">Space usage</h2>
                        <Meter label="Memory" used={usage?.memory_mb ?? null} limit={subscription.plan.memory_mb} unit="MB" />
                        <Meter label="Disk" used={usage?.disk_gb ?? null} limit={subscription.plan.disk_gb} unit="GB" />
                        <p className="text-fg-muted text-xs">
                            {formatMemory(subscription.plan.memory_mb)} memory and {subscription.plan.disk_gb} GB disk are reserved for you.
                        </p>
                    </Card>
                </div>
            </div>
        </SpaceLayout>
    );
}
