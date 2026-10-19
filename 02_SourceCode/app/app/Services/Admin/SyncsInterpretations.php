<?php

namespace App\Services\Admin;

use App\Models\Interpretation;
use App\Models\Museum;
use Illuminate\Database\Eloquent\Model;

/** Ghi diễn giải từ form quản trị; dòng tiêu đề rỗng = xóa (chỉ mức 2). */
trait SyncsInterpretations
{
    protected function syncInterpretations(Model $subject, array $items, ?int $authorId): void
    {
        foreach ($items as $item) {
            $key = [
                'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
                'locale' => $item['locale'], 'depth_level' => (int) $item['depth_level'],
            ];
            if (blank($item['title'] ?? null)) {
                Interpretation::query()->where($key)->where('depth_level', '>', 1)->delete();
                continue;
            }
            $existing = Interpretation::query()->where($key)->first();
            $values = [
                'title' => $item['title'],
                'summary' => $item['summary'] ?? null,
                'body' => (string) ($item['body'] ?? ''),
                'sources' => $item['sources'] ?? null,
                'author_id' => $existing?->author_id ?? $authorId,
            ];
            if ($existing) {
                $existing->fill($values);
                if ($existing->isDirty()) {
                    $existing->version = (int) $existing->version + 1;
                    $existing->save();
                }
            } else {
                Interpretation::create($key + $values);
            }
        }
        $subject->unsetRelation('interpretations');
    }

    /** Bảo tàng duy nhất; CSDL trống thì tạo bản ghi Naturalis từ cấu hình. */
    protected function museumId(): int
    {
        $museum = Museum::main() ?? Museum::create([
            'slug' => 'naturalis', 'name' => config('bt360.museum.name'), 'city' => 'Leiden', 'country' => 'NL',
            'website' => config('bt360.museum.website'), 'license' => 'cc_by_sa_3', 'status' => 'published',
        ]);

        return $museum->id;
    }
}
