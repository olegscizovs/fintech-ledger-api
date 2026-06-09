<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use App\Ledger\Entity\LedgerEntry;
use App\Ledger\Exception\InvalidTransactionException;

class DoubleEntryValidator
{
    /**
     * @param array<LedgerEntry> $entries
     * @throws InvalidTransactionException
     */
    public function validate(array $entries): void
    {
        if (count($entries) < 2) {
            throw new InvalidTransactionException('A double-entry transaction must have at least two entries.');
        }

        /** @var numeric-string $debitSum */
        $debitSum = '0.0000';
        /** @var numeric-string $creditSum */
        $creditSum = '0.0000';
        $currency = null;

        foreach ($entries as $entry) {
            /** @var numeric-string $amount */
            $amount = $entry->getAmount();

            // Check that amount is positive
            if (bccomp($amount, '0.0000', 4) <= 0) {
                throw new InvalidTransactionException('Transaction entry amount must be positive.');
            }

            // Ensure all entries are in the same currency (multi-currency transactions should only happen
            // between accounts of the same currency; cross-currency transactions would go through an exchange account)
            $accountCurrency = $entry->getAccount()->getCurrency();
            if ($currency === null) {
                $currency = $accountCurrency;
            } elseif ($currency !== $accountCurrency) {
                throw InvalidTransactionException::mismatchedCurrency($currency, $accountCurrency);
            }

            if ($entry->getDirection() === LedgerEntry::DIRECTION_DEBIT) {
                /** @var numeric-string $debitSum */
                $debitSum = bcadd($debitSum, $amount, 4);
            } elseif ($entry->getDirection() === LedgerEntry::DIRECTION_CREDIT) {
                /** @var numeric-string $creditSum */
                $creditSum = bcadd($creditSum, $amount, 4);
            } else {
                throw new InvalidTransactionException(sprintf('Invalid direction: %s', $entry->getDirection()));
            }
        }

        if (bccomp($debitSum, $creditSum, 4) !== 0) {
            throw InvalidTransactionException::unbalanced($debitSum, $creditSum);
        }
    }
}
