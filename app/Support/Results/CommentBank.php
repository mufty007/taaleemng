<?php

namespace App\Support\Results;

use App\Enums\CommentAudience;
use App\Models\CommentTemplate;
use Illuminate\Support\Collection;

/**
 * Picks ready-made report-card comments that fit a student's average.
 */
class CommentBank
{
    /** @var Collection<int, CommentTemplate>|null */
    private ?Collection $templates = null;

    /**
     * Every comment that fits the average, with the student's name filled in.
     *
     * @return list<string>
     */
    public function optionsFor(CommentAudience $audience, ?float $average, string $firstName): array
    {
        if ($average === null) {
            return [];
        }

        return $this->templates()
            ->filter(fn (CommentTemplate $template) => $template->audience === $audience && $average >= $template->min_average && $average <= $template->max_average)
            ->map(fn (CommentTemplate $template) => $template->render($firstName))
            ->values()
            ->all();
    }

    /**
     * One comment for the average. The seed (usually the enrolment id) varies the
     * choice so a class does not get the same sentence on every card.
     */
    public function suggest(CommentAudience $audience, ?float $average, string $firstName, int $seed = 0): ?string
    {
        $options = $this->optionsFor($audience, $average, $firstName);

        return $options === [] ? null : $options[$seed % count($options)];
    }

    /**
     * @return Collection<int, CommentTemplate>
     */
    private function templates(): Collection
    {
        return $this->templates ??= CommentTemplate::query()->orderBy('min_average')->orderBy('id')->get();
    }
}
