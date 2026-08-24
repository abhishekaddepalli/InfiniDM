@php
    // Slim plan / free-trial banner shown across the top of the user shell,
    // mirroring WaDesk. Self-hiding: renders nothing unless the signed-in,
    // non-admin user is on a live trial or has just run out of one. The plan
    // window lives on the USER in Instaflow (users.trial_ends_at / plan_ends_at),
    // there being no Workspace model here.
    $u = auth()->user();

    $trialEnd = $u?->trial_ends_at;
    $planEnd  = $u?->plan_ends_at;
    $hasPaid  = $planEnd && $planEnd->isFuture();

    $onTrial      = $u && ! ($u->is_admin ?? false) && $trialEnd && $trialEnd->isFuture() && ! $hasPaid;
    $trialExpired = $u && ! ($u->is_admin ?? false) && $trialEnd && $trialEnd->isPast()   && ! $hasPaid;

    // Days remaining, rounded UP so a fresh 14-day trial reads "14 days", not 13.
    $daysLeft = $onTrial ? max(1, (int) ceil(now()->diffInHours($trialEnd) / 24)) : 0;

    $plansUrl = \Illuminate\Support\Facades\Route::has('instagram.plans')
        ? route('instagram.plans')
        : url('/instagram/plans');

    $planName = $u?->package_id
        ? optional(\App\Models\Package::find($u->package_id))->name
        : null;

    $crown = '<svg viewBox="0 0 20 20" class="w-4 h-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M2.5 6.4l3 2.3 3.2-4.6a1.6 1.6 0 0 1 2.6 0l3.2 4.6 3-2.3c.8-.6 1.9.1 1.7 1.1l-1.5 7.2a1.3 1.3 0 0 1-1.3 1H3.6a1.3 1.3 0 0 1-1.3-1L.8 7.5C.6 6.5 1.7 5.8 2.5 6.4z"/></svg>';
@endphp

@if ($onTrial)
    <div class="w-full px-4 sm:px-6 py-2 flex items-center gap-3 text-white ig-grad-soft">
        <span class="grid place-items-center w-6 h-6 rounded-full bg-white/20 text-amber-200">{!! $crown !!}</span>
        <span class="text-[12.5px] font-medium leading-tight min-w-0 truncate">
            {{ $planName ? __(':plan trial', ['plan' => $planName]) : __('Free trial') }}
            <span class="opacity-90">·
                {{ trans_choice('{1}:count day left|[2,*]:count days left', $daysLeft, ['count' => $daysLeft]) }}
            </span>
        </span>
        <a href="{{ $plansUrl }}"
            class="ml-auto shrink-0 px-3 py-1 rounded-full bg-white text-[11.5px] font-semibold hover:opacity-90"
            style="color:#C13584">{{ __('Upgrade') }}</a>
    </div>
@elseif ($trialExpired)
    <div class="w-full px-4 sm:px-6 py-2 flex items-center gap-3 text-white" style="background:#B23A48">
        <span class="grid place-items-center w-6 h-6 rounded-full bg-white/20 text-amber-100">{!! $crown !!}</span>
        <span class="text-[12.5px] font-medium leading-tight min-w-0 truncate">
            {{ __('Your free trial has ended. Upgrade to keep using :brand.', ['brand' => brand_name()]) }}
        </span>
        <a href="{{ $plansUrl }}"
            class="ml-auto shrink-0 px-3 py-1 rounded-full bg-white text-[11.5px] font-semibold hover:opacity-90"
            style="color:#B23A48">{{ __('Choose a plan') }}</a>
    </div>
@endif
