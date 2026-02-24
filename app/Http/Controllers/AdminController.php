<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\Channel;
use App\Models\ChannelCategory;
class AdminController extends Controller
{
    public function __construct() {}

    public function dashboard()
    {
        $user = Auth::user(); 
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized');
        }
        return response()->json(['message' => 'Welcome to the admin dashboard']);
    }
    public function addCategories(CategoryRequest $request)
    {
       $category = ChannelCategory::firstOrCreate($request->validated());


    return response()->json([
        'message' => 'Category added successfully',
        'data' => $category
    ]);    
    }
}