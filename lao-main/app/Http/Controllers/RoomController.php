<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::withCount(['bookings' => function ($query) {
            $query->where('status', '!=', 'cancelled')
                  ->where('check_out', '>=', now());
        }])->get();

        if (auth()->user()->isAdmin()) {
            return view('rooms.admin-index', compact('rooms'));
        }

        return view('rooms.index', compact('rooms'));
    }

    public function create()
    {
        return view('rooms.create');
    }

    public function store(Request $request)
    {
        $imageUrl = $request->input('image_url');
        if (is_string($imageUrl)) {
            $request->merge(['image_url' => $this->normalizeImageUrl($imageUrl)]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_per_night' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
            'image_url' => 'nullable|url',
        ]);

        Room::create($validated);

        return redirect()->route('rooms.index')->with('success', 'Room added!');
    }

    public function edit(Room $room)
    {
        return view('rooms.edit', compact('room'));
    }

    public function update(Request $request, Room $room)
    {
        $imageUrl = $request->input('image_url');
        if (is_string($imageUrl)) {
            $request->merge(['image_url' => $this->normalizeImageUrl($imageUrl)]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_per_night' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
            'image_url' => 'nullable|url',
        ]);

        $room->update($validated);

        return redirect()->route('rooms.index')->with('success', 'Room updated!');
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()->route('rooms.index')->with('success', 'Room deleted!');
    }

    private function normalizeImageUrl(string $imageUrl): string
    {
        $parsedUrl = parse_url($imageUrl);

        if (! is_array($parsedUrl)
            || ! preg_match('/(^|\.)bing\.com$/i', $parsedUrl['host'] ?? '')
            || ($parsedUrl['path'] ?? '') !== '/images/search') {
            return $imageUrl;
        }

        parse_str($parsedUrl['query'] ?? '', $query);

        return is_string($query['mediaurl'] ?? null) ? $query['mediaurl'] : $imageUrl;
    }
}