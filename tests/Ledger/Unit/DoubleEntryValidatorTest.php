<?php

declare(strict_types=1);

namespace App\Tests\Ledger\Unit;

use App\Account\Entity\Account;
use App\Ledger\Entity\LedgerEntry;
use App\Ledger\Entity\Transaction;
use App\Ledger\Exception\InvalidTransactionException;
use App\Ledger\Service\DoubleEntryValidator;
use PHPUnit\Framework\TestCase;

class DoubleEntryValidatorTest extends TestCase
{
    private DoubleEntryValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DoubleEntryValidator();
    }

    public function testBalancedTransactionPasses(): void
    {
        $transaction = new Transaction('Balanced transaction');
        $account1 = new Account('customer-1', 'Cash Account', 'USD');
        $account2 = new Account('customer-1', 'Revenue Account', 'USD');

        // Debit $100 to account 1, Credit $100 to account 2
        $entry1 = new LedgerEntry($transaction, $account1, LedgerEntry::DIRECTION_DEBIT, '100.0000');
        $entry2 = new LedgerEntry($transaction, $account2, LedgerEntry::DIRECTION_CREDIT, '100.0000');

        $this->validator->validate([$entry1, $entry2]);
        $this->addToAssertionCount(1); // If no exception was thrown, we count it as a pass
    }

    public function testUnbalancedTransactionThrowsException(): void
    {
        $transaction = new Transaction('Unbalanced transaction');
        $account1 = new Account('customer-1', 'Cash Account', 'USD');
        $account2 = new Account('customer-1', 'Revenue Account', 'USD');

        // Debit $100 to account 1, Credit $50 to account 2
        $entry1 = new LedgerEntry($transaction, $account1, LedgerEntry::DIRECTION_DEBIT, '100.0000');
        $entry2 = new LedgerEntry($transaction, $account2, LedgerEntry::DIRECTION_CREDIT, '50.0000');

        $this->expectException(InvalidTransactionException::class);
        $this->expectExceptionMessage('unbalanced');

        $this->validator->validate([$entry1, $entry2]);
    }

    public function testMismatchedCurrencyThrowsException(): void
    {
        $transaction = new Transaction('Multi-currency transaction');
        $account1 = new Account('customer-1', 'Cash Account USD', 'USD');
        $account2 = new Account('customer-1', 'Revenue Account EUR', 'EUR');

        // Debit $100 to account 1 (USD), Credit $100 to account 2 (EUR)
        $entry1 = new LedgerEntry($transaction, $account1, LedgerEntry::DIRECTION_DEBIT, '100.0000');
        $entry2 = new LedgerEntry($transaction, $account2, LedgerEntry::DIRECTION_CREDIT, '100.0000');

        $this->expectException(InvalidTransactionException::class);
        $this->expectExceptionMessage('Mismatched currencies');

        $this->validator->validate([$entry1, $entry2]);
    }

    public function testNegativeAmountThrowsException(): void
    {
        $transaction = new Transaction('Negative amount transaction');
        $account1 = new Account('customer-1', 'Cash Account', 'USD');
        $account2 = new Account('customer-1', 'Revenue Account', 'USD');

        // Debit -$100 to account 1, Credit -$100 to account 2
        $entry1 = new LedgerEntry($transaction, $account1, LedgerEntry::DIRECTION_DEBIT, '-100.0000');
        $entry2 = new LedgerEntry($transaction, $account2, LedgerEntry::DIRECTION_CREDIT, '-100.0000');

        $this->expectException(InvalidTransactionException::class);
        $this->expectExceptionMessage('amount must be positive');

        $this->validator->validate([$entry1, $entry2]);
    }

    public function testSingleEntryThrowsException(): void
    {
        $transaction = new Transaction('Single entry transaction');
        $account = new Account('customer-1', 'Cash Account', 'USD');

        $entry = new LedgerEntry($transaction, $account, LedgerEntry::DIRECTION_DEBIT, '100.0000');

        $this->expectException(InvalidTransactionException::class);
        $this->expectExceptionMessage('at least two entries');

        $this->validator->validate([$entry]);
    }
}
