<?php

declare(strict_types=1);

namespace App\Ledger\Command;

use App\Account\Entity\Account;
use App\Ledger\Repository\LedgerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'reconcile:ledger',
    description: 'Reconcile cached account balances against ledger entry history'
)]
class ReconcileLedgerCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LedgerRepository $ledgerRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting Ledger Reconciliation');

        $accounts = $this->entityManager->getRepository(Account::class)->findAll();
        $discrepanciesCount = 0;

        foreach ($accounts as $account) {
            /** @var numeric-string $cachedBalance */
            $cachedBalance = $account->getBalance();
            /** @var numeric-string $calculatedBalance */
            $calculatedBalance = $this->ledgerRepository->calculateBalanceFromHistory($account);

            if (bccomp($cachedBalance, $calculatedBalance, 4) !== 0) {
                $io->error(sprintf(
                    'Discrepancy found for Account %s (%s): Cached balance = %s, Calculated from history = %s',
                    $account->getName(),
                    $account->getUuid(),
                    $cachedBalance,
                    $calculatedBalance
                ));
                $discrepanciesCount++;
            } else {
                $output->writeln(sprintf(
                    'Account %s (%s) is reconciled: Balance = %s %s',
                    $account->getName(),
                    $account->getUuid(),
                    $cachedBalance,
                    $account->getCurrency()
                ));
            }
        }

        if ($discrepanciesCount > 0) {
            $io->warning(sprintf('Reconciliation completed with %d discrepancies!', $discrepanciesCount));
            return Command::FAILURE;
        }

        $io->success('All accounts are fully reconciled and consistent!');
        return Command::SUCCESS;
    }
}
