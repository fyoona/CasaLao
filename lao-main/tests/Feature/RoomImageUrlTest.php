<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_bing_image_result_links_are_saved_as_direct_image_urls_when_creating_rooms(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('rooms.store'), [
            'name' => 'Test Room',
            'price_per_night' => 100,
            'capacity' => 2,
            'image_url' => 'https://www.bing.com/images/search?mediaurl=https%3A%2F%2Fimages.example.test%2Froom.jpg',
        ])->assertRedirect(route('rooms.index'));

        $this->assertDatabaseHas('rooms', [
            'name' => 'Test Room',
            'image_url' => 'https://images.example.test/room.jpg',
        ]);
    }

    public function test_bing_image_result_links_are_saved_as_direct_image_urls_when_updating_rooms(): void
    {
        $user = User::factory()->create();
        $room = Room::create([
            'name' => 'Test Room',
            'price_per_night' => 100,
            'capacity' => 2,
        ]);

        $this->actingAs($user)->put(route('rooms.update', $room), [
            'name' => 'Test Room',
            'price_per_night' => 100,
            'capacity' => 2,
            'image_url' => 'https://www.bing.com/images/search?mediaurl=https%3A%2F%2Fimages.example.test%2Froom.jpg',
        ])->assertRedirect(route('rooms.index'));

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'image_url' => 'https://images.example.test/room.jpg',
        ]);
    }
}
