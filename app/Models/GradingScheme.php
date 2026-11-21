<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns a total out of 100 into a grade and remark.
 *
 * @property list<array{min: int, grade: string, remark: string}> $bands Highest first.
 */
#[Fillable(['name', 'bands'])]
class GradingScheme extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bands' => 'array',
        ];
    }

    /**
     * @return array{grade: string, remark: string}
     */
    public function gradeFor(float $total): array
    {
        foreach ($this->bands as $band) {
            if ($total >= $band['min']) {
                return ['grade' => $band['grade'], 'remark' => $band['remark']];
            }
        }

        $last = end($this->bands);

        return ['grade' => $last['grade'], 'remark' => $last['remark']];
    }

    /**
     * Whether a grade is a pass (used to colour grades).
     */
    public function isCredit(string $grade): bool
    {
        return ! in_array($grade, ['D7', 'E8', 'F9', 'E', 'F'], true);
    }
}
