<?php

namespace App\Services;

use App\Models\CommunicationMessage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudService
{
    public function send(CommunicationMessage $message): string
    {
        $phoneId = config('services.whatsapp.phone_id');
        $token = config('services.whatsapp.access_token');
        $version = config('services.whatsapp.api_version');
        if (! $phoneId || ! $token || ! $version) {
            throw new RuntimeException('WhatsApp Cloud API is not configured. Set WHATSAPP_PHONE_ID, WHATSAPP_ACCESS_TOKEN and WHATSAPP_API_VERSION.');
        }

        $response = Http::withToken($token)->acceptJson()->timeout(20)->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => preg_replace('/\D+/', '', $message->to_phone),
            'type' => 'template',
            'template' => [
                'name' => $message->template_name,
                'language' => ['code' => $message->language],
                'components' => $message->parameters ? [['type' => 'body', 'parameters' => collect($message->parameters)->map(fn ($value) => ['type' => 'text', 'text' => (string) $value])->values()->all()]] : [],
            ],
        ])->throw()->json();

        $id = data_get($response, 'messages.0.id');
        if (! $id) {
            throw new RuntimeException('WhatsApp did not return a message identifier.');
        }

        return $id;
    }
}
