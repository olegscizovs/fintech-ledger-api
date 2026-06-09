<?php

declare(strict_types=1);

namespace App\Authentication\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $uuid;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserCredential::class, cascade: ['persist', 'remove'])]
    private ?UserCredential $credential = null;

    /**
     * @param non-empty-string $email
     */
    public function __construct(string $email)
    {
        if (empty($email)) {
            throw new \InvalidArgumentException('Email cannot be empty');
        }
        $this->email = $email;
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->roles = ['ROLE_USER'];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return non-empty-string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param non-empty-string $email
     */
    public function setEmail(string $email): self
    {
        if (empty($email)) {
            throw new \InvalidArgumentException('Email cannot be empty');
        }
        $this->email = $email;
        return $this;
    }

    /**
     * @see UserInterface
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getCredential(): ?UserCredential
    {
        return $this->credential;
    }

    public function setCredential(UserCredential $credential): self
    {
        $this->credential = $credential;
        // avoid infinite loop
        if ($credential->getUser() !== $this) {
            $credential->setUser($this);
        }
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->credential?->getPasswordHash();
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If we had any temporary plaintext passwords stored on the entity, clear them here.
    }
}
