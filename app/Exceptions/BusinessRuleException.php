<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown by services when a request is well-formed but breaks a business rule
 * (illegal status transition, event out of order, ...). Rendered as clean JSON.
 *  409 = conflicts with the current state of the shipment
 *  422 = the supplied data itself is not acceptable
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }

    /** Expected rule violations are not server errors: keep them out of the error log. */
    public function report(): bool
    {
        return true;
    }
}
