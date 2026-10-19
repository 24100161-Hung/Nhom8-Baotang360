<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\TokenController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GuestbookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MuseumController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderLookupController;
use App\Http\Controllers\PaymentSandboxController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\VisitEventController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $locale = in_array(session('locale'), ['vi', 'en'], true) ? session('locale') : 'vi';

    return redirect('/'.$locale);
})->name('root');

Route::prefix('{locale}')->where(['locale' => 'vi|en'])->middleware('locale')->group(function () {
    // ---------- Công khai ----------
    Route::get('/', HomeController::class)->name('home');
    Route::get('bao-tang', [MuseumController::class, 'show'])->name('museum.show');
    Route::get('tham-quan-360', [SceneController::class, 'index'])->name('scenes.index');
    Route::get('tham-quan-360/{scene:slug}', [SceneController::class, 'show'])->name('scenes.show');
    Route::get('hien-vat', [ArtifactController::class, 'index'])->name('artifacts.index');
    Route::get('hien-vat/{artifact:slug}', [ArtifactController::class, 'show'])->name('artifacts.show');
    Route::get('tim-kiem', [SearchController::class, 'index'])->name('search.index');
    Route::get('tour', [TourController::class, 'index'])->name('tours.index');
    Route::get('tour/{tour:slug}', [TourController::class, 'show'])->name('tours.show');
    Route::post('tour/{tour:slug}/bat-dau', [TourController::class, 'start'])->middleware('throttle:30,1,tour-start')->name('tours.start');
    Route::post('tour/{tour:slug}/tien-trinh', [TourController::class, 'progress'])->middleware('throttle:120,1,tour-progress')->name('tours.progress');
    // Sự kiện tham quan gửi theo lô (navigator.sendBeacon) – không cần đăng nhập, loại khỏi CSRF (bootstrap/app.php)
    Route::post('su-kien', [VisitEventController::class, 'store'])->middleware('throttle:events')->name('events.store');
    Route::get('ve', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('lien-he', [ContactController::class, 'create'])->name('contact.create');
    Route::post('lien-he', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
    Route::get('so-luu-but', [GuestbookController::class, 'index'])->name('guestbook.index');
    Route::post('so-luu-but', [GuestbookController::class, 'store'])->middleware('throttle:guestbook')->name('guestbook.store');
    Route::get('gioi-thieu', AboutController::class)->name('about');

    // ---------- Giỏ hàng (khách vãng lai dùng session, đăng nhập thì gộp vào tài khoản) ----------
    Route::get('gio-hang', [CartController::class, 'index'])->name('cart.index');
    Route::post('gio-hang', [CartController::class, 'store'])->middleware('throttle:30,1,cart')->name('cart.add');
    Route::patch('gio-hang/{item}', [CartController::class, 'update'])->whereNumber('item')->name('cart.update');
    Route::delete('gio-hang/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('cart.remove');

    // ---------- Liên kết ký số (signed URL, không cần phiên): QR vé, vé in, hóa đơn ----------
    Route::middleware('signed')->group(function () {
        Route::get('ve/{ticket:code}/qr', [TicketController::class, 'qr'])->name('tickets.qr');
        Route::get('ve/{ticket:code}/in', [TicketController::class, 'pdf'])->name('tickets.pdf');
        Route::get('don-hang/{order:code}/hoa-don', [OrderController::class, 'invoice'])->name('orders.invoice');
    });
    // Tra cứu đơn bằng token Crypt::encryptString (thư xác nhận); giải mã lỗi → 404
    Route::get('don-hang/tra-cuu/{token}', OrderLookupController::class)
        ->where('token', '[A-Za-z0-9_\-]+')->middleware('throttle:30,1,order-lookup')->name('orders.lookup');

    // ---------- Xác thực ----------
    Route::middleware('guest')->group(function () {
        Route::get('dang-nhap', [LoginController::class, 'create'])->name('login');
        Route::post('dang-nhap', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
        Route::get('dang-ky', [RegisterController::class, 'create'])->name('register');
        Route::post('dang-ky', [RegisterController::class, 'store'])->middleware('throttle:6,1,register')->name('register.store');
        Route::get('quen-mat-khau', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('quen-mat-khau', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
        Route::get('dat-lai-mat-khau/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('dat-lai-mat-khau', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
        // Frontend POST cùng URL có token trên đường dẫn; token trong body vẫn được kiểm
        Route::post('dat-lai-mat-khau/{token}', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update.token');
    });
    Route::post('dang-xuat', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    // ---------- Tài khoản, thanh toán, đơn hàng ----------
    Route::middleware('auth')->group(function () {
        Route::get('tai-khoan', AccountDashboardController::class)->name('account.dashboard');
        Route::get('tai-khoan/ho-so', [ProfileController::class, 'edit'])->name('account.profile');
        Route::patch('tai-khoan/ho-so', [ProfileController::class, 'update'])->name('account.profile.update');
        Route::patch('tai-khoan/mat-khau', [ProfileController::class, 'password'])->name('account.password.update');
        Route::post('tai-khoan/token', [TokenController::class, 'store'])->middleware('throttle:10,1,api-token')->name('account.tokens.store');
        Route::delete('tai-khoan/token/{token}', [TokenController::class, 'destroy'])->whereNumber('token')->name('account.tokens.destroy');

        Route::post('yeu-thich', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
        Route::post('danh-gia', [ReviewController::class, 'store'])->middleware('throttle:10,1,review')->name('reviews.store');

        Route::get('thanh-toan', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('thanh-toan', [CheckoutController::class, 'store'])->middleware('throttle:10,1,checkout')->name('checkout.store');
        Route::get('don-hang/{order:code}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('don-hang/{order:code}/thanh-toan', [OrderController::class, 'pay'])->name('orders.pay');
        Route::post('don-hang/{order:code}/huy', [OrderController::class, 'cancel'])->name('orders.cancel');

        // Cổng mô phỏng chỉ tồn tại khi PAYMENT_GATEWAY=sandbox (cổng khác → 404)
        if (config('bt360.payment.gateway') === 'sandbox') {
            Route::get('cong-thanh-toan/{payment:txn_ref}', [PaymentSandboxController::class, 'show'])->name('payments.sandbox');
            Route::post('cong-thanh-toan/{payment:txn_ref}/ket-qua', [PaymentSandboxController::class, 'result'])->name('payments.sandbox.result');
        }
    });

    // ---------- Quản trị: auth trước role (khách → đăng nhập, sai vai trò → 403) ----------
    Route::prefix('quan-tri')->name('admin.')->middleware(['auth', 'role:admin,curator'])->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        foreach ([
            ['hien-vat', 'artifacts', 'artifact', Admin\ArtifactController::class],
            ['khong-gian-360', 'scenes', 'scene', Admin\SceneController::class],
            ['tour', 'tours', 'tour', Admin\TourController::class],
            ['blog', 'posts', 'post', Admin\PostController::class],
        ] as [$path, $name, $param, $controller]) {
            Route::get($path, [$controller, 'index'])->name("$name.index");
            Route::get("$path/moi", [$controller, 'create'])->name("$name.create");
            Route::post($path, [$controller, 'store'])->name("$name.store");
            Route::get("$path/{{$param}}/sua", [$controller, 'edit'])->name("$name.edit");
            Route::put("$path/{{$param}}", [$controller, 'update'])->name("$name.update");
            Route::delete("$path/{{$param}}", [$controller, 'destroy'])->name("$name.destroy");
        }
        Route::post('hien-vat/{artifact}/anh', [Admin\ArtifactController::class, 'storeImage'])->name('artifacts.images.store');
        Route::delete('hien-vat/{artifact}/anh/{media}', [Admin\ArtifactController::class, 'destroyImage'])->whereNumber('media')->name('artifacts.images.destroy');

        Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
        Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');
        Route::post('khong-gian-360/{scene}/panorama', [Admin\SceneController::class, 'panorama'])->name('scenes.panorama');
        Route::get('khong-gian-360/{scene}/tiles', [Admin\SceneController::class, 'tilesStatus'])->name('scenes.tiles.status');
        Route::post('khong-gian-360/{scene}/audio', [Admin\SceneAudioController::class, 'store'])->name('scenes.audio.store');
        Route::delete('khong-gian-360/{scene}/audio/{media}', [Admin\SceneAudioController::class, 'destroy'])->whereNumber('media')->name('scenes.audio.destroy');
        Route::post('khong-gian-360/{scene}/hotspot', [Admin\HotspotController::class, 'store'])->name('hotspots.store');
        Route::put('khong-gian-360/{scene}/hotspot/{hotspot}', [Admin\HotspotController::class, 'update'])->whereNumber('hotspot')->name('hotspots.update');
        Route::delete('khong-gian-360/{scene}/hotspot/{hotspot}', [Admin\HotspotController::class, 'destroy'])->whereNumber('hotspot')->name('hotspots.destroy');
        Route::put('tour/{tour}/buoc', [Admin\TourController::class, 'steps'])->name('tours.steps');

        Route::get('danh-gia', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::patch('danh-gia/{review}', [Admin\ReviewController::class, 'update'])->whereNumber('review')->name('reviews.update');
        Route::get('so-luu-but', [Admin\GuestbookController::class, 'index'])->name('guestbook.index');
        Route::patch('so-luu-but/{entry}', [Admin\GuestbookController::class, 'update'])->whereNumber('entry')->name('guestbook.update');
        Route::delete('so-luu-but/{entry}', [Admin\GuestbookController::class, 'destroy'])->whereNumber('entry')->name('guestbook.destroy');

        Route::get('thong-ke', [Admin\StatsController::class, 'index'])->name('stats.index');
        Route::get('phan-tich-luong', [Admin\FlowController::class, 'index'])->name('flow.index');
        Route::post('phan-tich-luong', [Admin\FlowController::class, 'run'])->name('flow.run');
        Route::post('phan-tich-luong/chay', [Admin\FlowController::class, 'run'])->name('flow.run.alias');
        Route::get('chat-luong-du-lieu', [Admin\QualityController::class, 'index'])->name('quality.index');
        Route::post('chat-luong-du-lieu/kiem-tra', [Admin\QualityController::class, 'run'])->name('quality.run');

        // Chỉ admin: gói vé, lịch vé, đơn hàng, liên hệ, tài khoản, nhật ký
        Route::middleware('role:admin')->group(function () {
            Route::get('goi-ve', [Admin\PackageController::class, 'index'])->name('packages.index');
            Route::get('goi-ve/moi', [Admin\PackageController::class, 'create'])->name('packages.create');
            Route::post('goi-ve', [Admin\PackageController::class, 'store'])->name('packages.store');
            Route::get('goi-ve/{package}/sua', [Admin\PackageController::class, 'edit'])->name('packages.edit');
            Route::put('goi-ve/{package}', [Admin\PackageController::class, 'update'])->name('packages.update');
            Route::delete('goi-ve/{package}', [Admin\PackageController::class, 'destroy'])->name('packages.destroy');

            Route::get('lich-ve', [Admin\VisitDayController::class, 'index'])->name('visitdays.index');
            Route::patch('lich-ve', [Admin\VisitDayController::class, 'update'])->name('visitdays.update');

            Route::get('don-hang', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('don-hang/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::patch('don-hang/{order}/trang-thai', [Admin\OrderController::class, 'status'])->name('orders.status');

            Route::get('lien-he', [Admin\ContactController::class, 'index'])->name('contacts.index');
            Route::patch('lien-he/{message}', [Admin\ContactController::class, 'update'])->whereNumber('message')->name('contacts.update');

            Route::get('nguoi-dung', [Admin\UserController::class, 'index'])->name('users.index');
            Route::patch('nguoi-dung/{user}', [Admin\UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::get('nhat-ky', [Admin\AuditLogController::class, 'index'])->name('audit.index');
        });
    });
});
