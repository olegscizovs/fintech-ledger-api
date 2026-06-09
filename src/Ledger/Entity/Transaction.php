<?php

declare(strict_types=1);

namespace App\Ledger\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'transactions')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $uuid;

    #[ORM\Column(type: 'string', length: 255)]
    private string $description;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, LedgerEntry>
     */
    #[ORM\OneToMany(mappedBy: 'transaction', targetEntity: LedgerEntry::class, cascade: ['persist', 'remove'])]
    private Collection $entries;

    public function __construct(string $description)
    {
        $this->description = $description;
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, LedgerEntry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(LedgerEntry $entry): self
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
            $entry->setTransaction($this);
        }
        return $this;
    }
}
