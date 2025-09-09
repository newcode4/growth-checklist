(function(){
  const AJAX = GC3_DATA.ajax;
  const QA = JSON.parse(document.getElementById('gc3-questions').textContent);
  const els = {
    sec: document.getElementById('gc-sections'),
    bonus: document.getElementById('gc-bonus'),
    bar: document.getElementById('sd-bar'),
    count: document.getElementById('sd-count'),
    hint: document.getElementById('sd-hint'),
    submit: document.getElementById('sd-submit'),
    bonusVal: document.getElementById('sd-bonusVal')
  };

  // 트래킹(세션 1회)
  try{
    if(!sessionStorage.getItem('gc3_seen')){
      sessionStorage.setItem('gc3_seen','1');
      const fd=new FormData(); fd.append('action','gc3_track_view'); fd.append('form',GC3_DATA.form);
      fetch(AJAX,{method:'POST',body:fd});
    }
  }catch(_){}

  // 렌더
  els.sec.innerHTML = (QA.sections||[]).map((sec,si)=>{
    const items = sec.items.map((it,ii)=>{
      const human = (si+1)+'-'+(ii+1);
      return `
        <div class="sd-row" id="row-${it.id}" data-human="${human}">
          <div><div class="sd-q">${it.q}</div><div class="sd-crit">${it.crit||''}</div></div>
          <div class="sd-opts">
            <label class="sd-opt"><input type="radio" name="${it.id}" value="3" required>예</label>
            <label class="sd-opt"><input type="radio" name="${it.id}" value="1">약간</label>
            <label class="sd-opt"><input type="radio" name="${it.id}" value="0">아니오</label>
          </div>
        </div>`;
    }).join('');
    return `<div class="sd-card"><div class="sd-pill">${sec.title}</div>${items}</div>`;
  }).join('');
  els.bonus.innerHTML = (QA.bonus||[]).map(b=>`
    <label class="sd-opt"><input type="checkbox" class="gc-b" data-id="${b.id}"> ${b.label}</label>
  `).join('');

  const ids=[]; (QA.sections||[]).forEach(sec=>sec.items.forEach(it=>ids.push(it.id)));

  function calc(){
    let answered=0, base=0;
    ids.forEach(id=>{const el=document.querySelector(`input[name="${id}"]:checked`); if(el){answered++; base+=Number(el.value||0);} });
    let bonus=0; document.querySelectorAll('.gc-b:checked').forEach(()=>bonus++);
    if(bonus>5) bonus=5;
    const total=base+bonus, pct=Math.round((answered/ids.length)*100);
    els.bar.style.width=pct+'%'; els.count.textContent=answered; els.hint.textContent= answered<ids.length?'응답 중':'제출 가능';
    els.bonusVal.textContent=bonus;
    const band = (total<=15)?'위험 단계':(total<=30?'성장 정체 단계':'성장 가속 단계');
    return {
      total, band,
      answers: ids.reduce((o,id)=>{const v=document.querySelector(`input[name="${id}"]:checked`); o[id]=v?Number(v.value):null; return o;},{}),
      bonusMap: Array.from(document.querySelectorAll('.gc-b')).reduce((o,c)=>{o[c.dataset.id]=c.checked?1:0; return o;}, {})
    };
  }
  document.getElementById('gc3-wrap').addEventListener('change',calc);
  calc();

  els.submit.addEventListener('click',()=>{
    const missing=[];
    ids.forEach(id=>{
      if(!document.querySelector(`input[name="${id}"]:checked`)){
        const row=document.getElementById('row-'+id);
        if(row) missing.push(row.getAttribute('data-human'));
      }
    });
    if(missing.length){
      const firstId=ids.find(id=>!document.querySelector(`input[name="${id}"]:checked`));
      const row=document.getElementById('row-'+firstId);
      row.classList.add('sd-miss'); row.scrollIntoView({behavior:'smooth',block:'center'});
      setTimeout(()=>row.classList.remove('sd-miss'),2000);
      alert('다음 문항이 비어 있습니다:\n- '+missing.join('\n- '));
      return;
    }

    const s=calc();
    els.submit.disabled=true; els.submit.textContent='제출 중…';
    const fd=new FormData();
    fd.append('action','gc_submit');
    fd.append('form', GC3_DATA.form);
    fd.append('score',s.total);
    fd.append('band',s.band);
    fd.append('answers', JSON.stringify({answers:s.answers, bonus:s.bonusMap}));

    const slow=setTimeout(()=>{ els.hint.textContent='서버 응답 지연 중… 잠시만요.'; }, 2500);

    fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(resp=>{
      clearTimeout(slow);
      if(!resp.success){ alert(resp.data && resp.data.msg ? resp.data.msg : '제출 실패'); els.submit.disabled=false; els.submit.textContent='결과 제출'; return; }
      window.location.href = resp.data.redirect;
    }).catch(()=>{ clearTimeout(slow); alert('네트워크 오류'); els.submit.disabled=false; els.submit.textContent='결과 제출'; });
  });
})();
