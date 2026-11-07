/* fq: upgraded FAQ behavior */
(function(){
  var RM=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
  var CATS=[['מחיר ותקציב',/עולה|מחיר|תקציב|עלות|₪|כסף|תשלום/],['זמנים ותוצאות',/זמן|מתי|תוצאות|כמה פעמים|מהר|הושעה/],['איך עובדים איתי',/אתם|אני צריך|להכין|בעצמי|מי |מחזיק|לבקש|מותר/]];
  [].forEach.call(document.querySelectorAll('.fq'),function(fq){
    var list=fq.querySelector('.faq-list'),items=[].slice.call(list.querySelectorAll('details.faq'));
    items.forEach(function(d,i){
      var s=d.querySelector('summary'),t=s.textContent.trim();d._t=t;
      s.innerHTML='<span class="q-n">'+(i<9?'0':'')+(i+1)+'</span><span class="q-t"></span><span class="q-i" aria-hidden="true"></span>';
      s.querySelector('.q-t').textContent=t;
      d._c=[];CATS.forEach(function(c,k){if(c[1].test(t))d._c.push(k)});
      var a=d.querySelector('.ans');
      s.addEventListener('click',function(e){
        if(RM||!a.animate)return;e.preventDefault();
        if(d.open){var h=a.offsetHeight;var an=a.animate([{height:h+'px',opacity:1},{height:'0px',opacity:0}],{duration:240,easing:'ease'});an.onfinish=function(){d.open=false};}
        else{d.open=true;var h2=a.offsetHeight;a.animate([{height:'0px',opacity:0},{height:h2+'px',opacity:1}],{duration:300,easing:'cubic-bezier(.22,.61,.36,1)'})}
      });
    });
    var tools=document.createElement('div');tools.className='fq-tools';
    var chips=CATS.map(function(c,k){return items.filter(function(d){return d._c.indexOf(k)>-1}).length>=2?'<button type="button" class="fq-chip" data-k="'+k+'" aria-pressed="false">'+c[0]+'</button>':''}).join('');
    tools.innerHTML='<label class="fq-search"><span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">חיפוש שאלה</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" placeholder="חפשו שאלה, למשל: כמה זה עולה"></label>'+(chips?'<div class="fq-chips" role="group" aria-label="סינון לפי נושא"><button type="button" class="fq-chip" data-k="-1" aria-pressed="true">הכל</button>'+chips+'</div>':'');
    list.parentNode.insertBefore(tools,list);
    var empty=document.createElement('div');empty.className='fq-empty';empty.hidden=true;list.parentNode.insertBefore(empty,list.nextSibling);
    var inp=tools.querySelector('input'),cat=-1;
    function filter(){
      var q=inp.value.trim(),n=0;
      items.forEach(function(d){var ok=(cat<0||d._c.indexOf(cat)>-1)&&(!q||(d._t+' '+d.querySelector('.ans').textContent).indexOf(q)>-1);d.hidden=!ok;if(ok)n++});
      empty.hidden=n>0;
      if(!n)empty.innerHTML='לא מצאתי שאלה כזאת כאן. <a href="https://wa.me/972508249824?text='+encodeURIComponent('היי משה, יש לי שאלה: '+q)+'" target="_blank" rel="noopener">שלחו לי אותה בוואטסאפ</a> ואענה אישית.';
    }
    inp.addEventListener('input',filter);
    [].forEach.call(tools.querySelectorAll('.fq-chip'),function(b){b.addEventListener('click',function(){cat=+b.getAttribute('data-k');[].forEach.call(tools.querySelectorAll('.fq-chip'),function(x){x.setAttribute('aria-pressed',x===b?'true':'false')});filter()})});
  });
})();
