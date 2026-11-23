<!DOCTYPE html>
<html lang="en">
<body>
    <h1>Booking confirmed</h1>
    <p>Hello {{ $booking->user->name }},</p>
    <p>Your booking has been confirmed:</p>
    <ul>
        <li>Room: {{ $booking->room->name }}</li>
        <li>Check-in: {{ $booking->check_in->format('M d, Y') }}</li>
        <li>Check-out: {{ $booking->check_out->format('M d, Y') }}</li>
    </ul>
    <p>Thank you for booking with CasaLao.</p>
</body>
</html>
