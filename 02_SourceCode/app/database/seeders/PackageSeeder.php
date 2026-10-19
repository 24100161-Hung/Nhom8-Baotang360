<?php

namespace Database\Seeders;

use App\Models\TicketPackage;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Gói vé (packages.json; thiếu file → Regular 150.000đ/lượt và Premium 590.000đ/năm theo đề). */
class PackageSeeder extends Seeder
{
    use ReadsSeedJson;

    private const DEFAULTS = [
        ['code' => 'regular', 'package_type' => 'single', 'price_vnd' => 150000, 'validity_days' => 1, 'max_per_order' => 10, 'is_featured' => true,
            'vi' => ['name' => 'Vé thường (Regular Ticket)', 'description' => 'Vé tham quan một lượt trong ngày đã chọn.', 'benefits' => ['Vào cửa 1 lượt', 'Tour 360° có hướng dẫn', 'Audio guide tiếng Việt/Anh']],
            'en' => ['name' => 'Regular Ticket', 'description' => 'Single entry on the selected date.', 'benefits' => ['Single entry', 'Guided 360° tours', 'Vietnamese/English audio guide']]],
        ['code' => 'premium', 'package_type' => 'membership', 'price_vnd' => 590000, 'validity_days' => 365, 'max_per_order' => 1, 'is_featured' => true,
            'vi' => ['name' => 'Hội viên Premium (Premium Membership)', 'description' => 'Hội viên 1 năm với nhiều ưu đãi.', 'benefits' => ['Giảm 20% vé Regular', 'Ưu tiên sự kiện', 'Bản tin khoa học hằng tháng']],
            'en' => ['name' => 'Premium Membership', 'description' => 'One-year membership with perks.', 'benefits' => ['20% off Regular tickets', 'Priority event access', 'Monthly science newsletter']]],
    ];

    public function run(): void
    {
        $items = $this->readJson('packages.json') ?: self::DEFAULTS;
        foreach (array_values($items) as $i => $item) {
            if (empty($item['code']) || ! in_array($item['package_type'] ?? '', ['single', 'membership'], true)) {
                continue;
            }
            $package = TicketPackage::withTrashed()->updateOrCreate(['code' => $item['code']], [
                'package_type' => $item['package_type'],
                'price_vnd' => (int) ($item['price_vnd'] ?? 0),
                'validity_days' => (int) ($item['validity_days'] ?? ($item['package_type'] === 'membership' ? 365 : 1)),
                'benefits' => ['vi' => array_values((array) ($item['vi']['benefits'] ?? [])), 'en' => array_values((array) ($item['en']['benefits'] ?? []))],
                'max_per_order' => (int) ($item['max_per_order'] ?? 10),
                'is_featured' => (bool) ($item['is_featured'] ?? false),
                'sort_order' => (int) ($item['sort_order'] ?? $i + 1),
                'status' => $item['status'] ?? 'published',
            ]);
            if ($package->trashed()) {
                $package->restore();
            }
            $package->syncTranslations($this->translationsFrom($item, ['name', 'description']), 'name');
        }
    }
}
