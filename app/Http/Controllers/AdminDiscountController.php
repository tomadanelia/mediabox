<?php
namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminDiscountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Discount::withCount('users')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'value'       => 'required|numeric|min:0', 
            'target_id'   => 'nullable|uuid|exists:subscription_plans,id', 
            'is_global'   => 'required|boolean',
            'is_active'   => 'nullable|boolean', 
            'starts_at'   => 'nullable|date',
            'expires_at'  => 'nullable|date|after_or_equal:starts_at', 
        ]);

        $discount = Discount::create($validated);
        
        return response()->json([
            'message' => 'Discount/Sale created successfully',
            'data' => $discount
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $discount = Discount::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'value'       => 'sometimes|numeric|min:0',
            'target_id'   => 'nullable|uuid|exists:subscription_plans,id',
            'is_global'   => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
            'starts_at'   => 'nullable|date',
            'expires_at'  => 'nullable|date|after_or_equal:starts_at',
        ]);

        $discount->update($validated);

        return response()->json([
            'message' => 'Discount updated successfully',
            'data' => $discount
        ]);
    }

    public function assignToUser(Request $request, $discountId): JsonResponse
    {
        $request->validate(['user_id' => 'required|uuid|exists:users,id']);
        
        $discount = Discount::findOrFail($discountId);

        if ($discount->is_global) {
            return response()->json(['message' => 'This is a global sale; all users already have access.'], 422);
        }

        $discount->users()->syncWithoutDetaching($request->user_id);

        return response()->json(['message' => 'User assigned to special price successfully']);
    }

    public function destroy($id): JsonResponse
    {
        $discount = Discount::findOrFail($id);
        $discount->delete();

        return response()->json(['message' => 'Discount deleted successfully']);
    }
}