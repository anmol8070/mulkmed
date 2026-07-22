<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\LongevityPlan;

class LongevityPlanController extends Controller
{
    public function list()
    {
        $plans = LongevityPlan::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) {
                $whatsIncluded = is_array($item->whats_included) ? $item->whats_included : [];
                $benefits = is_array($item->benefits) ? $item->benefits : [];

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'subtitle' => $item->subtitle,
                    'description' => $item->description,
                    'image' => !empty($item->image) ? ltrim($item->image, '/') : null,
                    'whats_included' => $whatsIncluded,
                    'benefits' => $benefits,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Longevity plans fetched successfully',
            'data' => $plans,
        ]);
    }

    public function details($id)
    {
        $item = LongevityPlan::where('status', 1)->find($id);

        if (!$item) {
            return response()->json([
                'status' => false,
                'message' => 'Longevity plan not found',
                'data' => null,
            ]);
        }

        $whatsIncluded = is_array($item->whats_included) ? $item->whats_included : [];
        $benefits = is_array($item->benefits) ? $item->benefits : [];

        return response()->json([
            'status' => true,
            'message' => 'Longevity plan details fetched successfully',
            'data' => [
                'id' => $item->id,
                'title' => $item->title,
                'subtitle' => $item->subtitle,
                'description' => $item->description,
                'image' => !empty($item->image) ? ltrim($item->image, '/') : null,
                'whats_included' => $whatsIncluded,
                'benefits' => $benefits,
                'about_this_plan' => $item->description,
            ],
        ]);
    }
}
