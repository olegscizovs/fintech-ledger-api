<?php

declare(strict_types=1);

namespace App\Ledger\Entity;

use App\Account\Entity\Account;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'ledger_entries')]
#[ORM\Index(columns: ['account_id'], name: 'idx_ledger_entries_account_id')]
class LedgerEntry
{
    public const DIRECTION_DEBIT = 'DEBIT';
    public const DIRECTION_CREDIT = 'CREDIT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: Transaction::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(name: 'transaction_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Transaction $transaction;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false)]
    private Account $account;

    #[ORM\Column(type: 'string', length: 6)]
    private string $direction;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 4)]
    private string $amount;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Transaction $transaction, Account $account, string $direction, string $amount)
    {
        $this->transaction = $transaction;
        $this->account = $account;
        $this->direction = strtoupper($direction);
        $this->amount = $amount;
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(Transaction $transaction): self
    {
        $this->transaction = $transaction;
        return $this;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): self
    {
        $this->account = $account;
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): self
    {
        $this->direction = strtoupper($direction);
        return $this;
    }

    public function getAmount(): string
    {
        /** @var numeric-string $amount */
        $amount = $this->amount;
        return bcadd($amount, '0', 4);
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
