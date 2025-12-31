<?php
namespace App\Http\Controllers;
use App\Services\CheckBalanceService;
use App\Services\CreditBalanceService;
use Illuminate\Http\Request;
class InterPayController extends Controller
{
    public function __construct(
        private CheckBalanceService $checkBalance,
        private CreditBalanceService $creditBalance
    ) {}

    public function handle(Request $request)
    {
        return match ($request->input('OP')) {
            'debt' => $this->checkBalance->showBalance($request),
            'paysuccess' => $this->creditBalance->increaseBalance($request),
            default => response()->json(['error' => 'Invalid OP'], 400),
        };
    }
}

