<?php

declare(strict_types=1);

namespace App\Ledger\Exception;

class InsufficientFundsException extends \DomainException
{
    public static function forAccount(string $accountUuid, string $currentBalance, string $debitAmount): self
    {
        return new self(sprintf(
            'Account "%s" has insufficient funds. Current balance: %s, attempted debit: %s',
            $accountUuid,
            $currentBalance,
            $debitAmount
        ));
    }
}
