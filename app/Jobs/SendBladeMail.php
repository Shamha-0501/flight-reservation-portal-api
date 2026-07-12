<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBladeMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public string $recipientEmail,
        public string $subject,
        public string $view,
        public array $data = [],
        public ?string $pdfContent = null,
        public ?string $pdfName = null,
        public string $logLabel = 'mail',
        public array $context = [],
    ) {
    }

    public function handle(): void
    {
        $htmlBody = view($this->view, $this->data)->render();

        $result = MailService::sendMail(
            $this->recipientEmail,
            $this->subject,
            $htmlBody,
            $this->pdfContent,
            $this->pdfName ?? 'attachment.pdf'
        );

        if (! ($result['ok'] ?? false)) {
            Log::error("{$this->logLabel} mail send failed.", array_merge($this->context, [
                'email' => $this->recipientEmail,
                'subject' => $this->subject,
                'error' => $result['message'] ?? 'Unknown mail error',
            ]));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("{$this->logLabel} mail job failed.", array_merge($this->context, [
            'email' => $this->recipientEmail,
            'subject' => $this->subject,
            'error' => $exception->getMessage(),
        ]));
    }
}
