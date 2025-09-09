(function(){
  const btn=document.getElementById('gc-consult-btn');
  const form=document.getElementById('gc-consult');
  const hint=document.getElementById('gc-hint');
  form.addEventListener('submit',()=>{
  const fd=new FormData(form);
  fd.append('action','gc_consult_signup');
  fd.append('ref', GC3_RESULTS.ref);
  // 클라이언트 측 010 검증(서버에서도 재검증)
  const ph=fd.get('phone')||'';
  if(!/^010\d{8}$/.test(ph)){ alert('휴대폰은 010으로 시작하는 숫자 11자리(하이픈 없이)로 입력해 주세요.'); return; }
  btn.disabled=true; btn.textContent='예약 처리 중…';
  fetch(GC3_RESULTS.ajax,{method:'POST',body:fd}) 
      .then(r=>r.json()).then(resp=>{
        if(!resp.success){ alert(resp.data && resp.data.msg ? resp.data.msg : '제출 실패'); btn.disabled=false; btn.textContent='15분 무료 진단 콜 예약'; return; }
        window.location.href = resp.data.redirect;
      }).catch(()=>{ alert('네트워크 오류'); btn.disabled=false; btn.textContent='15분 무료 진단 콜 예약'; });
  });
})();
