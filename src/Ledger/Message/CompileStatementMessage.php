<?php

declare(strict_types=1);

namespace App\Ledger\Message;

class CompileStatementMessage
{
    public function __construct(
        private string $accountUuid,
        private string $recipientEmail
    ) {
    }

    public function getAccountUuid(): string
    {
        return $this->accountUuid;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }
}
