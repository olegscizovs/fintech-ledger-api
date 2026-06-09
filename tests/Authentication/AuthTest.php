<?php

declare(strict_types=1);

namespace App\Tests\Authentication;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use App\Authentication\Entity\User;

class AuthTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Recreate database schema for test environment
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $classes = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($classes);
    }

    public function testRegisterAndLoginFlow(): void
    {
        // 1. Register
        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'securePassword123'
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(201);
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('success', $responseContent['status']);
        $this->assertSame('test@example.com', $responseContent['user']['email']);
        $this->assertNotEmpty($responseContent['user']['uuid']);

        // 2. Login
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'securePassword123'
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(200);
        $loginResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $loginResponse);
        $this->assertArrayHasKey('refresh_token', $loginResponse);
        $jwtToken = $loginResponse['token'];
        $refreshToken = $loginResponse['refresh_token'];

        // 3. Refresh Token Rotation
        $this->client->request(
            'POST',
            '/api/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refresh_token' => $refreshToken
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(200);
        $refreshResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $refreshResponse);
        $this->assertArrayHasKey('refresh_token', $refreshResponse);
        $this->assertNotEquals($refreshToken, $refreshResponse['refresh_token']);
    }

    public function testLoginFailure(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nonexistent@example.com',
                'password' => 'wrong'
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(401);
    }
}
