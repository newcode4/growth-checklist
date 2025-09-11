// public/js/results.js (교체)
(function(){
  const btn = document.getElementById('gc-consult-btn');
  const form = document.getElementById('gc-consult');
  const hint = document.getElementById('gc-hint');
  const srcSel = document.getElementById('gc-source');
  const srcOther = document.getElementById('gc-source-other');

  // 기타 선택 시 텍스트 필드 노출
  if (srcSel) {
    srcSel.addEventListener('change', () => {
      srcOther.style.display = (srcSel.value === 'other') ? 'block' : 'none';
      if (srcSel.value !== 'other') srcOther.value = '';
    });
  }

  function isValidURL(v){
    try { const u=new URL(v); return ['http:','https:'].includes(u.protocol); } catch(_){ return false; }
  }

  form.addEventListener('submit', (e)=>{
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('action','gc_consult_signup');
    fd.append('ref', GC3_RESULTS.ref);

    // 010 휴대폰 검증
    const ph = (fd.get('phone')||'').trim();
    if(!/^010\d{8}$/.test(ph)){ alert('휴대폰은 010으로 시작하는 숫자 11자리(하이픈 없이)로 입력해 주세요.'); return; }

    // 필수 URL/텍스트 검증
    const siteUrl = (fd.get('site_url')||'').trim();
    if(!isValidURL(siteUrl)){ alert('홈페이지 URL을 정확히 입력해 주세요. (예: https://example.com)'); return; }

    const company = (fd.get('company_name')||'').trim();
    if(!company){ alert('회사 상호를 입력해 주세요.'); return; }

    const industry = (fd.get('industry')||'').trim();
    if(!industry){ alert('업종을 선택해 주세요.'); return; }

    const employees = (fd.get('employees')||'').trim();
    if(!employees){ alert('직원 수를 선택해 주세요.'); return; }

    const cofounder = (fd.get('cofounder')||'').trim();
    if(!cofounder){ alert('공동대표 유무를 선택해 주세요.'); return; }

    const age = (fd.get('company_age')||'').trim();
    if(!age){ alert('회사 연차를 선택해 주세요.'); return; }

    const source = (fd.get('source')||'').trim();
    if(!source){ alert('유입 경로를 선택해 주세요.'); return; }
    if(source==='other'){
      const other = (fd.get('source_other')||'').trim();
      if(other.length < 2){ alert('유입 경로의 기타 내용을 간단히 적어 주세요.'); return; }
    }

    const companyUrl = (fd.get('company_url')||'').trim();
    if(companyUrl && !isValidURL(companyUrl)){ alert('회사/서비스 추가 URL 형식을 확인해 주세요.'); return; }

    btn.disabled = true; btn.textContent='예약 처리 중…';
    fetch(GC3_RESULTS.ajax,{method:'POST',body:fd})
      .then(r=>r.json()).then(resp=>{
        if(!resp.success){
          alert(resp.data && resp.data.msg ? resp.data.msg : '제출 실패');
          btn.disabled=false; btn.textContent='30분 무료 진단 콜 예약'; return;
        }
        window.location.href = resp.data.redirect;
      }).catch(()=>{
        alert('네트워크 오류');
        btn.disabled=false; btn.textContent='30분 무료 진단 콜 예약';
      });
  });
})();
