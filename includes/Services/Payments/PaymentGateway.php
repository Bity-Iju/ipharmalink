<?php

/**
 * iPharmaLink :: Payment gateway abstraction
 * ---------------------------------------------------------------------------
 * Strategy pattern. Every gateway implements the same three-step contract:
 *
 *   initialize() -> redirect to the gateway, mark payment 'processing'
 *   verify()     -> server-to-server confirmation of a reference/amount
 *   handleWebhook() -> validate an inbound callback signature and payload
 *
 * Adding Flutterwave (or any future provider) means writing one class and
 * registering it in the factory. No order, cart or controller code changes.
 *
 * SECURITY: an order is never marked paid on the strength of a browser
 * returning to /payment/success. Only verify() — driven by a webhook or a
 * server-side API call — may set payment_status to 'successful'.
 */

declare(strict_types=1);

namespace App\Services\Payments;

use App\Database;
use App\HttpException;
use App\Logger;
use App\Setting;

interface PaymentGateway
{
    public function code(): string;
    public function label(): string;

    /**
     * Begin a payment. Returns the URL to redirect the customer to.
     *
     * @param array<string,mixed> $order
     */
    public function initialize(array $order, float $amount, string $callbackUrl, ?string $reference = null): string;

    /**
     * Server-to-server verification. Returns a normalised result.
     *
     * @return array{success:bool, reference:string, amount:float, raw:array<string,mixed>, message:string}
     */
    public function verify(string $reference, float $expectedAmount): array;

    /**
     * Validate and normalise an inbound webhook.
     *
     * @param  array<string,string> $headers
     * @param  array<string,mixed>  $payload
     * @return array{valid:bool, reference:string, amount:float, status:string, raw:array<string,mixed>, message:string}
     */
    public function handleWebhook(array $headers, array $payload): array;
}
