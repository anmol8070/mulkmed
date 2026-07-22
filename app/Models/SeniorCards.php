<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeniorCards extends Model
{
    use HasFactory, LogsActivity;
    public $table = "senior_cards";

    protected $casts = [
        'gender' => 'integer',
    ];

        protected static function boot(){
            parent::boot();
            static::creating(function ($card){
                $card->card_number = self::generateUniqueCardNumbers();
                $card->points = 900; // static
            });
        
        }

        protected static function generateUniqueCardNumbers()
        {
            do {
                $prefix = '1800';
                $blocks = [$prefix];

                // Generate 3 random 4-digit blocks
                for ($i = 0; $i < 3; $i++) {
                    $blocks[] = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                }

                $number = implode(' ', $blocks);

            } while (self::where('card_number', $number)->exists());

            return $number;
        }

        public function getBalanceAedAttribute(){
            return $this->points / 20;
        }
}
