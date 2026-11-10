import { Button, Segmented, ServiceIcon, Tag } from '@/components/falak';
import { FalakMark } from '@/components/falak/logo';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CreditCard, Gauge, Globe, Lock, MousePointerClick, RotateCcw, Server, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { PlanCard } from '../components/plan-card';
import { type Plan, formatMemory, priceOf } from '../types';

interface App {
    slug: string;
    name: string;
    description: string;
    icon: string | null;
    min_memory_mb: number | null;
}

interface Props {
    brand: string;
    plans: Plan[];
    apps: App[];
    currencies: string[];
    payment_methods: string[];
    app_domain: string | null;
}

const STEPS = [
    {
        icon: CreditCard,
        title: 'Pick a plan and pay',
        body: 'Pay in Libyan dinars or Egyptian pounds. Your space starts as soon as the payment clears.',
    },
    { icon: Server, title: 'Get your own space', body: 'A private server space with its own CPU, memory and disk, ready about a minute after you pay.' },
    { icon: MousePointerClick, title: 'Deploy in one click', body: 'Launch n8n or another app, or push your own project. HTTPS is set up for you.' },
];

const FEATURES = [
    { icon: Lock, title: 'Isolated', body: 'Every customer gets a separate space. Other people’s apps cannot see or slow down yours.' },
    { icon: Gauge, title: 'Guaranteed limits', body: 'The CPU, memory and disk in your plan are reserved for you and enforced by the server.' },
    { icon: Globe, title: 'HTTPS included', body: 'Every app gets a free subdomain with a certificate. Bring your own domain whenever you want.' },
    { icon: RotateCcw, title: 'Nothing is lost', body: 'If a plan lapses your space pauses. Renew and your apps and data come back as they were.' },
];

const FAQ = [
    {
        q: 'What can I host?',
        a: 'Course projects (Laravel, Node, Python, static sites, Docker), databases, and ready-made apps like n8n, WordPress or Uptime Kuma.',
    },
    { q: 'Do I need to know Linux?', a: 'No. Deploy from the dashboard. A terminal is there if you want one.' },
    { q: 'Can I change plans?', a: 'Yes. Pay for a bigger plan and your space grows in place, with your apps and data kept.' },
    { q: 'What happens when the period ends?', a: 'Your space is paused, not deleted. Renew to start it again.' },
];

export default function Landing({ brand, plans, apps, currencies, payment_methods, app_domain }: Props) {
    const { auth, registration } = usePage<SharedData>().props;
    const [currency, setCurrency] = useState(currencies[0] ?? 'LYD');
    const n8n = apps.find((app) => app.slug === 'n8n');
    const n8nPlan = plans.find((plan) => n8n && plan.runs.includes(n8n.name));
    const cheapest = plans[0];
    const canSignUp = registration !== 'closed';
    const exampleHost = `my-n8n.${app_domain ?? 'your-space.app'}`;

    return (
        <div className="bg-bg text-fg min-h-svh">
            <Head title={`${brand}: your own server space`} />

            <header className="border-border/60 bg-bg/80 sticky top-0 z-10 border-b backdrop-blur">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                    <Link href="/" className="flex items-center gap-2">
                        <FalakMark size={22} />
                        <span className="text-sm font-semibold">{brand}</span>
                    </Link>
                    <nav className="flex items-center gap-1 sm:gap-2">
                        <a href="#plans" className="text-fg-muted hover:text-fg hidden px-2 text-sm sm:inline">
                            Plans
                        </a>
                        <a href="#apps" className="text-fg-muted hover:text-fg hidden px-2 text-sm sm:inline">
                            Apps
                        </a>
                        <a href="#faq" className="text-fg-muted hover:text-fg hidden px-2 text-sm sm:inline">
                            FAQ
                        </a>
                        {auth.user ? (
                            <Button size="sm" variant="primary" asChild>
                                <Link href="/home">Open dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button size="sm" variant="ghost" asChild>
                                    <Link href={route('login')}>Log in</Link>
                                </Button>
                                {canSignUp && (
                                    <Button size="sm" variant="primary" asChild>
                                        <Link href={route('billing.start')}>Get started</Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main>
                <section className="relative overflow-hidden">
                    <div
                        aria-hidden
                        className="bg-dotted pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_70%_60%_at_50%_30%,black,transparent)]"
                    />
                    <div className="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 md:grid-cols-[1.1fr_1fr] md:py-24">
                        <div className="space-y-6">
                            <Tag tone="accent">Pay in LYD or EGP</Tag>
                            <h1 className="text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl">
                                Your own server space, ready in minutes.
                            </h1>
                            <p className="text-fg-muted max-w-xl text-base text-pretty sm:text-lg">
                                Host your course projects and run n8n automations with one click. Real HTTPS, no server to manage
                                {cheapest ? `, from ${priceOf(cheapest, currency)} a month` : ''}.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Button variant="primary" className="h-10 px-4" asChild>
                                    <Link href={n8nPlan ? route('billing.start', n8nPlan.slug) : '#plans'}>
                                        Start with n8n
                                        <ArrowRight className="size-4" aria-hidden />
                                    </Link>
                                </Button>
                                <Button className="h-10 px-4" asChild>
                                    <a href="#plans">See plans</a>
                                </Button>
                            </div>
                            <ul className="text-fg-muted flex flex-wrap gap-x-5 gap-y-2 text-sm">
                                <li className="flex items-center gap-1.5">
                                    <ShieldCheck className="text-success size-4" aria-hidden />
                                    Private, isolated space
                                </li>
                                <li className="flex items-center gap-1.5">
                                    <ShieldCheck className="text-success size-4" aria-hidden />
                                    Free HTTPS subdomain
                                </li>
                                <li className="flex items-center gap-1.5">
                                    <ShieldCheck className="text-success size-4" aria-hidden />
                                    Data kept if you pause
                                </li>
                            </ul>
                        </div>

                        <div className="border-border bg-surface-1 rounded-xl border p-5 shadow-sm">
                            <div className="flex items-center gap-3">
                                <ServiceIcon name="n8n" title="n8n" size={36} />
                                <div>
                                    <p className="font-medium">One-click n8n</p>
                                    <p className="text-fg-muted text-sm">Workflow automation with 400+ integrations</p>
                                </div>
                                <Tag tone="accent" className="ml-auto">
                                    Popular
                                </Tag>
                            </div>
                            <ol className="mt-5 space-y-3 text-sm">
                                {['Choose a plan and pay', 'Wait about a minute for your space', 'Click “Deploy n8n”'].map((step, i) => (
                                    <li key={step} className="flex items-center gap-3">
                                        <span className="bg-surface-3 text-fg flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-medium">
                                            {i + 1}
                                        </span>
                                        {step}
                                    </li>
                                ))}
                            </ol>
                            <div className="border-border bg-bg mt-5 flex items-center gap-2 rounded-md border px-3 py-2 font-mono text-xs">
                                <Lock className="text-success size-3.5 shrink-0" aria-hidden />
                                <span className="truncate">https://{exampleHost}</span>
                            </div>
                            {n8nPlan && (
                                <p className="text-fg-muted mt-3 text-xs">
                                    n8n runs on the {n8nPlan.name} plan and up ({formatMemory(n8nPlan.memory_mb)} RAM), {priceOf(n8nPlan, currency)} a
                                    month.
                                </p>
                            )}
                        </div>
                    </div>
                </section>

                <section className="border-border/60 bg-surface-1/40 border-y">
                    <div className="mx-auto grid max-w-6xl gap-6 px-4 py-12 md:grid-cols-3">
                        {STEPS.map(({ icon: Icon, title, body }) => (
                            <div key={title} className="space-y-2">
                                <Icon className="text-primary size-5" aria-hidden />
                                <h2 className="font-medium">{title}</h2>
                                <p className="text-fg-muted text-sm">{body}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="plans" className="mx-auto max-w-6xl scroll-mt-16 px-4 py-16">
                    <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <div className="space-y-1">
                            <h2 className="text-2xl font-semibold tracking-tight">Plans</h2>
                            <p className="text-fg-muted text-sm">Every plan is a private space with reserved CPU, memory and disk.</p>
                        </div>
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
                                    <Button className="w-full" variant={plan.featured ? 'primary' : 'secondary'} asChild>
                                        <Link href={route('billing.start', plan.slug)}>Choose {plan.name}</Link>
                                    </Button>
                                }
                            />
                        ))}
                    </div>
                    {payment_methods.length > 0 && (
                        <p className="text-fg-muted mt-6 text-sm">
                            <span className="text-fg font-medium">Payment: </span>
                            {payment_methods.join(' · ')}
                        </p>
                    )}
                </section>

                {apps.length > 0 && (
                    <section id="apps" className="border-border/60 bg-surface-1/40 scroll-mt-16 border-y">
                        <div className="mx-auto max-w-6xl px-4 py-16">
                            <div className="mb-8 space-y-1">
                                <h2 className="text-2xl font-semibold tracking-tight">One-click apps</h2>
                                <p className="text-fg-muted text-sm">Ready to run in your space, each on its own HTTPS address.</p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {apps.map((app) => {
                                    const smallest = plans.find((plan) => plan.runs.includes(app.name));

                                    return (
                                        <div key={app.slug} className="border-border bg-bg flex flex-col gap-2 rounded-lg border p-4">
                                            <div className="flex items-center gap-2">
                                                <ServiceIcon name={app.icon ?? app.slug} title={app.name} size={24} />
                                                <span className="text-sm font-medium">{app.name}</span>
                                            </div>
                                            <p className="text-fg-muted line-clamp-2 text-xs">{app.description}</p>
                                            {smallest && <p className="text-fg-muted mt-auto text-xs">From the {smallest.name} plan</p>}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>
                )}

                <section className="mx-auto max-w-6xl px-4 py-16">
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {FEATURES.map(({ icon: Icon, title, body }) => (
                            <div key={title} className="space-y-2">
                                <Icon className="text-primary size-5" aria-hidden />
                                <h3 className="font-medium">{title}</h3>
                                <p className="text-fg-muted text-sm">{body}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="faq" className="border-border/60 scroll-mt-16 border-t">
                    <div className="mx-auto max-w-3xl px-4 py-16">
                        <h2 className="mb-6 text-2xl font-semibold tracking-tight">Questions</h2>
                        <div className="divide-border divide-y">
                            {FAQ.map(({ q, a }) => (
                                <details key={q} className="group py-4">
                                    <summary className="cursor-pointer list-none font-medium">{q}</summary>
                                    <p className="text-fg-muted mt-2 text-sm">{a}</p>
                                </details>
                            ))}
                        </div>
                    </div>
                </section>

                {canSignUp && !auth.user && (
                    <section className="mx-auto max-w-6xl px-4 pb-20">
                        <div className="border-border bg-surface-1 flex flex-col items-start justify-between gap-4 rounded-xl border p-6 sm:flex-row sm:items-center">
                            <div>
                                <p className="text-lg font-semibold">Ready to launch?</p>
                                <p className="text-fg-muted text-sm">Create an account, pick a plan and deploy your first app today.</p>
                            </div>
                            <Button variant="primary" className="h-10 px-4" asChild>
                                <Link href={route('billing.start')}>Get started</Link>
                            </Button>
                        </div>
                    </section>
                )}
            </main>

            <footer className="border-border/60 border-t">
                <div className="text-fg-muted mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-6 text-xs">
                    <span>
                        © {new Date().getFullYear()} {brand}
                    </span>
                    <span>Powered by Falak</span>
                </div>
            </footer>
        </div>
    );
}
