<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\HomeSampleCollection;
use App\Models\HomeSampleLab;
use App\Models\MajorOrganUserSelection;
use App\Models\Users;
use App\Mail\HomeSampleCollectionLabMail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * Home sample collection after a Mulk Longevity package purchase:
 * payment-success summary + form options, saving the address/time, and the user's bookings.
 */
class HomeSampleCollectionController extends Controller
{
    const EMIRATES = ['Abu Dhabi', 'Dubai', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'];

    const TIME_SLOTS = ['7 – 9 AM', '9 – 11 AM', '11 AM – 1 PM', '4 – 6 PM'];

    // Number of days offered in the date picker, starting tomorrow.
    const DAYS_AHEAD = 4;

    /**
     * Payment successful screen + form options (emirates, dates, time slots).
     * Query: user_id (required), order_id (optional, defaults to the latest paid package).
     */
    public function options(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'order_id' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $selection = $this->paidSelection((int) $request->user_id, $request->order_id);

        $dates = [];
        for ($i = 1; $i <= self::DAYS_AHEAD; $i++) {
            $day = Carbon::today()->addDays($i);
            $dates[] = [
                'date' => $day->format('Y-m-d'),
                'day' => $day->format('D'),
                'day_number' => (int) $day->format('j'),
            ];
        }

        $lastBooking = HomeSampleCollection::where('user_id', (int) $request->user_id)->latest('id')->first();

        return response()->json([
            'status' => true,
            'message' => 'Home sample collection options fetched successfully',
            'data' => [
                'payment' => [
                    'is_paid' => (bool) $selection,
                    'order_id' => $selection->order_id ?? null,
                    'selection_id' => $selection->id ?? null,
                    'package_id' => $selection->package_id ?? null,
                    'package_title' => $selection->package_title ?? null,
                ],
                'labs' => $this->activeLabs(),
                'emirates' => self::EMIRATES,
                'dates' => $dates,
                'time_slots' => self::TIME_SLOTS,
                'default_country_code' => '+971',
                // Prefill the form with the user's previous address.
                'last_address' => $lastBooking ? $lastBooking->only([
                    'emirate', 'area', 'building', 'flat_no', 'latitude', 'longitude', 'country_code', 'mobile_number',
                ]) : null,
            ],
        ]);
    }

    /**
     * Confirm home collection.
     */
    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'order_id' => 'nullable|string',
            'lab_id' => 'nullable|integer|exists:home_sample_labs,id',
            'emirate' => 'required|string|max:191',
            // 'emirate' => 'required|string|in:' . implode(',', self::EMIRATES),
            'area' => 'required|string|max:191',
            'building' => 'required|string|max:191',
            'flat_no' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'country_code' => 'nullable|string|max:8',
            'mobile_number' => 'required|string|min:7|max:20',
            'preferred_date' => 'required|date_format:Y-m-d|after:today',
            'preferred_time_slot' => 'required|string|in:' . implode(',', self::TIME_SLOTS),
            'notes' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $selection = $this->paidSelection((int) $request->user_id, $request->order_id);
        if (!$selection) {
            return response()->json([
                'status' => false,
                'message' => 'No paid package found for this user. Please complete the payment first.',
            ], 422);
        }

        // One booking per order: confirming again updates the address/time.
        $booking = HomeSampleCollection::updateOrCreate(
            ['user_id' => (int) $request->user_id, 'selection_id' => $selection->id],
            [
                'order_id' => $selection->order_id,
                'package_id' => $selection->package_id,
                'package_title' => $selection->package_title,
                'lab_id' => $request->filled('lab_id') ? (int) $request->lab_id : null,
                'emirate' => $request->emirate,
                'area' => $request->area,
                'building' => $request->building,
                'flat_no' => $request->flat_no,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'country_code' => $request->input('country_code') ?: '+971',
                'mobile_number' => preg_replace('/\s+/', '', $request->mobile_number),
                'preferred_date' => $request->preferred_date,
                'preferred_time_slot' => $request->preferred_time_slot,
                'status' => 'pending',
                'notes' => $request->notes,
            ]
        );

        $labEmailSent = $this->notifyLab($booking);

        return response()->json([
            'status' => true,
            'message' => 'Home sample collection confirmed successfully',
            'lab_email_sent' => $labEmailSent,
            'data' => $this->format($booking),
        ]);
    }

    /**
     * The user's home sample collection bookings, newest first.
     */
    public function list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $bookings = HomeSampleCollection::where('user_id', (int) $request->user_id)
            ->orderByDesc('id')
            ->get()
            ->map(fn($booking) => $this->format($booking));

        return response()->json([
            'status' => true,
            'message' => 'Home sample collections fetched successfully',
            'data' => $bookings,
        ]);
    }

    /**
     * Active labs (added by admin) the user can pick for home sample collection.
     * For now only lab id 1 is returned.
     */
    public function labs()
    {
        return response()->json([
            'status' => true,
            'message' => 'Labs fetched successfully',
            'data' => HomeSampleLab::where('status', 1)
                ->where('id', 1)
                ->get(['id', 'name', 'email'])
                ->toArray(),
        ]);
    }

    /**
     * Emails the booking details to the lab the user picked. A mail failure never fails the booking.
     */
    private function notifyLab(HomeSampleCollection $booking): bool
    {
        $lab = $booking->lab;
        if (!$lab || empty($lab->email)) {
            return false;
        }

        try {
            Mail::to($lab->email)->send(new HomeSampleCollectionLabMail($booking, Users::find($booking->user_id), $lab->name));
            Log::info('Home sample collection: lab email sent', ['booking_id' => $booking->id, 'lab_id' => $lab->id, 'email' => $lab->email]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Home sample collection: lab email failed', ['booking_id' => $booking->id, 'lab_id' => $lab->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function activeLabs(): array
    {
        return HomeSampleLab::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    /**
     * The user's paid package purchase: the given order, else the latest one.
     */
    private function paidSelection(int $userId, ?string $orderId): ?MajorOrganUserSelection
    {
        $query = MajorOrganUserSelection::where('user_id', $userId)
            ->whereIn('selection_type', ['longevity', 'package'])
            ->where('payment_status', 1);

        if (!empty($orderId)) {
            $query->where('order_id', $orderId);
        }

        return $query->orderByDesc('id')->first();
    }

    private function format(HomeSampleCollection $booking): array
    {
        return [
            'id' => $booking->id,
            'user_id' => (int) $booking->user_id,
            'order_id' => $booking->order_id,
            'package_id' => $booking->package_id,
            'package_title' => $booking->package_title,
            'lab_id' => $booking->lab_id,
            'lab_name' => $booking->lab->name ?? null,
            'lab_email' => $booking->lab->email ?? null,
            'emirate' => $booking->emirate,
            'area' => $booking->area,
            'building' => $booking->building,
            'flat_no' => $booking->flat_no,
            'latitude' => $booking->latitude,
            'longitude' => $booking->longitude,
            'country_code' => $booking->country_code,
            'mobile_number' => $booking->mobile_number,
            'preferred_date' => $booking->preferred_date?->format('Y-m-d'),
            'preferred_time_slot' => $booking->preferred_time_slot,
            'status' => $booking->status,
            'notes' => $booking->notes,
            'created_at' => $booking->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
