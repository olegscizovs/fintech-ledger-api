<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use App\Account\Entity\Account;
use App\Ledger\Entity\LedgerEntry;
use App\Ledger\Exception\InsufficientFundsException;
use App\Ledger\Exception\InvalidTransactionException;
use App\Ledger\Message\CompileStatementMessage;
use App\Ledger\Repository\LedgerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class LedgerController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LedgerRepository $ledgerRepository,
        private MessageBusInterface $messageBus,
        private \Symfony\Bundle\SecurityBundle\Security $security
    ) {
    }

    #[Route('/api/transactions', name: 'api_post_transaction', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }
        $description = isset($data['description']) && is_string($data['description']) ? trim($data['description']) : '';
        $entriesData = isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : [];

        if ($description === '' || count($entriesData) === 0) {
            return new JsonResponse(['error' => 'Missing description or entries'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($description) > 255) {
            return new JsonResponse(['error' => 'Description cannot exceed 255 characters'], Response::HTTP_BAD_REQUEST);
        }


        $formattedEntries = [];
        $accountRepository = $this->entityManager->getRepository(Account::class);

        try {
            foreach ($entriesData as $entry) {
                if (!is_array($entry)) {
                    return new JsonResponse(['error' => 'Invalid entry format'], Response::HTTP_BAD_REQUEST);
                }
                $accountUuid = isset($entry['account_uuid']) && is_string($entry['account_uuid']) ? $entry['account_uuid'] : '';
                $direction = isset($entry['direction']) && is_string($entry['direction']) ? $entry['direction'] : '';
                $amount = isset($entry['amount']) && (is_string($entry['amount']) || is_numeric($entry['amount'])) ? (string) $entry['amount'] : '';

                if ($accountUuid === '' || $direction === '' || $amount === '') {
                    return new JsonResponse(['error' => 'Each entry must have account_uuid, direction, and amount'], Response::HTTP_BAD_REQUEST);
                }

                $account = $accountRepository->findOneBy([
                    'uuid' => $accountUuid,
                    'user' => $user
                ]);
                if (!$account) {
                    return new JsonResponse(['error' => sprintf('Account "%s" not found', $accountUuid)], Response::HTTP_NOT_FOUND);
                }

                if (!is_numeric($amount) || bccomp($amount, '0.0000', 4) <= 0) {
                    return new JsonResponse(['error' => 'Amount must be a positive number'], Response::HTTP_BAD_REQUEST);
                }

                $formattedEntries[] = [
                    'account' => $account,
                    'direction' => strtoupper($direction),
                    'amount' => number_format((float)$amount, 4, '.', ''),
                ];
            }

            // Post transaction using our repository (uses pessimistic locking internally)
            $transaction = $this->ledgerRepository->postTransaction($description, $formattedEntries);

            $responseEntries = [];
            foreach ($transaction->getEntries() as $entry) {
                $responseEntries[] = [
                    'uuid' => $entry->getUuid(),
                    'account_uuid' => $entry->getAccount()->getUuid(),
                    'direction' => $entry->getDirection(),
                    'amount' => $entry->getAmount(),
                ];
            }

            return new JsonResponse([
                'uuid' => $transaction->getUuid(),
                'description' => $transaction->getDescription(),
                'created_at' => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'entries' => $responseEntries,
            ], Response::HTTP_CREATED);

        } catch (InsufficientFundsException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (InvalidTransactionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An unexpected error occurred during processing'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    #[Route('/api/accounts/{uuid}/statement', name: 'api_get_statement', methods: ['GET'])]
    public function getStatement(string $uuid): JsonResponse
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

        $entries = $this->entityManager->getRepository(LedgerEntry::class)->findBy(
            ['account' => $account],
            ['createdAt' => 'DESC']
        );

        $response = [];
        foreach ($entries as $entry) {
            $response[] = [
                'uuid' => $entry->getUuid(),
                'direction' => $entry->getDirection(),
                'amount' => $entry->getAmount(),
                'transaction' => [
                    'uuid' => $entry->getTransaction()->getUuid(),
                    'description' => $entry->getTransaction()->getDescription(),
                ],
                'created_at' => $entry->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route('/api/accounts/{uuid}/statement/compile', name: 'api_compile_statement', methods: ['POST'])]
    public function compileStatement(string $uuid, Request $request): JsonResponse
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
            $data = [];
        }
        $email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'A valid email is required'], Response::HTTP_BAD_REQUEST);
        }

        // Dispatch asynchronous statement compilation message
        $this->messageBus->dispatch(new CompileStatementMessage($uuid, $email));

        return new JsonResponse([
            'status' => 'accepted',
            'message' => 'Statement compilation request received and queued.',
        ], Response::HTTP_ACCEPTED);
    }
}
