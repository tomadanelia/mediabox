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
            $query->where('user_id', $user->id)
                  ->orWhereNull('user_id');
        })
        ->with(['readReceipts' => function($query) use ($user) {
            $query->where('user_id', $user->id);
        }])
        ->orderBy('created_at', 'desc')
        ->paginate($request->input('per_page', 20));

    $notifications->getCollection()->transform(function ($notification) use ($user) {
        if ($notification->user_id !== null) {
            $notification->read = ($notification->status === 'read');
        } else {
            $notification->read = $notification->readReceipts->isNotEmpty();
        }
        unset($notification->readReceipts);
        
        return $notification;
    });

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