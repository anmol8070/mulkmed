<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MajorOrganPackage extends Model
{
    use HasFactory;

    public $table = 'major_organ_package';

    protected $fillable = [
        'title',
        'badge',
        'description',
        'price',
        'image',
        'test_ids',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'price' => 'decimal:2',
        'test_ids' => 'array',
    ];

    /**
     * Active organ tests included in this package. A package without chosen tests
     * includes every active organ test.
     */
    public function includedTests()
    {
        $query = MajorOrganTest::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc');

        $testIds = array_filter(array_map('intval', (array) ($this->test_ids ?? [])));
        if (!empty($testIds)) {
            $query->whereIn('id', $testIds);
        }

        return $query->get();
    }
}
