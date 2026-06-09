<?php

declare(strict_types=1);

namespace App\Ledger\Repository;

use App\Account\Entity\Account;
use App\Ledger\Entity\LedgerEntry;
use App\Ledger\Entity\Transaction;
use App\Ledger\Exception\InsufficientFundsException;
use App\Ledger\Service\DoubleEntryValidator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LedgerEntry>
 */
class LedgerRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private DoubleEntryValidator $validator
    ) {
        parent::__construct($registry, LedgerEntry::class);
    }

    /**
     * Post a new double-entry transaction.
     * Uses pessimistic locking to prevent race conditions and validates the double-entry rule.
     *
     * @param string $description
     * @param array<array{account: Account, direction: string, amount: string}> $entryData
     * @return Transaction
     */
    public function postTransaction(string $description, array $entryData): Transaction
    {
        $em = $this->getEntityManager();

        return $em->wrapInTransaction(function () use ($description, $entryData, $em) {
            $transaction = new Transaction($description);
            $entries = [];

            // Sort accounts by ID to prevent deadlocks when locking multiple accounts
            usort($entryData, function ($a, $b) {
                return ($a['account']->getId() ?? 0) <=> ($b['account']->getId() ?? 0);
            });

            // Lock accounts and construct entries
            $lockedAccounts = [];
            foreach ($entryData as $data) {
                /** @var Account $account */
                $account = $data['account'];
                $accountId = $account->getId();

                if (!isset($lockedAccounts[$accountId])) {
                    // Pessimistic Write Lock
                    $lockedAccount = $em->find(Account::class, $accountId, LockMode::PESSIMISTIC_WRITE);
                    if (!$lockedAccount) {
                        throw new \InvalidArgumentException(sprintf('Account ID %d not found for locking.', $accountId));
                    }
                    $lockedAccounts[$accountId] = $lockedAccount;
                }

                $entry = new LedgerEntry(
                    $transaction,
                    $lockedAccounts[$accountId],
                    $data['direction'],
                    $data['amount']
                );
                $transaction->addEntry($entry);
                $entries[] = $entry;
            }

            // Perform double-entry validation
            $this->validator->validate($entries);

            // Update account balances
            foreach ($entries as $entry) {
                $account = $entry->getAccount();
                /** @var numeric-string $currentBalance */
                $currentBalance = $account->getBalance();
                /** @var numeric-string $amount */
                $amount = $entry->getAmount();

                if ($entry->getDirection() === LedgerEntry::DIRECTION_CREDIT) {
                    $newBalance = bcadd($currentBalance, $amount, 4);
                } else {
                    $newBalance = bcsub($currentBalance, $amount, 4);
                    // Prevent balance from going negative
                    if (bccomp($newBalance, '0.0000', 4) < 0) {
                        throw InsufficientFundsException::forAccount($account->getUuid(), $currentBalance, $amount);
                    }
                }

                $account->setBalance($newBalance);
            }

            $em->persist($transaction);

            return $transaction;
        });
    }

    /**
     * Calculate account balance from transaction logs to reconcile.
     */
    public function calculateBalanceFromHistory(Account $account): string
    {
        $qb = $this->createQueryBuilder('le')
            ->select('le.direction, SUM(le.amount) as amountSum')
            ->where('le.account = :account')
            ->setParameter('account', $account)
            ->groupBy('le.direction');

        /** @var array<int, array{direction: string, amountSum: string|float|int}> $results */
        $results = $qb->getQuery()->getResult();

        $debitSum = '0.0000';
        $creditSum = '0.0000';

        foreach ($results as $row) {
            $sum = (string) $row['amountSum'];
            if (!is_numeric($sum)) {
                $sum = '0.0000';
            }
            if ($row['direction'] === LedgerEntry::DIRECTION_DEBIT) {
                $debitSum = $sum;
            } elseif ($row['direction'] === LedgerEntry::DIRECTION_CREDIT) {
                $creditSum = $sum;
            }
        }

        /** @var numeric-string $creditSum */
        /** @var numeric-string $debitSum */
        return bcsub($creditSum, $debitSum, 4);
    }
}
