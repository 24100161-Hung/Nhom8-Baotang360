<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\TicketPackage;
use Illuminate\Support\Facades\DB;

/** Ghi gói vé + bản dịch + quyền lợi theo locale (admin). */
class PackageWriter
{
    public function save(TicketPackage $package, array $data): TicketPackage
    {
        return DB::transaction(function () use ($package, $data) {
            $package->fill([
                'code' => $data['code'],
                'package_type' => $data['package_type'],
                'price_vnd' => (int) $data['price_vnd'],
                'validity_days' => (int) $data['validity_days'],
                'max_per_order' => (int) $data['max_per_order'],
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'status' => $data['status'],
                'benefits' => [
                    'vi' => array_values(array_filter((array) ($data['benefits_vi'] ?? []), 'filled')),
                    'en' => array_values(array_filter((array) ($data['benefits_en'] ?? []), 'filled')),
                ],
            ])->save();
            $package->syncTranslations([
                'vi' => ['name' => $data['name_vi'], 'description' => $data['description_vi'] ?? null],
                'en' => ['name' => $data['name_en'], 'description' => $data['description_en'] ?? null],
            ], 'name');

            return $package;
        });
    }

    public function isInUse(TicketPackage $package): bool
    {
        return OrderItem::query()->where('package_id', $package->id)->exists();
    }

    /** Dữ liệu form quản trị (bản dịch theo từng locale, không fallback). */
    public function formData(TicketPackage $package): array
    {
        $vi = $package->translationFor('vi');
        $en = $package->translationFor('en');

        return [
            'id' => $package->id,
            'code' => $package->code,
            'package_type' => $package->package_type,
            'price_vnd' => (int) $package->price_vnd,
            'validity_days' => (int) $package->validity_days,
            'max_per_order' => (int) $package->max_per_order,
            'is_featured' => (bool) $package->is_featured,
            'sort_order' => (int) $package->sort_order,
            // Form quản trị dùng active|inactive (published|draft)
            'status' => $package->status === 'published' ? 'active' : 'inactive',
            'status_code' => $package->status,
            'name_vi' => $vi?->name, 'name_en' => $en?->name,
            'description_vi' => $vi?->description, 'description_en' => $en?->description,
            'benefits_vi' => array_values((array) ($package->benefits['vi'] ?? [])),
            'benefits_en' => array_values((array) ($package->benefits['en'] ?? [])),
            'benefits' => ['vi' => array_values((array) ($package->benefits['vi'] ?? [])), 'en' => array_values((array) ($package->benefits['en'] ?? []))],
        ];
    }
}
