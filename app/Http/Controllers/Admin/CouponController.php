<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Discount codes for subscription packages. */
class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::latest()->paginate(20);
        $stats = [
            'active'      => Coupon::where('is_active', true)->count(),
            'total'       => Coupon::count(),
            'redemptions' => (int) Coupon::sum('redeemed_count'),
        ];
        return view('admin.coupons.index', compact('coupons', 'stats'));
    }

    public function create()
    {
        return view('admin.coupons.form', [
            'coupon' => new Coupon(['type' => 'percent', 'is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);
        Coupon::create($data);
        return redirect()->route('admin.coupons.index')->with('success', __('Coupon created.'));
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $this->validated($request, $coupon->id);
        $data['code'] = strtoupper($data['code']);
        $coupon->update($data);
        return redirect()->route('admin.coupons.index')->with('success', __('Coupon updated.'));
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return back()->with('success', __('Coupon deleted.'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'            => ['required', 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'description'     => ['nullable', 'string', 'max:255'],
            'type'            => ['required', 'in:percent,fixed'],
            'value'           => ['required', 'numeric', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:0'],
            'starts_at'       => ['nullable', 'date'],
            'expires_at'      => ['nullable', 'date'],
            'is_active'       => ['nullable', 'boolean'],
        ]);
    }
}
