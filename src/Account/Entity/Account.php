<?php

declare(strict_types=1);

namespace App\Account\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(columns: ['customer_id'], name: 'idx_accounts_customer_id')]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $uuid;

    #[ORM\Column(type: 'string', length: 180)]
    private string $customerId;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 4)]
    private string $balance = '0.0000';

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\ManyToOne(targetEntity: \App\Authentication\Entity\User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?\App\Authentication\Entity\User $user = null;

    public function __construct(string $customerId, string $name, string $currency, string $initialBalance = '0.0000', ?\App\Authentication\Entity\User $user = null)
    {
        $this->customerId = $customerId;
        $this->name = $name;
        $this->currency = strtoupper($currency);
        $this->balance = $initialBalance;
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->user = $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function getBalance(): string
    {
        /** @var numeric-string $balance */
        $balance = $this->balance;
        return bcadd($balance, '0', 4);
    }

    public function setBalance(string $balance): self
    {
        $this->balance = $balance;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUser(): ?\App\Authentication\Entity\User
    {
        return $this->user;
    }

    public function setUser(?\App\Authentication\Entity\User $user): self
    {
        $this->user = $user;
        return $this;
    }
}
