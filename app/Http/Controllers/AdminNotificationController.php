<?php
namespace App\Http\Controllers;

use App\Services\BroadcastService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminNotificationController extends Controller
{
    public function __construct(protected BroadcastService $broadcast) {}

    public function broadcastGlobal(Request $request): JsonResponse
    {
        $request->validate([
            'title'   => 'required|string|max:100',
            'message' => 'required|string|max:500',
        ]);

        $this->broadcast->sendGlobalAnnouncement(
            $request->title, 
            $request->message
        );

        return response()->json(['message' => 'Global announcement sent to Redis']);
    }

    public function notifyUser(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'event' => 'required|string', 
            'data'  => 'required|array',
        ]);

        $user = User::findOrFail($userId);

        $this->broadcast->sendUserNotify(
            $user->id, 
            $request->event, 
            $request->data
        );

        return response()->json(['message' => "Notification sent to User {$user->id}"]);
    }
}