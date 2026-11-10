import { Button, Input, PageHeader, Section, StatusPill, Switch, Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/falak';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { type PaymentRow, type Plan, type Space, PAYMENT_TONE, formatCpus, formatMemory } from '../../types';

interface AdminSpace extends Space {
    organization: string;
    plan: string | null;
    ends_at: string | null;
}

interface Props {
    plans: Plan[];
    payments: (PaymentRow & { organization: string })[];
    spaces: AdminSpace[];
    pending_reviews: number;
}

const NUMERIC: (keyof Plan)[] = ['price_lyd', 'price_egp', 'period_days', 'cpus', 'memory_mb', 'disk_gb', 'max_apps', 'max_databases'];

function PlanRow({ plan }: { plan: Plan }) {
    const form = useForm({
        name: plan.name,
        description: plan.description ?? '',
        price_lyd: plan.price_lyd,
        price_egp: plan.price_egp,
        period_days: String(plan.period_days),
        cpus: String(plan.cpus),
        memory_mb: String(plan.memory_mb),
        disk_gb: String(plan.disk_gb),
        max_apps: String(plan.max_apps),
        max_databases: String(plan.max_databases),
        featured: plan.featured,
        active: plan.active,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/billing/admin/plans/${plan.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="border-border space-y-3 rounded-lg border p-3">
            <div className="flex flex-wrap items-center gap-3">
                <Input className="w-40" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} aria-label="Name" />
                <span className="text-fg-muted font-mono text-xs">{plan.slug}</span>
                <label className="text-fg-muted ml-auto flex items-center gap-2 text-xs">
                    Featured <Switch checked={form.data.featured} onCheckedChange={(v) => form.setData('featured', v)} />
                </label>
                <label className="text-fg-muted flex items-center gap-2 text-xs">
                    On sale <Switch checked={form.data.active} onCheckedChange={(v) => form.setData('active', v)} />
                </label>
                <Button type="submit" size="sm" variant="primary" loading={form.processing}>
                    Save
                </Button>
            </div>
            <Input
                value={form.data.description}
                onChange={(e) => form.setData('description', e.target.value)}
                aria-label="Description"
                placeholder="Description"
            />
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8">
                {NUMERIC.map((key) => (
                    <label key={key} className="text-fg-muted space-y-1 text-xs">
                        <span>{key.replace('_', ' ')}</span>
                        <Input
                            inputMode="decimal"
                            value={String(form.data[key as keyof typeof form.data])}
                            onChange={(e) => form.setData(key as never, e.target.value as never)}
                        />
                    </label>
                ))}
            </div>
            {Object.values(form.errors).length > 0 && <p className="text-danger text-xs">{Object.values(form.errors).join(' ')}</p>}
        </form>
    );
}

export default function AdminIndex({ plans, payments, spaces, pending_reviews }: Props) {
    const act = (url: string, data: Record<string, string> = {}) => router.post(url, data, { preserveScroll: true });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Plan & space', href: '/billing' },
                { title: 'Hosting admin', href: '/billing/admin' },
            ]}
        >
            <Head title="Hosting admin" />
            <div className="mx-auto max-w-[1200px] space-y-6 p-4">
                <PageHeader title="Hosting admin" description="Customers' spaces, payments waiting for review, and the plans on sale." />

                <Tabs defaultValue={pending_reviews > 0 ? 'payments' : 'spaces'}>
                    <TabsList>
                        <TabsTrigger value="spaces">Spaces ({spaces.length})</TabsTrigger>
                        <TabsTrigger value="payments">Payments{pending_reviews > 0 ? ` (${pending_reviews} to review)` : ''}</TabsTrigger>
                        <TabsTrigger value="plans">Plans</TabsTrigger>
                    </TabsList>

                    <TabsContent value="spaces" className="pt-4">
                        <Section title="" bare>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Plan</TableHead>
                                        <TableHead>Limits</TableHead>
                                        <TableHead>Paid until</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Address</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {spaces.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-fg-muted text-center">
                                                No spaces yet.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {spaces.map((s) => (
                                        <TableRow key={s.id}>
                                            <TableCell className="font-medium">{s.organization}</TableCell>
                                            <TableCell>{s.plan ?? '—'}</TableCell>
                                            <TableCell className="text-xs">
                                                {formatCpus(s.cpus)} · {formatMemory(s.memory_mb)} · {s.disk_gb} GB
                                            </TableCell>
                                            <TableCell>{s.ends_at ? new Date(s.ends_at).toLocaleDateString() : '—'}</TableCell>
                                            <TableCell>
                                                <StatusPill status={s.status} label={s.status_label} />
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {s.instance}
                                                <br />
                                                {s.ipv4 ?? '—'}
                                            </TableCell>
                                            <TableCell className="space-x-1 text-right">
                                                {s.status === 'suspended' ? (
                                                    <Button size="sm" onClick={() => act(`/billing/admin/spaces/${s.id}/resume`)}>
                                                        Resume
                                                    </Button>
                                                ) : (
                                                    <Button size="sm" onClick={() => act(`/billing/admin/spaces/${s.id}/suspend`)}>
                                                        Suspend
                                                    </Button>
                                                )}
                                                <Button size="sm" variant="ghost" onClick={() => act(`/billing/admin/spaces/${s.id}/reprovision`)}>
                                                    Reprovision
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Section>
                    </TabsContent>

                    <TabsContent value="payments" className="pt-4">
                        <Section title="" bare>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Plan</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Payer / reference</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Review</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payments.map((p) => (
                                        <TableRow key={p.id}>
                                            <TableCell>{p.created_at ? new Date(p.created_at).toLocaleString() : '—'}</TableCell>
                                            <TableCell>{p.organization}</TableCell>
                                            <TableCell>{p.plan}</TableCell>
                                            <TableCell>{p.provider}</TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {p.payer ?? '—'}
                                                <br />
                                                {p.reference ?? ''}
                                            </TableCell>
                                            <TableCell>
                                                {p.amount} {p.currency}
                                            </TableCell>
                                            <TableCell>
                                                <StatusPill status={p.status} label={p.status.replace('_', ' ')} tone={PAYMENT_TONE[p.status]} />
                                            </TableCell>
                                            <TableCell className="space-x-1 text-right">
                                                {p.status === 'awaiting_review' && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            variant="primary"
                                                            onClick={() => act(`/billing/admin/payments/${p.id}/approve`)}
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="danger"
                                                            onClick={() => act(`/billing/admin/payments/${p.id}/reject`)}
                                                        >
                                                            Reject
                                                        </Button>
                                                    </>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Section>
                    </TabsContent>

                    <TabsContent value="plans" className="space-y-3 pt-4">
                        {plans.map((plan) => (
                            <PlanRow key={plan.id} plan={plan} />
                        ))}
                        <p className="text-fg-muted text-xs">
                            New limits apply to a customer's space at their next payment, or right away with Reprovision. See also{' '}
                            <Link href="/billing" className="text-primary hover:underline">
                                the customer view
                            </Link>
                            .
                        </p>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
