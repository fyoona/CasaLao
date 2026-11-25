<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['name' => 'Standard Room', 'description' => 'Cozy room with 1 queen bed, perfect for solo travelers or couples.', 'price_per_night' => 1500, 'capacity' => 2, 'image_url' => '/room-images/room-1.png'],
            ['name' => 'Deluxe Queen Room', 'description' => 'Spacious room with city view and modern amenities.', 'price_per_night' => 2500, 'capacity' => 3, 'image_url' => '/room-images/room-2.png'],
            ['name' => 'Deluxe King Room', 'description' => 'Perfect blend of comfort and luxury with a stunning city view.', 'price_per_night' => 3200, 'capacity' => 2, 'image_url' => '/room-images/room-3.png'],
            ['name' => 'Family Suite', 'description' => 'Large suite for the whole family, with 2 queen beds.', 'price_per_night' => 4800, 'capacity' => 5, 'image_url' => '/room-images/room-4.png'],
            ['name' => 'Standard Twin Room', 'description' => 'Two comfortable single beds, ideal for friends or colleagues.', 'price_per_night' => 1800, 'capacity' => 2, 'image_url' => '/room-images/room-5.png'],
        ];

        foreach ($rooms as $room) {
            Room::firstOrCreate(['name' => $room['name']], $room);
        }
    }
}
