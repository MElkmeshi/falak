import { registerCommands, registerNavigation, registerSettingsNav, registerShellBanners } from '@/lib/registry';
import { CreditCard, ShieldCheck } from 'lucide-react';
import { PlanBanner } from './components/plan-banner';
import { billingOf } from './types';

registerNavigation({
    id: 'billing',
    title: 'Plan & space',
    url: '/billing',
    icon: CreditCard,
    order: 50,
    permission: 'billing.view',
    activePrefix: '/billing',
});

registerShellBanners({ id: 'billing.plan', order: 50, permission: 'billing.view', component: PlanBanner });

registerSettingsNav({
    id: 'billing',
    title: 'Plan & billing',
    url: '/billing',
    group: 'organization',
    order: 20,
    icon: CreditCard,
    permission: 'billing.view',
    requiresOrganization: true,
    keywords: ['plan', 'payment', 'subscription', 'space', 'n8n'],
});

registerSettingsNav({
    id: 'billing-admin',
    title: 'Hosting admin',
    url: '/billing/admin',
    group: 'organization',
    order: 21,
    icon: ShieldCheck,
    permission: 'billing.operate',
    requiresOrganization: true,
    when: (ctx) => billingOf(ctx.props)?.can_operate === true,
    keywords: ['customers', 'spaces', 'payments', 'plans'],
});

registerCommands({
    id: 'billing',
    commands: (ctx) => [
        {
            id: 'billing.open',
            title: 'Plan & space',
            group: 'Navigation',
            href: '/billing',
            icon: CreditCard,
            keywords: ['billing', 'plan', 'pay', 'n8n'],
        },
        ...(billingOf(ctx.props)?.can_operate
            ? [
                  {
                      id: 'billing.admin',
                      title: 'Hosting admin',
                      group: 'Navigation',
                      href: '/billing/admin',
                      icon: ShieldCheck,
                      keywords: ['customers', 'spaces'],
                  },
              ]
            : []),
    ],
});
