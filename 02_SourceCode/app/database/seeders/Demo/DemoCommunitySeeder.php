<?php

namespace Database\Seeders\Demo;

use App\Models\Artifact;
use App\Models\ContactMessage;
use App\Models\GuestbookEntry;
use App\Models\Museum;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;

/** 20 đánh giá (nhiều trạng thái), 10 lời sổ lưu bút, 5 tin liên hệ mẫu; chạy lại thì thay thế dữ liệu mẫu cũ. */
class DemoCommunitySeeder extends Seeder
{
    public const REVIEWERS = ['visitor@baotang360.test', 'premium@baotang360.test', 'visitor2@baotang360.test', 'visitor3@baotang360.test'];
    public const CONTACT_DOMAIN = '@example.com';

    private const REVIEW_TEXTS = [
        [5, 'Tuyệt vời', 'Trải nghiệm 360° rất mượt, thuyết minh dễ hiểu.'],
        [4, 'Rất đáng xem', 'Hình ảnh đẹp, mong có thêm hiện vật.'],
        [5, 'Amazing', 'The T. rex gallery is stunning in 360°.'],
        [3, 'Khá ổn', 'Nội dung hay nhưng tải ảnh hơi chậm trên 4G.'],
        [4, 'Great for kids', 'My children loved the guided tour.'],
    ];

    private const GUESTBOOK = [
        ['Minh Anh', 'Cảm ơn Naturalis và nhóm BaoTang360, tour khủng long thật tuyệt!', 'vi', 'approved'],
        ['Hoàng Nam', 'Lần đầu tham quan bảo tàng Hà Lan mà không cần ra sân bay.', 'vi', 'approved'],
        ['Emma', 'Lovely virtual visit, the Ice Age room is my favourite.', 'en', 'approved'],
        ['Thu Trang', 'Audio tiếng Việt rất dễ nghe.', 'vi', 'approved'],
        ['Lucas', 'Great project, very educational.', 'en', 'approved'],
        ['Bảo Ngọc', 'Mong có thêm phòng côn trùng.', 'vi', 'approved'],
        ['Quang Huy', 'Bản đồ mini giúp định hướng rất tốt.', 'vi', 'pending'],
        ['Sophie', 'Can you add a Dutch audio guide?', 'en', 'pending'],
        ['Khách vãng lai', 'Nội dung quảng cáo không liên quan.', 'vi', 'hidden'],
        ['Đức Long', 'Sẽ giới thiệu cho lớp sinh học.', 'vi', 'pending'],
    ];

    public function run(): void
    {
        $this->reviews();
        $this->guestbook();
        $this->contacts();
    }

    private function reviews(): void
    {
        $users = User::query()->whereIn('email', self::REVIEWERS)->get();
        Review::query()->whereIn('user_id', $users->pluck('id'))->delete();
        $subjects = collect([Museum::query()->first()])
            ->merge(Tour::query()->published()->orderBy('sort_order')->limit(3)->get())
            ->merge(Artifact::query()->published()->orderByDesc('is_highlight')->orderBy('id')->limit(4)->get())
            ->filter()->values();
        $statuses = ['approved', 'approved', 'approved', 'pending', 'approved', 'rejected', 'pending', 'approved', 'approved', 'pending'];
        $pairs = $users->crossJoin($subjects)->take(20)->values();
        foreach ($pairs as $count => [$user, $subject]) {
            [$rating, $title, $body] = self::REVIEW_TEXTS[$count % count(self::REVIEW_TEXTS)];
            $status = $statuses[$count % count($statuses)];
            Review::create([
                'user_id' => $user->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
                'rating' => $rating, 'title' => $title, 'body' => $body, 'status' => $status,
                'reviewed_at' => $status === 'pending' ? null : now()->subDays($count % 7),
                'review_note' => $status === 'rejected' ? 'Nội dung chưa phù hợp (mẫu)' : null,
            ]);
        }
    }

    private function guestbook(): void
    {
        GuestbookEntry::query()->whereIn('message', array_column(self::GUESTBOOK, 1))->delete();
        foreach (self::GUESTBOOK as $i => [$name, $message, $locale, $status]) {
            $entry = GuestbookEntry::create(['display_name' => $name, 'message' => $message, 'locale' => $locale, 'status' => $status]);
            $entry->forceFill(['created_at' => now()->subDays($i + 1)])->saveQuietly();
        }
    }

    private function contacts(): void
    {
        ContactMessage::query()->where('email', 'like', '%'.self::CONTACT_DOMAIN)->delete();
        $rows = [
            ['Nguyễn Hải', 'Hỏi về vé đoàn', 'Trường tôi muốn đặt vé cho 40 học sinh, có ưu đãi không?', 'new'],
            ['Lan Phương', 'Lỗi âm thanh', 'Audio phòng Earth không phát trên Safari.', 'read'],
            ['Peter', 'Partnership', 'We would like to collaborate on a Dutch audio guide.', 'replied'],
            ['Trần Bình', 'Hoàn tiền', 'Tôi đã hủy đơn, bao lâu thì nhận lại tiền?', 'replied'],
            ['Mai Chi', 'Góp ý giao diện', 'Nên có chế độ tối cho trình xem 360.', 'new'],
        ];
        foreach ($rows as $i => [$name, $subject, $body, $status]) {
            ContactMessage::create([
                'name' => $name, 'email' => 'lienhe'.($i + 1).self::CONTACT_DOMAIN, 'phone' => '09120000'.($i + 10),
                'subject' => $subject, 'body' => $body, 'status' => $status,
                'replied_at' => $status === 'replied' ? now()->subDays(1) : null,
                'reply_note' => $status === 'replied' ? 'Đã phản hồi qua email (mẫu).' : null,
            ]);
        }
    }
}
