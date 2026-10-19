<?php

namespace Database\Seeders\Concerns;

use App\Models\Interpretation;
use App\Models\MediaAsset;
use App\Models\MediaCaption;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Đọc dữ liệu hạt giống JSON (config bt360.seed_data_path); file thiếu/lỗi cú pháp → mảng rỗng
 * để seeder chạy được khi dữ liệu chưa đủ. Kèm tiện ích sao chép/tải tệp vào disk public.
 */
trait ReadsSeedJson
{
    protected function dataPath(string $file = ''): string
    {
        $base = rtrim((string) config('bt360.seed_data_path', database_path('data')), '/');

        return $base.($file !== '' ? '/'.$file : '');
    }

    protected function assetsPath(string $file = ''): string
    {
        $base = rtrim((string) config('bt360.seed_assets_path', database_path('seed-assets')), '/');

        return $base.($file !== '' ? '/'.ltrim($file, '/') : '');
    }

    protected function readJson(string $file): array
    {
        $path = str_starts_with($file, '/') ? $file : $this->dataPath($file);
        if (! is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /** Đường dẫn tài nguyên trong JSON dạng "seed-assets/x.jpg" hoặc "x.jpg" → đường dẫn tuyệt đối nếu tồn tại. */
    protected function assetFile(?string $relative): ?string
    {
        if (! is_string($relative) || trim($relative) === '' || str_contains($relative, '..')) {
            return null;
        }
        $relative = preg_replace('#^(database/)?seed-assets/#', '', ltrim($relative, '/'));
        $path = $this->assetsPath($relative);

        return is_file($path) ? $path : null;
    }

    /** Sao chép tệp nguồn vào disk public tại $target; trả về $target hoặc null nếu thiếu nguồn. */
    protected function copyToPublic(?string $source, string $target): ?string
    {
        if (! $source || ! is_file($source)) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($target) || $disk->size($target) !== filesize($source)) {
            $disk->put($target, (string) file_get_contents($source));
        }

        return $target;
    }

    /** Tải ảnh từ URL (có cache seed-assets/cache) rồi lưu vào disk public; lỗi → null (dùng external_url). */
    protected function downloadToPublic(string $url, string $targetDir): ?string
    {
        if (! config('bt360.seed_download_images', true) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'], true) ? $ext : 'jpg';
        $name = sha1($url).'.'.$ext;
        $cache = $this->assetsPath('cache/'.$name);
        if (! is_file($cache) && ! $this->fetch($url, $cache)) {
            return null;
        }

        return $this->copyToPublic($cache, trim($targetDir, '/').'/'.$name);
    }

    private function fetch(string $url, string $cache): bool
    {
        try {
            $response = Http::timeout(15)->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'BaoTang360-seeder/1.0 (education)'])->get($url);
            $body = $response->body();
            $mime = (string) $response->header('Content-Type');
            if (! $response->successful() || $body === '' || ! str_starts_with($mime, 'image/')) {
                return false;
            }
            if (! is_dir(dirname($cache))) {
                @mkdir(dirname($cache), 0775, true);
                @file_put_contents(dirname($cache).'/.gitignore', "*\n!.gitignore\n");
            }

            return file_put_contents($cache, $body) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** Ghi các lớp diễn giải từ khối "interp" {locale: {depth: {...}}}. */
    protected function syncInterpretations(Model $subject, array $interp): void
    {
        foreach ($interp as $locale => $depths) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }
            foreach ((array) $depths as $depth => $row) {
                if (! is_array($row) || empty($row['title']) || ! in_array((int) $depth, [1, 2], true)) {
                    continue;
                }
                $sources = $row['sources'] ?? null;
                Interpretation::updateOrCreate(
                    ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'locale' => $locale, 'depth_level' => (int) $depth],
                    [
                        'title' => mb_substr((string) $row['title'], 0, 255),
                        'summary' => $row['summary'] ?? null,
                        'body' => (string) ($row['body'] ?? ($row['summary'] ?? '')),
                        'sources' => is_array($sources) ? implode("\n", $sources) : $sources,
                        'is_machine_translated' => (bool) ($row['is_machine_translated'] ?? false),
                    ],
                );
            }
        }
    }

    /** Bản dịch đơn giản từ khối {vi: {...}, en: {...}} chỉ lấy các trường cho phép. */
    protected function translationsFrom(array $item, array $fields): array
    {
        $out = [];
        foreach (['vi', 'en'] as $locale) {
            $row = (array) ($item[$locale] ?? []);
            $values = [];
            foreach ($fields as $field) {
                $value = $row[$field] ?? null;
                $values[$field] = is_array($value) ? implode("\n", $value) : $value;
            }
            $out[$locale] = $values;
        }

        return $out;
    }

    /** Gắn thẻ theo danh sách slug, bỏ qua slug không tồn tại. */
    protected function syncTags(Model $subject, array $slugs): void
    {
        $ids = Tag::whereIn('slug', array_filter($slugs, 'is_string'))->pluck('id')->all();
        $subject->tags()->sync($ids);
    }

    /** Tạo/cập nhật media gắn với đối tượng theo (media_type, sort_order[, locale]); trả về bản ghi. */
    protected function upsertMedia(Model $subject, array $key, array $values, array $captions = []): MediaAsset
    {
        $media = MediaAsset::updateOrCreate(
            ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()] + $key,
            $values,
        );
        foreach ($captions as $locale => $caption) {
            if (array_filter($caption)) {
                MediaCaption::updateOrCreate(['media_id' => $media->id, 'locale' => $locale], $caption);
            }
        }

        return $media;
    }

    protected function slugOr(array $item, string $fallback): string
    {
        return Str::slug((string) ($item['slug'] ?? $fallback)) ?: Str::slug($fallback);
    }

    /** Kích thước ảnh {width, height} (null nếu không đọc được). */
    protected function imageSize(?string $path): array
    {
        $size = $path && is_file($path) ? @getimagesize($path) : false;

        return $size ? ['width' => $size[0], 'height' => $size[1]] : ['width' => null, 'height' => null];
    }
}
