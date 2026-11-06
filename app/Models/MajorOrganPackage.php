<?php

namespace App\Models;

use App\Services\SenoclockAiService;
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
        'existing_report_price',
        'image',
        'test_ids',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'price' => 'decimal:2',
        'existing_report_price' => 'decimal:2',
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

    /**
     * Plan-card counts from the longevity design: both plans cover 12 organs.
     * Comprehensive lists every SenoClock biomarker; Basic lists the required set.
     *
     * @return array{organ_health_check_count: int, total_biomarkers: int, summary: string}
     */
    public function cardSummary(): array
    {
        $title = (string) $this->title;
        $organs = 12;

        if (stripos($title, 'comprehensive') !== false) {
            $biomarkers = count(SenoclockAiService::ALL_SENOCLOCK_BIOMARKER_NAMES);
        } elseif (stripos($title, 'basic') !== false) {
            $biomarkers = count(SenoclockAiService::REQUIRED_SENOCLOCK_BIOMARKERS);
        } else {
            $tests = $this->includedTests();
            $organs = $tests->count();
            $biomarkers = (int) $tests->sum(fn ($test) => count(is_array($test->biomarkers) ? $test->biomarkers : []));
        }

        return [
            'organ_health_check_count' => $organs,
            'total_biomarkers' => $biomarkers,
            'summary' => $organs . ' organs • ' . $biomarkers . ' biomarkers',
        ];
    }

    /**
     * Price for "Have an existing lab report?". Uses the admin existing-report price
     * when it is set, otherwise the package price.
     */
    public function existingReportPrice(): float
    {
        if ($this->existing_report_price !== null && $this->existing_report_price !== '') {
            return (float) $this->existing_report_price;
        }

        return (float) $this->price;
    }
}
