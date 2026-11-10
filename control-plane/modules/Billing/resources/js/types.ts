import { type SharedData } from '@/types';

export interface Plan {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    price_lyd: string;
    price_egp: string;
    period_days: number;
    cpus: number;
    memory_mb: number;
    disk_gb: number;
    max_apps: number;
    max_databases: number;
    featured: boolean;
    active: boolean;
    runs: string[];
}

export interface Space {
    id: string;
    status: 'provisioning' | 'enrolling' | 'ready' | 'suspended' | 'error';
    status_label: string;
    server_id: string | null;
    instance: string;
    ipv4: string | null;
    cpus: number;
    memory_mb: number;
    disk_gb: number;
    error: string | null;
}

export interface PaymentRow {
    id: string;
    organization_id: string;
    organization?: string;
    plan: string | null;
    provider: string;
    amount: string;
    currency: string;
    status: 'pending' | 'awaiting_otp' | 'awaiting_review' | 'paid' | 'failed' | 'rejected';
    payer: string | null;
    reference: string | null;
    failure: string | null;
    paid_at: string | null;
    created_at: string | null;
}

export interface GatewayOption {
    key: string;
    label: string;
    fields: { name: string; label: string; type: string; placeholder?: string }[];
}

export interface BillingShared {
    operator: boolean;
    customer: boolean;
    brand: string;
    can_operate: boolean;
    active: boolean;
    plan: string | null;
    ends_at: string | null;
}

export function billingOf(props: SharedData): BillingShared | null {
    return ((props as unknown as { billing?: BillingShared | null }).billing ?? null) as BillingShared | null;
}

export function formatMemory(mb: number): string {
    return mb >= 1024 ? `${+(mb / 1024).toFixed(1)} GB` : `${mb} MB`;
}

export function formatCpus(cpus: number): string {
    return cpus === 1 ? '1 vCPU' : `${+cpus.toFixed(2)} vCPU`;
}

export function priceOf(plan: Plan, currency: string): string {
    return `${currency === 'EGP' ? plan.price_egp : plan.price_lyd} ${currency}`;
}

export const PAYMENT_TONE: Record<PaymentRow['status'], 'success' | 'warning' | 'danger' | 'info' | 'faint'> = {
    pending: 'faint',
    awaiting_otp: 'info',
    awaiting_review: 'warning',
    paid: 'success',
    failed: 'danger',
    rejected: 'danger',
};
