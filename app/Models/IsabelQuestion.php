<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IsabelQuestion extends Model
{
    use HasFactory;
    public $table = "isabel_questions";

    public function answer(){
        return $this->hasMany(IsabelAnswer::class, 'isabel_question_id');
    }
}
