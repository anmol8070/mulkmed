<?php

namespace App\Http\Controllers;

use App\Models\GlobalFunction;
use App\Models\MajorOrganPackage;
use App\Models\MajorOrganTest;
use Illuminate\Http\Request;

class MajorOrganTestController extends Controller
{
    public function index()
    {
        return view('majorOrganTests');
    }

    public function getPackage()
    {
        $package = MajorOrganPackage::first();

        if (!$package) {
            return response()->json([
                'status' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $package->id,
                'title' => $package->title,
                'badge' => $package->badge,
                'description' => $package->description,
                'price' => number_format((float) $package->price, 2, '.', ''),
                'image' => !empty($package->image) ? GlobalFunction::createMediaUrl($package->image) : null,
                'status' => (int) $package->status,
            ],
        ]);
    }

    public function savePackage(Request $request)
    {
        $package = MajorOrganPackage::first();

        if (!$package) {
            $package = new MajorOrganPackage();
        }

        $package->title = $request->title;
        $package->badge = $request->badge;
        $package->description = $request->description;
        $package->price = $request->price;
        $package->status = (int) $request->status;

        if ($request->hasFile('image')) {
            $package->image = GlobalFunction::saveFileAndGivePath($request->image);
        }

        $package->save();

        return GlobalFunction::sendSimpleResponse(true, 'Package saved successfully');
    }

    public function fetchOrganTests(Request $request)
    {
        $query = MajorOrganTest::query();
        $totalData = $query->count();

        $columns = [
            0 => 'display_order',
            1 => 'name',
            2 => 'price',
            3 => 'id',
        ];

        $limit = $request->input('length');
        $start = $request->input('start');
        $orderIndex = (int) $request->input('order.0.column', 0);
        $order = $columns[$orderIndex] ?? 'display_order';
        $dir = $request->input('order.0.dir', 'asc');

        $search = $request->input('search.value');
        $filteredQuery = MajorOrganTest::query();

        if (!empty($search)) {
            $filteredQuery->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('price', 'LIKE', "%{$search}%");
            });
        }

        $totalFiltered = (clone $filteredQuery)->count();

        $result = $filteredQuery
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($result as $item) {
            $biomarkers = is_array($item->biomarkers) ? $item->biomarkers : [];
            $count = count($biomarkers);

            $icon = '<img src="http://placehold.jp/50x50.png" width="50" height="50">';
            if (!empty($item->icon)) {
                $iconUrl = GlobalFunction::createMediaUrl($item->icon);
                $icon = '<img src="' . $iconUrl . '" width="50" height="50" style="object-fit:cover;border-radius:6px;">';
            }

            $biomarkerItems = '';
            foreach ($biomarkers as $biomarker) {
                $biomarkerItems .= '<li>' . e($biomarker) . '</li>';
            }

            $biomarkersCol = '<div class="organ-test-biomarkers">'
                . '<a href="#" class="toggle-biomarkers font-weight-bold" data-target="biomarkers-' . $item->id . '">'
                . $count . ' ' . __('Biomarkers')
                . '</a>'
                . '<ul id="biomarkers-' . $item->id . '" class="biomarker-list d-none list-unstyled mb-0 mt-2 pl-3">'
                . $biomarkerItems
                . '</ul></div>';

            $status = $item->status
                ? '<span class="badge badge-success">' . __('Active') . '</span>'
                : '<span class="badge badge-secondary">' . __('Inactive') . '</span>';

            $edit = '<a href="#" class="mr-2 btn btn-primary text-white edit"'
                . ' data-name="' . e($item->name) . '"'
                . ' data-icon="' . e(!empty($item->icon) ? GlobalFunction::createMediaUrl($item->icon) : '') . '"'
                . ' data-price="' . e($item->price) . '"'
                . ' data-status="' . (int) $item->status . '"'
                . ' data-display_order="' . (int) $item->display_order . '"'
                . ' data-biomarkers="' . e(json_encode($biomarkers)) . '"'
                . ' rel="' . $item->id . '">' . __('Edit') . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel="' . $item->id . '">' . __('Delete') . '</a>';

            $data[] = [
                $icon,
                e($item->name),
                number_format((float) $item->price, 2),
                $biomarkersCol,
                $status,
                (int) $item->display_order,
                $edit . $delete,
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

    public function addOrganTest(Request $request)
    {
        $biomarkers = $this->normalizeBiomarkers($request->input('biomarkers', []));

        if (empty($biomarkers)) {
            return GlobalFunction::sendSimpleResponse(false, 'At least one biomarker is required');
        }

        $item = new MajorOrganTest();
        $item->name = $request->name;
        $item->price = $request->price;
        $item->biomarkers = $biomarkers;
        $item->status = (int) $request->status;
        $item->display_order = (int) ($request->display_order ?? 0);

        if ($request->hasFile('icon')) {
            $item->icon = GlobalFunction::saveFileAndGivePath($request->icon);
        }

        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Organ test added successfully');
    }

    public function editOrganTest(Request $request)
    {
        $item = MajorOrganTest::find($request->id);

        if (!$item) {
            return GlobalFunction::sendSimpleResponse(false, 'Organ test not found');
        }

        $biomarkers = $this->normalizeBiomarkers($request->input('biomarkers', []));

        if (empty($biomarkers)) {
            return GlobalFunction::sendSimpleResponse(false, 'At least one biomarker is required');
        }

        $item->name = $request->name;
        $item->price = $request->price;
        $item->biomarkers = $biomarkers;
        $item->status = (int) $request->status;
        $item->display_order = (int) ($request->display_order ?? 0);

        if ($request->hasFile('icon')) {
            $item->icon = GlobalFunction::saveFileAndGivePath($request->icon);
        }

        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Organ test updated successfully');
    }

    public function deleteOrganTest($id)
    {
        $item = MajorOrganTest::find($id);

        if (!$item) {
            return GlobalFunction::sendSimpleResponse(false, 'Organ test not found');
        }

        $item->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Organ test deleted successfully');
    }

    public function previewOrganTests()
    {
        $tests = MajorOrganTest::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) {
                $biomarkers = is_array($item->biomarkers) ? $item->biomarkers : [];

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'icon' => !empty($item->icon) ? GlobalFunction::createMediaUrl($item->icon) : null,
                    'price' => number_format((float) $item->price, 2, '.', ''),
                    'biomarker_count' => count($biomarkers),
                    'biomarkers' => $biomarkers,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $tests,
        ]);
    }

    private function normalizeBiomarkers($biomarkers): array
    {
        if (is_string($biomarkers)) {
            $decoded = json_decode($biomarkers, true);
            $biomarkers = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($biomarkers)) {
            return [];
        }

        $normalized = [];
        foreach ($biomarkers as $biomarker) {
            $value = trim((string) $biomarker);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values($normalized);
    }
}
