/* Instaflow public landing — interactive behaviours (extracted from the design).
   All selectors are page-scoped; every block guards for missing nodes so the
   file is safe to load on any front page that doesn't use a given widget. */
(function () {
  /* reveal on scroll */
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.14 });
  document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });

  /* feature tabs */
  var tabs = document.querySelectorAll('.ftab'), panes = document.querySelectorAll('.fpane');
  var chatTimer = null;
  var chatScript = [
    { who: 'them', t: 'Hi! Do you ship to Canada? 🇨🇦' },
    { who: 'us', t: 'Hey! Yes — free shipping to Canada on orders over $50. Usually 3–5 days. 📦' },
    { who: 'them', t: 'Amazing. Is the spring set still in stock?' },
    { who: 'us', t: 'It is! Want me to drop the link right here so you can grab it? ✨' },
    { who: 'them', t: 'Yes please! 🙌' },
    { who: 'us', t: 'Done — link sent. Anything else, just ask!' }
  ];
  function runChat() {
    var box = document.getElementById('chatBox'); if (!box) return;
    box.innerHTML = ''; var i = 0;
    var add = function (m) { var d = document.createElement('div'); d.className = 'msg ' + m.who; if (m.who === 'us') d.style.background = 'linear-gradient(135deg,#833AB4,#E1306C)'; d.textContent = m.t; box.appendChild(d); box.scrollTop = box.scrollHeight; };
    var step = function () {
      if (i >= chatScript.length) { chatTimer = setTimeout(function () { box.innerHTML = ''; i = 0; step(); }, 2600); return; }
      var m = chatScript[i];
      if (m.who === 'us') {
        var typ = document.createElement('div');
        typ.className = 'msg them typing'; typ.innerHTML = '<span></span><span></span><span></span>';
        box.appendChild(typ);
        chatTimer = setTimeout(function () { typ.remove(); add(m); i++; chatTimer = setTimeout(step, 900); }, 900);
      } else { add(m); i++; chatTimer = setTimeout(step, 1100); }
    };
    step();
  }
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('on'); });
      panes.forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
      var f = t.dataset.f;
      var pane = document.querySelector('.fpane[data-pane="' + f + '"]');
      if (pane) pane.classList.add('on');
      if (chatTimer) { clearTimeout(chatTimer); chatTimer = null; }
      if (f === 'inbox') runChat();
      if (f === 'analytics') animateBars('#anaBars');
    });
  });

  /* prefill inbox thread so it never looks empty */
  (function () { var box = document.getElementById('chatBox'); if (!box) return; chatScript.forEach(function (m) { var d = document.createElement('div'); d.className = 'msg ' + m.who; if (m.who === 'us') d.style.background = 'linear-gradient(135deg,#833AB4,#E1306C)'; d.textContent = m.t; box.appendChild(d); }); })();

  /* live demo chat */
  var replies = [
    "Great question! Our team usually replies within minutes — but I can help right now. 😊",
    "Absolutely — I'll make a note and someone will follow up with the details. ✨",
    "Yes! That's included on the Pro plan. Want me to send you the link?",
    "Love it. I've saved your message — check your DMs for the next steps! 📩"
  ];
  function autoReply(v) {
    var s = v.toLowerCase();
    if (s.includes('ship') && s.includes('canada')) return "Yes! Free shipping to Canada on orders over $50 — usually lands in 3–5 days. 📦";
    if (s.includes('return')) return "Easy returns within 30 days, no questions asked. Want the returns portal link?";
    if (s.includes('stock')) return "The spring set is in stock ✨ Only a few left in size M — want me to hold one?";
    if (s.includes('ship')) return "Standard shipping is $4.90, free over $50. Express next-day is $12. 🚚";
    if (s.includes('price') || s.includes('cost') || s.includes('how much')) return "It's $39 — and code BLOOM26 gets you 20% off this week. 🌸";
    if (s.includes('hi') || s.includes('hey') || s.includes('hello')) return "Hey there! 👋 Ask me about shipping, stock, returns — anything.";
    return replies[Math.floor(Math.random() * replies.length)];
  }
  var lf = document.getElementById('liveForm'), li = document.getElementById('liveInput'), lc = document.getElementById('liveChat');
  if (lf && li && lc) {
    var bubble = function (t, who) { var d = document.createElement('div'); d.className = 'msg ' + who; if (who === 'us') d.style.background = 'linear-gradient(135deg,#833AB4,#E1306C)'; else { d.style.background = 'rgba(255,255,255,.1)'; d.style.color = '#fff'; } d.textContent = t; lc.appendChild(d); lc.scrollTop = lc.scrollHeight; };
    bubble('Hey 👋 send me a message — or tap a suggestion — and watch the auto-reply.', 'them');
    lf.addEventListener('submit', function (e) {
      e.preventDefault(); var v = li.value.trim(); if (!v) return;
      bubble(v, 'us'); li.value = '';
      var typ = document.createElement('div'); typ.className = 'msg them typing'; typ.style.background = 'rgba(255,255,255,.1)'; typ.innerHTML = '<span></span><span></span><span></span>'; lc.appendChild(typ); lc.scrollTop = lc.scrollHeight;
      setTimeout(function () { typ.remove(); bubble(autoReply(v), 'them'); }, 900);
    });
    document.querySelectorAll('.chip-q').forEach(function (c) { c.addEventListener('click', function () { li.value = c.textContent.trim(); lf.requestSubmit(); }); });
  }

  /* live counter */
  var dmC = document.getElementById('dmCount'); var dmN = 4218304;
  if (dmC) setInterval(function () { dmN += Math.floor(Math.random() * 3) + 1; dmC.textContent = dmN.toLocaleString('en-US'); }, 1400);

  /* count-up */
  var cio = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { count(e.target); cio.unobserve(e.target); } }); }, { threshold: 0.6 });
  document.querySelectorAll('.count').forEach(function (el) { cio.observe(el); });
  function count(el) {
    var to = parseFloat(el.dataset.to), dec = parseInt(el.dataset.dec || '0'), suf = el.dataset.suffix || '';
    var dur = 1200, t0 = performance.now();
    var tick = function (t) { var p = Math.min((t - t0) / dur, 1); p = 1 - Math.pow(1 - p, 3); el.textContent = (to * p).toFixed(dec) + suf; if (p < 1) requestAnimationFrame(tick); };
    requestAnimationFrame(tick);
  }

  /* bar grow */
  function animateBars(sel) { var bars = document.querySelectorAll(sel + ' .bar'); bars.forEach(function (b) { var h = b.style.height; b.style.height = '0%'; requestAnimationFrame(function () { setTimeout(function () { b.style.height = h; }, 40); }); }); }
  var heroBars = document.getElementById('heroBars');
  if (heroBars) { var bio = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { animateBars('#heroBars'); bio.unobserve(e.target); } }); }, { threshold: 0.4 }); bio.observe(heroBars); }

  /* how-it-works stepper */
  var stepEls = [].slice.call(document.querySelectorAll('#steps .step')), spanes = [].slice.call(document.querySelectorAll('.spane')), phTitle = document.getElementById('phTitle');
  var phNames = [];
  stepEls.forEach(function (s) { var t = s.querySelector('.font-semibold'); phNames.push(t ? t.textContent : ''); });
  var sTimer = null;
  function goStep(i) { stepEls.forEach(function (s, j) { s.classList.toggle('on', j === i); }); spanes.forEach(function (p, j) { p.classList.toggle('on', j === i); }); if (phTitle && phNames[i]) phTitle.textContent = phNames[i]; clearTimeout(sTimer); sTimer = setTimeout(function () { goStep((i + 1) % stepEls.length); }, 5000); }
  stepEls.forEach(function (s, i) { s.addEventListener('click', function () { goStep(i); }); });
  if (stepEls.length) goStep(0);
  document.querySelectorAll('.mini-tg').forEach(function (t) { t.addEventListener('click', function () { t.classList.toggle('on'); }); });

  /* testimonial carousel — reads content from the DOM-seeded first slide + data attrs */
  var tData = window.__IF_TESTIMONIALS__ || null;
  if (tData && tData.length) {
    var tavas = [].slice.call(document.querySelectorAll('.tava'));
    var tI = 0, tTimer = setInterval(function () { goT((tI + 1) % tData.length); }, 5500);
    var goT = function (i) {
      tI = i; var s = document.getElementById('tSlide'); if (!s) return;
      document.getElementById('tQuote').textContent = tData[i].q;
      document.getElementById('tName').textContent = tData[i].n;
      document.getElementById('tRole').textContent = tData[i].r;
      document.getElementById('tAvatar').textContent = tData[i].a;
      s.classList.remove('tslide'); void s.offsetWidth; s.classList.add('tslide');
      tavas.forEach(function (b, j) { b.classList.toggle('on', j === i); });
    };
    tavas.forEach(function (b, i) { b.addEventListener('click', function () { clearInterval(tTimer); tTimer = setInterval(function () { goT((tI + 1) % tData.length); }, 5500); goT(i); }); });
  }

  /* pricing toggle */
  var tg = document.getElementById('billToggle'), lblM = document.getElementById('lblM'), lblY = document.getElementById('lblY'), proNote = document.getElementById('proNote');
  if (tg) {
    var annual = false;
    tg.addEventListener('click', function () {
      annual = !annual; tg.classList.toggle('on', annual);
      lblM.classList.toggle('text-ink-900', !annual); lblM.classList.toggle('text-ink-500', annual);
      lblY.classList.toggle('text-ink-900', annual); lblY.classList.toggle('text-ink-500', !annual);
      document.querySelectorAll('.price').forEach(function (p) { p.textContent = annual ? p.dataset.y : p.dataset.m; });
      if (proNote) proNote.textContent = annual ? 'Billed annually · save 20%' : 'Billed monthly';
    });
  }
})();
