<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ auth()->user()->isAdmin() ? 'All Bookings' : 'My Bookings' }}</h2>
    </x-slot>

    <div class="max-w-6xl mx-auto" x-data="{ tab: 'all' }" x-cloak>
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-50 text-green-700 border border-green-200 rounded-lg text-sm">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="mb-4 p-3 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-sm">{{ session('warning') }}</div>
        @endif

        <div class="flex items-center justify-between mb-4">
            <div class="inline-flex bg-white border border-gray-200 rounded-lg p-1 text-sm">
                <button type="button" @click="tab = 'all'" :class="tab === 'all' ? 'bg-[#0f1c30] text-white' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-md font-medium transition">All</button>
                <button type="button" @click="tab = 'upcoming'" :class="tab === 'upcoming' ? 'bg-[#0f1c30] text-white' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-md font-medium transition">Upcoming</button>
                <button type="button" @click="tab = 'completed'" :class="tab === 'completed' ? 'bg-[#0f1c30] text-white' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-md font-medium transition">Completed</button>
                <button type="button" @click="tab = 'cancelled'" :class="tab === 'cancelled' ? 'bg-[#0f1c30] text-white' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-md font-medium transition">Cancelled</button>
            </div>
            <a href="{{ route('bookings.create') }}" class="px-4 py-2 bg-amber-400 text-[#0f1c30] rounded-lg hover:bg-amber-300 transition text-sm font-semibold">+ New Booking</a>
        </div>

        @php
            $hasAny = $bookings->isNotEmpty();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @forelse ($bookings as $booking)
                @php
                    $bucket = $booking->status === 'cancelled'
                        ? 'cancelled'
                        : ($booking->check_out->isPast() ? 'completed' : 'upcoming');
                @endphp
                <div x-show="tab === 'all' || tab === '{{ $bucket }}'" class="bg-white rounded-xl border border-gray-100 overflow-hidden flex">
                    <div class="w-28 shrink-0 bg-gray-100">
                        @if ($booking->room->image_url)
                            <img src="{{ $booking->room->image_url }}" class="w-full h-full object-cover">
                        @endif
                    </div>
                    <div class="p-4 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-gray-800">{{ $booking->room->name }}</p>
                                @if (auth()->user()->isAdmin())
                                    <p class="text-xs text-gray-400">{{ $booking->user->name }}</p>
                                @endif
                            </div>
                            <span @class([
                                'px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wide shrink-0',
                                'bg-amber-100 text-amber-700' => $booking->status == 'pending',
                                'bg-green-100 text-green-700' => $booking->status == 'confirmed',
                                'bg-red-100 text-red-700' => $booking->status == 'cancelled',
                            ])>{{ $booking->status }}</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ $booking->check_in->format('M d') }} – {{ $booking->check_out->format('M d, Y') }}</p>
                        <p class="text-sm text-gray-500">{{ $booking->check_in->diffInDays($booking->check_out) }} night(s) · ₱{{ number_format($booking->room->price_per_night * $booking->check_in->diffInDays($booking->check_out), 2) }}</p>
                        @if (auth()->user()->isAdmin())
                            <form action="{{ route('admin.bookings.status', $booking) }}" method="POST" class="flex items-center gap-2 mt-3">
                                @csrf
                                @method('PATCH')
                                <label for="booking-status-{{ $booking->id }}" class="sr-only">Booking status</label>
                                <select id="booking-status-{{ $booking->id }}" name="status" class="text-sm border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="pending" @selected($booking->status === 'pending')>Pending</option>
                                    <option value="confirmed" @selected($booking->status === 'confirmed')>Confirmed</option>
                                    <option value="cancelled" @selected($booking->status === 'cancelled')>Cancelled</option>
                                </select>
                                <button type="submit" class="text-sm font-medium text-indigo-600 hover:underline">Update status</button>
                            </form>
                        @endif
                        <div class="flex items-center gap-3 mt-3">
                            <a href="{{ route('bookings.edit', $booking) }}" class="text-sm font-medium text-amber-600 hover:underline">Edit</a>
                            <form action="{{ route('bookings.destroy', $booking) }}" method="POST" onsubmit="return confirm('Delete this booking?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-500 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 bg-white border border-dashed border-gray-200 rounded-xl p-12 text-center text-gray-400">
                    No bookings yet. <a href="{{ route('bookings.create') }}" class="text-amber-600 font-medium hover:underline">Create one</a>.
                </div>
            @endforelse

            @if ($hasAny)
                <div x-show="!{{ $bookings->pluck('id')->isEmpty() ? 'true' : 'false' }}" class="hidden"></div>
            @endif
        </div>

        @if ($hasAny)
            <template x-if="tab !== 'all'">
                <div
                    x-show="![
                        @foreach ($bookings as $booking)
                            '{{ $booking->status === 'cancelled' ? 'cancelled' : ($booking->check_out->isPast() ? 'completed' : 'upcoming') }}'{{ !$loop->last ? ',' : '' }}
                        @endforeach
                    ].includes(tab)"
                    class="sm:col-span-2 bg-white border border-dashed border-gray-200 rounded-xl p-12 text-center text-gray-400 mt-4"
                >
                    Walang booking sa tab na ito.
                </div>
            </template>
        @endif
    </div>
</x-app-layout>