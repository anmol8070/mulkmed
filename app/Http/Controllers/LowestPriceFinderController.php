<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class LowestPriceFinderController extends Controller
{

    function index()
    {
        return view('lowestPriceFinder.set_value');
    }

    public function fetch(Request $request)
    {
        $columns = [
            0 => 'hpp.id',
            1 => 'h.name',
            2 => 'hp.procedure',
            4 => 'hpp.price',
        ];

        $totalData = DB::table('hospital_procedure_prices')
            ->where('is_deleted', 0)
            ->count();

        $rows = DB::table('hospital_procedure_prices as hpp')
            ->join('hospitals as h', 'h.id', '=', 'hpp.hospital_id')
            ->join('hospital_procedures as hp', 'hp.id', '=', 'hpp.procedure_id')
            ->where('hpp.is_deleted', 0)
            ->where('hp.is_deleted', 0)
            ->select(
                'hpp.id',
                'hpp.hospital_id',
                'hpp.procedure_id',
                'h.name as hospital_name',
                'hp.procedure as procedure_name',
                'hpp.price'
            )->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;

       if (empty($request->input('search.value'))) {

    $result = DB::table('hospital_procedure_prices as hpp')
        ->join('hospitals as h', 'h.id', '=', 'hpp.hospital_id')
        ->join('hospital_procedures as hp', 'hp.id', '=', 'hpp.procedure_id')
        ->select(
            'hpp.id',
            'hpp.hospital_id',
            'hpp.procedure_id',
            'h.name as hospital_name',
            'hp.procedure as procedure_name',
            'hpp.price'
        )
        ->offset($start)
        ->limit($limit)
        ->orderBy($order, $dir)
        ->get();
    } else {

        $search = $request->input('search.value');

        $baseQuery = DB::table('hospital_procedure_prices as hpp')
            ->join('hospitals as h', 'h.id', '=', 'hpp.hospital_id')
            ->join('hospital_procedures as hp', 'hp.id', '=', 'hpp.procedure_id')
            ->select(
                'hpp.id',
                'hpp.hospital_id',
                'hpp.procedure_id',
                'h.name as hospital_name',
                'hp.procedure as procedure_name',
                'hpp.price'
            )
            ->where(function ($query) use ($search) {
                $query->where('h.name', 'LIKE', "%{$search}%")
                    ->orWhere('hp.procedure', 'LIKE', "%{$search}%")
                    ->orWhere('hpp.price', 'LIKE', "%{$search}%");
            });

        $result = $baseQuery
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        $totalFiltered = $baseQuery->count();
    }



        $data = [];
        foreach ($result as $row) {
           $edit = '<button class="btn btn-sm btn-primary edit-btn"
                    data-id="'.$row->id.'"
                    data-hospital="'.$row->hospital_id.'"
                    data-procedure="'.$row->procedure_id.'"
                    data-price="'.$row->price.'">Edit</button>

                 <button class="btn btn-sm btn-danger delete-btn"
                    data-id="'.$row->id.'">Delete</button>';
            $data[] = [
                $row->id,
                $row->hospital_name,
                $row->procedure_name,

                number_format($row->price, 2),
                $edit
                
            ];
        }

        return response()->json([
            "draw"            => intval($request->draw),
            "recordsTotal"    => $totalData,
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        ]);
    }

    public function getHospitals()
    {
        return DB::table('hospitals')
            ->select('id', 'name', 'procedure_ids')
            ->orderBy('name')
            ->get();
    }

    /**
     * Procedures dropdown (JSON)
     */
    public function getProcedureByHospital($hospital_id)
    {
        $hospital = DB::table('hospitals')
            ->where('id', $hospital_id)
            ->select('procedure_ids')
            ->first();

        if (!$hospital || !$hospital->procedure_ids) {
            return response()->json([]);
        }

        $procedureIds = json_decode($hospital->procedure_ids, true);

        if (!is_array($procedureIds)) {
            return response()->json([]);
        }

        return DB::table('hospital_procedures')
            ->whereIn('id', $procedureIds)
            ->where('is_deleted', 0)
            ->select('id', 'procedure')
            ->orderBy('procedure')
            ->get();
    }

    /**
     * Store
     */
    public function store(Request $request)
    {
        $request->validate([
            'hospital_id'  => 'required|integer',
            'procedure_id' => 'required|integer',
            'price'        => 'required|numeric'
        ]);

        DB::table('hospital_procedure_prices')->insert([
            'hospital_id' => $request->hospital_id,
            'procedure_id'=> $request->procedure_id,
            'price'       => $request->price,
            'is_deleted'  => 0,
            'created_at'  => now(),
            'updated_at'  => now()
        ]);

        return response()->json([
            'status' => true,
            'message'=> 'Price saved successfully'
        ]);
    }

    /**
     * Update
     */
    public function update(Request $request)
    {
        $request->validate([
            'id'           => 'required|integer',
            'hospital_id'  => 'required|integer',
            'procedure_id' => 'required|integer',
            'price'        => 'required|numeric'
        ]);

        DB::table('hospital_procedure_prices')
            ->where('id', $request->id)
            ->update([
                'hospital_id' => $request->hospital_id,
                'procedure_id'=> $request->procedure_id,
                'price'       => $request->price,
                'updated_at'  => now()
            ]);

        return response()->json([
            'status' => true,
            'message'=> 'Price updated successfully'
        ]);
    }

    /**
     * Soft delete
     */
    public function delete($id)
    {
        DB::table('hospital_procedure_prices')
            ->where('id', $id)
            ->update([
                'is_deleted' => 1,
                'updated_at' => now()
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Price deleted successfully'
        ]);
    }
}
