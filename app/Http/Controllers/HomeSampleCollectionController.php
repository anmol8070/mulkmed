<?php

namespace App\Http\Controllers;

use App\Models\GlobalFunction;
use App\Models\HomeSampleCollection;
use App\Models\HomeSampleLab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin: home sample collection requests booked after a Mulk Longevity package purchase,
 * and the labs (name + email) the app offers for them.
 */
class HomeSampleCollectionController extends Controller
{
    const STATUSES = ['pending', 'confirmed', 'collected', 'cancelled'];

    public function index()
    {
        return view('homeSampleCollections', ['statuses' => self::STATUSES]);
    }

    // Separate admin page to add / edit / delete the labs used for home sample collection.
    public function labsIndex()
    {
        return view('homeSampleLabs');
    }

    public function fetch(Request $request)
    {
        $totalData = HomeSampleCollection::count();

        $columns = [
            0 => 'home_sample_collections.id',
            2 => 'home_sample_collections.package_title',
            6 => 'home_sample_collections.preferred_date',
            7 => 'home_sample_collections.status',
            8 => 'home_sample_collections.created_at',
        ];

        $limit = $request->input('length');
        $start = $request->input('start');
        $orderIndex = (int) $request->input('order.0.column', 0);
        $order = $columns[$orderIndex] ?? 'home_sample_collections.id';
        $dir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = HomeSampleCollection::query()
            ->leftJoin('users', 'users.id', '=', 'home_sample_collections.user_id')
            ->leftJoin('home_sample_labs', 'home_sample_labs.id', '=', 'home_sample_collections.lab_id')
            ->select('home_sample_collections.*', 'users.fullname as user_name');

        if (in_array($request->input('status_filter'), self::STATUSES, true)) {
            $query->where('home_sample_collections.status', $request->input('status_filter'));
        }

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('users.fullname', 'LIKE', "%{$search}%")
                    ->orWhere('home_sample_collections.mobile_number', 'LIKE', "%{$search}%")
                    ->orWhere('home_sample_collections.order_id', 'LIKE', "%{$search}%")
                    ->orWhere('home_sample_collections.emirate', 'LIKE', "%{$search}%")
                    ->orWhere('home_sample_collections.area', 'LIKE', "%{$search}%")
                    ->orWhere('home_sample_collections.building', 'LIKE', "%{$search}%")
                    ->orWhere('home_sample_collections.package_title', 'LIKE', "%{$search}%")
                    ->orWhere('home_sample_labs.name', 'LIKE', "%{$search}%");
            });
        }

        $totalFiltered = (clone $query)->count();

        $result = $query->offset($start)->limit($limit)->orderBy($order, $dir)->get();

        $labs = HomeSampleLab::get(['id', 'name'])->keyBy('id');

        $badgeClass = [
            'pending' => 'badge-warning',
            'confirmed' => 'badge-info',
            'collected' => 'badge-success',
            'cancelled' => 'badge-secondary',
        ];

        $data = [];
        foreach ($result as $item) {
            $user = $item->user_name
                ? '<a href="' . route('viewUserProfile', $item->user_id) . '"><span class="badge bg-primary text-white">' . e($item->user_name) . '</span></a>'
                : '#' . (int) $item->user_id;

            $address = e(implode(', ', array_filter([
                $item->flat_no ? 'Flat ' . $item->flat_no : null,
                $item->building,
                $item->area,
                $item->emirate,
            ])));
            if ($item->latitude && $item->longitude) {
                $address .= '<br><a href="https://www.google.com/maps?q=' . $item->latitude . ',' . $item->longitude
                    . '" target="_blank" rel="noopener">' . __('View on map') . '</a>';
            }

            $package = e($item->package_title ?: '-')
                . ($item->order_id ? '<br><small class="text-muted">' . e($item->order_id) . '</small>' : '');

            // The lab is chosen by the user in the app; the admin only sees it.
            $labName = $labs[$item->lab_id]->name ?? null;
            $labSelect = $labName
                ? '<span class="badge badge-info">' . e($labName) . '</span>'
                : '<span class="text-muted">' . __('Not selected') . '</span>';

            $status = '<span class="badge ' . ($badgeClass[$item->status] ?? 'badge-light') . '">' . e(ucfirst($item->status)) . '</span>';

            $statusSelect = '<select class="form-control form-control-sm change-status" rel="' . $item->id . '">';
            foreach (self::STATUSES as $value) {
                $statusSelect .= '<option value="' . $value . '"' . ($item->status === $value ? ' selected' : '') . '>' . __(ucfirst($value)) . '</option>';
            }
            $statusSelect .= '</select>';

            $data[] = [
                (int) $item->id,
                $user,
                $package,
                $labSelect,
                $address,
                e($item->country_code . ' ' . $item->mobile_number),
                e($item->preferred_date?->format('d M, Y')) . '<br>' . e($item->preferred_time_slot),
                $status,
                $item->created_at?->format('d M, Y h:i A'),
                $statusSelect,
            ];
        }

        echo json_encode([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
        exit();
    }

    public function updateStatus(Request $request)
    {
        $item = HomeSampleCollection::find($request->id);
        if (!$item) {
            return GlobalFunction::sendSimpleResponse(false, 'Home sample collection not found');
        }
        if (!in_array($request->status, self::STATUSES, true)) {
            return GlobalFunction::sendSimpleResponse(false, 'Invalid status');
        }

        $item->status = $request->status;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Status updated successfully');
    }

    public function updateLab(Request $request)
    {
        $item = HomeSampleCollection::find($request->id);
        if (!$item) {
            return GlobalFunction::sendSimpleResponse(false, 'Home sample collection not found');
        }
        if ($request->filled('lab_id') && !HomeSampleLab::whereKey($request->lab_id)->exists()) {
            return GlobalFunction::sendSimpleResponse(false, 'Lab not found');
        }

        $item->lab_id = $request->filled('lab_id') ? (int) $request->lab_id : null;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Lab updated successfully');
    }

    public function listLabs()
    {
        $labs = HomeSampleLab::orderBy('name')->get()->map(fn($lab) => [
            'id' => $lab->id,
            'name' => $lab->name,
            'email' => $lab->email,
            'status' => (int) $lab->status,
            'bookings' => HomeSampleCollection::where('lab_id', $lab->id)->count(),
        ]);

        return response()->json(['status' => true, 'data' => $labs]);
    }

    /**
     * Add a lab, or update it when id is sent.
     */
    public function saveLab(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:home_sample_labs,id',
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'status' => 'nullable|in:0,1',
        ]);
        if ($validator->fails()) {
            return GlobalFunction::sendSimpleResponse(false, $validator->errors()->first());
        }

        $lab = $request->filled('id') ? HomeSampleLab::find($request->id) : new HomeSampleLab();
        $lab->name = trim($request->name);
        $lab->email = trim($request->email);
        $lab->status = (int) $request->input('status', 1);
        $lab->save();

        return GlobalFunction::sendSimpleResponse(true, $request->filled('id') ? 'Lab updated successfully' : 'Lab added successfully');
    }

    public function deleteLab($id)
    {
        $lab = HomeSampleLab::find($id);
        if (!$lab) {
            return GlobalFunction::sendSimpleResponse(false, 'Lab not found');
        }

        // Bookings keep their record; they just lose the lab.
        HomeSampleCollection::where('lab_id', $lab->id)->update(['lab_id' => null]);
        $lab->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Lab deleted successfully');
    }
}
