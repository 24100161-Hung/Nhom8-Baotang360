<?php

namespace Database\Factories\Concerns;

use App\Models\Interpretation;
use Illuminate\Database\Eloquent\Model;

/** Sinh diễn giải depth 1 vi/en cho factory. */
trait WritesInterpretations
{
    protected static function interpretations(Model $subject, string $viTitle, string $enTitle): void
    {
        foreach (['vi' => $viTitle, 'en' => $enTitle] as $locale => $title) {
            Interpretation::create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'locale' => $locale,
                'depth_level' => 1,
                'title' => $title,
                'summary' => fake()->sentence(10),
                'body' => fake()->paragraphs(2, true),
                'sources' => 'Naturalis Biodiversity Center',
            ]);
        }
    }
}
