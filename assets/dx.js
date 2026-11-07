/* dx: guided diagnosis. Markup: <div class="dx" data-dx><script type="application/json">{...}</script></div> */
(function(){
  var reduce=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
  var IC={
    ok:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
    bad:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>',
    back:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>',
    wa:'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24z"/></svg>',
    tel:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
  };
  var L=['א','ב','ג','ד'];
  function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
  [].forEach.call(document.querySelectorAll('[data-dx]'),function(root){
    var D;try{D=JSON.parse(root.querySelector('script[type="application/json"]').textContent)}catch(e){return}
    var n=D.qs.length,ans=[],cur=0;
    root.innerHTML=
      '<div class="dx-q"><div class="dx-top"><div class="dx-steps" aria-hidden="true">'+D.qs.map(function(){return'<i></i>'}).join('')+'</div><span class="dx-count" aria-live="polite"></span></div>'+
      '<div class="dx-stage"><div class="dx-card"></div></div>'+
      '<div class="dx-nav"><button type="button" class="dx-back" hidden>'+IC.back+'שאלה קודמת</button><span class="dx-hint">בלי הרשמה ובלי מייל</span></div></div>'+
      '<aside class="dx-r" aria-label="תמונת המצב שלכם"><div class="dx-rh"><span class="dot" aria-hidden="true"></span>תמונת מצב · '+esc(D.topic)+'</div>'+
      '<p class="dx-rt">'+esc(D.rtitle||'כאן נבנית תמונת המצב שלכם')+'</p>'+
      '<div class="dx-meter" aria-hidden="true">'+D.qs.map(function(){return'<i></i>'}).join('')+'</div><ul class="dx-list"></ul></aside>';
    var card=root.querySelector('.dx-card'),steps=root.querySelectorAll('.dx-steps i'),meter=root.querySelectorAll('.dx-meter i'),
        count=root.querySelector('.dx-count'),back=root.querySelector('.dx-back'),hint=root.querySelector('.dx-hint'),list=root.querySelector('.dx-list'),rt=root.querySelector('.dx-rt');
    function swap(html,after){
      if(reduce){card.innerHTML=html;after&&after();return}
      card.classList.add('out');
      setTimeout(function(){card.innerHTML=html;card.classList.remove('out');card.classList.add('in');void card.offsetWidth;card.classList.remove('in');after&&after()},260);
    }
    function report(){
      var h='';
      ans.forEach(function(w,i){if(w==null)return;var q=D.qs[i];
        h+= w===0 ? '<li class="ok">'+IC.ok+'<div><b>'+esc(q.ok)+'</b></div></li>'
                  : '<li class="w'+w+'">'+IC.bad+'<div><b>'+esc(q.f)+'</b><span>'+esc(q.fix)+'</span></div></li>';
      });
      list.innerHTML=h||'<li class="dx-empty" style="display:block">ענו על השאלה הראשונה, וכאן יתחיל להיבנות הדוח שלכם: מה עובד, מה דולף, ומה הייתי מטפל בו ראשון.</li>';
      [].forEach.call(meter,function(m,i){m.className=ans[i]==null?'':'w'+ans[i]});
    }
    function show(i,first){
      cur=i;
      [].forEach.call(steps,function(s,k){s.className=k<=i?'on':''});
      count.textContent='שאלה '+(i+1)+' מתוך '+n;
      back.hidden=i===0;hint.hidden=i!==0;
      var q=D.qs[i];
      var html='<h3>'+esc(q.q)+'</h3><div class="dx-opts" role="group" aria-label="'+esc(q.q)+'">'+
        q.o.map(function(o,k){return'<button type="button" class="dx-opt'+(ans[i]===o[1]&&ans[i]!=null&&ansIdx[i]===k?' sel':'')+'" data-w="'+o[1]+'" data-k="'+k+'"><span class="k">'+L[k]+'</span>'+esc(o[0])+'</button>'}).join('')+'</div>';
      first?(card.innerHTML=html):swap(html);
    }
    var ansIdx=[];
    card.addEventListener('click',function(e){
      var b=e.target.closest('.dx-opt');if(!b)return;
      [].forEach.call(card.querySelectorAll('.dx-opt'),function(x){x.classList.remove('sel')});b.classList.add('sel');
      ans[cur]=+b.getAttribute('data-w');ansIdx[cur]=+b.getAttribute('data-k');report();
      setTimeout(function(){cur<n-1?show(cur+1):result()},reduce?0:240);
    });
    back.addEventListener('click',function(){if(cur>0)show(cur-1)});
    function result(){
      var tot=ans.reduce(function(a,b){return a+b},0),lv=D.lv[D.lv.length-1];
      for(var k=0;k<D.lv.length;k++){if(tot<=D.lv[k][0]){lv=D.lv[k];break}}
      var gaps=ans.map(function(w,i){return{w:w,i:i}}).filter(function(x){return x.w>0}).sort(function(a,b){return b.w-a.w||a.i-b.i}).slice(0,3);
      var first=gaps.map(function(g){return'<li>'+esc(D.qs[g.i].fix)+'</li>'}).join('');
      var msg='היי משה, עשיתי את האבחון באתר ('+D.topic+').\nיצא לי: '+lv[1]+'\n'+(gaps.length?'הפערים שעלו:\n'+gaps.map(function(g){return'- '+D.qs[g.i].f}).join('\n')+'\n':'')+'אשמח לדבר.';
      var wa='https://wa.me/972508249824?text='+encodeURIComponent(msg);
      [].forEach.call(steps,function(s){s.className='on'});
      count.textContent='האבחון הושלם';root.classList.add('done');back.hidden=true;hint.hidden=true;
      rt.textContent=gaps.length?'נמצאו '+(gaps.length===1?'פער אחד':gaps.length+' פערים')+' שכדאי לטפל בהם':'לא נמצאו פערים משמעותיים';
      swap('<div class="dx-res"><span class="lbl">התוצאה שלכם</span><h3>'+esc(lv[1])+'</h3><p>'+esc(lv[2])+'</p>'+
        (first?'<span class="lbl">מה הייתי עושה ראשון</span><ol class="dx-first">'+first+'</ol>':'')+
        '<div class="dx-ctas"><a class="wa" href="'+wa+'" target="_blank" rel="noopener">'+IC.wa+'שלחו לי את התוצאה בוואטסאפ</a><a class="tel" href="tel:0508249824">'+IC.tel+'050-824-9824</a></div>'+
        '<button type="button" class="dx-again">לעשות את האבחון מחדש</button></div>',function(){
          card.querySelector('.dx-again').addEventListener('click',function(){ans=[];ansIdx=[];root.classList.remove('done');report();rt.textContent=D.rtitle||'כאן נבנית תמונת המצב שלכם';show(0)});
        });
    }
    report();show(0,true);
  });
})();
