<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\UserDevice;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('account'));
    }
    public function registerBusiness(Request $request): JsonResponse
{
    $request->validate([
        'company_name' => 'required|string|max:255',
        'tax_id'       => 'required|string|max:50|unique:companies,tax_id',
        'purpose'      => 'required|string|max:1000',
    ]);

    $user = $request->user();

    if ($user->company_id) {
        return response()->json(['message' => 'User is already associated with a company.'], 422);
    }

    $company = DB::transaction(function () use ($request, $user) {
        $company = Company::create([
            'name'    => $request->company_name,
            'tax_id'  => $request->tax_id,
            'purpose' => $request->purpose,
        ]);

        $user->update([
            'company_id' => $company->id,
            'role' => 'businessman'
        ]);

        return $company;
    });

    return response()->json([
        'success' => true,
        'company' => $company
    ]);
}
    public function giveTvDeviceName(Request $request):JsonResponse
    {
        $request->validate([
        'device_id' => 'required|string|exists:user_devices,device_id',
        'name'=>'required|string'
        ]);
        try {
        $affected = UserDevice::where('device_id', $request->device_id)
        ->update(['name' => $request->name]);

       if ($affected === 0) {
        return response()->json([
            'success' => false,
            'message' => 'No device updated'
        ], 404);
       }

         return response()->json([
        'success' => true,
        'name' => $request->name
       ]);
        } catch (\Exception $e) {
         return response()->json([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ], 500);
}
        
    }
}