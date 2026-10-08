(function(){var d=document.documentElement;if(matchMedia('(prefers-reduced-motion: reduce)').matches){d.classList.add('no-up');return;}d.classList.add('up-js');
var io=('IntersectionObserver' in window)?new IntersectionObserver(function(es){es.forEach(function(e){if(!e.isIntersecting)return;var t=e.target;t.classList.add('up-in','in');
 if(t.classList.contains('prose')){[].forEach.call(t.querySelectorAll('p'),function(p,i){p.style.transitionDelay=Math.min(i,6)*140+'ms';});}
 io.unobserve(t);});},{threshold:.25}):null;
document.querySelectorAll('.problem-sec .prose').forEach(function(pr,k){pr.querySelectorAll(':scope > p').forEach(function(p){var t=p.textContent.trim();if(k===0&&t.length<=48&&!p.querySelector('a')){p.classList.add('up-line');if(/\?$/.test(t))p.classList.add('q');}});});
var tg=document.querySelectorAll('.problem-sec .prose,.problem-sec .callout,.sg-card,.adv');
if(io)tg.forEach(function(e){io.observe(e)});else tg.forEach(function(e){e.classList.add('up-in','in')});
['.srv-grid','.advs'].forEach(function(s){document.querySelectorAll(s).forEach(function(g){[].forEach.call(g.children,function(c,i){c.style.setProperty('--i',i%4);});});});
document.querySelectorAll('.process-sec .timeline').forEach(function(tl){if(tl.id==='cxTl')return;var steps=tl.querySelectorAll('.tl-step');
 function f(){var r=tl.getBoundingClientRect(),p=(innerHeight*.6-r.top)/r.height;p=Math.max(0,Math.min(1,p));tl.style.setProperty('--fill',p*100+'%');
  steps.forEach(function(s){s.classList.toggle('lit',s.getBoundingClientRect().top<innerHeight*.6);});}
 addEventListener('scroll',f,{passive:true});f();});
})();
