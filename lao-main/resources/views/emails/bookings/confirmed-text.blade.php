Booking confirmed

Hello {{ $booking->user->name }},

Your booking has been confirmed:
Room: {{ $booking->room->name }}
Check-in: {{ $booking->check_in->format('M d, Y') }}
Check-out: {{ $booking->check_out->format('M d, Y') }}

Thank you for booking with CasaLao.
