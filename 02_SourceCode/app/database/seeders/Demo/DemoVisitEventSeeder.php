<?php

namespace Database\Seeders\Demo;

use App\Models\Scene;
use App\Models\Tour;
use App\Models\VisitEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ~3000 sự kiện tham quan 30 ngày (meta.demo = true): phiên tự do đi qua các phòng và phễu tour.
 * Tour đầu tiên có ≥3 bước bị rơi rõ ở bước 3 (85 phiên tới bước 3, chỉ 30 phiên đi tiếp) để minh họa drop-off.
 */
class DemoVisitEventSeeder extends Seeder
{
    public const FREE_SESSIONS = 170;
    public const DROPOFF_TOUR_STARTS = 100;
    /** Số phiên tới từng bước của tour drop-off (phần còn lại theo hệ số 0.9). */
    public const DROPOFF_REACHED = [1 => 100, 2 => 92, 3 => 85, 4 => 30];

    private array $rows = [];
    private int $seed = 7;
    private ?\Random\Randomizer $random = null;

    public function run(): void
    {
        VisitEvent::query()->whereJsonContains('meta', ['demo' => true])->delete();
        $scenes = Scene::query()->published()->with('hotspots:id,scene_id,type,artifact_id')->orderBy('sort_order')->get();
        if ($scenes->isEmpty()) {
            return;
        }
        // Bộ sinh số riêng có seed: không bị mã khác dùng chung trạng thái mt_rand làm lệch
        $this->random = new \Random\Randomizer(new \Random\Engine\Mt19937(2026));
        for ($i = 0; $i < self::FREE_SESSIONS; $i++) {
            $this->freeSession($scenes);
        }
        $tours = Tour::query()->published()->with('steps.scene')->orderBy('sort_order')->get()->filter(fn ($t) => $t->steps->count() >= 3)->values();
        foreach ($tours as $i => $tour) {
            $i === 0 ? $this->funnel($tour, self::DROPOFF_REACHED, self::DROPOFF_TOUR_STARTS) : $this->funnel($tour, [], 30 + ($i * 7) % 20);
        }
        $this->flush();
    }

    /** Phiên tự do: vào 3–8 phòng, mỗi phòng scene_enter → (hotspot/artifact/audio) → scene_leave. */
    private function freeSession($scenes): void
    {
        [$key, $device, $locale, $at] = $this->newSession();
        $index = $this->rand(0, $scenes->count() - 1);
        $visits = $this->rand(3, 8);
        for ($v = 0; $v < $visits; $v++) {
            $scene = $scenes[$index];
            $dwell = $this->rand(8000, 120000);
            $this->push($key, $device, $locale, $at, 'scene_enter', ['scene_id' => $scene->id]);
            $hotspot = $scene->hotspots->isNotEmpty() && $this->rand(0, 2) > 0 ? $scene->hotspots[$this->rand(0, $scene->hotspots->count() - 1)] : null; // bộ sinh có seed → lặp lại được
            if ($hotspot) {
                $this->push($key, $device, $locale, $at->copy()->addSeconds(5), 'hotspot_click', ['scene_id' => $scene->id, 'hotspot_id' => $hotspot->id]);
                if ($hotspot->type === 'artifact' && $hotspot->artifact_id) {
                    $this->push($key, $device, $locale, $at->copy()->addSeconds(6), 'artifact_view', ['scene_id' => $scene->id, 'artifact_id' => $hotspot->artifact_id]);
                }
            }
            if ($this->rand(0, 3) === 0) {
                $this->push($key, $device, $locale, $at->copy()->addSeconds(3), 'audio_play', ['scene_id' => $scene->id]);
            }
            $at = $at->copy()->addMilliseconds($dwell);
            $this->push($key, $device, $locale, $at, 'scene_leave', ['scene_id' => $scene->id, 'duration_ms' => $dwell]);
            // Đi sang phòng kề (theo thứ tự) hoặc nhảy ngẫu nhiên
            $index = $this->rand(0, 3) > 0 ? ($index + 1) % $scenes->count() : $this->rand(0, $scenes->count() - 1);
            $at = $at->copy()->addSeconds(2);
        }
    }

    /** Phễu tour: $reached[n] phiên tới bước n; bước không khai báo giảm 10%; phiên hết bước cuối → complete. */
    private function funnel(Tour $tour, array $reached, int $starts): void
    {
        $steps = $tour->steps->values();
        $counts = [];
        $prev = $starts;
        foreach ($steps as $i => $step) {
            $prev = $counts[$step->step_order] = $reached[$i + 1] ?? ($i === 0 ? $starts : (int) round($prev * 0.9));
        }
        // Tour drop-off chỉ 3 bước: số hoàn thành = số phiên khai báo cho bước 4 (rơi rõ ngay tại bước 3)
        $completes = (int) ($reached[$steps->count() + 1] ?? round(end($counts) * 0.92));
        for ($s = 0; $s < $starts; $s++) {
            [$key, $device, $locale, $at] = $this->newSession();
            $this->push($key, $device, $locale, $at, 'tour_start', ['tour_id' => $tour->id, 'meta' => ['mode' => 'guided']]);
            $last = null;
            foreach ($steps as $step) {
                if ($s >= $counts[$step->step_order]) {
                    break;
                }
                $at = $at->copy()->addSeconds($this->rand(20, 90));
                $this->push($key, $device, $locale, $at, 'tour_step', ['tour_id' => $tour->id, 'step_order' => $step->step_order, 'scene_id' => $step->scene_id]);
                $this->push($key, $device, $locale, $at, 'scene_enter', ['scene_id' => $step->scene_id, 'tour_id' => $tour->id]);
                $last = $step;
            }
            $done = $last && $last->is($steps->last()) && $s < $completes;
            $this->push($key, $device, $locale, $at->copy()->addSeconds(30), $done ? 'tour_complete' : 'tour_abandon',
                ['tour_id' => $tour->id, 'step_order' => $last?->step_order]);
        }
    }

    /** [session_key 40 ký tự, thiết bị, locale, thời điểm bắt đầu trong 30 ngày]. */
    private function newSession(): array
    {
        $device = ['mobile', 'mobile', 'desktop', 'desktop', 'desktop', 'tablet'][$this->rand(0, 5)];
        $at = now()->subDays($this->rand(0, 29))->setTime($this->rand(8, 20), $this->rand(0, 59), $this->rand(0, 59));

        return [Str::lower(Str::random(40)), $device, $this->rand(0, 2) ? 'vi' : 'en', $at];
    }

    private function push(string $key, string $device, string $locale, Carbon $at, string $type, array $data): void
    {
        $meta = ['demo' => true] + (array) ($data['meta'] ?? []);
        unset($data['meta']);
        $this->rows[] = array_merge([
            'session_key' => $key, 'user_id' => null, 'event_type' => $type, 'scene_id' => null, 'hotspot_id' => null,
            'tour_id' => null, 'step_order' => null, 'artifact_id' => null, 'duration_ms' => null,
        ], $data, ['locale' => $locale, 'device' => $device, 'meta' => json_encode($meta), 'created_at' => min($at, now())]);
        if (count($this->rows) >= 500) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        if ($this->rows !== []) {
            VisitEvent::query()->insert($this->rows);
            $this->rows = [];
        }
    }

    private function rand(int $min, int $max): int
    {
        $this->random ??= new \Random\Randomizer(new \Random\Engine\Mt19937(2026));

        return $this->random->getInt($min, $max);
    }
}
