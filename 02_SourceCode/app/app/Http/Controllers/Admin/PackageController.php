<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageRequest;
use App\Http\Resources\PackageItemResource;
use App\Models\TicketPackage;
use App\Services\AuditLogger;
use App\Services\PackageWriter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** CRUD gói vé (chỉ admin – route có role:admin, kiểm thêm Gate manage-commerce). */
class PackageController extends Controller
{
    public function __construct(private readonly PackageWriter $writer, private readonly AuditLogger $audit)
    {
    }

    public function index(): Response
    {
        $this->authorize('manage-commerce');
        $packages = TicketPackage::query()->with('translations')->withCount('orderItems as order_items_count')
            ->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Admin/Packages/Index', [
            'packages' => $packages->map(fn ($p) => PackageItemResource::one($p) + ['orders_count' => (int) $p->order_items_count])->all(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manage-commerce');

        return $this->form(new TicketPackage(['package_type' => 'single', 'validity_days' => 1, 'max_per_order' => 10, 'status' => 'draft', 'price_vnd' => 0]));
    }

    public function edit(TicketPackage $package): Response
    {
        $this->authorize('manage-commerce');

        return $this->form($package->load('translations'));
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        $package = $this->writer->save(new TicketPackage(), $request->validated());
        $this->audit->log('admin.package.create', $package, ['code' => $package->code]);

        return redirect(localized_route('admin.packages.index'))->with('success', __('shop.flash.package_saved'));
    }

    public function update(PackageRequest $request, TicketPackage $package): RedirectResponse
    {
        $this->writer->save($package, $request->validated());
        $this->audit->log('admin.package.update', $package, ['code' => $package->code]);

        return redirect(localized_route('admin.packages.index'))->with('success', __('shop.flash.package_saved'));
    }

    public function destroy(TicketPackage $package): RedirectResponse
    {
        $this->authorize('manage-commerce');
        // Gói đã có đơn: chỉ ngừng bán để giữ lịch sử đơn/hóa đơn
        if ($this->writer->isInUse($package)) {
            $package->update(['status' => 'draft']);

            return back()->with('error', __('shop.errors.package_in_use'));
        }
        $package->delete();
        $this->audit->log('admin.package.delete', $package, ['code' => $package->code]);

        return redirect(localized_route('admin.packages.index'))->with('success', __('shop.flash.package_deleted'));
    }

    private function form(TicketPackage $package): Response
    {
        return Inertia::render('Admin/Packages/Form', [
            'package' => $this->writer->formData($package),
            'enums' => [
                'package_types' => $this->enumOptions('package_types', [TicketPackage::SINGLE, TicketPackage::MEMBERSHIP]),
                'statuses' => $this->enumOptions('statuses', ['draft', 'published']),
            ],
        ]);
    }
}
