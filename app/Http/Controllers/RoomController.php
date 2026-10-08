<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    /**
     * Display a listing of the rooms.
     */
    public function index(): Response
    {
        return Inertia::render('Rooms/Index', [
            'rooms' => Room::withCount('sections')->orderBy('room_name')->get(),
        ]);
    }

    /**
     * Store a newly created room.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'room_name' => ['required', 'string', 'max:255'],
        ]);

        Room::create($validated);

        return redirect()->route('rooms.index')->with('success', 'Room created.');
    }

    /**
     * Update the given room.
     */
    public function update(Request $request, Room $room): RedirectResponse
    {
        $validated = $request->validate([
            'room_name' => ['required', 'string', 'max:255'],
        ]);

        $room->update($validated);

        return redirect()->route('rooms.index')->with('success', 'Room updated.');
    }

    /**
     * Remove the given room.
     */
    public function destroy(Room $room): RedirectResponse
    {
        $room->delete();

        return redirect()->route('rooms.index')->with('success', 'Room deleted.');
    }
}
