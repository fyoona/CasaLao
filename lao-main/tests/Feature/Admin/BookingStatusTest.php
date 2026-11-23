<?php

namespace Tests\Feature\Admin;

use App\Mail\BookingConfirmed;
use App\Mail\BookingEventNotification;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class BookingStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.booking_notifications.emails', []);
    }

    public function test_admin_can_update_booking_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->createBooking();
        config()->set('services.booking_notifications.emails', ['booking-alerts@example.net']);
        Mail::fake();

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed'])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('success', 'Booking status updated!');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
        $this->assertNotNull($booking->fresh()->confirmation_email_sent_at);

        Mail::assertSent(BookingConfirmed::class, function (BookingConfirmed $mail) use ($booking) {
            $content = $mail->content();

            return $mail->hasTo($booking->user->email)
                && $mail->booking->is($booking)
                && str_contains($mail->render(), 'Booking confirmed')
                && $content->text === 'emails.bookings.confirmed-text'
                && str_contains(view($content->text, ['booking' => $booking])->render(), 'Your booking has been confirmed');
        });
        Mail::assertSent(BookingEventNotification::class, function (BookingEventNotification $mail) use ($booking) {
            $content = $mail->content();

            return $mail->hasTo('booking-alerts@example.net')
                && $mail->event === 'confirmed'
                && $mail->booking->is($booking)
                && $content->text === 'emails.bookings.event-notification-text'
                && str_contains(view($content->text, [
                    'booking' => $booking,
                    'event' => $mail->event,
                ])->render(), 'A booking was confirmed.');
        });
    }

    public function test_new_booking_does_not_notify_the_configured_recipient(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $room = Room::create([
            'name' => 'Test Room',
            'price_per_night' => 1000,
            'capacity' => 2,
        ]);
        config()->set('services.booking_notifications.emails', ['booking-alerts@example.net']);
        Mail::fake();

        $this->actingAs($user)
            ->post(route('bookings.store'), [
                'room_id' => $room->id,
                'check_in' => now()->addDay()->toDateString(),
                'check_out' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect(route('bookings.index'));

        $this->assertDatabaseCount('bookings', 1);
        Mail::assertNothingSent();
    }

    public function test_cancelled_booking_notifies_the_configured_recipient(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->createBooking();
        config()->set('services.booking_notifications.emails', ['booking-alerts@example.net']);
        Mail::fake();

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', $booking), ['status' => 'cancelled'])
            ->assertRedirect(route('bookings.index'));

        Mail::assertSent(BookingEventNotification::class, function (BookingEventNotification $mail) use ($booking) {
            return $mail->hasTo('booking-alerts@example.net')
                && $mail->event === 'cancelled'
                && $mail->booking->is($booking);
        });
        Mail::assertSent(BookingEventNotification::class, function (BookingEventNotification $mail) use ($booking) {
            return $mail->hasTo($booking->user->email)
                && $mail->event === 'cancelled'
                && $mail->booking->is($booking);
        });
        $this->assertSame('cancelled', $booking->fresh()->status_notification_sent_for);
    }

    public function test_admin_can_retry_status_email_for_an_unchanged_unnotified_booking(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->createBooking([
            'status' => 'cancelled',
            'confirmation_email_sent_at' => now(),
        ]);
        config()->set('services.booking_notifications.emails', ['booking-alerts@example.net']);
        Mail::fake();

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', $booking), ['status' => 'cancelled'])
            ->assertRedirect(route('bookings.index'));

        Mail::assertSent(BookingEventNotification::class, function (BookingEventNotification $mail) {
            return $mail->hasTo('booking-alerts@example.net')
                && $mail->event === 'cancelled';
        });
        Mail::assertSent(BookingEventNotification::class, function (BookingEventNotification $mail) use ($booking) {
            return $mail->hasTo($booking->user->email)
                && $mail->event === 'cancelled';
        });
        $this->assertSame('cancelled', $booking->fresh()->status_notification_sent_for);
    }

    public function test_regular_user_cannot_update_booking_status(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $booking = $this->createBooking();

        $this->actingAs($user)
            ->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed'])
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_cannot_set_an_invalid_booking_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->createBooking();

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', $booking), ['status' => 'invalid'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_is_not_emailed_again_when_booking_is_already_confirmed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->createBooking([
            'status' => 'confirmed',
            'confirmation_email_sent_at' => now(),
        ]);
        Mail::fake();

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed'])
            ->assertRedirect(route('bookings.index'));

        Mail::assertNothingSent();
    }

    public function test_booking_stays_confirmed_and_shows_warning_if_email_delivery_fails(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->createBooking();
        Mail::shouldReceive('to')
            ->once()
            ->with($booking->user->email)
            ->andThrow(new TransportException('SMTP unavailable'));

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed'])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('warning');

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->confirmation_email_sent_at);
    }

    public function test_admin_can_retry_email_for_a_confirmed_booking_that_was_not_notified(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->createBooking(['status' => 'confirmed']);
        Mail::fake();

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed'])
            ->assertRedirect(route('bookings.index'));

        Mail::assertSent(BookingConfirmed::class);
        $this->assertNotNull($booking->fresh()->confirmation_email_sent_at);
    }

    private function createBooking(array $attributes = []): Booking
    {
        $user = User::factory()->create();
        $room = Room::create([
            'name' => 'Test Room',
            'price_per_night' => 1000,
            'capacity' => 2,
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
            ...$attributes,
        ]);

        $internalFields = array_intersect_key($attributes, array_flip([
            'confirmation_email_sent_at',
            'status_notification_sent_for',
        ]));

        if ($internalFields !== []) {
            $booking->forceFill($internalFields)->save();
        }

        return $booking;
    }
}
