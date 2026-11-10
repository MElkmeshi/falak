import { Button, CopyButton, Dialog } from '@/components/falak';
import { Link, router } from '@inertiajs/react';
import { ExternalLink, LayoutGrid, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AppIcon, AppStatusBadge, Card, SettingUp, type SpaceApp, usePoll } from '../../components/space-bits';
import { SpaceLayout } from '../../components/space-layout';
import { type Plan, type Space } from '../../types';

interface Props {
    subscription: { plan: Plan; status: 'active' | 'expired' } | null;
    space: Space | null;
    apps: SpaceApp[];
}

const FIRST_VISIT: Record<string, string> = {
    n8n: 'First visit: n8n asks you to create the owner account. Use an email and password you will remember.',
    'uptime-kuma': 'First visit: Uptime Kuma asks you to create an admin account.',
    wordpress: 'First visit: WordPress walks you through choosing a site title and admin account.',
    ghost: 'Open /ghost on your address to create the admin account.',
};

export default function Apps({ subscription, space, apps }: Props) {
    const [busy, setBusy] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<SpaceApp | null>(null);
    const ready = space?.status === 'ready' && subscription?.status === 'active';

    usePoll(apps.some((app) => app.status === 'starting') || (subscription !== null && !ready), 'apps,space');

    const restart = (app: SpaceApp) => {
        setBusy(app.id);
        router.post(`/apps/${app.id}/restart`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    const destroy = () => {
        if (!deleting) return;
        setBusy(deleting.id);
        router.delete(`/apps/${deleting.id}`, { preserveScroll: true, onFinish: () => (setBusy(null), setDeleting(null)) });
    };

    return (
        <SpaceLayout
            title="My apps"
            description={subscription ? `${apps.length} of ${subscription.plan.max_apps} apps on your ${subscription.plan.name} plan` : undefined}
            actions={
                ready && (
                    <Button variant="primary" className="h-10 px-4" asChild>
                        <Link href="/store">
                            <LayoutGrid className="size-4" aria-hidden /> Add an app
                        </Link>
                    </Button>
                )
            }
        >
            {!subscription ? (
                <Card className="py-10 text-center">
                    <p className="text-lg font-semibold">You don’t have a plan yet</p>
                    <p className="text-fg-muted mt-1 text-sm">Choose a plan to get your space, then add apps here.</p>
                    <Button variant="primary" className="mt-4 h-10 px-5" asChild>
                        <Link href="/billing">See plans</Link>
                    </Button>
                </Card>
            ) : !ready && space ? (
                <SettingUp status={subscription.status === 'expired' ? 'suspended' : space.status} />
            ) : apps.length === 0 ? (
                <Card className="py-10 text-center">
                    <p className="text-lg font-semibold">No apps yet</p>
                    <p className="text-fg-muted mt-1 text-sm">Pick one from the App store. n8n is the most popular.</p>
                    <Button variant="primary" className="mt-4 h-10 px-5" asChild>
                        <Link href="/store">Open the App store</Link>
                    </Button>
                </Card>
            ) : (
                <div className="space-y-3">
                    {apps.map((app) => (
                        <Card key={app.id} className="space-y-4">
                            <div className="flex flex-wrap items-center gap-4">
                                <AppIcon app={app} size={40} />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-base font-semibold">{app.name}</p>
                                        <AppStatusBadge status={app.status} />
                                    </div>
                                    {app.url && (
                                        <div className="text-fg-muted mt-1 flex items-center gap-1 font-mono text-xs">
                                            <span className="truncate">{app.url.replace('https://', '')}</span>
                                            <CopyButton value={app.url} label="Copy address" />
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {app.url && app.status === 'running' ? (
                                        <Button variant="primary" className="h-9 px-4" asChild>
                                            <a href={app.url} target="_blank" rel="noreferrer">
                                                Open <ExternalLink className="size-3.5" aria-hidden />
                                            </a>
                                        </Button>
                                    ) : (
                                        <Button variant="primary" className="h-9 px-4" disabled>
                                            Open
                                        </Button>
                                    )}
                                    <Button
                                        className="h-9"
                                        onClick={() => restart(app)}
                                        loading={busy === app.id && !deleting}
                                        disabled={app.status === 'starting'}
                                    >
                                        <RotateCcw className="size-3.5" aria-hidden /> Restart
                                    </Button>
                                    <Button variant="ghost" className="h-9" onClick={() => setDeleting(app)} aria-label={`Delete ${app.name}`}>
                                        <Trash2 className="size-3.5" aria-hidden />
                                    </Button>
                                </div>
                            </div>
                            {app.status === 'starting' && (
                                <p className="text-fg-muted text-sm">Starting your app. This takes about a minute; the page updates by itself.</p>
                            )}
                            {app.status === 'failed' && <p className="text-danger text-sm">{app.error}</p>}
                            {app.status === 'running' && app.icon && FIRST_VISIT[app.icon] && (
                                <p className="text-fg-muted text-sm">{FIRST_VISIT[app.icon]}</p>
                            )}
                        </Card>
                    ))}
                </div>
            )}

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete ${deleting?.name ?? 'app'}?`}
                description="The app stops and its address goes away. Its saved data is kept on your space in case you add it again."
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setDeleting(null)}>
                            Cancel
                        </Button>
                        <Button variant="danger" onClick={destroy} loading={busy !== null}>
                            Delete
                        </Button>
                    </>
                }
            />
        </SpaceLayout>
    );
}
