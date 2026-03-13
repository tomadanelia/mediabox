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
            'password'  => 'required|string|min:8',
            'full_name' => 'nullable|string|max:255',
            'username'  => 'nullable|string|unique:users',
        ]);

       $user = DB::transaction(function () use ($validated) {
        $user = User::create([
            'email'     => $validated['email'] ?? null,
            'phone'     => $validated['phone'] ?? null,
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

            \Illuminate\Support\Facades\Log::info("Admin Balance Adjustment", [
                'admin_id'   => $request->user()->id,
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
}