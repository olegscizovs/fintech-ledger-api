<?php

declare(strict_types=1);

namespace App\Tests\Ledger\Functional;

use App\Authentication\Entity\User;
use App\Authentication\Entity\UserCredential;
use App\Account\Entity\Account;
use App\Ledger\Entity\LedgerEntry;
use App\Ledger\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LedgerLockingTest extends WebTestCase
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
        $this->user = new User('ledger_user@example.com');
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
            json_encode(['email' => 'ledger_user@example.com', 'password' => 'password123'], JSON_THROW_ON_ERROR)
        );

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->jwtToken = isset($response['token']) ? (string) $response['token'] : '';
    }

    public function testPostTransactionSuccess(): void
    {
        // Create two accounts
        $account1 = new Account('customer-x', 'Cash Account', 'USD', '100.0000', $this->user);
        $account2 = new Account('customer-x', 'Expenses Account', 'USD', '10.0000', $this->user);

        $this->entityManager->persist($account1);
        $this->entityManager->persist($account2);
        $this->entityManager->flush();

        // Post a balanced transaction: Debit $20 from Cash, Credit $20 to Expenses
        // Wait, under our rules:
        // Credit increases balance, Debit decreases balance.
        // So Cash (debit 20) -> Cash goes to 80.
        // Expenses (credit 20) -> Expenses goes to 30.
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
                'description' => 'Transfer Cash to Expenses',
                'entries' => [
                    [
                        'account_uuid' => $account1->getUuid(),
                        'direction' => 'DEBIT',
                        'amount' => '20.0000'
                    ],
                    [
                        'account_uuid' => $account2->getUuid(),
                        'direction' => 'CREDIT',
                        'amount' => '20.0000'
                    ]
                ]
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($response['uuid']);
        $this->assertSame('Transfer Cash to Expenses', $response['description']);
        $this->assertCount(2, $response['entries']);

        // Refresh accounts from db and assert balances
        $this->entityManager->clear();
        $account1Db = $this->entityManager->getRepository(Account::class)->find($account1->getId());
        $account2Db = $this->entityManager->getRepository(Account::class)->find($account2->getId());

        $this->assertSame('80.0000', $account1Db->getBalance());
        $this->assertSame('30.0000', $account2Db->getBalance());
    }

    public function testPostTransactionInsufficientFunds(): void
    {
        // Create two accounts
        $account1 = new Account('customer-x', 'Cash Account', 'USD', '10.0000', $this->user);
        $account2 = new Account('customer-x', 'Expenses Account', 'USD', '10.0000', $this->user);

        $this->entityManager->persist($account1);
        $this->entityManager->persist($account2);
        $this->entityManager->flush();

        // Attempt to debit $20 from Cash (balance is only 10)
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
                'description' => 'Transfer Cash to Expenses',
                'entries' => [
                    [
                        'account_uuid' => $account1->getUuid(),
                        'direction' => 'DEBIT',
                        'amount' => '20.0000'
                    ],
                    [
                        'account_uuid' => $account2->getUuid(),
                        'direction' => 'CREDIT',
                        'amount' => '20.0000'
                    ]
                ]
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('insufficient funds', $response['error']);

        // Verify balances were not modified (transaction rolled back)
        $this->entityManager->clear();
        $account1Db = $this->entityManager->getRepository(Account::class)->find($account1->getId());
        $account2Db = $this->entityManager->getRepository(Account::class)->find($account2->getId());
        $this->assertSame('10.0000', $account1Db->getBalance());
        $this->assertSame('10.0000', $account2Db->getBalance());
    }

    public function testPostTransactionUnbalancedThrowsException(): void
    {
        // Create two accounts
        $account1 = new Account('customer-x', 'Cash Account', 'USD', '100.0000', $this->user);
        $account2 = new Account('customer-x', 'Expenses Account', 'USD', '10.0000', $this->user);

        $this->entityManager->persist($account1);
        $this->entityManager->persist($account2);
        $this->entityManager->flush();

        // Debit 20 from Cash, Credit 10 to Expenses (unbalanced!)
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
                'description' => 'Unbalanced Transfer',
                'entries' => [
                    [
                        'account_uuid' => $account1->getUuid(),
                        'direction' => 'DEBIT',
                        'amount' => '20.0000'
                    ],
                    [
                        'account_uuid' => $account2->getUuid(),
                        'direction' => 'CREDIT',
                        'amount' => '10.0000'
                    ]
                ]
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('unbalanced', $response['error']);
    }

    public function testStatementRetrieval(): void
    {
        $account1 = new Account('customer-stmt', 'Cash', 'USD', '100.0000', $this->user);
        $account2 = new Account('customer-stmt', 'Expenses', 'USD', '10.0000', $this->user);

        $this->entityManager->persist($account1);
        $this->entityManager->persist($account2);
        $this->entityManager->flush();

        // Post balanced transaction
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
                'description' => 'Statement Test Tx',
                'entries' => [
                    [
                        'account_uuid' => $account1->getUuid(),
                        'direction' => 'DEBIT',
                        'amount' => '15.0000'
                    ],
                    [
                        'account_uuid' => $account2->getUuid(),
                        'direction' => 'CREDIT',
                        'amount' => '15.0000'
                    ]
                ]
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(201);

        // Fetch statement
        $this->client->request(
            'GET',
            '/api/accounts/' . $account1->getUuid() . '/statement',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken
            ]
        );

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response);
        $this->assertSame('DEBIT', $response[0]['direction']);
        $this->assertSame('15.0000', $response[0]['amount']);
        $this->assertSame('Statement Test Tx', $response[0]['transaction']['description']);
    }
}
