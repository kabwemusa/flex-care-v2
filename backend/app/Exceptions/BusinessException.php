<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * BusinessException
 *
 * Thrown for domain-level validation and rule violations — e.g. "Application
 * cannot be converted", "Member already exists", "Promo code already used".
 *
 * The global exception handler in bootstrap/app.php renders this as a 422
 * JSON response, so controllers no longer need their own try/catch for
 * business-rule failures.
 *
 * Usage:
 *   throw new BusinessException('Application cannot be converted.');
 *   throw new BusinessException('Plan not found.', 404);
 */
class BusinessException extends RuntimeException
{
    /**
     * @param  string  $message  Human-readable error shown to the API consumer.
     * @param  int     $code     HTTP status code (default 422 Unprocessable Entity).
     */
    public function __construct(string $message, int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
