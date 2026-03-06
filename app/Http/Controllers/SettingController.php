<?php
namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
   public function updateLogo(Request $request)
{
    $request->validate([
        'svg' => 'required|string'
    ]);

    $filename = 'logos/logo.svg';
    Storage::disk('public')->put($filename, $request->svg);

    $url = '/storage/' . $filename;

    Setting::updateOrCreate(
        ['key' => 'site_logo'],
        ['value' => $url]
    );

    return response()->json(['logo' => $url]);
}

    public function getLogos(): JsonResponse
    {
        $logos = SiteSetting::whereIn('key', ['logo_light', 'logo_dark'])->pluck('value', 'key');
        
        return response()->json($logos);
    }
}