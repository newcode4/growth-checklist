(function($){
  const ROOT = $('#gc-root');
  const QA = GC_VARS.questions || {};
  const IS_LOGIN = !!GC_VARS.is_logged_in;

  function bandOf(total){
    if(total<=15) return {key:'위험 단계', cls:'bad',
      msg:'근본 원인 점검이 필요합니다. 35분 무료 컨설팅으로 핵심 병목을 즉시 짚어드립니다.',
      hint:'핵심 구조 재설계'};
    if(total<=30) return {key:'성장 정체 단계', cls:'mid',
      msg:'퍼널 병목을 2주 스프린트로 교정합시다. 컨설팅에서 우선순위를 정리해드립니다.',
      hint:'병목 교정 & 우선순위'};
    return {key:'성장 가속 단계', cls:'good',
      msg:'레버리지 실험(가격·패키지·보증·리퍼럴)로 성장을 가속하세요.',
      hint:'실험 설계 & 확장'};
  }

  function renderApp(){
    const secHtml = (QA.sections||[]).map(sec=>{
      const items = sec.items.map(it=>{
        return `
        <div class="sd-row" data-id="${it.id}">
          <div>
            <div class="sd-q">${it.q}</div>
            <div class="sd-crit">${it.crit||''}</div>
          </div>
          <div class="sd-opts">
            <label class="sd-opt"><input type="radio" name="${it.id}" value="3">예</label>
            <label class="sd-opt"><input type="radio" name="${it.id}" value="1">부분적으로</label>
            <label class="sd-opt"><input type="radio" name="${it.id}" value="0">아니오</label>
          </div>
        </div>`;
      }).join('');
      return `<div class="sd-card sd-sec"><div class="sd-pill">${sec.title}</div>${items}</div>`;
    }).join('');

    const bonus = (QA.bonus||[]).map(b=>`
      <label class="sd-opt">
        <input type="checkbox" class="gc-bonus" data-id="${b.id}"> ${b.label}
      </label>
    `).join('');

    const html = `
      <div class="sd-card">
        <h2 class="sd-h">홈페이지 진단 체크리스트: 당신의 비즈니스는 지금 몇 점인가요?</h2>
        <p class="sd-sub"><b>예(3점)</b> · <b>부분적으로(1점)</b> · <b>아니오(0점)</b> — 총 45점 + 보너스 5점 = <b>50점</b></p>
        <div class="sd-grid cols-2">
          <div class="sd-kpis">
            <div class="sd-k"><span>총점</span><span id="sd-totalLabel" class="sd-badge">— / 50</span></div>
            <div class="sd-meter"><span id="sd-totalBar"></span></div>
            <div class="sd-k"><span id="sd-bandLabel">상태: —</span><span id="sd-hint" class="sd-badge">가이드 대기</span></div>
          </div>
          <div>
            <div class="sd-pill">채점 기준</div>
            <ul class="sd-sub" style="margin:8px 0 0 18px; line-height:1.6">
              <li>예: 3점 — 확실히 적용/작동</li>
              <li>부분적으로: 1점 — 일부 시도, 지속 실행은 아님</li>
              <li>아니오: 0점 — 미비/미적용</li>
            </ul>
            <div class="sd-small">구간: 0–15 위험 · 16–30 성장 정체 · 31–50 성장 가속</div>
          </div>
        </div>
      </div>

      ${secHtml}

      <div class="sd-card">
        <div class="sd-pill">보너스 (최대 5점)</div>
        <div class="sd-crit" style="margin:8px 0 10px">
          최근 30일 기준, 아래 중 해당하는 것에 체크하세요. <b>(각 1점)</b>
        </div>
        <div class="sd-opts">${bonus}</div>
        <div class="sd-small" style="margin-top:8px">보너스: <b><span id="sd-bonusVal">0</span>/5</b></div>
      </div>

      <div class="sd-card">
        <h3 class="sd-res-title">진단 결과</h3>
        <p class="sd-res-text" id="sd-result">문항을 선택하면 결과가 표시됩니다.</p>
        <div class="sd-cta">
          <a class="sd-btn" id="sd-cta" href="#signup">35분 무료 컨설팅 예약</a>
          <button class="sd-btn secondary" id="sd-share">링크 공유</button>
          <button class="sd-btn secondary" id="sd-copy">결과 텍스트 복사</button>
        </div>
        <div class="sd-note">* 결과는 제출 시 저장·메일 발송되며, 공유 링크를 통해 다시 확인할 수 있습니다.</div>
      </div>

      <div class="sd-card" id="signup">
        <div class="sd-pill">무료 회원가입 · 결과 저장 & 맞춤 가이드 받기</div>
        ${!IS_LOGIN ? '<div class="sd-small" style="color:#b91c1c">로그인 후 제출 가능</div>' : ''}
        <form class="sd-form" id="gc-form">
          <input type="text" name="name" placeholder="이름" ${!IS_LOGIN?'disabled':''} required>
          <input type="email" name="email" placeholder="이메일" ${!IS_LOGIN?'disabled':''} required>
          <button class="sd-btn" id="gc-submit" ${!IS_LOGIN?'disabled':''}>회원가입/로그인 상태에서 결과 제출</button>
          <div class="sd-small">WP-Members 로그인 상태에서 제출 시 결과 메일이 발송됩니다.</div>
        </form>
      </div>
    `;
    ROOT.html(html);
  }

  function calc(){
    const ids = [];
    (GC_VARS.questions.sections||[]).forEach(sec=>{
      sec.items.forEach(it=>ids.push(it.id));
    });
    let base = 0;
    ids.forEach(id=>{
      const v = Number($(`input[name="${id}"]:checked`).val()||'NaN');
      if(!isNaN(v)) base += v;
    });
    let bonus = 0;
    $('.gc-bonus:checked').each(function(){ bonus += 1; });
    if(bonus>5) bonus = 5;
    const total = base + bonus;
    const pct = Math.round((total/50)*100);
    $('#sd-totalBar').css('width', pct+'%');
    $('#sd-totalLabel').text(`${total} / 50`);
    const band = bandOf(total);
    $('#sd-bandLabel').html(`상태: <b class="sd-band ${band.cls}">${band.key}</b>`);
    $('#sd-hint').text(band.hint);
    $('#sd-result').html(`<b>총점:</b> ${total}점 (기본 ${base}/45 + 보너스 ${bonus}/5)<br><b>구간:</b> ${band.key}<br><br>${band.msg}`);
    $('#sd-bonusVal').text(bonus);
    return {base, bonus, total, band:band.key};
  }

  function bind(){
    ROOT.on('change','input[type="radio"], .gc-bonus', calc);

    ROOT.on('click','#sd-copy', function(){
      const s = calc();
      const txt = `홈페이지 진단 결과\n총점: ${s.total}/50 (기본 ${s.base}/45 + 보너스 ${s.bonus}/5)\n구간: ${s.band}`;
      navigator.clipboard.writeText(txt);
      $('#sd-hint').text('결과 텍스트 복사 완료');
    });

    ROOT.on('click','#sd-share', async function(){
      // 제출이 선행되어야 공유 링크가 생성됨 → 제출 트리거 유도
      alert('공유 링크는 결과 제출 후 생성됩니다. 아래에서 제출을 완료하세요.');
    });

    ROOT.on('click','#gc-submit', function(e){
      e.preventDefault();
      if(!IS_LOGIN){ alert('로그인/회원가입 후 제출 가능합니다.'); return; }
      const s = calc();
      const name = ROOT.find('input[name="name"]').val().trim();
      const email= ROOT.find('input[name="email"]').val().trim();
      if(!name || !email){ alert('이름/이메일을 입력하세요.'); return; }

      const answers = {};
      (GC_VARS.questions.sections||[]).forEach(sec=>{
        sec.items.forEach(it=>{
          const val = Number($(`input[name="${it.id}"]:checked`).val()||'');
          answers[it.id] = isNaN(val) ? null : val;
        });
      });
      // 보너스
      const b = {};
      $('.gc-bonus').each(function(){ b[$(this).data('id')] = $(this).is(':checked') ? 1 : 0; });

      $.post(GC_VARS.ajax, {
        action: 'gc_submit',
        nonce: GC_VARS.nonce,
        name, email,
        score: s.total,
        band: s.band,
        answers: JSON.stringify({answers, bonus:b})
      }, function(resp){
        if(resp && resp.success){
          const url = resp.data.share_url;
          $('#sd-hint').text('제출 완료 · 이메일 발송됨');
          // 공유 버튼 활성화
          ROOT.off('click','#sd-share').on('click','#sd-share', function(){
            navigator.clipboard.writeText(url);
            $('#sd-hint').text('공유 링크가 복사되었습니다');
          });
          alert('제출 완료! 공유 링크가 복사 버튼에 연결되었습니다.');
        } else {
          alert('제출 중 문제가 발생했습니다.');
        }
      });
    });
  }

  function boot(){
    renderApp();
    bind();
  }

  $(boot);
})(jQuery);
