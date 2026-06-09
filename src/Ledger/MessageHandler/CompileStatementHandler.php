<?php

declare(strict_types=1);

namespace App\Ledger\MessageHandler;

use App\Account\Entity\Account;
use App\Ledger\Entity\LedgerEntry;
use App\Ledger\Message\CompileStatementMessage;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class CompileStatementHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {
    }

    public function __invoke(CompileStatementMessage $message): void
    {
        $accountUuid = $message->getAccountUuid();
        $email = $message->getRecipientEmail();

        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['uuid' => $accountUuid]);
        if (!$account) {
            return;
        }

        // Fetch ledger entries for this account
        $entries = $this->entityManager->getRepository(LedgerEntry::class)->findBy(
            ['account' => $account],
            ['createdAt' => 'DESC']
        );

        $outputDir = '/app/var/statements';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $filename = sprintf('statement_%s_%d.pdf', $accountUuid, time());
        $filePath = sprintf('%s/%s', $outputDir, $filename);

        // Build a professional HTML statement template
        $html = '
        <html>
        <head>
            <style>
                body { font-family: sans-serif; color: #333; line-height: 1.4; }
                .header { border-bottom: 2px solid #0b0f19; padding-bottom: 10px; margin-bottom: 20px; }
                .title { font-size: 24px; font-weight: bold; color: #0b0f19; }
                .meta-table { width: 100%; margin-bottom: 30px; border-collapse: collapse; }
                .meta-table td { padding: 5px 0; font-size: 14px; }
                .entries-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .entries-table th { background-color: #f2f2f2; border-bottom: 2px solid #ddd; padding: 10px; text-align: left; font-size: 13px; }
                .entries-table td { border-bottom: 1px solid #eee; padding: 10px; font-size: 12px; }
                .debit { color: #dc3545; }
                .credit { color: #28a745; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">VeloLedger Financial Statement</div>
            </div>
            
            <table class="meta-table">
                <tr>
                    <td><strong>Account Name:</strong> ' . htmlspecialchars($account->getName()) . '</td>
                    <td><strong>Generated At:</strong> ' . (new \DateTimeImmutable())->format('Y-m-d H:i:s') . '</td>
                </tr>
                <tr>
                    <td><strong>Account UUID:</strong> ' . htmlspecialchars($account->getUuid()) . '</td>
                    <td><strong>Customer ID:</strong> ' . htmlspecialchars($account->getCustomerId()) . '</td>
                </tr>
                <tr>
                    <td><strong>Current Balance:</strong> ' . htmlspecialchars($account->getBalance() . ' ' . $account->getCurrency()) . '</td>
                    <td><strong>Recipient:</strong> ' . htmlspecialchars($email) . '</td>
                </tr>
            </table>

            <h3>Ledger Entries</h3>
            <table class="entries-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Direction</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($entries as $entry) {
            $class = $entry->getDirection() === 'DEBIT' ? 'debit' : 'credit';
            $sign = $entry->getDirection() === 'DEBIT' ? '-' : '+';
            $html .= '
                    <tr>
                        <td>' . $entry->getCreatedAt()->format('Y-m-d H:i:s') . '</td>
                        <td><span class="' . $class . '">' . htmlspecialchars($entry->getDirection()) . '</span></td>
                        <td><strong class="' . $class . '">' . $sign . htmlspecialchars($entry->getAmount()) . '</strong></td>
                        <td>' . htmlspecialchars($entry->getTransaction()->getDescription()) . '</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
        </body>
        </html>';

        // Configure Dompdf Options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        // Render PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($filePath, $dompdf->output());

        // Construct and send email with PDF attachment
        $emailMessage = (new Email())
            ->from('no-reply@velo.finance')
            ->to($email)
            ->subject(sprintf('VeloLedger Account Statement - %s', $account->getName()))
            ->text(sprintf(
                "Hello,\n\nPlease find attached the account statement PDF for '%s' (%s).\n\nCurrent Balance: %s %s\nGenerated At: %s\n\nBest regards,\nVeloLedger Team",
                $account->getName(),
                $account->getUuid(),
                $account->getBalance(),
                $account->getCurrency(),
                (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ))
            ->attachFromPath($filePath, $filename, 'application/pdf');

        $this->mailer->send($emailMessage);
    }
}
