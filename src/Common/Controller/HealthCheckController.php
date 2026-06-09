<?php

// src/Common/Controller/HealthCheckController.php

declare(strict_types=1);

namespace App\Common\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheckController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            // Run an ultra-fast, cheap query to verify database is alive
            $connection->executeQuery('SELECT 1');

            return new JsonResponse([
                'status' => 'UP',
                'timestamp' => time()
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'DOWN',
                'error' => 'Database connection failed'
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
