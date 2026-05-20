<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSupportTicketRequest;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function store(CreateSupportTicketRequest $request): JsonResponse
    {
        $user = $request->user();
        $ua = $request->userAgent() ?? '';

        $diagnosticMetadata = array_merge([
            'ip' => $request->ip(),
            'user_agent' => $ua,
            'os' => $this->parseOs($ua),
            'app_version' => $request->header('X-App-Version', 'web-1.0.0'),
        ], $request->input('device_info', []));

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'type' => $request->type,
            'subject' => $request->subject,
            'message' => $request->message,
            'metadata' => $diagnosticMetadata,
        ]);

        return response()->json([
            'message' => 'Support request submitted successfully.',
            'ticket_id' => $ticket->id
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->latest()
            ->paginate(15);

        return response()->json($tickets);
    }

    private function parseOs(string $ua): string
    {
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iOS';
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Macintosh')) return 'MacOS';
        return 'Unknown';
    }
}