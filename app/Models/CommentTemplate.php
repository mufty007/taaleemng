<?php

namespace App\Models;

use App\Enums\CommentAudience;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A ready-made report-card comment for averages in a band. `{name}` becomes the student's first name.
 *
 * @property CommentAudience $audience
 */
#[Fillable(['audience', 'min_average', 'max_average', 'body'])]
class CommentTemplate extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => CommentAudience::class,
            'min_average' => 'float',
            'max_average' => 'float',
        ];
    }

    public function render(string $firstName): string
    {
        return str_replace('{name}', $firstName, $this->body);
    }
}
