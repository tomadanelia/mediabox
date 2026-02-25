<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Http\Requests\CategoryRequest; 
use Illuminate\Http\Request; 
use Illuminate\Http\JsonResponse; 
class AdminCategoryController extends Controller
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
    public function removeCategory(string $categoryId)
    {
       $category = ChannelCategory::findOrFail($categoryId);
       $category->delete();

    return response()->json([
        'message' => 'Category removed successfully',
        'data' => $category
    ]);    
    }
    public function editCategory(CategoryRequest $request, string $categoryId)
    {
        $category = ChannelCategory::findOrFail($categoryId);
        $category->update($request->validated());

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

   public function getChannelsForCategory(string $categoryId): JsonResponse
{
    $category = ChannelCategory::findOrFail($categoryId);

    $channels = Channel::where('category_id', $categoryId)
        ->select([
            'id',
            'external_id',
            'name_ka',
            'name_en',
            'icon_url',
            'category_id',
            'number'
        ])
        ->with('category:id,name_en')
        ->orderBy('number', 'asc')
        ->get();

    return response()->json([
        'target_category' => $category->name_en,
        'channels' => $channels
    ]);
}

  
    public function assignChannelsToCategory(Request $request, string $categoryId): JsonResponse
    {
        $request->validate([
        'channel_ids' => ['required', 'array'],
        'channel_ids.*' => ['required', 'uuid', 'exists:channels,id'],
        ]);

        $category = ChannelCategory::findOrFail($categoryId);

        Channel::whereIn('id', $request->channel_ids)
            ->update(['category_id' => $category->id]);

        return response()->json([
            'message' => 'Channels successfully added to ' . $category->name_en,
            'count' => count($request->channel_ids)
        ]);
    }
    public function users()
    {
        $users = User::with('account')
    ->select('id', 'username', 'email', 'full_name', 'avatar_url', 'role')
    ->paginate(20);
        return response()->json($users);
    }
}