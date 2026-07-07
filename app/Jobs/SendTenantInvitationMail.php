<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTenantInvitationMail implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(
        public string $recipientEmail,
        public string $subject,
        public string $htmlBody,
        public int $tenantId,
        public int $invitationId,
    ) {
    }

    public function handle(): void
    {
        $result = MailService::sendMail(
            $this->recipientEmail,
            $this->subject,
            $this->htmlBody
        );

        if (! ($result['ok'] ?? false)) {
            Log::error('Tenant invitation mail send failed.', [
                'tenant_id' => $this->tenantId,
                'email' => $this->recipientEmail,
                'invitation_id' => $this->invitationId,
                'error' => $result['message'] ?? 'Unknown mail error',
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Tenant invitation mail job failed.', [
            'tenant_id' => $this->tenantId,
            'email' => $this->recipientEmail,
            'invitation_id' => $this->invitationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
