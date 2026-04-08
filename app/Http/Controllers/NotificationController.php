<?php

namespace App\Http\Controllers;
use App\Models\Notification;
use App\Models\NotificationReadReceipt;
class NotificationController extends Controller
{

public function index(Request $request)
{
    $user = $request->user();

    $notifications = Notification::where(function ($query) use ($user) {
        $query->where('user_id', $user->id)
              ->where('status', '!=', 'read');
    })
    ->orWhere(function ($query) use ($user) {
        $query->whereNull('user_id')
              ->whereDoesntHave('readReceipts', function ($q) use ($user) {
                  $q->where('user_id', $user->id);
              });
    })
    ->orderBy('created_at', 'desc')
    ->take(20)
    ->get();

    return response()->json($notifications);
}

public function markAsRead($id, Request $request)
{
    $user = $request->user();
    $notification = Notification::findOrFail($id);
    if ($notification->user_id === $user->id) {
        $notification->update(['status' => 'read']);
    } 
    elseif ($notification->user_id === null) {
        NotificationReadReceipt::updateOrInsert([
            'user_id' => $user->id,
            'notification_id' => $notification->id
        ], ['read_at' => now()]);
    }

    return response()->json(['message' => 'success']);
}
}