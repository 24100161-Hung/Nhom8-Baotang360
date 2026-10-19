<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * Sinh vé khi đơn đã thanh toán: mỗi khách một vé (gói vé lượt), mã TK- ngẫu nhiên,
 * qr_token = payload.chữ ký HMAC-SHA256 (khóa dẫn xuất từ APP_KEY) để cổng soát vé xác minh offline.
 */
class TicketService
{
    /** Tạo vé cho mọi dòng gói single của đơn (idempotent: dòng đã có vé thì bỏ qua). */
    public function issueForOrder(Order $order): int
    {
        $created = 0;
        $order->loadMissing('items.package', 'items.tickets');
        foreach ($order->items as $item) {
            if (! $item->package?->isSingle() || $item->tickets->isNotEmpty()) {
                continue;
            }
            for ($i = 1; $i <= $item->quantity; $i++) {
                $code = $this->uniqueCode();
                $item->tickets()->create([
                    'code' => $code,
                    'qr_token' => $this->token($code, $order->code, $item->visit_date?->toDateString()),
                    'holder_name' => mb_substr($order->contact_name, 0, 120),
                    'visit_date' => $item->visit_date,
                    'status' => 'valid',
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** Payload ký "mã vé|mã đơn|ngày" + "." + HMAC-SHA256 (≤ 128 ký tự, vừa cột qr_token). */
    public function token(string $ticketCode, string $orderCode, ?string $visitDate): string
    {
        $payload = $ticketCode.'|'.$orderCode.'|'.($visitDate ?? '');

        return $payload.'.'.hash_hmac('sha256', $payload, $this->key());
    }

    /** Xác minh token QR; trả payload hoặc null nếu chữ ký sai. */
    public function verify(string $token): ?array
    {
        [$payload, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if ($payload === '' || ! hash_equals(hash_hmac('sha256', $payload, $this->key()), $sig)) {
            return null;
        }
        [$t, $o, $d] = array_pad(explode('|', $payload, 3), 3, '');

        return ['t' => $t, 'o' => $o, 'd' => $d !== '' ? $d : null];
    }

    public function svg(Ticket $ticket, int $size = 280): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($ticket->qr_token);
    }

    /** PNG vẽ bằng GD từ ma trận QR của bacon (không cần Imagick). */
    public function png(Ticket $ticket, int $scale = 8, int $margin = 2): string
    {
        $matrix = Encoder::encode($ticket->qr_token, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ENCODING)->getMatrix();
        $n = $matrix->getWidth();
        $px = ($n + 2 * $margin) * $scale;
        $img = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $x0 = ($x + $margin) * $scale;
                    $y0 = ($y + $margin) * $scale;
                    imagefilledrectangle($img, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'TK-'.Str::upper(Str::random(10));
        } while (Ticket::query()->where('code', $code)->exists());

        return $code;
    }

    private function key(): string
    {
        return hash_hmac('sha256', 'bt360-ticket-qr', (string) config('app.key'));
    }
}
