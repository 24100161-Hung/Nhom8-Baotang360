<?php

namespace App\Services\Admin;

use App\Models\Interpretation;
use App\Models\Period;
use App\Models\Scene;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;

/** Dữ liệu dùng chung cho form quản trị: 4 ô diễn giải, danh mục thời kỳ/không gian/thẻ. */
class AdminOptions
{
    /** [vi1, vi2, en1, en2] – luôn đủ 4 ô (ô trống khi chưa có). */
    public function interpretationSlots(?Model $subject): array
    {
        $items = $subject ? Interpretation::query()->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())->get() : collect();
        $slots = [];
        foreach (['vi', 'en'] as $locale) {
            foreach ([1, 2] as $depth) {
                $i = $items->first(fn ($x) => $x->locale === $locale && (int) $x->depth_level === $depth);
                $slots[] = [
                    'locale' => $locale, 'depth_level' => $depth, 'title' => $i?->title, 'summary' => $i?->summary,
                    'body' => $i?->body, 'sources' => $i?->sources, 'version' => $i?->version,
                ];
            }
        }

        return $slots;
    }

    public function periods(): array
    {
        return Period::query()->with('translations')->orderBy('sort_order')->get()
            ->map(fn (Period $p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name])->all();
    }

    public function scenes(): array
    {
        return Scene::query()->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)])->orderBy('sort_order')->get()
            ->map(fn (Scene $s) => ['id' => $s->id, 'slug' => $s->slug, 'code' => $s->code, 'title' => $s->title, 'status' => $s->status,
                'minimap' => ['x' => (float) $s->minimap_x, 'y' => (float) $s->minimap_y]])->all();
    }

    public function tags(?string $group = null): array
    {
        return Tag::query()->with('translations')->when($group, fn ($q, $g) => $q->where('tag_group', $g))
            ->orderBy('tag_group')->orderBy('slug')->get()
            ->map(fn (Tag $t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->name, 'group' => $t->tag_group, 'tag_group' => $t->tag_group])->all();
    }

    /**
     * Tiêu đề vi/en mức 1 và các mức diễn giải đã có theo ngôn ngữ cho danh sách quản trị.
     * @return array<int, array{title_vi: ?string, title_en: ?string, depths_vi: int[], depths_en: int[]}>
     */
    public function interpretationSummary(string $morph, array $ids): array
    {
        $out = array_fill_keys($ids, ['title_vi' => null, 'title_en' => null, 'depths_vi' => [], 'depths_en' => []]);
        if ($ids === []) {
            return $out;
        }
        $rows = \App\Models\Interpretation::query()->where('subject_type', $morph)->whereIn('subject_id', $ids)
            ->whereIn('locale', ['vi', 'en'])->orderBy('depth_level')->get(['subject_id', 'locale', 'depth_level', 'title']);
        foreach ($rows as $row) {
            $id = (int) $row->subject_id;
            $out[$id]['depths_'.$row->locale][] = (int) $row->depth_level;
            if ((int) $row->depth_level === 1) {
                $out[$id]['title_'.$row->locale] = $row->title;
            }
        }

        return $out;
    }

    /** Danh sách enum {code, name}. */
    public function enum(string $group, array $codes): array
    {
        return array_map(fn ($code) => ['code' => $code, 'name' => __("enums.$group.$code")], $codes);
    }
}
