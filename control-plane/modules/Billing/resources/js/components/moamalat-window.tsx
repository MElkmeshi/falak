import { Button, Callout } from '@/components/falak';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface LightboxConfig {
    MID: string;
    TID: string;
    AmountTrxn: number;
    MerchantReference: string;
    TrxDateTime: number;
    SecureHash: string;
}

interface LightboxResult {
    NetworkReference?: string;
}

interface MoamalatLightbox {
    Checkout: {
        configure: LightboxConfig & {
            completeCallback: (data: LightboxResult) => void;
            errorCallback: (error: unknown) => void;
            cancelCallback: () => void;
        };
        showLightbox: () => void;
    };
}

type State = 'loading' | 'open' | 'closed' | 'confirming' | 'error';

const loadScript = (src: string) =>
    new Promise<void>((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) return resolve();
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('The card window could not load.'));
        document.head.appendChild(script);
    });

export function MoamalatWindow({ paymentId }: { paymentId: string }) {
    const [state, setState] = useState<State>('loading');
    const [error, setError] = useState<string | null>(null);
    const completed = useRef(false);

    const open = useCallback(async () => {
        setState('loading');
        setError(null);
        completed.current = false;

        try {
            const response = await fetch(`/billing/payments/${paymentId}/moamalat`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('This payment is no longer open.');
            const { script, config } = (await response.json()) as { script: string; config: LightboxConfig };
            await loadScript(script);
            const lightbox = (window as unknown as { Lightbox?: MoamalatLightbox }).Lightbox;
            if (!lightbox) throw new Error('The card window could not load.');

            lightbox.Checkout.configure = {
                ...config,
                completeCallback: (data) => {
                    completed.current = true;
                    setState('confirming');
                    router.post(`/billing/payments/${paymentId}/moamalat`, { network_reference: data.NetworkReference ?? null }, { preserveScroll: true });
                },
                errorCallback: () => {
                    setError('The bank did not accept the card. You can try again or use another card.');
                    setState('error');
                },
                cancelCallback: () => {
                    if (!completed.current) setState('closed');
                },
            };
            lightbox.Checkout.showLightbox();
            setState('open');
        } catch (e) {
            setError(e instanceof Error ? e.message : 'The card window could not load.');
            setState('error');
        }
    }, [paymentId]);

    useEffect(() => {
        void open();
    }, [open]);

    if (state === 'confirming') {
        return <Callout tone="info" title="Checking with the bank">Hold on, we are confirming your payment.</Callout>;
    }

    return (
        <Callout
            tone={state === 'error' ? 'danger' : 'info'}
            title={state === 'error' ? 'Card payment did not go through' : 'Pay with your bank card'}
            action={
                <Button size="sm" variant="primary" onClick={() => void open()} loading={state === 'loading'}>
                    {state === 'open' ? 'Reopen the card window' : 'Open the card window'}
                </Button>
            }
        >
            {error ?? 'Enter your card details in the Moamalat window. Your plan starts as soon as the bank approves the payment.'}
        </Callout>
    );
}
