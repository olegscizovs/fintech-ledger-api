<?php

declare(strict_types=1);

namespace App\Authentication\Controller;

use App\Authentication\Entity\RefreshToken;
use App\Authentication\Entity\User;
use App\Authentication\Entity\UserCredential;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $email = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';

        if ($email === '' || $password === '') {
            return new JsonResponse(['error' => 'Missing email or password'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Invalid email address format'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return new JsonResponse(['error' => 'Password must be at least 8 characters long'], Response::HTTP_BAD_REQUEST);
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'User already exists'], Response::HTTP_CONFLICT);
        }

        $user = new User($email);


        // Hash password and assign credentials
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $credential = new UserCredential($user, $hashedPassword);
        $user->setCredential($credential);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'user' => [
                'uuid' => $user->getUuid(),
                'email' => $user->getEmail(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): void
    {
        // This controller action will never be executed as it is intercepted by Symfony Security json_login.
    }

    #[Route('/api/refresh', name: 'api_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $tokenValue = isset($data['refresh_token']) && is_string($data['refresh_token']) ? $data['refresh_token'] : '';

        if ($tokenValue === '') {
            return new JsonResponse(['error' => 'Missing refresh token'], Response::HTTP_BAD_REQUEST);
        }

        $refreshTokenRepository = $this->entityManager->getRepository(RefreshToken::class);
        /** @var RefreshToken|null $refreshToken */
        $refreshToken = $refreshTokenRepository->findOneBy(['token' => $tokenValue]);

        if (!$refreshToken || !$refreshToken->isValid()) {
            return new JsonResponse(['error' => 'Invalid or expired refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $refreshToken->getUser();

        // Token Rotation: revoke old token
        $refreshToken->revoke();

        // Generate new JWT
        $newJwt = $this->jwtManager->create($user);

        // Generate new refresh token
        $newRefreshTokenValue = bin2hex(random_bytes(32));
        $newExpiresAt = (new \DateTimeImmutable())->modify('+7 days');
        $newRefreshToken = new RefreshToken($user, $newRefreshTokenValue, $newExpiresAt);

        $this->entityManager->persist($newRefreshToken);
        $this->entityManager->flush();

        return new JsonResponse([
            'token' => $newJwt,
            'refresh_token' => $newRefreshTokenValue,
        ]);
    }
}
