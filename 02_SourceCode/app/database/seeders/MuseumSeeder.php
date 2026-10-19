<?php

namespace Database\Seeders;

use App\Models\Museum;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Bảo tàng Naturalis (museum.json; thiếu file → giá trị mặc định từ config bt360.museum). */
class MuseumSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $data = $this->readJson('museum.json');
        $defaults = (array) config('bt360.museum');

        $museum = Museum::updateOrCreate(['slug' => $data['slug'] ?? 'naturalis'], [
            'name' => $data['name'] ?? $defaults['name'],
            'city' => $data['city'] ?? 'Leiden',
            'country' => $data['country'] ?? 'NL',
            'website' => $data['website'] ?? $defaults['website'],
            'license' => in_array($data['license'] ?? null, ['cc_by_sa_3', 'cc_by_4', 'cc0', 'other'], true) ? $data['license'] : 'cc_by_sa_3',
            'attribution_text' => $data['attribution_text'] ?? $defaults['attribution'],
            'logo_path' => $this->logo($data['logo'] ?? null),
            'address' => $data['address'] ?? $defaults['address'],
            'phone' => $data['phone'] ?? $defaults['phone'],
            'email' => $data['email'] ?? $defaults['email'],
            'floorplan' => $this->floorplan($data['floorplan'] ?? null),
            'lat' => $data['lat'] ?? 52.1647,
            'lng' => $data['lng'] ?? 4.4740,
            'status' => 'published',
        ]);

        $this->syncInterpretations($museum, [
            'vi' => [1 => ['title' => $museum->name, 'summary' => $data['vi']['summary'] ?? null,
                'body' => $data['vi']['description'] ?? 'Bảo tàng lịch sử tự nhiên quốc gia Hà Lan tại Leiden.']],
            'en' => [1 => ['title' => $museum->name, 'summary' => $data['en']['summary'] ?? null,
                'body' => $data['en']['description'] ?? 'The national natural history museum of the Netherlands in Leiden.']],
        ]);
    }

    /** Logo: tệp trong seed-assets → disk public, hoặc giữ URL ngoài. */
    private function logo(mixed $logo): ?string
    {
        if (! is_array($logo)) {
            return null;
        }
        $file = $this->assetFile($logo['path'] ?? null);
        if ($file) {
            return $this->copyToPublic($file, 'museum/logo.'.pathinfo($file, PATHINFO_EXTENSION));
        }

        return isset($logo['url']) && is_string($logo['url']) ? $logo['url'] : null;
    }

    /** Sơ đồ tầng {image (URL disk public), width, height}. */
    private function floorplan(mixed $plan): ?array
    {
        $plan = is_array($plan) ? $plan : [];
        $file = $this->assetFile($plan['image'] ?? 'seed-assets/floorplan.svg');
        $path = $file ? $this->copyToPublic($file, 'floorplan/'.basename($file)) : null;

        return [
            'image' => $path,
            'width' => (int) ($plan['width'] ?? 1000),
            'height' => (int) ($plan['height'] ?? 600),
        ];
    }
}
