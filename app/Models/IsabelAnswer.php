<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IsabelAnswer extends Model
{
    use HasFactory;
    public $table = "isabel_answers";

    public function question(){
        return $this->belongsTo(IsabelQuestion::class, 'isabel_question_id');
    }
}
