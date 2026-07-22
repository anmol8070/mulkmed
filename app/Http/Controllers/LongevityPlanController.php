<?php

namespace App\Http\Controllers;

use App\Models\GlobalFunction;
use App\Models\LongevityPlan;
use Illuminate\Http\Request;

class LongevityPlanController extends Controller
{
    public function index()
    {
        return view('longevityPlans');
    }

    public function fetch(Request $request)
    {
        $totalData = LongevityPlan::count();

        $columns = [
            0 => 'display_order',
            1 => 'title',
            2 => 'subtitle',
            3 => 'id',
        ];

        $limit = $request->input('length');
        $start = $request->input('start');
        $orderIndex = (int) $request->input('order.0.column', 0);
        $order = $columns[$orderIndex] ?? 'display_order';
        $dir = $request->input('order.0.dir', 'asc');

        $search = $request->input('search.value');
        $filteredQuery = LongevityPlan::query();

        if (!empty($search)) {
            $filteredQuery->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('subtitle', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
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
            $whatsIncluded = is_array($item->whats_included) ? $item->whats_included : [];
            $benefits = is_array($item->benefits) ? $item->benefits : [];

            $image = '<img src="http://placehold.jp/80x50.png" width="80" height="50" style="object-fit:cover;border-radius:6px;">';
            if (!empty($item->image)) {
                $imageUrl = GlobalFunction::createMediaUrl($item->image);
                $image = '<img src="' . $imageUrl . '" width="80" height="50" style="object-fit:cover;border-radius:6px;">';
            }

            $status = $item->status
                ? '<span class="badge badge-success">' . __('Active') . '</span>'
                : '<span class="badge badge-secondary">' . __('Inactive') . '</span>';

            $edit = '<a href="#" class="mr-2 btn btn-primary text-white edit"'
                . ' data-title="' . e($item->title) . '"'
                . ' data-subtitle="' . e($item->subtitle) . '"'
                . ' data-description="' . e($item->description) . '"'
                . ' data-image="' . e(!empty($item->image) ? GlobalFunction::createMediaUrl($item->image) : '') . '"'
                . ' data-status="' . (int) $item->status . '"'
                . ' data-display_order="' . (int) $item->display_order . '"'
                . ' data-whats_included="' . e(json_encode($whatsIncluded)) . '"'
                . ' data-benefits="' . e(json_encode($benefits)) . '"'
                . ' rel="' . $item->id . '">' . __('Edit') . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel="' . $item->id . '">' . __('Delete') . '</a>';

            $data[] = [
                $image,
                e($item->title),
                e($item->subtitle),
                count($whatsIncluded) . ' ' . __('Items'),
                count($benefits) . ' ' . __('Items'),
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

    public function add(Request $request)
    {
        $whatsIncluded = $this->normalizeList($request->input('whats_included', []));
        $benefits = $this->normalizeList($request->input('benefits', []));

        $item = new LongevityPlan();
        $item->title = $request->title;
        $item->subtitle = $request->subtitle;
        $item->description = $request->description;
        $item->whats_included = $whatsIncluded;
        $item->benefits = $benefits;
        $item->status = (int) $request->status;
        $item->display_order = (int) ($request->display_order ?? 0);

        if ($request->hasFile('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }

        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Longevity plan added successfully');
    }

    public function edit(Request $request)
    {
        $item = LongevityPlan::find($request->id);

        if (!$item) {
            return GlobalFunction::sendSimpleResponse(false, 'Longevity plan not found');
        }

        $item->title = $request->title;
        $item->subtitle = $request->subtitle;
        $item->description = $request->description;
        $item->whats_included = $this->normalizeList($request->input('whats_included', []));
        $item->benefits = $this->normalizeList($request->input('benefits', []));
        $item->status = (int) $request->status;
        $item->display_order = (int) ($request->display_order ?? 0);

        if ($request->hasFile('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }

        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Longevity plan updated successfully');
    }

    public function delete($id)
    {
        $item = LongevityPlan::find($id);

        if (!$item) {
            return GlobalFunction::sendSimpleResponse(false, 'Longevity plan not found');
        }

        $item->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Longevity plan deleted successfully');
    }

    private function normalizeList($items): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values($normalized);
    }
}
