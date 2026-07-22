<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'sheet_name',
        'imported_by',
        'imported_at',
    ];
}
