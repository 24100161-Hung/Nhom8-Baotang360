<?php

namespace Tests\Feature\Infra;

use App\Http\Resources\TicketItemResource;
use App\Models\Order;
use App\Services\PaymentService;
use App\Support\OrderLookupToken;
use App\Support\PrintableDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Shop\ShopHelpers;
use Tests\TestCase;

/** Rà soát cứng hóa: chân trang học thuật mọi trang, token tra cứu đơn (Crypt), nhật ký 403 / đăng nhập / analytics. */
class HardeningTest extends TestCase
{
    use RefreshDatabase, ShopHelpers;

    private const FOOTER_COURSE = 'CSE703073 – Lập trình ứng dụng web trong du lịch 2';

    private const FOOTER_DISCLAIMER = 'Dữ liệu mô phỏng hoặc công khai (Naturalis CC BY-SA 3.0); thanh toán ở chế độ thử nghiệm.';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/bt360-hard-'.uniqid();
        File::ensureDirectoryExists($this->tmp);
        config(['logging.channels.nghiepvu.path' => $this->tmp.'/nghiepvu.log']);
        Log::forgetChannel('nghiepvu');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    private function nghiepvu(): string
    {
        $file = $this->tmp.'/nghiepvu-'.now()->format('Y-m-d').'.log';

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    private function assertAcademicFooter(string $html): void
    {
        $this->assertStringContainsString(self::FOOTER_COURSE, $html);
        $this->assertStringContainsString('Nhóm 08 BaoTang360', $html);
        $this->assertStringContainsString('Trường CNTT, Đại học Phenikaa', $html);
        $this->assertStringContainsString(self::FOOTER_DISCLAIMER, $html);
        $this->assertStringContainsString('Phiên bản '.config('bt360.version'), $html);
    }

    private function paidOrder(): Order
    {
        $order = $this->placedOrder(null, 1);
        $payments = app(PaymentService::class);
        $signed = $payments->simulate($payments->initiate($order, $order->user), 'success');
        $payments->handleCallback($signed['payload'], $signed['signature']);

        return $order->fresh();
    }

    public function test_server_rendered_footer_is_in_html_of_public_and_admin_pages(): void
    {
        $this->assertAcademicFooter($this->get('/vi')->assertOk()->getContent());
        $admin = $this->actingAs($this->admin())->get('/vi/quan-tri')->assertOk()->getContent();
        $this->assertAcademicFooter($admin);
        $this->assertStringContainsString('bt360-static-footer', $admin);
        $this->assertStringContainsString('html.js-mounted .bt360-static-footer', $admin);
    }

    public function test_blade_error_pages_have_footer(): void
    {
        $this->assertAcademicFooter($this->get('/vi/khong-ton-tai-xyz')->assertNotFound()->getContent());
        $this->assertAcademicFooter($this->actingAs($this->visitor())->get('/vi/quan-tri')->assertForbidden()->getContent());
    }

    public function test_shared_app_props_expose_version_and_school(): void
    {
        $this->get('/vi')->assertInertia(fn ($page) => $page
            ->where('app.version', config('bt360.version'))
            ->where('app.school', 'Trường CNTT, Đại học Phenikaa'));
    }

    public function test_order_lookup_with_encrypted_token_shows_order_without_login(): void
    {
        $order = $this->paidOrder();
        $url = OrderLookupToken::url($order);

        $this->assertStringNotContainsString($order->code, $url);
        $html = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();
        $this->assertStringContainsString($order->code, $html);
        $this->assertStringNotContainsString((string) $order->contact_email, $html);
        $this->assertAcademicFooter($html);
    }

    public function test_order_lookup_with_tampered_garbage_or_expired_token_is_404(): void
    {
        $order = $this->paidOrder();
        $token = OrderLookupToken::make($order);
        $tampered = substr($token, 0, 20).($token[20] === 'A' ? 'B' : 'A').substr($token, 21);

        $this->get('/vi/don-hang/tra-cuu/'.$tampered)->assertNotFound();
        $this->get('/vi/don-hang/tra-cuu/abc')->assertNotFound();
        $this->get('/vi/don-hang/tra-cuu/'.OrderLookupToken::make($order, -1))->assertNotFound();
        // Mã hóa hợp lệ nhưng mã đơn không tồn tại
        $foreign = rtrim(strtr(base64_encode(Crypt::encryptString(json_encode(['c' => 'BT-NOPE', 'e' => now()->addDay()->getTimestamp()]))), '+/', '-_'), '=');
        $this->get('/vi/don-hang/tra-cuu/'.$foreign)->assertNotFound();
        $this->assertStringContainsString('order.lookup_invalid_token', $this->nghiepvu());
    }

    public function test_paid_mail_contains_encrypted_lookup_link(): void
    {
        $order = $this->paidOrder();
        $html = PrintableDocument::paidMail($order->load('items.package.translations'));

        $this->assertMatchesRegularExpression('#/vi/don-hang/tra-cuu/[A-Za-z0-9_\-]{40,}#', $html);
    }

    public function test_tampered_signed_invoice_url_is_403_and_logged_as_warning(): void
    {
        $order = $this->paidOrder();
        $url = TicketItemResource::signed('orders.invoice', ['order' => $order->code]);

        $this->get(preg_replace('/signature=([0-9a-f])/', 'signature=x$1', $url))->assertForbidden();
        $log = $this->nghiepvu();
        $this->assertMatchesRegularExpression('/\.WARNING: access\.denied .*"route":"orders\.invoice".*"client_ip":"127\.0\.0\.0".*"reason":"invalid_signature"/', $log);
    }

    public function test_rbac_403_is_logged_with_user_id(): void
    {
        $visitor = $this->visitor();
        $this->actingAs($visitor)->get('/vi/quan-tri')->assertForbidden();

        $this->assertMatchesRegularExpression('/\.WARNING: access\.denied .*"route":"admin\.dashboard".*"user_id":'.$visitor->id.'/', $this->nghiepvu());
    }

    public function test_business_log_has_info_warning_and_error_levels(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('', 500)]);
        $this->post('/vi/dang-nhap', ['email' => $this->admin()->email, 'password' => 'password']);
        $this->actingAs($this->visitor())->get('/vi/quan-tri');
        $this->get('/vi/tim-kiem?q=khung+long');

        $log = $this->nghiepvu();
        $this->assertStringContainsString('.INFO: auth.login', $log);
        $this->assertStringContainsString('.WARNING: access.denied', $log);
        $this->assertStringContainsString('.ERROR: python.error', $log);
    }

    public function test_ip_masking_keeps_only_network_prefix(): void
    {
        $this->assertSame('203.0.113.0', \App\Exceptions\AccessDeniedReporter::maskIp('203.0.113.77'));
        $this->assertSame('2001:db8:abcd::', \App\Exceptions\AccessDeniedReporter::maskIp('2001:db8:abcd:12::1'));
        $this->assertSame('unknown', \App\Exceptions\AccessDeniedReporter::maskIp('not-an-ip'));
        // Bộ lọc PII không nhầm IP ẩn danh với số điện thoại, nhưng vẫn che số điện thoại
        $this->assertSame('ip 172.18.0.0 / 203.113.45.0', \App\Logging\RedactSensitiveData::scrub('ip 172.18.0.0 / 203.113.45.0'));
        $this->assertSame('sdt [phone]', \App\Logging\RedactSensitiveData::scrub('sdt 0912.345.678'));
    }
}
