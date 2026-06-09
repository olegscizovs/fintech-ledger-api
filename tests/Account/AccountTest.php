<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Authentication\Entity\User;
use App\Authentication\Entity\UserCredential;
use App\Account\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AccountTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private string $jwtToken;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        // Recreate database schema for test environment
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $classes = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($classes);

        // Create a user and authenticate to get a JWT
        $this->user = new User('test_user@example.com');
        $hashed = $passwordHasher->hashPassword($this->user, 'password123');
        $credential = new UserCredential($this->user, $hashed);
        $this->user->setCredential($credential);

        $this->entityManager->persist($this->user);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test_user@example.com', 'password' => 'password123'], JSON_THROW_ON_ERROR)
        );

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->jwtToken = isset($response['token']) ? (string) $response['token'] : '';
    }

    public function testCreateAccount(): void
    {
        $this->client->request(
            'POST',
            '/api/accounts',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken
            ],
            json_encode([
                'customer_id' => 'customer-123',
                'name' => 'Main Checking USD',
                'currency' => 'USD',
                'initial_balance' => '500.25'
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNotEmpty($response['uuid']);
        $this->assertSame('customer-123', $response['customer_id']);
        $this->assertSame('Main Checking USD', $response['name']);
        $this->assertSame('USD', $response['currency']);
        $this->assertSame('500.2500', $response['balance']);

        // Check database state
        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['uuid' => $response['uuid']]);
        $this->assertNotNull($account);
        $this->assertSame('500.2500', $account->getBalance());
    }

    public function testGetAccount(): void
    {
        $account = new Account('customer-abc', 'Savings Account', 'EUR', '1000.0000', $this->user);
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $this->client->request(
            'GET',
            '/api/accounts/' . $account->getUuid(),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken
            ]
        );

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Savings Account', $response['name']);
        $this->assertSame('1000.0000', $response['balance']);
    }

    public function testUnauthenticatedRequestIsBlocked(): void
    {
        $this->client->request('GET', '/api/accounts/non-existent-uuid');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdateAccountSuccess(): void
    {
        $account = new Account('customer-update', 'Old Name', 'USD', '200.0000', $this->user);
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $this->client->request(
            'PUT',
            '/api/accounts/' . $account->getUuid(),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken
            ],
            json_encode([
                'customer_id' => 'customer-updated-id',
                'name' => 'New Awesome Name',
                'currency' => 'EUR'
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('customer-updated-id', $response['customer_id']);
        $this->assertSame('New Awesome Name', $response['name']);
        $this->assertSame('EUR', $response['currency']);

        // Check DB
        $updatedAccount = $this->entityManager->getRepository(Account::class)->find($account->getId());
        $this->assertNotNull($updatedAccount);
        $this->assertSame('customer-updated-id', $updatedAccount->getCustomerId());
        $this->assertSame('New Awesome Name', $updatedAccount->getName());
        $this->assertSame('EUR', $updatedAccount->getCurrency());
    }

    public function testUpdateAccountCurrencyFailsWithTransactions(): void
    {
        $account1 = new Account('cust-1', 'Account 1', 'USD', '100.0000', $this->user);
        $account2 = new Account('cust-2', 'Account 2', 'USD', '100.0000', $this->user);
        $this->entityManager->persist($account1);
        $this->entityManager->persist($account2);
        $this->entityManager->flush();

        // Post a balanced transaction
        $this->client->request(
            'POST',
            '/api/transactions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken
            ],
            json_encode([
                'description' => 'Transfer $10',
                'entries' => [
                    [
                        'account_uuid' => $account1->getUuid(),
                        'direction' => 'DEBIT',
                        'amount' => '10.0000'
                    ],
                    [
                        'account_uuid' => $account2->getUuid(),
                        'direction' => 'CREDIT',
                        'amount' => '10.0000'
                    ]
                ]
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(201);

        // Try updating the currency of $account1
        $this->client->request(
            'PUT',
            '/api/accounts/' . $account1->getUuid(),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken
            ],
            json_encode([
                'currency' => 'EUR'
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Cannot change currency of an account with existing transactions', $response['error']);
    }

    public function testUpdateAccountValidationFails(): void
    {
        $account = new Account('customer-validation', 'Name', 'USD', '100.0000', $this->user);
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $this->client->request(
            'PUT',
            '/api/accounts/' . $account->getUuid(),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken
            ],
            json_encode([
                'name' => '',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('name cannot be empty', $response['error']);
    }
}
