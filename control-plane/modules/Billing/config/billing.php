<?php

return [
    'currencies' => ['LYD', 'EGP'],

    'operator_organization' => env('FALAK_BILLING_OPERATOR_ORG'),

    'operator_from_recovery' => (bool) env('FALAK_BILLING_OPERATOR_FROM_DR', false),

    'incus' => [
        'url' => env('FALAK_INCUS_URL', 'https://172.18.0.1:8443'),
        'cert' => env('FALAK_INCUS_CERT', '/opt/falak/secrets/incus/client.crt'),
        'key' => env('FALAK_INCUS_KEY', '/opt/falak/secrets/incus/client.key'),
        'project' => env('FALAK_INCUS_PROJECT', 'spaces'),
        'image' => env('FALAK_INCUS_IMAGE', 'falak-space'),
        'pool' => env('FALAK_INCUS_POOL', 'tenants'),
        'network' => env('FALAK_INCUS_NETWORK', 'incusbr0'),
    ],

    'router' => [
        'admin_url' => env('FALAK_SPACE_ROUTER_ADMIN', 'http://space-router:2019'),
        'https_listen' => ':8443',
        'http_listen' => ':8080',
    ],

    'landing' => (bool) env('FALAK_BILLING_LANDING', true),

    'brand' => env('FALAK_BILLING_BRAND', 'Falak Spaces'),

    'space_overhead_mb' => (int) env('FALAK_BILLING_SPACE_OVERHEAD_MB', 768),

    'one_click' => array_values(array_filter(explode(',', (string) env('FALAK_BILLING_ONE_CLICK', 'n8n,uptime-kuma,wordpress,ghost,metabase,nocodb,minio,plausible')))),

    'providers' => [
        'demo' => [
            'enabled' => (bool) env('FALAK_PAY_DEMO', true),
            'label' => 'Demo wallet',
            'otp' => env('FALAK_PAY_DEMO_OTP', '123456'),
        ],
        'manual' => [
            'enabled' => (bool) env('FALAK_PAY_MANUAL', true),
            'label' => 'Bank transfer / wallet (manual review)',
            'instructions' => env('FALAK_PAY_MANUAL_INSTRUCTIONS', 'Send the amount by InstaPay, Vodafone Cash, Sadad or a bank transfer, then enter the transaction reference. We activate your plan after we check it.'),
        ],
        'adfali' => [
            'enabled' => (bool) env('FALAK_PAY_ADFALI', false),
            'label' => 'Adfali (Libyana)',
            'base_url' => env('ADFALI_BASE_URL'),
            'merchant_mobile' => env('ADFALI_MERCHANT_MOBILE'),
            'merchant_pin' => env('ADFALI_MERCHANT_PIN'),
            'service_password' => env('ADFALI_SERVICE_PASSWORD'),
        ],
        'moamalat' => [
            'enabled' => (bool) env('FALAK_PAY_MOAMALAT', false),
            'label' => 'Moamalat card',
            'merchant_id' => env('MOAMALAT_MERCHANT_ID'),
            'terminal_id' => env('MOAMALAT_TERMINAL_ID'),
            'secret_key' => env('MOAMALAT_SECRET_KEY'),
            'lightbox_url' => env('MOAMALAT_LIGHTBOX_URL', 'https://tnpg.moamalat.net:6006/js/lightbox.js'),
        ],
        'paymob' => [
            'enabled' => (bool) env('FALAK_PAY_PAYMOB', false),
            'label' => 'Paymob (cards, wallets, Fawry)',
            'api_key' => env('PAYMOB_API_KEY'),
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
            'iframe_id' => env('PAYMOB_IFRAME_ID'),
            'hmac' => env('PAYMOB_HMAC'),
        ],
    ],
];
