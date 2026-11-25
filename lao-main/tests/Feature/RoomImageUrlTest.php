<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoomSeeder;
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

    public function test_room_setup_uses_local_images_and_can_be_repeated(): void
    {
        $this->get('/setup-rooms-xk92')->assertOk();

        $this->assertDatabaseCount('rooms', 5);
        $this->assertDatabaseHas('rooms', [
            'name' => 'Standard Room',
            'image_url' => '/room-images/room-1.png',
        ]);
        $this->assertDatabaseHas('rooms', [
            'name' => 'Standard Twin Room',
            'image_url' => '/room-images/room-5.png',
        ]);

        $this->get('/setup-rooms-xk92')->assertOk();

        $this->assertDatabaseCount('rooms', 5);
    }

    public function test_room_seeder_creates_default_rooms_without_duplicates(): void
    {
        $this->seed(RoomSeeder::class);
        $this->seed(RoomSeeder::class);

        $this->assertDatabaseCount('rooms', 5);
        $this->assertDatabaseHas('rooms', [
            'name' => 'Standard Room',
            'image_url' => '/room-images/room-1.png',
        ]);
    }
}
