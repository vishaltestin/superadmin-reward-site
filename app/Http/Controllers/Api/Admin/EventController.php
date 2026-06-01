<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'vertical_id' => 'required|exists:verticals,id',
        ]);

        $events = Event::with('children.children')
            ->where('vertical_id', $request->vertical_id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $events,
        ]);
    }
}
