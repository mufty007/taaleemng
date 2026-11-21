<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One question. Options are [{key, text}]; the answer is a list of correct
 * option keys, or of accepted short answers.
 *
 * @property QuestionType $type
 * @property list<array{key: string, text: string}>|null $options
 * @property list<string> $answer
 */
#[Fillable(['quiz_id', 'type', 'prompt', 'options', 'answer', 'marks', 'position'])]
class QuizQuestion extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'answer' => 'array',
            'marks' => 'integer',
            'position' => 'integer',
        ];
    }
}
