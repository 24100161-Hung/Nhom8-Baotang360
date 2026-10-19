<?php

namespace App\Providers;

use App\Models\Artifact;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use App\Models\ContactMessage;
use App\Models\GuestbookEntry;
use App\Models\Hotspot;
use App\Models\HotspotTranslation;
use App\Models\Interpretation;
use App\Models\MediaAsset;
use App\Models\MediaCaption;
use App\Models\Membership;
use App\Models\Museum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Review;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Models\Ticket;
use App\Models\TicketPackage;
use App\Models\TicketPackageTranslation;
use App\Models\Tour;
use App\Models\TourStep;
use App\Models\TourStepTranslation;
use App\Models\TourTranslation;
use App\Models\User;
use App\Models\VisitDay;
use App\Observers\FlushPageCache;
use App\Policies\OrderPolicy;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SandboxGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** Giá trị mẫu từng nằm trong kho mã, không bao giờ được dùng làm khóa thật. */
    public const INSECURE_PAYMENT_SECRETS = ['sandbox-secret-change-me', 'changeme', 'secret'];

    /** Alias morph (cột *_type) theo conventions; bổ sung alias cho đối tượng của nhật ký kiểm toán. */
    public const MORPH_MAP = [
        'museum' => Museum::class,
        'scene' => Scene::class,
        'hotspot' => Hotspot::class,
        'artifact' => Artifact::class,
        'tour' => Tour::class,
        'step' => TourStep::class,
        'package' => TicketPackage::class,
        'order' => Order::class,
        'ticket' => Ticket::class,
        'review' => Review::class,
        'post' => BlogPost::class,
        'user' => User::class,
        'payment' => Payment::class,
        'media' => MediaAsset::class,
        'tag' => Tag::class,
        'period' => Period::class,
        'category' => BlogCategory::class,
        'membership' => Membership::class,
        'visit_day' => VisitDay::class,
        'contact' => ContactMessage::class,
        'guestbook' => GuestbookEntry::class,
    ];

    public function register(): void
    {
        // Chỉ cổng mô phỏng được hiện thực; cấu hình cổng khác thì không bind (route sandbox cũng tắt)
        if (config('bt360.payment.gateway') === 'sandbox') {
            $this->app->bind(PaymentGateway::class, SandboxGateway::class);
        }
        $this->app->singleton(\App\Services\PageCache::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(self::MORPH_MAP);

        // Resource tự bọc "data" chỉ ở API; Inertia nhận mảng phẳng
        JsonResource::withoutWrapping();

        $this->ensurePaymentSecret();
        $this->registerAuthorization();
        $this->registerRateLimiters();
        $this->registerPageCacheInvalidation();

        Vite::useCspNonce();
    }

    /** Production + cổng sandbox mà khóa HMAC rỗng hoặc còn giá trị mẫu công khai → dừng ngay. */
    public function ensurePaymentSecret(): void
    {
        if (! $this->app->isProduction() || config('bt360.payment.gateway') !== 'sandbox'
            || $this->app->runningConsoleCommand('package:discover')) {
            return;
        }
        $secret = (string) config('bt360.payment.sandbox_secret');
        if ($secret === '' || in_array($secret, self::INSECURE_PAYMENT_SECRETS, true)) {
            throw new \RuntimeException('PAYMENT_SANDBOX_SECRET must be set to a random value in production.');
        }
    }

    /** Nội dung hiển thị trên trang công khai đã đệm: lưu/xóa → tăng phiên bản PageCache. */
    private function registerPageCacheInvalidation(): void
    {
        foreach ([
            Museum::class, Scene::class, Hotspot::class, HotspotTranslation::class, Artifact::class, Interpretation::class,
            MediaAsset::class, MediaCaption::class, Tag::class, TagTranslation::class, Period::class,
            Tour::class, TourTranslation::class, TourStep::class, TourStepTranslation::class,
            TicketPackage::class, TicketPackageTranslation::class, BlogPost::class, BlogPostTranslation::class, Review::class,
        ] as $model) {
            $model::observe(FlushPageCache::class);
        }
    }

    private function registerAuthorization(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);

        // admin + curator: nội dung (hiện vật, không gian 360, hotspot, tour, audio, blog, kiểm duyệt, thống kê)
        Gate::define('manage-content', fn (User $user) => $user->isStaff());
        // chỉ admin: tài khoản, gói vé, lịch vé, đơn hàng, liên hệ, nhật ký
        Gate::define('manage-commerce', fn (User $user) => $user->isAdmin());
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());
        Gate::define('view-audit', fn (User $user) => $user->isAdmin());
        Gate::define('view-revenue', fn (User $user) => $user->isAdmin());
    }

    private function registerRateLimiters(): void
    {
        // Chống dò mật khẩu: 5 lần/phút cho mỗi cặp email + IP, thêm 5 lần/phút theo riêng email
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('email-ip:'.$email.'|'.$request->ip()),
                Limit::perMinute(5)->by('email:'.$email),
            ];
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        // Sự kiện tham quan gửi theo lô (sendBeacon): 120 lô/phút/phiên-IP
        RateLimiter::for('events', fn (Request $request) => Limit::perMinute(120)->by('events:'.$request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by('contact:'.$request->ip()));
        RateLimiter::for('guestbook', fn (Request $request) => Limit::perMinute(5)->by('guestbook:'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('pwreset:'.$request->ip()),
            Limit::perMinute(3)->by('pwreset-email:'.Str::lower(trim((string) $request->input('email')))),
        ]);
    }
}
