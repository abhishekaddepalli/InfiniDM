{{-- Cookie-consent bar — WaDesk-style. Hidden by default; site.js reveals it
     only when consent hasn't been recorded (localStorage). Admin-editable copy. --}}
@if(front_bool('cookie_show'))
@php($cookieLink = front('cookie_link') ?: route('page.legal', 'cookies'))
<div id="cookieBar" class="fixed bottom-0 inset-x-0 z-[60] hidden">
  <div class="mx-auto max-w-5xl m-3 sm:m-5 rounded-2xl bg-white hairline shadow-[0_30px_70px_-40px_rgba(26,19,32,0.6)] p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
    <div class="flex items-start gap-3 flex-1">
      <span class="w-9 h-9 rounded-xl ig-grad-soft grid place-items-center text-white shrink-0 mt-0.5">
        <svg viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7.5"/><circle cx="8" cy="8" r=".8" fill="currentColor"/><circle cx="12.5" cy="11.5" r=".8" fill="currentColor"/><circle cx="8.5" cy="13" r=".8" fill="currentColor"/></svg>
      </span>
      <p class="text-[12.5px] text-ink-600 leading-relaxed">{{ front('cookie_text') }} <a href="{{ $cookieLink }}" class="ig-text font-semibold">{{ __('Learn more') }}</a></p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
      <button type="button" data-cookie="decline" class="px-4 py-2 rounded-full border border-paper-200 text-[12.5px] font-semibold hover:bg-paper-50">{{ front('cookie_decline') }}</button>
      <button type="button" data-cookie="accept" class="btn-grad ig-grad-soft text-white px-5 py-2 rounded-full text-[12.5px] font-semibold">{{ front('cookie_accept') }}</button>
    </div>
  </div>
</div>
@endif
