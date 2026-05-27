<?php
namespace App\Http\Controllers;

class InterpayInitController extends Controller
{
    public function init(InterPayRequest $request)
    {
        $data = $request->validated();

        try {
            $transaction = PaymentTransaction::create([
                'user_id' => $data['user_id'],
                'amount' => $data['amount'],
                'status' => 'pending',
                'payment_method' => 'interpay',
            ]);

            return response()->json([
                'success' => true,
                'transaction_id' => $transaction->id,
                'message' => 'Payment initialized successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error initializing InterPay transaction: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment.',
            ], 500);
        }
    }
}