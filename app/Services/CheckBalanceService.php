<?php
class CheckBalanceService
{
    public function showBalance(Request $request)
    {
        $accountId = $request->input('account_id');
        $account = Account::where('id', $accountId)->first(); 

        if (! $account) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        return response()->json([
            'balance' => $account->balance,
        ]);
    }
}