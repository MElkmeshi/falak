import { Button, Callout, Field, Input, Section, StatusPill } from '@/components/falak';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { SpaceLayout } from '../components/space-layout';
import { type PaymentRow, PAYMENT_TONE } from '../types';

interface Props {
    payment: PaymentRow;
    step: { kind: string; message: string; otp_length: number } | null;
}

export default function Payment({ payment, step }: Props) {
    const form = useForm({ otp: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/billing/payments/${payment.id}/confirm`, { preserveScroll: true, onSuccess: () => form.reset('otp') });
    };

    return (
        <SpaceLayout
            title={`${payment.plan ?? 'Plan'} · ${payment.amount} ${payment.currency}`}
            description={`Paid with ${payment.provider}${payment.reference ? ` · ref ${payment.reference}` : ''}`}
            actions={<StatusPill status={payment.status} label={payment.status.replace('_', ' ')} tone={PAYMENT_TONE[payment.status]} />}
        >
            <div className="max-w-[640px] space-y-6">
                {payment.status === 'awaiting_otp' && (
                    <Section title="Enter the code" description={step?.message}>
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <Field label="Code" error={form.errors.otp} className="w-48">
                                <Input
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={step?.otp_length ?? 6}
                                    value={form.data.otp}
                                    onChange={(e) => form.setData('otp', e.target.value)}
                                    autoFocus
                                    required
                                />
                            </Field>
                            <Button type="submit" variant="primary" loading={form.processing}>
                                Confirm payment
                            </Button>
                        </form>
                    </Section>
                )}

                {payment.status === 'awaiting_review' && (
                    <Callout tone="warning" title="Waiting for review">
                        {step?.message ?? 'We will activate your plan as soon as we confirm the transfer.'}
                    </Callout>
                )}

                {payment.status === 'paid' && (
                    <Callout
                        tone="success"
                        title="Payment received"
                        action={
                            <Button asChild variant="primary" size="sm">
                                <Link href="/home">Go to my space</Link>
                            </Button>
                        }
                    >
                        Your plan is active. Your private space will be ready in about a minute.
                    </Callout>
                )}

                {(payment.status === 'failed' || payment.status === 'rejected') && (
                    <Callout
                        tone="danger"
                        title={payment.status === 'rejected' ? 'Payment rejected' : 'Payment failed'}
                        action={
                            <Button asChild size="sm">
                                <Link href="/billing">Try again</Link>
                            </Button>
                        }
                    >
                        {payment.failure ?? 'The payment did not go through.'}
                    </Callout>
                )}
            </div>
        </SpaceLayout>
    );
}
