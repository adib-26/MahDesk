<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InboundEmail\InboundEmailIngestor;
use App\Services\InboundEmail\PostmarkInboundParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InboundEmailController extends Controller
{
    /**
     * Postmark inbound webhook. Configure the webhook URL with HTTP basic auth,
     * e.g. https://inbound:SECRET@desk.example.com/api/v1/inbound/email/postmark,
     * or send the secret in an X-Inbound-Secret header.
     */
    public function postmark(Request $request, InboundEmailIngestor $ingestor, PostmarkInboundParser $parser): JsonResponse
    {
        $secret = (string) config('services.inbound_email.secret');
        $provided = (string) ($request->header('X-Inbound-Secret') ?? $request->getPassword() ?? '');

        // Fail closed: without a configured secret the endpoint accepts nothing.
        // 403 also tells Postmark not to retry.
        abort_if($secret === '' || ! hash_equals($secret, $provided), 403);

        if ($request->header('Content-Length') !== null
            && (int) $request->header('Content-Length') > (int) config('services.inbound_email.max_payload_bytes')) {
            return response()->json(['message' => 'Inbound email payload is too large.'], 413);
        }

        $payload = $request->json()->all();
        if (! is_array($payload)) {
            return response()->json(['message' => 'Inbound email payload must be a JSON object.'], 422);
        }

        $recipientCount = collect([
            $payload['To'] ?? [],
            $payload['Cc'] ?? [],
            $payload['Bcc'] ?? [],
        ])->flatten()->filter()->count();
        if ($recipientCount > (int) config('services.inbound_email.max_recipients')) {
            return response()->json(['message' => 'Inbound email has too many recipients.'], 422);
        }

        try {
            $record = $ingestor->ingest($parser, $payload);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['id' => $record->id, 'status' => $record->status]);
    }
}
