<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'     => 'required_without:phone|nullable|email|unique:users',
            'phone'     => 'required_without:email|nullable|string|unique:users',
            'numeric_id'=> 'required|string|min:5',
            'password'  => 'required|string|min:8',
            'full_name' => 'nullable|string|max:255',
            'username'  => 'nullable|string|unique:users',
        ]);
        $numericId = $validated['numeric_id'];
        //for different database system in our company network users that has 5 digit id's
        if(strlen($numericId)==5){
        $numericId=$numericId."0";
        }
       
       $user = DB::transaction(function () use ($validated,$numericId) {
        $user = User::create([
            'email'     => $validated['email'] ?? null,
            'phone'     => $validated['phone'] ?? null,
            'numeric_id'=> (int) $numericId,
            'password'  => Hash::make($validated['password']),
            'full_name' => $validated['full_name'] ?? null,
            'username'  => $validated['username'] ?? null,
            'role'      => 'user',
        ]);

        Account::create(['user_id' => $user->id, 'balance' => 0]);

        return $user;
    });

    return response()->json([
        'message' => 'User created successfully',
        'user'    => $user->load('account'),
    ], 201);
    }

    /**
     * Adjust User Balance (Increase or Decrease)
     */
    public function adjustBalance(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string', 
            'amount'     => 'required|numeric',
            'reason'     => 'nullable|string'
        ]);

        $id = $request->identifier;

        return DB::transaction(function () use ($id, $request) {
            $user = User::where('numeric_id', $id)
                        ->orWhere('email', $id)
                        ->orWhere('phone', $id)
                        ->firstOrFail();

            $account = $user->account()->lockForUpdate()->firstOrFail();
            
            $newBalance = $account->balance + $request->amount;
            if ($newBalance < 0) {
                return response()->json(['message' => 'Insufficient funds for this decrease'], 422);
            }

            $account->update(['balance' => $newBalance]);
            //have to modify this after securing
            \Illuminate\Support\Facades\Log::info("Admin Balance Adjustment", [
                'admin_id'   => "request->user()->id",
                'user_id'    => $user->id,
                'adjustment' => $request->amount,
                'new_total'  => $newBalance,
                'reason'     => $request->reason
            ]);

            return response()->json([
                'message' => 'Balance updated successfully',
                'new_balance' => $newBalance,
                'user' => $user->username ?? $user->email
            ]);
        });
    }
    public function search(Request $request): JsonResponse
{
    $request->validate([
        'q' => 'required|string|min:1'
    ]);

    $query = trim($request->input('q'));
   $userQuery = User::query();

if (str_contains($query, '@')) {
    $userQuery->where('email', $query);
} else if (preg_match('/^\+?[\d\s\-]+$/', $query)) {
    $digits = preg_replace('/[\s\-]/', '', ltrim($query, '+'));

    if (strlen($digits) === 6) {
        $userQuery->where('numeric_id', (int) $digits);
    } else {
        $userQuery->where('phone', $digits);
    }
} else {
    $userQuery->where('username', $query);
}

$user = $userQuery->select(['id','numeric_id','username','email','phone','full_name','role','created_at'])
                ->with(['account', 'subscriptionPlans' => function($q) {
                    $q->wherePivot('expires_at', '>', now())
                      ->wherePivot('is_active', true);
                }])
                ->first();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    return response()->json([
    'user' => [
        'id'         => $user->id,
        'numeric_id' => $user->numeric_id,
        'username'   => $user->username,
        'email'      => $user->email,
        'phone'      => $user->phone,
        'full_name'  => $user->full_name,
        'role'       => $user->role,
        'created_at' => $user->created_at,
    ],

    'account' => $user->account ? [
        'balance' => $user->account->balance,
        'status'  => $user->account->status,
    ] : null,

    'active_plans' => $user->subscriptionPlans 
        ? $user->subscriptionPlans->map(function($plan) {
            $expiresAt = $plan->pivot->expires_at;
            $daysLeft  = $expiresAt ? now()->diffInDays($expiresAt, false) : null;
            return [
                'name'       => $plan->name_en,
                'expires_at' => $expiresAt,
                'days_left'  => $daysLeft !== null ? max(0, $daysLeft) : null,
            ];
        })
        : [],
    'meta' => [
    'is_verified'  => (bool) $user->email_verified_at || (bool) $user->phone_verified_at,
    'has_account'  => $user->account !== null,
    'has_plans'    => $user->subscriptionPlans->isNotEmpty(),
],
]);
}
}