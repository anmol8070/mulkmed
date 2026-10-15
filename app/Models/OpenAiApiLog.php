<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenAiApiLog extends Model
{
    use HasFactory;

    public $table = 'openai_api_logs';

    protected $fillable = [
        'user_id',
        'purpose',
        'method',
        'endpoint',
        'model',
        'is_paid',
        'http_status',
        'success',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'duration_ms',
        'filename',
        'error_message',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_paid' => 'integer',
        'http_status' => 'integer',
        'success' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'duration_ms' => 'integer',
    ];
}
