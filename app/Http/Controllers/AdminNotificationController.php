<?php
namespace App\Http\Controllers;

use App\Services\BroadcastService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminNotificationController extends Controller
{
    public function __construct(protected BroadcastService $broadcast) {}
    public function notifyUser(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string',
            'event' => 'required|string', 
        ]);

        $user = User::findOrFail($userId);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $request->event,
            'title' => $request->title,
            'payload' => ['message' => $request->message],
            'status' => 'pending'
        ]);

        $this->broadcast->sendUserNotify(
            $user->id, 
            'notification_received', 
            [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $request->message,
                'event' => $request->event
            ]
        );

        $notification->update(['status' => 'sent', 'sent_at' => now()]);

        return response()->json(['message' => "Notification recorded and sent."]);
    }

    public function broadcastGlobal(Request $request): JsonResponse
{
    $request->validate([
        'title'   => 'required|string|max:100',
        'message' => 'required|string|max:500',
    ]);

    Notification::create([
        'user_id' => null, 
        'type'    => 'global_announcement',
        'title'   => $request->title,
        'payload' => ['message' => $request->message],
        'status'  => 'sent',
        'sent_at' => now()
    ]);

    $this->broadcast->sendGlobalAnnouncement($request->title, $request->message);

    return response()->json(['message' => 'Global announcement saved and broadcasted.']);
}
}