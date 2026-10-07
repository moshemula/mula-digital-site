/* Mula motion v1: count-up, bars, funnel, before/after */
(function(){
var reduce=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
function onView(el,fn,th){if(!('IntersectionObserver' in window)){fn();return;}var io=new IntersectionObserver(function(e){if(e[0].isIntersecting){io.disconnect();fn();}},{threshold:th||.35});io.observe(el);}
// 1. count-up
var sel='.er-kpis b,.mh-num,.ms-num,.ss-num,.sh-ranks b,.hx-metric b,.sh-tot b,.sh-adstats b,.dr-ranks b,.metric-sm .num,.tz-results b';
document.querySelectorAll(sel).forEach(function(el){
  if(el.children.length)return;var txt=el.textContent,m=txt.match(/\d[\d,]*(\.\d+)?/);if(!m)return;
  var raw=m[0],val=parseFloat(raw.replace(/,/g,'')),dec=(m[1]||'').length-(m[1]?1:0),comma=raw.indexOf(',')>-1,pre=txt.slice(0,m.index),post=txt.slice(m.index+raw.length);
  if(!isFinite(val)||val===0||/^\s*(19|20)\d{2}\s*$/.test(txt))return;
  function fmt(v){var s=dec?v.toFixed(dec):Math.round(v).toString();if(comma){var p=s.split('.');p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g,',');s=p.join('.');}return pre+s+post;}
  if(reduce)return;el.style.fontVariantNumeric='tabular-nums';el.textContent=fmt(0);
  onView(el,function(){var t0=null,d=Math.min(1800,900+String(Math.round(val)).length*150);
    function f(t){if(!t0)t0=t;var k=Math.min(1,(t-t0)/d),e=1-Math.pow(1-k,3);el.textContent=fmt(val*e);if(k<1)requestAnimationFrame(f);else el.textContent=txt;}
    requestAnimationFrame(f);},.6);
});
// 2. bars (Shai CPL)
document.querySelectorAll('.sh-bars').forEach(function(w){var sp=w.querySelectorAll('.sh-bar span');if(reduce)return;
  sp.forEach(function(s){s.dataset.h=s.style.height;s.style.height='0';s.style.transition='height 1.3s cubic-bezier(.2,.8,.2,1)';});
  onView(w,function(){sp.forEach(function(s,i){setTimeout(function(){s.style.height=s.dataset.h;},i*350);});});});
// 3. funnel stagger
document.querySelectorAll('.er-flow').forEach(function(ol){ol.classList.add('mo-flow');var li=ol.querySelectorAll('li');li.forEach(function(x,i){x.style.transitionDelay=(i*0.14)+'s';});
  var line=document.createElement('div');line.className='mo-flow-line';line.innerHTML='<i></i>';ol.parentNode.insertBefore(line,ol.nextSibling);
  if(reduce){ol.classList.add('mo-in');line.classList.add('mo-in');return;}
  onView(ol,function(){ol.classList.add('mo-in');setTimeout(function(){line.classList.add('mo-in');},300);},.25);});
// 4. goal bars
document.querySelectorAll('.mo-goal').forEach(function(g){var bars=g.querySelectorAll('.mo-goal-bar i');function go(){bars.forEach(function(b){b.style.width=b.dataset.w;});}
  if(reduce){go();return;}onView(g,go,.5);});
// 5. before/after slider
document.querySelectorAll('.mo-ba').forEach(function(b){var r=b.querySelector('input');function set(v){b.style.setProperty('--p',v+'%');}
  r.addEventListener('input',function(){set(r.value);});set(r.value);
  if(!reduce)onView(b,function(){var t0=null;function f(t){if(!t0)t0=t;var k=(t-t0)/1600;if(k>1)k=1;var v=50+Math.sin(k*Math.PI*2)*28*(1-k);r.value=v;set(v);if(k<1)requestAnimationFrame(f);}requestAnimationFrame(f);},.5);});
})();
