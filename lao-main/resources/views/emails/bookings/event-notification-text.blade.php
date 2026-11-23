Booking {{ ucfirst($event) }}

A booking was {{ $event }}.

Guest: {{ $booking->user->name }} ({{ $booking->user->email }})
Room: {{ $booking->room->name }}
Check-in: {{ $booking->check_in->format('M d, Y') }}
Check-out: {{ $booking->check_out->format('M d, Y') }}
