<?php
namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function updateLogos(Request $request): JsonResponse
    {
        $request->validate([
            'logo_light' => 'required|string', 
            'logo_dark'  => 'required|string',
        ]);

        SiteSetting::updateOrCreate(['key' => 'logo_light'], ['value' => $request->logo_light]);
        SiteSetting::updateOrCreate(['key' => 'logo_dark'],  ['value' => $request->logo_dark]);

        return response()->json(['message' => 'Logos updated successfully']);
    }

    public function getLogos(): JsonResponse
    {
        $logos = SiteSetting::whereIn('key', ['logo_light', 'logo_dark'])->pluck('value', 'key');
        
        return response()->json($logos);
    }
}