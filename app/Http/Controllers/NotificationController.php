<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationReadReceipt;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where(function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                          ->where('status', '!=', 'read');
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNull('user_id')
                          ->whereDoesntHave('readReceipts', function ($inner) use ($user) {
                              $inner->where('user_id', $user->id);
                          });
                    });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($notifications);
    }

    public function markAsRead($id, Request $request)
    {
        $user = $request->user();
        $notification = Notification::findOrFail($id);

        // ✅ Block access to other users' personal notifications
        if ($notification->user_id !== null && $notification->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        if ($notification->user_id === $user->id) {
            $notification->update(['status' => 'read']);
        } elseif ($notification->user_id === null) {
            NotificationReadReceipt::updateOrInsert(
                [
                    'user_id'         => $user->id,
                    'notification_id' => $notification->id
                ],
                ['read_at' => now()]
            );
        }

        return response()->json(['message' => 'success']);
    }
}