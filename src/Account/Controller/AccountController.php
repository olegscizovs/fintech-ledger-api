<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\Account;
use App\Ledger\Entity\LedgerEntry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccountController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private \Symfony\Bundle\SecurityBundle\Security $security
    ) {
    }

    #[Route('/api/accounts', name: 'api_list_accounts', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $accounts = $this->entityManager->getRepository(Account::class)->findBy(['user' => $user]);

        $data = [];
        foreach ($accounts as $account) {
            $data[] = [
                'id' => $account->getId(),
                'uuid' => $account->getUuid(),
                'customer_id' => $account->getCustomerId(),
                'name' => $account->getName(),
                'currency' => $account->getCurrency(),
                'balance' => $account->getBalance(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/accounts', name: 'api_create_account', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }
        $customerId = isset($data['customer_id']) && is_string($data['customer_id']) ? trim($data['customer_id']) : '';
        $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : '';
        $currency = isset($data['currency']) && is_string($data['currency']) ? trim($data['currency']) : '';
        $initialBalance = isset($data['initial_balance']) && (is_string($data['initial_balance']) || is_numeric($data['initial_balance'])) ? (string) $data['initial_balance'] : '0.0000';

        if ($customerId === '' || $name === '' || $currency === '') {
            return new JsonResponse(['error' => 'Missing customer_id, name or currency'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($customerId) > 180) {
            return new JsonResponse(['error' => 'Customer ID cannot exceed 180 characters'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($name) > 100) {
            return new JsonResponse(['error' => 'Name cannot exceed 100 characters'], Response::HTTP_BAD_REQUEST);
        }

        $currency = strtoupper($currency);
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            return new JsonResponse(['error' => 'Currency must be a valid 3-character ISO code'], Response::HTTP_BAD_REQUEST);
        }

        // Validate initial balance format
        if (!is_numeric($initialBalance)) {
            return new JsonResponse(['error' => 'Initial balance must be a number'], Response::HTTP_BAD_REQUEST);
        }

        /** @var numeric-string $initialBalance */
        if (bccomp($initialBalance, '0.0000', 4) < 0) {
            return new JsonResponse(['error' => 'Initial balance must be a non-negative number'], Response::HTTP_BAD_REQUEST);
        }

        // Format to 4 decimal places
        $formattedBalance = number_format((float)$initialBalance, 4, '.', '');

        /** @var \App\Authentication\Entity\User $user */
        $account = new Account($customerId, $name, $currency, $formattedBalance, $user);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $account->getId(),
            'uuid' => $account->getUuid(),
            'customer_id' => $account->getCustomerId(),
            'name' => $account->getName(),
            'currency' => $account->getCurrency(),
            'balance' => $account->getBalance(),
        ], Response::HTTP_CREATED);
    }


    #[Route('/api/accounts/{uuid}', name: 'api_get_account', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $account = $this->entityManager->getRepository(Account::class)->findOneBy([
            'uuid' => $uuid,
            'user' => $user
        ]);

        if (!$account) {
            return new JsonResponse(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $account->getId(),
            'uuid' => $account->getUuid(),
            'customer_id' => $account->getCustomerId(),
            'name' => $account->getName(),
            'currency' => $account->getCurrency(),
            'balance' => $account->getBalance(),
        ]);
    }

    #[Route('/api/accounts/{uuid}', name: 'api_update_account', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $account = $this->entityManager->getRepository(Account::class)->findOneBy([
            'uuid' => $uuid,
            'user' => $user
        ]);

        if (!$account) {
            return new JsonResponse(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        $customerId = isset($data['customer_id']) && is_string($data['customer_id']) ? trim($data['customer_id']) : null;
        $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : null;
        $currency = isset($data['currency']) && is_string($data['currency']) ? trim($data['currency']) : null;

        if ($customerId === '') {
            return new JsonResponse(['error' => 'customer_id cannot be empty'], Response::HTTP_BAD_REQUEST);
        }
        if ($customerId !== null && strlen($customerId) > 180) {
            return new JsonResponse(['error' => 'Customer ID cannot exceed 180 characters'], Response::HTTP_BAD_REQUEST);
        }

        if ($name === '') {
            return new JsonResponse(['error' => 'name cannot be empty'], Response::HTTP_BAD_REQUEST);
        }
        if ($name !== null && strlen($name) > 100) {
            return new JsonResponse(['error' => 'Name cannot exceed 100 characters'], Response::HTTP_BAD_REQUEST);
        }

        if ($currency === '') {
            return new JsonResponse(['error' => 'currency cannot be empty'], Response::HTTP_BAD_REQUEST);
        }

        if ($customerId !== null) {
            $account->setCustomerId($customerId);
        }

        if ($name !== null) {
            $account->setName($name);
        }

        if ($currency !== null) {
            $currency = strtoupper($currency);
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                return new JsonResponse(['error' => 'Currency must be a valid 3-character ISO code'], Response::HTTP_BAD_REQUEST);
            }

            if ($account->getCurrency() !== $currency) {
                // Check if account has any ledger entries
                $hasEntries = $this->entityManager->getRepository(LedgerEntry::class)->count(['account' => $account]) > 0;
                if ($hasEntries) {
                    return new JsonResponse(['error' => 'Cannot change currency of an account with existing transactions'], Response::HTTP_BAD_REQUEST);
                }
                $account->setCurrency($currency);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $account->getId(),
            'uuid' => $account->getUuid(),
            'customer_id' => $account->getCustomerId(),
            'name' => $account->getName(),
            'currency' => $account->getCurrency(),
            'balance' => $account->getBalance(),
        ]);
    }

    #[Route('/api/accounts/{uuid}', name: 'api_delete_account', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $account = $this->entityManager->getRepository(Account::class)->findOneBy([
            'uuid' => $uuid,
            'user' => $user
        ]);

        if (!$account) {
            return new JsonResponse(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        // Delete any ledger entries associated with this account
        $ledgerEntries = $this->entityManager->getRepository(LedgerEntry::class)->findBy(['account' => $account]);
        foreach ($ledgerEntries as $entry) {
            $this->entityManager->remove($entry);
        }

        $this->entityManager->remove($account);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
