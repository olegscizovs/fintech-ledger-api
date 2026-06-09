<?php

declare(strict_types=1);

namespace App\Authentication\EventListener;

use App\Authentication\Entity\RefreshToken;
use App\Authentication\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success', method: 'onAuthenticationSuccess')]
class JWTAuthenticationSuccessListener
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $tokenValue = bin2hex(random_bytes(32)); // 64 character hex string
        $expiresAt = (new \DateTimeImmutable())->modify('+7 days');

        $refreshToken = new RefreshToken($user, $tokenValue, $expiresAt);

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        $data = $event->getData();
        $data['refresh_token'] = $tokenValue;
        $data['user'] = [
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail(),
        ];

        $event->setData($data);
    }
}
