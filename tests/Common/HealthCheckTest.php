<?php

declare(strict_types=1);

namespace App\Tests\Common;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class HealthCheckTest extends WebTestCase
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

    public function testHealthCheckIsUp(): void
    {
        $this->client->request('GET', '/api/health');

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('UP', $response['status']);
        $this->assertArrayHasKey('timestamp', $response);
    }
}
