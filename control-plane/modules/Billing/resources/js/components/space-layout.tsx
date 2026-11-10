import { useFlashToasts } from '@/components/falak/flash';
import { FalakMark } from '@/components/falak/logo';
import { Toaster } from '@/components/falak/toast';
import { TooltipProvider } from '@/components/falak/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AppWindow, CreditCard, House, LayoutGrid, LogOut, ShieldCheck, UserRound } from 'lucide-react';
import { type ReactNode, useEffect } from 'react';
import { billingOf } from '../types';

const NAV = [
    { href: '/home', label: 'Home', icon: House },
    { href: '/apps', label: 'My apps', icon: AppWindow },
    { href: '/store', label: 'App store', icon: LayoutGrid },
    { href: '/billing', label: 'Plan', icon: CreditCard },
    { href: '/account', label: 'Account', icon: UserRound },
];

export function SpaceLayout({
    title,
    description,
    actions,
    children,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    useFlashToasts();
    useEffect(() => initializeTheme(), []);
    const page = usePage<SharedData>();
    const billing = billingOf(page.props);
    const brand = billing?.brand ?? 'Falak Spaces';
    const path = page.url.split('?')[0];
    const active = (href: string) => path === href || path.startsWith(`${href}/`);

    return (
        <TooltipProvider delayDuration={300}>
            <Head title={title} />
            <div className="bg-bg text-fg min-h-svh md:flex">
                <aside className="border-border/60 bg-surface-1/40 hidden w-60 shrink-0 flex-col border-r md:flex">
                    <Link href="/home" className="flex h-14 items-center gap-2 px-5">
                        <FalakMark size={22} />
                        <span className="text-sm font-semibold">{brand}</span>
                    </Link>
                    <nav className="flex flex-1 flex-col gap-1 px-3 py-2">
                        {NAV.map(({ href, label, icon: Icon }) => (
                            <Link
                                key={href}
                                href={href}
                                className={cn(
                                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                                    active(href) ? 'bg-surface-3 text-fg font-medium' : 'text-fg-muted hover:bg-surface-2 hover:text-fg',
                                )}
                            >
                                <Icon className="size-4" aria-hidden />
                                {label}
                            </Link>
                        ))}
                        {billing?.can_operate && (
                            <Link
                                href="/billing/admin"
                                className="text-fg-muted hover:bg-surface-2 hover:text-fg mt-4 flex items-center gap-3 rounded-md px-3 py-2 text-sm"
                            >
                                <ShieldCheck className="size-4" aria-hidden />
                                Hosting admin
                            </Link>
                        )}
                    </nav>
                    <div className="border-border/60 border-t p-3">
                        <p className="text-fg truncate px-3 text-sm font-medium">{page.props.auth.user?.name}</p>
                        <p className="text-fg-muted truncate px-3 text-xs">{billing?.plan ? `${billing.plan} plan` : 'No plan yet'}</p>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="text-fg-muted hover:bg-surface-2 hover:text-fg mt-2 flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm"
                        >
                            <LogOut className="size-4" aria-hidden />
                            Log out
                        </Link>
                    </div>
                </aside>

                <div className="flex min-w-0 flex-1 flex-col pb-20 md:pb-0">
                    <header className="border-border/60 flex h-14 items-center gap-2 border-b px-4 md:hidden">
                        <FalakMark size={20} />
                        <span className="text-sm font-semibold">{brand}</span>
                    </header>
                    <main id="main" className="mx-auto w-full max-w-5xl flex-1 px-4 py-6 md:px-8 md:py-10">
                        <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                            <div className="space-y-1">
                                <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                                {description && <p className="text-fg-muted text-sm">{description}</p>}
                            </div>
                            {actions}
                        </div>
                        {children}
                    </main>
                </div>

                <nav className="border-border bg-bg fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t md:hidden">
                    {NAV.map(({ href, label, icon: Icon }) => (
                        <Link
                            key={href}
                            href={href}
                            className={cn('flex flex-col items-center gap-1 py-2 text-[11px]', active(href) ? 'text-primary' : 'text-fg-muted')}
                        >
                            <Icon className="size-5" aria-hidden />
                            {label}
                        </Link>
                    ))}
                </nav>
                <Toaster />
            </div>
        </TooltipProvider>
    );
}
