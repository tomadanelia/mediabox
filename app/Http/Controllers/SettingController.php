<?php
namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function updateLogos(Request $request): JsonResponse
    {
        $request->validate([
            'logo_light' => 'nullable|string', 
            'logo_dark'  => 'nullable|string',
        ]);

        $results = [];

        if ($request->filled('logo_light')) {
            $path = 'logos/logo_light.svg';
            Storage::disk('public')->put($path, $request->logo_light);
            
            SiteSetting::updateOrCreate(
                ['key' => 'logo_light'],
                ['value' => $path]
            );
            $results['logo_light'] = Storage::url($path);
        }

        if ($request->filled('logo_dark')) {
            $path = 'logos/logo_dark.svg';
            Storage::disk('public')->put($path, $request->logo_dark);
            
            SiteSetting::updateOrCreate(
                ['key' => 'logo_dark'],
                ['value' => $path]
            );
            $results['logo_dark'] = Storage::url($path);
        }

        return response()->json([
            'message' => 'Logos saved as files and paths updated in DB',
            'urls' => $results
        ]);
    }

    public function getLogos(): JsonResponse
    {
        $settings = SiteSetting::whereIn('key', ['logo_light', 'logo_dark'])->get();
        
        $data = $settings->pluck('value', 'key')->map(function($path) {
            return asset(Storage::url($path));
        });

        return response()->json($data);
    }
}