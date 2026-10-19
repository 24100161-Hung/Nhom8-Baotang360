<?php

namespace Tests\Feature\Admin;

/** Dữ liệu form dùng chung cho test quản trị. */
trait AdminFormData
{
    protected function interpretations(string $viTitle = 'Tiêu đề VI', string $enTitle = 'Title EN'): array
    {
        return [
            ['locale' => 'vi', 'depth_level' => 1, 'title' => $viTitle, 'summary' => 'Tóm tắt', 'body' => 'Nội dung'],
            ['locale' => 'en', 'depth_level' => 1, 'title' => $enTitle, 'summary' => 'Summary', 'body' => 'Body'],
        ];
    }

    protected function sceneData(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'phong-khung-long', 'code' => 'dinosaur', 'floor' => 1, 'sort_order' => 2,
            'minimap_x' => 0.4, 'minimap_y' => 0.6, 'hfov_default' => 100, 'status' => 'published',
            'license' => 'cc_by_sa_4', 'interpretations' => $this->interpretations('Thời đại khủng long', 'Dinosaur era'),
        ], $overrides);
    }

    /** UploadedFile thật từ nội dung (finfo đọc MIME thật, khác UploadedFile::fake() đoán theo đuôi). */
    protected function realUpload(string $content, string $name): \Illuminate\Http\UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new \Illuminate\Http\UploadedFile($path, $name, null, null, true);
    }

    /** Tệp JPEG thật (GD) để finfo nhận image/jpeg. */
    protected function jpegPath(int $w = 64, int $h = 32): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pano').'.jpg';
        $img = imagecreatetruecolor($w, $h);
        imagejpeg($img, $path);
        imagedestroy($img);

        return $path;
    }
}
