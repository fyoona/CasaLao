<?php

namespace App\Http\Controllers;

use App\Mail\BookingConfirmed;
use App\Mail\BookingEventNotification;
use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->isAdmin()) {
            $bookings = Booking::with(['user', 'room'])->latest()->get();
        } else {
            $bookings = $request->user()->bookings()->with('room')->latest()->get();
        }

        return view('bookings.index', compact('bookings'));
    }

    public function create()
    {
        $rooms = Room::all();

        return view('bookings.create', compact('rooms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'notes' => 'nullable|string',
        ]);

        if ($this->hasOverlap($validated['room_id'], $validated['check_in'], $validated['check_out'])) {
            return back()->withInput()->withErrors(['check_in' => 'Room is already booked for the selected dates.']);
        }

        $validated['user_id'] = $request->user()->id;

        $booking = Booking::create($validated);

        return redirect()->route('bookings.index')->with('success', 'Booking created!');
    }

    public function edit(Booking $booking)
    {
        $this->authorizeBooking($booking);

        $rooms = Room::all();

        return view('bookings.edit', compact('booking', 'rooms'));
    }

    public function update(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);

        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'status' => 'sometimes|in:pending,confirmed,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($this->hasOverlap($validated['room_id'], $validated['check_in'], $validated['check_out'], $booking->id)) {
            return back()->withInput()->withErrors(['check_in' => 'Room is already booked for the selected dates.']);
        }

        $previousStatus = $booking->status;
        $booking->update($validated);

        if ($previousStatus !== $booking->status && ! $this->sendStatusNotifications($booking, $previousStatus)) {
            return redirect()->route('bookings.index')->with(
                'warning',
                'Booking updated, but one or more email notifications could not be sent. Check the mail settings.'
            );
        }

        return redirect()->route('bookings.index')->with('success', 'Booking updated!');
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled',
        ]);

        $previousStatus = $booking->status;
        $booking->update($validated);

        if (! $this->sendStatusNotifications($booking, $previousStatus)) {
            return redirect()->route('bookings.index')->with(
                'warning',
                'Booking status updated, but one or more email notifications could not be sent. Check the mail settings and update the status again to retry.'
            );
        }

        return redirect()->route('bookings.index')->with('success', 'Booking status updated!');
    }

    public function destroy(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);

        $booking->delete();

        return redirect()->route('bookings.index')->with('success', 'Booking deleted!');
    }

    public function bookedDates(Room $room)
    {
        $bookings = $room->bookings()->where('status', '!=', 'cancelled')->get();

        $dates = [];

        foreach ($bookings as $booking) {
            $period = CarbonPeriod::create($booking->check_in, $booking->check_out);
            foreach ($period as $date) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return response()->json(array_values(array_unique($dates)));
    }

    private function hasOverlap($roomId, $checkIn, $checkOut, $excludeBookingId = null)
    {
        $query = Booking::where('room_id', $roomId)
            ->where('status', '!=', 'cancelled')
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->exists();
    }

    private function sendStatusNotifications(Booking $booking, string $previousStatus): bool
    {
        $booking->loadMissing(['user', 'room']);
        $notificationFailed = false;

        if ($previousStatus !== $booking->status) {
            $booking->forceFill(['status_notification_sent_for' => null])->save();
        }

        if (in_array($booking->status, ['confirmed', 'cancelled'], true)
            && $booking->status_notification_sent_for !== $booking->status) {
            $eventSent = $this->sendBookingEventNotifications($booking, $booking->status);

            if ($booking->status === 'cancelled') {
                try {
                    Mail::to($booking->user->email)->send(new BookingEventNotification($booking, 'cancelled'));
                } catch (TransportExceptionInterface $exception) {
                    Log::error('Booking cancellation email could not be sent.', [
                        'booking_id' => $booking->id,
                        'recipient' => $booking->user->email,
                        'exception' => $exception,
                    ]);

                    $eventSent = false;
                }
            }

            if ($eventSent) {
                $booking->forceFill(['status_notification_sent_for' => $booking->status])->save();
            } else {
                $notificationFailed = true;
            }
        }

        if ($booking->status === 'confirmed' && $booking->confirmation_email_sent_at === null) {
            try {
                Mail::to($booking->user->email)->send(new BookingConfirmed($booking));
                $booking->forceFill(['confirmation_email_sent_at' => now()])->save();
            } catch (TransportExceptionInterface $exception) {
                Log::error('Booking confirmation email could not be sent.', [
                    'booking_id' => $booking->id,
                    'recipient' => $booking->user->email,
                    'exception' => $exception,
                ]);

                $notificationFailed = true;
            }
        }

        return ! $notificationFailed;
    }

    private function sendBookingEventNotifications(Booking $booking, string $event): bool
    {
        $booking->loadMissing(['user', 'room']);
        $recipients = config('services.booking_notifications.emails', []);
        $allSent = true;

        foreach (array_unique($recipients) as $recipient) {
            if (strcasecmp($recipient, $booking->user->email) === 0) {
                continue;
            }

            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                Log::error('Invalid booking notification email configured.', [
                    'booking_id' => $booking->id,
                    'recipient' => $recipient,
                ]);
                $allSent = false;

                continue;
            }

            try {
                Mail::to($recipient)->send(new BookingEventNotification($booking, $event));
            } catch (TransportExceptionInterface $exception) {
                Log::error('Booking event notification could not be sent.', [
                    'booking_id' => $booking->id,
                    'event' => $event,
                    'recipient' => $recipient,
                    'exception' => $exception,
                ]);
                $allSent = false;
            }
        }

        return $allSent;
    }

    private function authorizeBooking(Booking $booking): void
    {
        if (!auth()->user()->isAdmin() && $booking->user_id !== auth()->id()) {
            abort(403);
        }
    }
}