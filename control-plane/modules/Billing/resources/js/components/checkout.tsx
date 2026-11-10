import { Button, Field, Input, Select } from '@/components/falak';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { type GatewayOption, type Plan, formatCpus, formatMemory, priceOf } from '../types';

export function Checkout({ plan, currency, gateways, onCancel }: { plan: Plan; currency: string; gateways: GatewayOption[]; onCancel: () => void }) {
    const form = useForm<{ plan_id: string; provider: string; currency: string; fields: Record<string, string> }>({
        plan_id: plan.id,
        provider: gateways[0]?.key ?? '',
        currency,
        fields: {},
    });
    const gateway = gateways.find((g) => g.key === form.data.provider);
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/billing/checkout');
    };

    return (
        <form onSubmit={submit} className="border-border bg-surface-1 space-y-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 className="text-fg text-base font-medium">Pay for {plan.name}</h3>
                    <p className="text-fg-muted text-sm">
                        {formatCpus(plan.cpus)} · {formatMemory(plan.memory_mb)} RAM · {plan.disk_gb} GB disk · {plan.period_days} days
                    </p>
                </div>
                <span className="text-fg text-xl font-semibold">{priceOf(plan, currency)}</span>
            </div>

            {gateways.length === 0 ? (
                <p className="text-fg-muted text-sm">No payment method takes {currency} right now. Try the other currency.</p>
            ) : (
                <>
                    <Field label="Payment method" error={errors.provider}>
                        <Select
                            value={form.data.provider}
                            onValueChange={(provider) => form.setData((d) => ({ ...d, provider, fields: {} }))}
                            options={gateways.map((g) => ({ value: g.key, label: g.label }))}
                            aria-label="Payment method"
                        />
                    </Field>
                    {gateway?.fields.map((field) => (
                        <Field key={field.name} label={field.label} error={errors[field.name] ?? errors[`fields.${field.name}`]}>
                            <Input
                                type={field.type}
                                placeholder={field.placeholder}
                                value={form.data.fields[field.name] ?? ''}
                                onChange={(e) => form.setData('fields', { ...form.data.fields, [field.name]: e.target.value })}
                                required
                            />
                        </Field>
                    ))}
                    {errors.plan && <p className="text-danger text-sm">{errors.plan}</p>}
                    {errors.currency && <p className="text-danger text-sm">{errors.currency}</p>}
                </>
            )}

            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit" variant="primary" loading={form.processing} disabled={!gateway}>
                    Pay {priceOf(plan, currency)}
                </Button>
            </div>
        </form>
    );
}
