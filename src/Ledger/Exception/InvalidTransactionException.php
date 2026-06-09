<?php

declare(strict_types=1);

namespace App\Ledger\Exception;

class InvalidTransactionException extends \DomainException
{
    public static function unbalanced(string $debits, string $credits): self
    {
        return new self(sprintf(
            'Transaction is unbalanced. Debits sum: %s, credits sum: %s. Sum of Debits must equal sum of Credits.',
            $debits,
            $credits
        ));
    }

    public static function mismatchedCurrency(string $expected, string $actual): self
    {
        return new self(sprintf(
            'Mismatched currencies in transaction. Expected account currency: %s, actual: %s',
            $expected,
            $actual
        ));
    }
}
