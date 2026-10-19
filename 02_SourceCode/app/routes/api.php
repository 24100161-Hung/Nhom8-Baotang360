<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

// API v1: JSON, giới hạn 120 yêu cầu/phút (throttle:api), dữ liệu công khai + /me bằng token Sanctum
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('scenes', [V1\SceneController::class, 'index'])->name('scenes.index');
    Route::get('scenes/{slug}', [V1\SceneController::class, 'show'])->name('scenes.show');
    Route::get('artifacts', [V1\ArtifactController::class, 'index'])->name('artifacts.index');
    Route::get('artifacts/{slug}', [V1\ArtifactController::class, 'show'])->name('artifacts.show');
    Route::get('tours', [V1\TourController::class, 'index'])->name('tours.index');
    Route::get('tours/{slug}', [V1\TourController::class, 'show'])->name('tours.show');
    Route::get('tours/{slug}/similar', [V1\TourController::class, 'similar'])->name('tours.similar');
    Route::get('packages', [V1\CatalogController::class, 'packages'])->name('packages');
    Route::get('visit-days', [V1\CatalogController::class, 'visitDays'])->name('visit-days');
    Route::get('search', [V1\CatalogController::class, 'search'])->name('search');
    Route::get('posts', [V1\CatalogController::class, 'posts'])->name('posts');
    Route::get('openapi.json', V1\OpenApiController::class)->name('openapi');

    // IPN cổng thanh toán sandbox (server-to-server, xác minh chữ ký HMAC; chữ ký sai → 400)
    if (config('bt360.payment.gateway') === 'sandbox') {
        Route::post('payments/sandbox/ipn', V1\PaymentIpnController::class)->name('payments.ipn');
    }

    // Token cá nhân Sanctum: đăng nhập bằng email + mật khẩu (giới hạn như form đăng nhập)
    Route::post('tokens', [V1\TokenController::class, 'store'])->middleware('throttle:login')->name('tokens.store');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [V1\MeController::class, 'show'])->name('me');
        Route::delete('tokens/current', [V1\TokenController::class, 'destroy'])->name('tokens.destroy');
    });
});
