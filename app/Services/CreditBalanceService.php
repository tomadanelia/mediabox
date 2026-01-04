<?php
namespace App\Services;
use App\Models\Account;
use App\Models\InterpayPayment;
use App\Models\InterpayCallbackLog;
use App\Http\Requests\InterPayRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditBalanceService
{
    public function increaseBalance(InterPayRequest $request)
    {
        $logId = $this->logCallback($request, 'pending');
        
        try {
            $result = DB::transaction(function () use ($request) {
                $accountId = $request->input('CUSTOMER_ID');
                $paymentId = $request->input('PAYMENT_ID');
                $amount = (int) $request->input('PAY_AMOUNT');
                
                $paymentExists = InterpayPayment::where('payment_id', $paymentId)
                    ->lockForUpdate()
                    ->exists();
                    
                if ($paymentExists) {
                    return [
                        'success' => false,
                        'message' => 'Error,Payment already processed.',
                        'status' => 200
                    ];
                }
                
                $account = Account::where('id', $accountId)
                    ->lockForUpdate()
                    ->first();
                    
                if (!$account) {
                    return [
                        'success' => false,
                        'message' => 'Error,Customer not found.',
                        'status' => 404
                    ];
                }
                
                if (!is_numeric($amount) || $amount <= 0) {
                    return [
                        'success' => false,
                        'message' => 'Error,Invalid amount.',
                        'status' => 400
                    ];
                }
                $account->balance += $amount/100;
                $account->save();
                
                $payment = InterpayPayment::create([
                    'payment_id' => $paymentId,
                    'account_id' => $accountId,
                    'service_id' => $request->input('SERVICE_ID'),
                    'amount_tetri' => $amount,
                    'amount_lari' => $amount / 100, 
                    'status' => 'completed',
                    'provider' => $request->input('PROVIDER'),
                    'terminal' => $request->input('TERMINAL'),
                ]);
                
                  return [
                'success' => true,
                'status' => 200,
                'transaction_id' => 'TXN_' . $payment->id
            ];
            });
            
            $this->updateCallbackLog($logId, $result, $result['status']);
            return response()->json($result, $result['status']);

            
        } catch (\Exception $e) {
            Log::error('InterPay payment failed', [
                'error' => $e->getMessage(),
                'payment_id' => $request->input('PAYMENT_ID')
            ]);
            
            $errorResponse = ['message' => 'Error,Payment processing failed.'];
            $this->updateCallbackLog($logId, $errorResponse, 500);
            
            return response()->json($errorResponse, 500);
        }
    }
    
    private function logCallback(InterPayRequest $request, string $status): int
    {
        $log = InterpayCallbackLog::create([
            'payment_id' => $request->input('PAYMENT_ID'),
            'op' => $request->input('OP'),
            'request_headers' => $request->headers->all(),
            'request_body' => $request->all(),
            'ip_address' => $request->ip(),
            'received_at' => now(),
        ]);
        
        return $log->id;
    }
    
    private function updateCallbackLog(int $logId, array $response, int $status): void
    {
        InterpayCallbackLog::where('id', $logId)->update([
            'response_body' => $response,
            'response_status' => $status,
        ]);
    }
}
