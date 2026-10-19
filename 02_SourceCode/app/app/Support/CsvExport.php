<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Xuất CSV UTF-8 có BOM (Excel đọc đúng tiếng Việt), chống CSV/formula injection. */
final class CsvExport
{
    /** @param iterable<array> $rows */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) ? self::safe($v) : $v, array_values($row)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * Ô bắt đầu (sau khoảng trắng) bằng = + - @ | % hoặc tab/CR/LF được thêm dấu nháy đơn để Excel coi là văn bản.
     */
    public static function safe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s\x{00A0}]*[=+\-@|%]|^[\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
