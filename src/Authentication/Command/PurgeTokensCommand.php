<?php

declare(strict_types=1);

namespace App\Authentication\Command;

use App\Authentication\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tokens:purge',
    description: 'Purges expired or revoked refresh tokens from the database.'
)]
class PurgeTokensCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $qb = $this->entityManager->createQueryBuilder();
        $query = $qb->delete(RefreshToken::class, 'rt')
            ->where('rt.expiresAt < :now')
            ->orWhere('rt.isRevoked = true')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery();

        $result = $query->execute();
        $deletedCount = is_int($result) ? $result : 0;

        $io->success(sprintf('Successfully purged %d expired or revoked refresh token(s).', $deletedCount));

        return Command::SUCCESS;
    }
}
