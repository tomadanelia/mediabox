<?php
namespace App\Services;
use App\Models\Account;
use Illuminate\Http\Request;
class CreditBalanceService
{
   
public function increaseBalance(Request $request)
    {
        $accountId = $request->input('account_id');
        $amount = $request->input('amount');

        $account = Account::where('id', $accountId)->first();

        if (! $account) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $account->balance += $amount;
        $account->save();

        return response()->json([
            'message' => 'Balance updated successfully',
            'balance' => $account->balance,
        ]);
    }
}