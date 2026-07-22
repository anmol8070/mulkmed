<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueryProcedures extends Model
{
    use HasFactory;

    protected $fillable = [
        'procedure',
        'ar_procedure',
        'fr_procedure',
        'hi_procedure',
        'ur_procedure'
    ];
    public $table = "query_procedures";
}
