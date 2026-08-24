@extends('frontend.layout')
@section('title', brand_name())

@section('content')
@php
  $home = url('/');
  $reg  = route('register');
  $marquee = array_filter(array_map('trim', explode(',', front('marquee_items'))));
@endphp

<!-- ============ HERO ============ -->
<section id="top" class="relative overflow-hidden">
  <div class="absolute inset-0 dot-pattern opacity-60"></div>
  <div class="absolute -top-32 right-0 w-[620px] h-[620px] rounded-full opacity-20" style="background:radial-gradient(circle,#E1306C,transparent 65%)"></div>
  <div class="absolute -bottom-48 -left-40 w-[620px] h-[620px] rounded-full opacity-15" style="background:radial-gradient(circle,#833AB4,transparent 65%)"></div>

  <div class="relative w-full px-5 sm:px-8 lg:px-16 pt-28 lg:pt-36 pb-16 grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <span class="pill hairline bg-white text-ink-700">
        <span class="w-1.5 h-1.5 rounded-full bg-green-500 pulse-dot"></span>
        {{ front('hero_badge') }}
      </span>
      <h1 class="serif text-[54px] sm:text-[76px] lg:text-[88px] leading-[0.96] mt-5">
        {{ front('hero_title') }}<br/>on <span class="ig-text">{{ front('hero_accent') }}</span>.
      </h1>
      <p class="text-[16px] text-ink-600 mt-6 max-w-lg leading-relaxed">{{ front('hero_subtitle') }}</p>
      <div class="mt-8 flex flex-wrap items-center gap-3">
        <a href="{{ $reg }}" class="btn-grad ig-grad-soft text-white px-6 py-3.5 rounded-full text-[14px] font-semibold inline-flex items-center gap-2">
          {{ front('hero_cta1') }}
          <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
        </a>
        <a href="#demo" class="px-6 py-3.5 rounded-full text-[14px] font-semibold hairline bg-white inline-flex items-center gap-2 hover:bg-paper-50">
          <svg viewBox="0 0 20 20" class="w-4 h-4 text-ig-pink" fill="currentColor"><path d="M6 4l10 6-10 6z"/></svg>
          {{ front('hero_cta2') }}
        </a>
      </div>
      <div class="mt-9 flex items-center gap-4">
        <div class="flex -space-x-2.5">
          <span class="w-8 h-8 rounded-full ring-2 ring-white ig-grad"></span>
          <span class="w-8 h-8 rounded-full ring-2 ring-white bg-ig-purple"></span>
          <span class="w-8 h-8 rounded-full ring-2 ring-white bg-ig-orange"></span>
          <span class="w-8 h-8 rounded-full ring-2 ring-white bg-ink-900 text-white text-[10px] font-bold grid place-items-center">12k</span>
        </div>
        <div class="text-[12.5px] text-ink-600 leading-tight">
          <div class="flex items-center gap-1 text-ig-amber">★★★★★ <span class="text-ink-700 font-semibold ml-1">{{ front('hero_rating') }}</span></div>
          {{ front('hero_proof') }}
        </div>
      </div>
    </div>

    <!-- product mock (decorative) -->
    <div class="relative">
      <div class="rounded-3xl hairline bg-white shadow-[0_50px_110px_-55px_rgba(26,19,32,0.55)] overflow-hidden">
        <div class="hairline-b px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-2"><span class="ig-grad w-6 h-6 rounded-lg"></span><span class="text-[12px] font-semibold">Dashboard</span></div>
          <div class="flex items-center gap-1.5 mono text-[10px] text-ink-500 uppercase tracking-widest"><span class="w-1.5 h-1.5 rounded-full bg-green-500 pulse-dot"></span>3 accounts live</div>
        </div>
        <div class="p-5 space-y-4 bg-paper-50">
          <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl bg-white hairline p-3"><div class="mono text-[9px] uppercase tracking-widest text-ink-500">Reach</div><div class="serif text-[26px] leading-none mt-1 tabular">248k</div><div class="text-[10px] text-green-600 font-semibold mt-1">▲ 18%</div></div>
            <div class="rounded-2xl bg-white hairline p-3"><div class="mono text-[9px] uppercase tracking-widest text-ink-500">Replies</div><div class="serif text-[26px] leading-none mt-1 tabular">1,284</div><div class="text-[10px] text-green-600 font-semibold mt-1">86% auto</div></div>
            <div class="rounded-2xl bg-white hairline p-3"><div class="mono text-[9px] uppercase tracking-widest text-ink-500">Followers</div><div class="serif text-[26px] leading-none mt-1 tabular">+3.1k</div><div class="text-[10px] text-green-600 font-semibold mt-1">▲ 6%</div></div>
          </div>
          <div class="rounded-2xl bg-white hairline p-4">
            <div class="flex items-center justify-between mb-3"><div class="text-[11px] font-semibold">Engagement · 7 days</div><div class="mono text-[9px] text-ink-500">MON–SUN</div></div>
            <div class="flex items-end gap-2 h-24" id="heroBars">
              <div class="bar flex-1 rounded-t-md bg-paper-200" style="height:38%"></div><div class="bar flex-1 rounded-t-md bg-paper-200" style="height:56%"></div><div class="bar flex-1 rounded-t-md ig-grad-soft" style="height:44%"></div><div class="bar flex-1 rounded-t-md ig-grad-soft" style="height:72%"></div><div class="bar flex-1 rounded-t-md ig-grad-soft" style="height:60%"></div><div class="bar flex-1 rounded-t-md bg-ink-900" style="height:92%"></div><div class="bar flex-1 rounded-t-md bg-paper-200" style="height:50%"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="float-a absolute -left-6 top-24 w-52 rounded-2xl bg-white hairline shadow-xl p-3.5 hidden sm:block">
        <div class="flex items-center gap-2.5"><div class="w-8 h-8 rounded-lg ig-grad-soft grid place-items-center text-white"><svg viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3.5 6.5a5 5 0 0110 0v3l1.5 3h-13L3.5 9.5z"/><path d="M7.5 14a2.5 2.5 0 005 0"/></svg></div><div><div class="text-[11.5px] font-semibold leading-tight">Auto-reply sent</div><div class="text-[10px] text-ink-500">to @maya · 0.4s</div></div></div>
      </div>
      <div class="float-b absolute -right-4 bottom-16 w-48 rounded-2xl bg-white hairline shadow-xl p-3.5 hidden sm:block">
        <div class="mono text-[9px] uppercase tracking-widest text-ink-500">Scheduled</div><div class="text-[12px] font-semibold mt-1">Reel · Fri 6:00pm</div><div class="mt-2 flex items-center gap-1.5 text-[10px] text-ink-500"><span class="w-1.5 h-1.5 rounded-full bg-ig-pink"></span>Best time to post</div>
      </div>
    </div>
  </div>

  <!-- trust marquee -->
  <div class="relative border-y hairline-t hairline-b bg-white/60 py-5 overflow-hidden">
    <div class="w-full px-5 sm:px-8 lg:px-16 flex items-center gap-6">
      <span class="shrink-0 mono text-[10px] uppercase tracking-widest text-ink-500">{{ front('marquee_label') }}</span>
      <div class="flex-1 overflow-hidden" style="mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent)">
        <div class="marquee text-[17px] font-semibold text-ink-400">
          @foreach($marquee as $m)<span>{{ $m }}</span>@endforeach
          @foreach($marquee as $m)<span aria-hidden="true">{{ $m }}</span>@endforeach
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ INTERACTIVE FEATURES ============ -->
<section id="features" class="w-full px-5 sm:px-8 lg:px-16 py-20 lg:py-28">
  <div class="max-w-2xl mb-12 reveal">
    <span class="mono text-[10px] uppercase tracking-[0.2em] text-ig-pink font-medium">{{ front('feat_eyebrow') }}</span>
    <h2 class="serif text-[40px] sm:text-[52px] leading-[1.02] mt-3">{{ front('feat_title') }}<span class="ig-text">.</span></h2>
    <p class="text-[14.5px] text-ink-600 mt-4">{{ front('feat_subtitle') }}</p>
  </div>

  <div class="grid lg:grid-cols-[380px_1fr] gap-6 items-start">
    <div class="space-y-2" id="ftabs">
      <button class="ftab on w-full" data-f="schedule"><div class="flex items-center gap-3"><span class="w-10 h-10 rounded-xl ig-grad-soft grid place-items-center text-white shrink-0"><svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M3 8h14M7 2v4M13 2v4"/></svg></span><div><div class="font-semibold text-[15px]">{{ front('feat1_title') }}</div><div class="text-[12.5px] text-ink-600">{{ front('feat1_desc') }}</div></div></div></button>
      <button class="ftab w-full" data-f="inbox"><div class="flex items-center gap-3"><span class="w-10 h-10 rounded-xl bg-paper-100 grid place-items-center text-ig-pink shrink-0"><svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2.5 5.5h15v8H10l-4 3v-3H2.5z"/></svg></span><div><div class="font-semibold text-[15px]">{{ front('feat2_title') }}</div><div class="text-[12.5px] text-ink-600">{{ front('feat2_desc') }}</div></div></div></button>
      <button class="ftab w-full" data-f="ads"><div class="flex items-center gap-3"><span class="w-10 h-10 rounded-xl bg-paper-100 grid place-items-center text-ig-orange shrink-0"><svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 13l6-2.5 5-6 3.5 11-8.5-1.5z"/><path d="M5 13v3"/></svg></span><div><div class="font-semibold text-[15px]">{{ front('feat3_title') }}</div><div class="text-[12.5px] text-ink-600">{{ front('feat3_desc') }}</div></div></div></button>
      <button class="ftab w-full" data-f="analytics"><div class="flex items-center gap-3"><span class="w-10 h-10 rounded-xl bg-paper-100 grid place-items-center text-ig-purple shrink-0"><svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 13l4-5 3 3 6-7"/><path d="M3 3v14h14"/></svg></span><div><div class="font-semibold text-[15px]">{{ front('feat4_title') }}</div><div class="text-[12.5px] text-ink-600">{{ front('feat4_desc') }}</div></div></div></button>
    </div>

    <div class="rounded-3xl hairline bg-white p-6 lg:p-8 min-h-[420px] relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full opacity-[0.07] ig-grad"></div>
      <div class="fpane on" data-pane="schedule">
        <div class="flex items-center justify-between mb-5"><div><div class="font-semibold text-[16px]">Content calendar</div><div class="text-[12.5px] text-ink-600">April · Week 3</div></div><span class="pill ig-grad-soft text-white">Auto-queue on</span></div>
        <div class="grid grid-cols-7 gap-2">
          <div class="mono text-[9px] uppercase text-ink-500 text-center">M</div><div class="mono text-[9px] uppercase text-ink-500 text-center">T</div><div class="mono text-[9px] uppercase text-ink-500 text-center">W</div><div class="mono text-[9px] uppercase text-ink-500 text-center">T</div><div class="mono text-[9px] uppercase text-ink-500 text-center">F</div><div class="mono text-[9px] uppercase text-ink-500 text-center">S</div><div class="mono text-[9px] uppercase text-ink-500 text-center">S</div>
          <div class="rounded-xl bg-paper-50 hairline h-20 p-2"><div class="text-[9px] text-ink-500">15</div><div class="mt-1 h-8 rounded-md ig-grad-soft opacity-80"></div></div><div class="rounded-xl bg-paper-50 hairline h-20 p-2"><div class="text-[9px] text-ink-500">16</div></div><div class="rounded-xl bg-paper-50 hairline h-20 p-2"><div class="text-[9px] text-ink-500">17</div><div class="mt-1 h-8 rounded-md bg-ink-900"></div></div><div class="rounded-xl bg-paper-50 hairline h-20 p-2"><div class="text-[9px] text-ink-500">18</div></div><div class="rounded-xl bg-white hairline h-20 p-2 ring-2 ring-ig-pink"><div class="text-[9px] font-semibold text-ig-pink">19 · best</div><div class="mt-1 h-8 rounded-md ig-grad-soft"></div></div><div class="rounded-xl bg-paper-50 hairline h-20 p-2"><div class="text-[9px] text-ink-500">20</div><div class="mt-1 h-8 rounded-md bg-paper-200"></div></div><div class="rounded-xl bg-paper-50 hairline h-20 p-2"><div class="text-[9px] text-ink-500">21</div></div>
        </div>
        <div class="mt-5 flex items-center gap-2 text-[12px] text-ink-600"><span class="w-2 h-2 rounded-full ig-grad-soft"></span>Reels <span class="w-2 h-2 rounded-full bg-ink-900 ml-3"></span>Carousels <span class="w-2 h-2 rounded-full bg-paper-200 ml-3"></span>Stories</div>
      </div>
      <div class="fpane" data-pane="inbox">
        <div class="grid lg:grid-cols-[minmax(0,420px)_1fr] gap-6 items-start">
          <div class="rounded-[26px] hairline bg-paper-50 overflow-hidden">
            <div class="bg-white hairline-b px-4 py-3 flex items-center justify-between"><div class="flex items-center gap-2.5"><span class="ig-ring"><span class="block w-8 h-8 rounded-full ig-grad"></span></span><div><div class="font-semibold text-[13px]">@maya.creates</div><div class="text-[10.5px] text-green-600 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500 pulse-dot"></span>Active now</div></div></div><span class="pill bg-green-50 text-green-700">Auto</span></div>
            <div class="p-4 h-[290px] flex flex-col gap-2.5 overflow-hidden" id="chatBox"></div>
            <div class="bg-white hairline-t px-3 py-2.5 flex items-center gap-2"><div class="flex-1 bg-paper-100 rounded-full px-4 py-2 text-[12px] text-ink-500">Message…</div><span class="w-8 h-8 rounded-full ig-grad-soft grid place-items-center text-white"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8h10M9 4l4 4-4 4"/></svg></span></div>
          </div>
          <div class="space-y-3">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">Behind the scenes — your rules</div>
            <div class="rounded-2xl hairline bg-white p-4"><div class="flex items-center gap-2 text-[12px] text-ink-600"><span class="pill bg-paper-100 text-ink-600 mono">WHEN</span>DM contains “shipping”</div><div class="mt-2 flex items-center gap-2 text-[13px] font-medium"><span class="pill ig-grad-soft text-white mono">THEN</span>Reply with Shipping FAQ<span class="ml-auto mono text-[10px] text-ink-500">0.4s</span></div></div>
            <div class="rounded-2xl hairline bg-white p-4"><div class="flex items-center gap-2 text-[12px] text-ink-600"><span class="pill bg-paper-100 text-ink-600 mono">WHEN</span>Comment asks for price</div><div class="mt-2 flex items-center gap-2 text-[13px] font-medium"><span class="pill ig-grad-soft text-white mono">THEN</span>Reply + DM the product link<span class="ml-auto mono text-[10px] text-ink-500">0.6s</span></div></div>
            <div class="rounded-2xl bg-ink-900 text-white p-4 flex items-center justify-between"><div><div class="text-[11px] text-white/60">Replies today</div><div class="serif text-[24px] leading-none mt-1 tabular">1,284</div></div><div class="text-right"><div class="text-[11px] text-white/60">Handled automatically</div><div class="serif text-[24px] leading-none mt-1 tabular text-ig-amber">86%</div></div></div>
          </div>
        </div>
      </div>
      <div class="fpane" data-pane="ads">
        <div class="flex items-center justify-between mb-5"><div><div class="font-semibold text-[16px]">Campaign · Spring launch</div><div class="text-[12.5px] text-ink-600">A/B test running</div></div><span class="pill bg-green-50 text-green-700">Live</span></div>
        <div class="grid sm:grid-cols-2 gap-3">
          <div class="rounded-2xl hairline p-4"><div class="flex items-center justify-between"><span class="text-[12px] font-semibold">Variant A</span><span class="text-[11px] text-ink-500">reel</span></div><div class="mt-3 flex items-end gap-1.5 h-16"><div class="flex-1 rounded-t bg-paper-200" style="height:50%"></div><div class="flex-1 rounded-t bg-paper-200" style="height:64%"></div><div class="flex-1 rounded-t ig-grad-soft" style="height:78%"></div></div><div class="mt-3 flex justify-between text-[11px]"><span class="text-ink-500">CTR</span><span class="font-semibold tabular">2.4%</span></div></div>
          <div class="rounded-2xl hairline p-4 ring-2 ring-ig-pink"><div class="flex items-center justify-between"><span class="text-[12px] font-semibold">Variant B · winner</span><span class="text-[11px] text-ig-pink">▲</span></div><div class="mt-3 flex items-end gap-1.5 h-16"><div class="flex-1 rounded-t bg-paper-200" style="height:60%"></div><div class="flex-1 rounded-t ig-grad-soft" style="height:82%"></div><div class="flex-1 rounded-t ig-grad-soft" style="height:100%"></div></div><div class="mt-3 flex justify-between text-[11px]"><span class="text-ink-500">CTR</span><span class="font-semibold tabular text-ig-pink">4.1%</span></div></div>
        </div>
        <div class="mt-4 rounded-2xl bg-ink-900 text-white p-4 flex items-center justify-between"><div><div class="text-[11px] text-white/60">Spend today</div><div class="serif text-[24px] leading-none mt-1 tabular">$42.80</div></div><div class="text-right"><div class="text-[11px] text-white/60">Budget cap</div><div class="text-[13px] font-semibold">$60.00</div></div></div>
      </div>
      <div class="fpane" data-pane="analytics">
        <div class="flex items-center justify-between mb-5"><div><div class="font-semibold text-[16px]">What actually worked</div><div class="text-[12.5px] text-ink-600">Last 30 days</div></div><span class="pill bg-paper-100 text-ink-600 mono">saves · follows</span></div>
        <div class="rounded-2xl hairline p-4"><div class="flex items-end gap-2 h-40" id="anaBars"><div class="bar flex-1 rounded-t-md bg-paper-200" style="height:30%"></div><div class="bar flex-1 rounded-t-md bg-paper-200" style="height:45%"></div><div class="bar flex-1 rounded-t-md ig-grad-soft" style="height:55%"></div><div class="bar flex-1 rounded-t-md ig-grad-soft" style="height:40%"></div><div class="bar flex-1 rounded-t-md bg-ink-900" style="height:88%"></div><div class="bar flex-1 rounded-t-md ig-grad-soft" style="height:66%"></div><div class="bar flex-1 rounded-t-md bg-paper-200" style="height:50%"></div><div class="bar flex-1 rounded-t-md ig-grad-soft" style="height:100%"></div></div></div>
        <div class="mt-4 grid grid-cols-3 gap-3"><div class="rounded-xl bg-paper-50 hairline p-3"><div class="mono text-[9px] uppercase text-ink-500">Saves</div><div class="serif text-[24px] tabular leading-none mt-1">8.2k</div></div><div class="rounded-xl bg-paper-50 hairline p-3"><div class="mono text-[9px] uppercase text-ink-500">New follows</div><div class="serif text-[24px] tabular leading-none mt-1">3.1k</div></div><div class="rounded-xl bg-paper-50 hairline p-3"><div class="mono text-[9px] uppercase text-ink-500">DMs</div><div class="serif text-[24px] tabular leading-none mt-1">642</div></div></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ LIVE DEMO ============ -->
<section id="demo" class="bg-ink-900 text-white relative overflow-hidden">
  <div class="absolute inset-0 dot-pattern opacity-30" style="background-image:radial-gradient(circle at 1px 1px, rgba(255,255,255,0.12) 1px, transparent 0)"></div>
  <div class="absolute -top-40 -right-32 w-[520px] h-[520px] rounded-full opacity-25" style="background:radial-gradient(circle,#833AB4,transparent 65%)"></div>
  <div class="absolute -bottom-40 -left-32 w-[520px] h-[520px] rounded-full opacity-20" style="background:radial-gradient(circle,#E1306C,transparent 65%)"></div>
  <div class="relative w-full px-5 sm:px-8 lg:px-16 py-20 lg:py-24 grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">
    <div class="reveal">
      <span class="mono text-[10px] uppercase tracking-[0.2em] text-ig-amber font-medium">{{ front('demo_eyebrow') }}</span>
      <h2 class="serif text-[40px] sm:text-[54px] leading-[1.04] mt-3">{{ front('demo_title') }}</h2>
      <p class="text-[14.5px] text-white/70 mt-4 max-w-md">{{ front('demo_subtitle') }}</p>
      <div class="mt-7 mono text-[10px] uppercase tracking-widest text-white/50">Or tap one of these</div>
      <div class="mt-3 flex flex-wrap gap-2">
        <button class="chip-q px-4 py-2 rounded-full border border-white/15 bg-white/5 text-[12.5px] hover:bg-white/15 transition">Do you ship to Canada?</button>
        <button class="chip-q px-4 py-2 rounded-full border border-white/15 bg-white/5 text-[12.5px] hover:bg-white/15 transition">What's your return policy?</button>
        <button class="chip-q px-4 py-2 rounded-full border border-white/15 bg-white/5 text-[12.5px] hover:bg-white/15 transition">Is the spring set in stock?</button>
        <button class="chip-q px-4 py-2 rounded-full border border-white/15 bg-white/5 text-[12.5px] hover:bg-white/15 transition">How much is shipping?</button>
      </div>
      <div class="mt-10 grid grid-cols-3 gap-4 max-w-md">
        <div><div class="serif text-[32px] leading-none text-ig-amber">{{ front('demo_stat1') }}</div><div class="text-[11px] text-white/60 mt-1.5">{{ front('demo_stat1l') }}</div></div>
        <div><div class="serif text-[32px] leading-none">{{ front('demo_stat2') }}</div><div class="text-[11px] text-white/60 mt-1.5">{{ front('demo_stat2l') }}</div></div>
        <div><div class="serif text-[32px] leading-none">{{ front('demo_stat3') }}</div><div class="text-[11px] text-white/60 mt-1.5">{{ front('demo_stat3l') }}</div></div>
      </div>
      <div class="mt-8 flex items-center gap-2 text-[12px] text-white/60"><span class="w-1.5 h-1.5 rounded-full bg-green-400 pulse-dot"></span><span class="tabular font-semibold text-white/90" id="dmCount">4,218,304</span> DMs answered — and counting</div>
    </div>
    <div class="justify-self-center w-full max-w-[420px]">
      <div class="rounded-[34px] border border-white/10 bg-[#120C1A] shadow-[0_60px_120px_-40px_rgba(0,0,0,.85)] overflow-hidden">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between"><div class="flex items-center gap-3"><span class="ig-ring"><span class="block w-9 h-9 rounded-full ig-grad"></span></span><div><div class="text-[13px] font-semibold">@yourbrand</div><div class="text-[10.5px] text-green-400 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-400 pulse-dot"></span>Auto-reply active</div></div></div><span class="pill bg-white/10 text-white/70 mono uppercase tracking-widest" style="font-size:9px">Live demo</span></div>
        <div id="liveChat" class="p-4 h-[340px] flex flex-col gap-2.5 overflow-y-auto"></div>
        <form id="liveForm" class="m-3 flex items-center gap-2 bg-white/10 rounded-full p-1.5 pl-4 border border-white/10"><input id="liveInput" class="flex-1 bg-transparent outline-none text-[13px] text-white placeholder:text-white/40" placeholder="Type a message…" autocomplete="off"/><button class="ig-grad-soft text-white text-[12px] font-semibold rounded-full px-4 py-2">Send</button></form>
      </div>
    </div>
  </div>
</section>

<!-- ============ HOW IT WORKS ============ -->
<section id="how" class="bg-white hairline-b">
  <div class="w-full px-5 sm:px-8 lg:px-16 py-20 lg:py-28">
    <div class="text-center max-w-xl mx-auto mb-12 reveal">
      <span class="mono text-[10px] uppercase tracking-[0.2em] text-ig-pink font-medium">{{ front('how_eyebrow') }}</span>
      <h2 class="serif text-[40px] sm:text-[52px] leading-[1.02] mt-3">{{ front('how_title') }}</h2>
      <p class="text-[13.5px] text-ink-600 mt-3">{{ front('how_subtitle') }}</p>
    </div>
    <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center max-w-5xl mx-auto">
      <div class="space-y-2" id="steps">
        <button class="step on"><div class="flex items-start gap-4"><span class="serif text-[34px] ig-text leading-none">01</span><div class="flex-1"><div class="font-semibold text-[16px]">{{ front('how1_title') }}</div><p class="text-[13px] text-ink-600 mt-1">{{ front('how1_desc') }}</p><div class="sprog"><i></i></div></div></div></button>
        <button class="step"><div class="flex items-start gap-4"><span class="serif text-[34px] ig-text leading-none">02</span><div class="flex-1"><div class="font-semibold text-[16px]">{{ front('how2_title') }}</div><p class="text-[13px] text-ink-600 mt-1">{{ front('how2_desc') }}</p><div class="sprog"><i></i></div></div></div></button>
        <button class="step"><div class="flex items-start gap-4"><span class="serif text-[34px] ig-text leading-none">03</span><div class="flex-1"><div class="font-semibold text-[16px]">{{ front('how3_title') }}</div><p class="text-[13px] text-ink-600 mt-1">{{ front('how3_desc') }}</p><div class="sprog"><i></i></div></div></div></button>
      </div>
      <div class="mx-auto w-full max-w-[340px]">
        <div class="rounded-[36px] hairline bg-white shadow-[0_40px_90px_-45px_rgba(26,19,32,0.5)] p-3">
          <div class="rounded-[26px] bg-paper-50 hairline overflow-hidden">
            <div class="hairline-b bg-white px-4 py-2.5 flex items-center justify-between"><span class="text-[11px] font-semibold" id="phTitle">{{ front('how1_title') }}</span><span class="mono text-[9px] text-ink-500">9:41</span></div>
            <div class="p-4 h-[350px]">
              <div class="spane on">
                <div class="space-y-2.5">
                  <div class="rounded-2xl bg-white hairline p-3 flex items-center gap-3"><span class="ig-ring"><span class="block w-8 h-8 rounded-full bg-paper-100"></span></span><div class="flex-1"><div class="text-[12px] font-semibold">@maya.creates</div><div class="text-[10px] text-ink-500">Creator · 214k</div></div><span class="pill bg-green-50 text-green-700">✓ Connected</span></div>
                  <div class="rounded-2xl bg-white hairline p-3 flex items-center gap-3"><span class="ig-ring"><span class="block w-8 h-8 rounded-full bg-paper-100"></span></span><div class="flex-1"><div class="text-[12px] font-semibold">@bloomly.shop</div><div class="text-[10px] text-ink-500">Business · 96k</div></div><span class="pill bg-green-50 text-green-700">✓ Connected</span></div>
                  <div class="rounded-2xl bg-white hairline p-3 flex items-center gap-3"><span class="ig-ring"><span class="block w-8 h-8 rounded-full bg-paper-100"></span></span><div class="flex-1"><div class="text-[12px] font-semibold">@studio.kern</div><div class="text-[10px] text-ink-500">Business · 88k</div></div><span class="pill ig-grad-soft text-white">+ Connect</span></div>
                </div>
                <div class="mt-4 rounded-xl bg-paper-100 p-3 text-[11px] text-ink-600">Official Graph API — revoke access anytime.</div>
              </div>
              <div class="spane">
                <div class="space-y-2.5">
                  <div class="rounded-2xl bg-white hairline p-3 flex items-center justify-between"><div><div class="text-[12px] font-semibold">Auto-reply DMs</div><div class="text-[10px] text-ink-500">In your brand voice</div></div><div class="toggle-track mini on mini-tg"><div class="toggle-knob"></div></div></div>
                  <div class="rounded-2xl bg-white hairline p-3 flex items-center justify-between"><div><div class="text-[12px] font-semibold">Auto-comments</div><div class="text-[10px] text-ink-500">FAQs &amp; spam filter</div></div><div class="toggle-track mini on mini-tg"><div class="toggle-knob"></div></div></div>
                  <div class="rounded-2xl bg-white hairline p-3 flex items-center justify-between"><div><div class="text-[12px] font-semibold">Post at best time</div><div class="text-[10px] text-ink-500">Per-account queue</div></div><div class="toggle-track mini mini-tg"><div class="toggle-knob"></div></div></div>
                </div>
                <div class="mt-4"><div class="mono text-[9px] uppercase tracking-widest text-ink-500 mb-2">Cadence</div><div class="flex gap-2"><span class="pill hairline bg-white">Daily</span><span class="pill ig-grad-soft text-white">3× week</span><span class="pill hairline bg-white">Weekly</span></div></div>
                <div class="mt-4 rounded-xl bg-paper-100 p-3 text-[11px] text-ink-600">Try the toggles — nothing goes live without preview.</div>
              </div>
              <div class="spane">
                <div class="flex items-center gap-2 mb-3"><span class="w-2 h-2 rounded-full bg-green-500 pulse-dot"></span><span class="text-[12px] font-semibold">Autopilot active</span></div>
                <div class="space-y-2">
                  <div class="rounded-xl bg-white hairline p-2.5 flex items-center justify-between"><span class="text-[11.5px]">Reel published · @maya.creates</span><span class="mono text-[9px] text-ink-500">6:00pm</span></div>
                  <div class="rounded-xl bg-white hairline p-2.5 flex items-center justify-between"><span class="text-[11.5px]">Auto-replied to @kai_west</span><span class="mono text-[9px] text-ink-500">0.4s</span></div>
                  <div class="rounded-xl bg-white hairline p-2.5 flex items-center justify-between"><span class="text-[11.5px]">Comment answered · pricing FAQ</span><span class="mono text-[9px] text-ink-500">2m</span></div>
                  <div class="rounded-xl bg-white hairline p-2.5 flex items-center justify-between"><span class="text-[11.5px]">Story queued · @bloomly.shop</span><span class="mono text-[9px] text-ink-500">8:30pm</span></div>
                  <div class="rounded-xl bg-white hairline p-2.5 flex items-center justify-between"><span class="text-[11.5px]">Weekly report ready</span><span class="mono text-[9px] text-ink-500">Mon 9:00</span></div>
                </div>
                <div class="mt-4 rounded-xl bg-ink-900 text-white p-3 flex items-center justify-between"><span class="text-[11px] text-white/70">Time saved this week</span><span class="serif text-[20px] leading-none tabular">9.2 hrs</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ STATS ============ -->
<section class="w-full px-5 sm:px-8 lg:px-16 py-20 lg:py-24">
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="rounded-3xl hairline bg-white p-8 text-center reveal"><div class="serif text-[52px] leading-none ig-text tabular"><span class="count" data-to="{{ front('stat1_num') }}" data-suffix="{{ front('stat1_suffix') }}">0</span></div><div class="text-[12.5px] text-ink-600 mt-2">{{ front('stat1_label') }}</div></div>
    <div class="rounded-3xl hairline bg-white p-8 text-center reveal"><div class="serif text-[52px] leading-none tabular"><span class="count" data-to="{{ front('stat2_num') }}" data-suffix="{{ front('stat2_suffix') }}" data-dec="1">0</span></div><div class="text-[12.5px] text-ink-600 mt-2">{{ front('stat2_label') }}</div></div>
    <div class="rounded-3xl hairline bg-white p-8 text-center reveal"><div class="serif text-[52px] leading-none tabular"><span class="count" data-to="{{ front('stat3_num') }}" data-suffix="{{ front('stat3_suffix') }}">0</span></div><div class="text-[12.5px] text-ink-600 mt-2">{{ front('stat3_label') }}</div></div>
    <div class="rounded-3xl hairline bg-white p-8 text-center reveal"><div class="serif text-[52px] leading-none tabular"><span class="count" data-to="{{ front('stat4_num') }}">0</span><span class="text-[28px]">{{ front('stat4_suffix') }}</span></div><div class="text-[12.5px] text-ink-600 mt-2">{{ front('stat4_label') }}</div></div>
  </div>
</section>

<!-- ============ TESTIMONIALS ============ -->
<section class="w-full px-5 sm:px-8 lg:px-16 pb-20 lg:pb-28">
  <div class="rounded-[32px] ig-grad-soft text-white p-8 sm:p-16 relative overflow-hidden reveal">
    <div class="absolute inset-0 dot-pattern opacity-25" style="background-image:radial-gradient(circle at 1px 1px, rgba(255,255,255,0.18) 1px, transparent 0)"></div>
    <div class="relative max-w-4xl">
      <div class="serif text-[46px] leading-none opacity-40">"</div>
      <div id="tSlide" class="tslide">
        <p id="tQuote" class="serif text-[28px] sm:text-[38px] leading-[1.2] -mt-4 min-h-[3.6em]">{{ front('t1_quote') }}</p>
        <div class="mt-7 flex items-center gap-3"><span id="tAvatar" class="w-11 h-11 rounded-full bg-white/20 ring-2 ring-white/40 grid place-items-center font-semibold">MR</span><div><div id="tName" class="font-semibold text-[14px]">{{ front('t1_name') }}</div><div id="tRole" class="text-[12px] text-white/75">{{ front('t1_role') }}</div></div></div>
      </div>
      <div class="mt-9 flex items-center gap-2.5">
        <button class="tava on w-10 h-10 rounded-full bg-white/25 ring-2 ring-white/40 grid place-items-center text-[11px] font-bold">MR</button>
        <button class="tava w-10 h-10 rounded-full bg-white/25 ring-2 ring-white/40 grid place-items-center text-[11px] font-bold">JK</button>
        <button class="tava w-10 h-10 rounded-full bg-white/25 ring-2 ring-white/40 grid place-items-center text-[11px] font-bold">PN</button>
        <span class="ml-2 text-[11px] text-white/60">Tap to switch — rotates on its own</span>
      </div>
    </div>
  </div>
</section>

<!-- ============ PRICING ============ -->
<section id="pricing" class="bg-white hairline-t hairline-b">
  <div class="w-full px-5 sm:px-8 lg:px-16 py-20 lg:py-28">
    <div class="text-center max-w-xl mx-auto mb-10 reveal">
      <span class="mono text-[10px] uppercase tracking-[0.2em] text-ig-pink font-medium">{{ front('price_eyebrow') }}</span>
      <h2 class="serif text-[40px] sm:text-[52px] leading-[1.02] mt-3">{{ front('price_title') }}</h2>
      <div class="mt-6 inline-flex items-center gap-3 text-[13px] font-medium"><span id="lblM" class="text-ink-900">Monthly</span><div class="toggle-track" id="billToggle"><div class="toggle-knob"></div></div><span id="lblY" class="text-ink-500">Annual <span class="text-ig-pink font-semibold">{{ front('price_note') }}</span></span></div>
    </div>
    <div class="grid md:grid-cols-3 gap-4 max-w-5xl mx-auto">
      <div class="rounded-3xl hairline bg-paper-50 p-7 flex flex-col"><div class="mono text-[10px] uppercase tracking-widest text-ink-500">Starter</div><div class="serif text-[46px] leading-none mt-3">$0</div><div class="text-[12px] text-ink-500 mt-1">Forever free</div><ul class="mt-6 space-y-2.5 text-[13px] flex-1"><li class="flex gap-2.5"><span class="text-green-600">✓</span> 1 connected account</li><li class="flex gap-2.5"><span class="text-green-600">✓</span> 30 scheduled posts / mo</li><li class="flex gap-2.5"><span class="text-green-600">✓</span> Basic auto-replies</li><li class="flex gap-2.5"><span class="text-green-600">✓</span> 7-day analytics</li></ul><a href="{{ $reg }}" class="mt-7 text-center py-3 rounded-full hairline bg-white text-[13px] font-semibold hover:bg-paper-100">Get started</a></div>
      <div class="rounded-3xl bg-ink-900 text-white p-7 flex flex-col relative overflow-hidden"><span class="absolute top-5 right-5 pill ig-grad-soft text-white">Most popular</span><div class="mono text-[10px] uppercase tracking-widest text-white/60">Pro</div><div class="serif text-[46px] leading-none mt-3">$<span class="price" data-m="29" data-y="23">29</span><span class="text-[18px] text-white/60">/mo</span></div><div class="text-[12px] text-white/60 mt-1" id="proNote">Billed monthly</div><ul class="mt-6 space-y-2.5 text-[13px] flex-1"><li class="flex gap-2.5"><span class="text-ig-amber">✓</span> 5 connected accounts</li><li class="flex gap-2.5"><span class="text-ig-amber">✓</span> Unlimited scheduling</li><li class="flex gap-2.5"><span class="text-ig-amber">✓</span> AI auto-replies &amp; comments</li><li class="flex gap-2.5"><span class="text-ig-amber">✓</span> Ads + full analytics</li></ul><a href="{{ $reg }}" class="btn-grad mt-7 text-center py-3 rounded-full ig-grad-soft text-[13px] font-semibold">Start 14-day trial</a></div>
      <div class="rounded-3xl hairline bg-paper-50 p-7 flex flex-col"><div class="mono text-[10px] uppercase tracking-widest text-ink-500">Team</div><div class="serif text-[46px] leading-none mt-3">$<span class="price" data-m="79" data-y="63">79</span><span class="text-[18px] text-ink-500">/mo</span></div><div class="text-[12px] text-ink-500 mt-1">Up to 10 seats</div><ul class="mt-6 space-y-2.5 text-[13px] flex-1"><li class="flex gap-2.5"><span class="text-green-600">✓</span> Everything in Pro</li><li class="flex gap-2.5"><span class="text-green-600">✓</span> Unlimited accounts</li><li class="flex gap-2.5"><span class="text-green-600">✓</span> Roles &amp; approvals</li><li class="flex gap-2.5"><span class="text-green-600">✓</span> Priority support</li></ul><a href="{{ $reg }}" class="mt-7 text-center py-3 rounded-full hairline bg-white text-[13px] font-semibold hover:bg-paper-100">Contact sales</a></div>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section id="faq" class="w-full px-5 sm:px-8 lg:px-16 py-20 lg:py-28">
  <div class="max-w-[820px] mx-auto">
    <div class="text-center mb-10 reveal"><span class="mono text-[10px] uppercase tracking-[0.2em] text-ig-pink font-medium">{{ front('faq_eyebrow') }}</span><h2 class="serif text-[40px] sm:text-[52px] leading-[1.02] mt-3">{{ front('faq_title') }}</h2></div>
    <div class="space-y-3">
      @foreach([1,2,3,4] as $i)
      <details class="group rounded-2xl hairline bg-white px-6 py-5" @if($i===1) open @endif>
        <summary class="flex items-center justify-between cursor-pointer list-none font-semibold text-[15px]">{{ front('faq'.$i.'_q') }}<span class="text-ig-pink text-xl font-light group-open:rotate-45 transition">+</span></summary>
        <p class="text-[13.5px] text-ink-600 mt-3">{{ front('faq'.$i.'_a') }}</p>
      </details>
      @endforeach
    </div>
  </div>
</section>

<!-- ============ CTA ============ -->
<section class="w-full px-5 sm:px-8 lg:px-16 pb-20">
  <div class="rounded-[32px] hairline bg-white p-10 sm:p-16 text-center relative overflow-hidden reveal">
    <div class="absolute inset-0 dot-pattern opacity-50"></div>
    <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[440px] h-[440px] rounded-full opacity-15 ig-grad"></div>
    <div class="relative">
      <h2 class="serif text-[42px] sm:text-[60px] leading-[1.02]">{{ front('cta_title') }}<span class="ig-text">.</span></h2>
      <p class="text-[14.5px] text-ink-600 mt-4 max-w-md mx-auto">{{ front('cta_subtitle') }}</p>
      <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ $reg }}" class="btn-grad ig-grad-soft text-white px-7 py-3.5 rounded-full text-[14px] font-semibold inline-flex items-center gap-2">{{ front('cta_primary') }}<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8h10M9 4l4 4-4 4"/></svg></a>
        <a href="{{ route('login') }}" class="px-7 py-3.5 rounded-full text-[14px] font-semibold hairline bg-white hover:bg-paper-50">{{ front('cta_secondary') }}</a>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
@php
  $initials = function ($name) {
      $parts = preg_split('/\s+/', trim($name));
      $a = mb_substr($parts[0] ?? '', 0, 1);
      $b = mb_substr($parts[1] ?? '', 0, 1);
      return mb_strtoupper($a . $b);
  };
  $tst = [];
  foreach ([1, 2, 3] as $i) {
      $q = front('t' . $i . '_quote'); $n = front('t' . $i . '_name');
      if ($q === '' && $n === '') continue;
      $tst[] = ['q' => $q, 'n' => $n, 'r' => front('t' . $i . '_role'), 'a' => $initials($n)];
  }
@endphp
<script>window.__IF_TESTIMONIALS__ = @json($tst);</script>
<script src="{{ asset('frontend/instaflow-landing.js') }}?v=1"></script>
@endpush
