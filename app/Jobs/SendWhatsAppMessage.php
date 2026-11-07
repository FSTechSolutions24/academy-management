<?php

namespace App\Jobs;

use App\Models\CommunicationMessage;
use App\Models\CommunicationConsent;
use App\Services\WhatsAppCloudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $messageId) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(WhatsAppCloudService $whatsApp): void
    {
        $message = CommunicationMessage::find($this->messageId);
        if (! $message || $message->status !== 'queued') {
            return;
        }

        $consent = CommunicationConsent::where('organization_id', $message->organization_id)
            ->where('branch_id', $message->branch_id)->where('student_id', $message->student_id)
            ->where('guardian_id', $message->guardian_id)->where('is_opted_in', true)->exists();
        if (! $consent) {
            $message->update(['status' => 'cancelled', 'last_error' => 'WhatsApp consent was withdrawn before delivery.']);
            return;
        }

        $claimed = CommunicationMessage::whereKey($message->id)->where('status', 'queued')->update(['status' => 'sending', 'attempts' => DB::raw('attempts + 1'), 'last_error' => null]);
        if (! $claimed) {
            return;
        }
        $message->refresh();
        try {
            $providerId = $whatsApp->send($message);
            $message->update(['status' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now(), 'last_error' => null]);
        } catch (Throwable $error) {
            $message->update(['status' => $this->attempts() >= $this->tries ? 'failed' : 'queued', 'last_error' => mb_substr($error->getMessage(), 0, 2000)]);
            throw $error;
        }
    }

    public function failed(Throwable $error): void
    {
        CommunicationMessage::whereKey($this->messageId)->whereIn('status', ['queued', 'sending'])->update(['status' => 'failed', 'last_error' => mb_substr($error->getMessage(), 0, 2000)]);
    }
}
