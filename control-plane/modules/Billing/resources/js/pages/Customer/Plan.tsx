import { Button, Segmented } from '@/components/falak';
import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Checkout } from '../../components/checkout';
import { PlanCard } from '../../components/plan-card';
import { Card } from '../../components/space-bits';
import { SpaceLayout } from '../../components/space-layout';
import { type GatewayOption, type PaymentRow, type Plan, formatCpus, formatMemory } from '../../types';

interface Props {
    subscription: { plan: Plan; status: 'active' | 'expired'; ends_at: string | null } | null;
    plans: Plan[];
    payments: PaymentRow[];
    gateways: GatewayOption[];
    currencies: string[];
    selected_plan: string | null;
    can_manage: boolean;
}

const PAYMENT_LABEL: Record<PaymentRow['status'], { label: string; className: string }> = {
    pending: { label: 'Started', className: 'text-fg-muted' },
    awaiting_otp: { label: 'Waiting for code', className: 'text-warning' },
    awaiting_review: { label: 'Being checked', className: 'text-warning' },
    awaiting_gateway: { label: 'Waiting for the bank', className: 'text-info' },
    paid: { label: 'Paid', className: 'text-success' },
    failed: { label: 'Failed', className: 'text-danger' },
    rejected: { label: 'Rejected', className: 'text-danger' },
};

export default function PlanPage({ subscription, plans, payments, gateways, currencies, selected_plan, can_manage }: Props) {
    const [currency, setCurrency] = useState(currencies[0] ?? 'LYD');
    const [choosing, setChoosing] = useState<Plan | null>(() => plans.find((p) => p.slug === selected_plan && p.active) ?? null);
    const checkoutRef = useRef<HTMLDivElement>(null);
    const current = subscription?.plan;

    useEffect(() => {
        if (choosing) checkoutRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, [choosing]);

    const pending = payments.find((p) => p.status === 'awaiting_otp' || p.status === 'awaiting_review' || p.status === 'awaiting_gateway');

    return (
        <SpaceLayout title="Plan" description="Pay for 30 days at a time. Renewing adds 30 more days; your apps keep running.">
            <div className="space-y-8">
                {pending && (
                    <Card className="flex flex-wrap items-center justify-between gap-3 p-4">
                        <p className="text-sm">
                            You have a payment of {pending.amount} {pending.currency} that is {PAYMENT_LABEL[pending.status].label.toLowerCase()}.
                        </p>
                        <Button variant="primary" asChild>
                            <Link href={`/billing/payments/${pending.id}`}>Continue</Link>
                        </Button>
                    </Card>
                )}

                {current && subscription && (
                    <Card className="flex flex-wrap items-center justify-between gap-4">
                        <div className="space-y-1">
                            <p className="text-fg-muted text-sm">Your plan</p>
                            <p className="text-xl font-semibold">{current.name}</p>
                            <p className="text-fg-muted text-sm">
                                {formatCpus(current.cpus)} · {formatMemory(current.memory_mb)} memory · {current.disk_gb} GB disk · up to{' '}
                                {current.max_apps} apps
                            </p>
                            <p className={subscription.status === 'active' ? 'text-sm' : 'text-danger text-sm'}>
                                {subscription.status === 'active' ? 'Active until' : 'Expired on'}{' '}
                                {subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString(undefined, { dateStyle: 'long' }) : '—'}
                            </p>
                        </div>
                        {can_manage && (
                            <Button variant="primary" className="h-10 px-5" onClick={() => setChoosing(current)}>
                                Renew {current.name}
                            </Button>
                        )}
                    </Card>
                )}

                {can_manage && (
                    <section className="space-y-4">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <h2 className="text-lg font-semibold">{current ? 'Change plan' : 'Choose a plan'}</h2>
                            {currencies.length > 1 && (
                                <Segmented
                                    label="Currency"
                                    size="md"
                                    value={currency}
                                    onValueChange={setCurrency}
                                    options={currencies.map((c) => ({ value: c, label: c }))}
                                />
                            )}
                        </div>
                        <div className="grid gap-4 pt-2 md:grid-cols-3">
                            {plans.map((plan) => (
                                <PlanCard
                                    key={plan.id}
                                    plan={plan}
                                    currency={currency}
                                    action={
                                        <Button
                                            className="h-10 w-full"
                                            variant={plan.featured ? 'primary' : 'secondary'}
                                            onClick={() => setChoosing(plan)}
                                            disabled={!plan.active}
                                        >
                                            {current?.id === plan.id ? 'Renew' : current ? `Switch to ${plan.name}` : `Choose ${plan.name}`}
                                        </Button>
                                    }
                                />
                            ))}
                        </div>
                        <div ref={checkoutRef} className="scroll-mt-6">
                            {choosing && (
                                <Checkout
                                    key={`${choosing.id}-${currency}`}
                                    plan={choosing}
                                    currency={currency}
                                    gateways={gateways.filter((g) => g.currencies.includes(currency))}
                                    onCancel={() => setChoosing(null)}
                                />
                            )}
                        </div>
                    </section>
                )}

                {payments.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">Payments</h2>
                        <Card className="divide-border divide-y p-0">
                            {payments.map((p) => (
                                <Link
                                    key={p.id}
                                    href={`/billing/payments/${p.id}`}
                                    className="hover:bg-surface-2 flex items-center justify-between gap-3 px-5 py-3 text-sm"
                                >
                                    <span>
                                        {p.plan} · {p.amount} {p.currency}
                                        <span className="text-fg-muted ml-2">{p.created_at ? new Date(p.created_at).toLocaleDateString() : ''}</span>
                                    </span>
                                    <span className={PAYMENT_LABEL[p.status].className}>{PAYMENT_LABEL[p.status].label}</span>
                                </Link>
                            ))}
                        </Card>
                    </section>
                )}
            </div>
        </SpaceLayout>
    );
}
