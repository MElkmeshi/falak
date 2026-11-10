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
            'label' => env('FALAK_PAY_ADFALI_LABEL', 'Edfali (Libyana)'),
            'base_url' => env('ADFALI_BASE_URL', 'http://62.240.55.2:6187/BCDUssd/Edfali.asmx'),
            'merchant_mobile' => env('ADFALI_MERCHANT_MOBILE'),
            'merchant_pin' => env('ADFALI_MERCHANT_PIN'),
            'service_password' => env('ADFALI_SERVICE_PASSWORD'),
            'proxy' => env('ADFALI_PROXY', env('FALAK_PAY_LIBYA_PROXY')),
        ],
        'onepay' => [
            'enabled' => (bool) env('FALAK_PAY_ONEPAY', false),
            'base_url' => env('ONEPAY_BASE_URL', 'https://masarat.hajat.com.ly/YusorOnline'),
            'cert_path' => env('ONEPAY_CERT_PATH'),
            'key_path' => env('ONEPAY_KEY_PATH'),
            'ca_path' => env('ONEPAY_CA_PATH'),
            'user_id' => env('ONEPAY_USER_ID'),
            'pin' => env('ONEPAY_PIN'),
            'provider_id' => env('ONEPAY_PROVIDER_ID'),
            'auth_user_type' => (int) env('ONEPAY_AUTH_USER_TYPE', 0),
            'signin_url' => env('ONEPAY_SIGNIN_URL', 'api/OnlinePaymentServices/Signin'),
            'init_url' => env('ONEPAY_INIT_URL', 'api/OnlinePaymentServices/OpenSession'),
            'complete_url' => env('ONEPAY_COMPLETE_URL', 'api/OnlinePaymentServices/CompleteSession'),
            'report_url' => env('ONEPAY_REPORT_URL', 'api/OnlinePaymentServices/AccountReport'),
            'proxy' => env('ONEPAY_PROXY', env('FALAK_PAY_LIBYA_PROXY')),
            'wallets' => array_values(array_filter(explode(',', (string) env('FALAK_PAY_ONEPAY_WALLETS', 'yussor_online')))),
        ],
        'moamalat' => [
            'enabled' => (bool) env('FALAK_PAY_MOAMALAT', false),
            'label' => env('FALAK_PAY_MOAMALAT_LABEL', 'Bank card (Moamalat)'),
            'merchant_id' => env('MOAMALATPAY_MID'),
            'terminal_id' => env('MOAMALATPAY_TID'),
            'secret_key' => env('MOAMALATPAY_KEY'),
            'notification_key' => env('MOAMALATPAY_NOTIFICATION_KEY'),
            'production' => (bool) env('MOAMALATPAY_PRODUCTION', false),
            'proxy' => env('MOAMALATPAY_PROXY', env('FALAK_PAY_LIBYA_PROXY')),
            'intermediate_ca_path' => env('MOAMALATPAY_INTERMEDIATE_CA_PATH'),
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
