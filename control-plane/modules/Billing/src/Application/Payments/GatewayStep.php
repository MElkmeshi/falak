<?php

namespace Falak\Billing\Application\Payments;

final readonly class GatewayStep
{
    public const OTP = 'otp';

    public const PAID = 'paid';

    public const REVIEW = 'review';

    public const FAILED = 'failed';

    public const REDIRECT = 'redirect';

    public function __construct(
        public string $kind,
        public string $message = '',
        public ?string $redirectUrl = null,
        public int $otpLength = 6,
    ) {}

    public static function otp(string $message, int $length = 6): self
    {
        return new self(self::OTP, $message, otpLength: $length);
    }

    public static function paid(string $message = 'Payment received.'): self
    {
        return new self(self::PAID, $message);
    }

    public static function redirect(string $message, ?string $url = null): self
    {
        return new self(self::REDIRECT, $message, redirectUrl: $url);
    }

    public static function review(string $message): self
    {
        return new self(self::REVIEW, $message);
    }

    public static function failed(string $message): self
    {
        return new self(self::FAILED, $message);
    }

    public function toArray(): array
    {
        return ['kind' => $this->kind, 'message' => $this->message, 'redirect_url' => $this->redirectUrl, 'otp_length' => $this->otpLength];
    }
}
