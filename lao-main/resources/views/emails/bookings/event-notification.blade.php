<!DOCTYPE html>
<html lang="en">
<body>
    <h1>Booking {{ ucfirst($event) }}</h1>
    <p>A booking was {{ $event }}.</p>
    <ul>
        <li>Guest: {{ $booking->user->name }} ({{ $booking->user->email }})</li>
        <li>Room: {{ $booking->room->name }}</li>
        <li>Check-in: {{ $booking->check_in->format('M d, Y') }}</li>
        <li>Check-out: {{ $booking->check_out->format('M d, Y') }}</li>
    </ul>
</body>
</html>
