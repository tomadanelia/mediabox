<?php
namespace App\Services;
use App\Http\Requests\InterPayRequest;
use App\Models\Account;
use App\Models\InterpayCallbackLog;
use Illuminate\Support\Facades\Log;

class CheckBalanceService
{
    public function showBalance(InterPayRequest $request)
    {
        $logId = $this->logCallback($request);
        
        try {
            $accountId = $request->input('CUSTOMER_ID');
            
            $account = Account::where('id', $accountId)->first();
            
            if (!$account) {
                $response = ['message' => 'Error,Customer not found.'];
                $this->updateCallbackLog($logId, $response, 404);
                
                return response()->json($response, 404);
            }
            
            $response = [
                'message' => 'Success',
                'customer_id' => $account->id,
                'balance' => $account->balance,
            ];
            
            $this->updateCallbackLog($logId, $response, 200);
            
            return response()->json($response, 200);
            
        } catch (\Exception $e) {
            Log::error('InterPay balance check failed', [
                'error' => $e->getMessage(),
                'customer_id' => $request->input('CUSTOMER_ID')
            ]);
            
            $errorResponse = ['message' => 'Error,Balance check failed.'];
            $this->updateCallbackLog($logId, $errorResponse, 500);
            
            return response()->json($errorResponse, 500);
        }
    }
    
    private function logCallback(InterPayRequest $request): int
    {
        $log = InterpayCallbackLog::create([
            'payment_id' => null, 
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