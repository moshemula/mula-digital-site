(function(){var r=document.getElementById('hubDemo');if(!r)return;
var svg=r.querySelector('.hubd-svg'),stage=r.querySelector('.hubd-stage'),vs=[].slice.call(r.querySelectorAll('.hubd-n.v')),own=r.querySelector('.hubd-n.own'),hub=r.querySelector('.hubd-n.hub'),NS='http://www.w3.org/2000/svg',lines=[],main,k=0,anim,timer;
var red=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
function c(el){var a=stage.getBoundingClientRect(),b=el.getBoundingClientRect();return[(b.left+b.width/2-a.left)/a.width*600,(b.top+b.height/2-a.top)/a.height*400];}
function build(){svg.innerHTML='';lines=vs.map(function(){var l=document.createElementNS(NS,'line');svg.appendChild(l);return l;});main=document.createElementNS(NS,'line');main.setAttribute('stroke','url(#g)');main.setAttribute('stroke-width','4');var d=document.createElementNS(NS,'defs');d.innerHTML='<linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E8003D"/><stop offset="1" stop-color="#7B2FBE"/></linearGradient>';svg.appendChild(d);svg.appendChild(main);draw(k);}
function mix(a,b,t){return a+(b-a)*t;}
function draw(t){var o=c(own),h=c(hub);vs.forEach(function(v,i){var p=c(v),l=lines[i],tx=mix(o[0]+(i-2.5)*14,h[0],t),ty=mix(o[1],h[1],t);
 l.setAttribute('x1',p[0]);l.setAttribute('y1',p[1]);l.setAttribute('x2',tx);l.setAttribute('y2',ty);
 l.setAttribute('stroke',t>.5?'#7B2FBE':'#E8003D');l.setAttribute('stroke-opacity',mix(.55,.35,t));l.setAttribute('stroke-dasharray',t>.5?'0':'6 6');});
 main.setAttribute('x1',h[0]);main.setAttribute('y1',h[1]);main.setAttribute('x2',mix(h[0],o[0],t));main.setAttribute('y2',mix(h[1],o[1],t));main.setAttribute('opacity',t);}
function go(to){cancelAnimationFrame(anim);r.classList.toggle('after',to===1);r.querySelectorAll('.hubd-tabs button').forEach(function(b){b.classList.toggle('on',+b.dataset.s===to)});
 if(red){k=to;draw(k);return;}var from=k,t0=null;function f(t){if(!t0)t0=t;var p=Math.min(1,(t-t0)/1100),e=p<.5?4*p*p*p:1-Math.pow(-2*p+2,3)/2;k=mix(from,to,e);draw(k);if(p<1)anim=requestAnimationFrame(f);}anim=requestAnimationFrame(f);}
r.querySelectorAll('.hubd-tabs button').forEach(function(b){b.addEventListener('click',function(){clearTimeout(timer);go(+b.dataset.s);});});
build();window.addEventListener('resize',function(){draw(k);});
if('IntersectionObserver' in window){var io=new IntersectionObserver(function(e){if(e[0].isIntersecting){io.disconnect();timer=setTimeout(function(){go(1)},1600);}},{threshold:.4});io.observe(r);}else go(1);
})();

/* v2: hard-questions stage, hero bubble, timeline fill */
(function(){
var red=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
var root=document.getElementById('cxQ');
var items=[].slice.call(document.querySelectorAll('.cxq-list li')).map(function(li){return{q:li.textContent.trim(),who:li.getAttribute('data-who'),why:li.getAttribute('data-why')};});
if(root&&items.length){
 var who=root.querySelector('.cxq-who'),txt=root.querySelector('.cxq-text'),why=root.querySelector('.cxq-why'),nav=root.querySelector('.cxq-nav'),i=0,tt,nx,btns=[],DUR=7000,started=false;
 items.forEach(function(it,k){var b=document.createElement('button');b.type='button';b.setAttribute('aria-label','שאלה '+(k+1));b.innerHTML='<i></i>';b.style.setProperty('--dur',DUR/1000+'s');b.addEventListener('click',function(){show(k);});nav.appendChild(b);btns.push(b);});
 function show(k){clearTimeout(tt);clearTimeout(nx);i=k;var it=items[k];who.textContent=it.who;why.textContent=it.why;why.classList.remove('on');
  btns.forEach(function(b,j){b.classList.remove('on');b.classList.toggle('done',j<k);void b.offsetWidth;});btns[k].classList.add('on');
  if(red){txt.textContent=it.q;why.classList.add('on');nx=setTimeout(function(){show((i+1)%items.length)},DUR);return;}
  var n=0;txt.innerHTML='<span></span><i class="cur"></i>';var sp=txt.firstChild;
  (function type(){n++;sp.textContent=it.q.slice(0,n);if(n<it.q.length){tt=setTimeout(type,28);}else{tt=setTimeout(function(){why.classList.add('on');},250);}})();
  nx=setTimeout(function(){show((i+1)%items.length)},DUR);}
 if('IntersectionObserver' in window){var io=new IntersectionObserver(function(e){if(e[0].isIntersecting&&!started){started=true;show(0);}},{threshold:.35});io.observe(root);}else show(0);
}
var hb=document.querySelector('.cx-bubble'),hq=hb&&hb.querySelector('.cx-q');
if(hb&&items.length&&!red){var h=2;setInterval(function(){hb.classList.add('out');setTimeout(function(){hq.textContent=items[h].q;h=(h+1)%items.length;hb.classList.remove('out');},450);},4200);}
var lines=document.querySelector('.cx-lines');
if(lines){if('IntersectionObserver' in window){var lo=new IntersectionObserver(function(e){if(e[0].isIntersecting){lines.classList.add('in');lo.disconnect();}},{threshold:.4});lo.observe(lines);}else lines.classList.add('in');}
var seats=[].slice.call(document.querySelectorAll('.cx-seat'));
if(seats.length&&'IntersectionObserver' in window){var so=new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');so.unobserve(x.target);}});},{threshold:.4});seats.forEach(function(s){so.observe(s);});}else seats.forEach(function(s){s.classList.add('in')});
var tl=document.getElementById('cxTl');
if(tl){var f=function(){var r=tl.getBoundingClientRect(),vh=innerHeight,p=(vh*.6-r.top)/r.height;tl.style.setProperty('--fill',Math.max(0,Math.min(1,p))*100+'%');};addEventListener('scroll',f,{passive:true});f();}
})();
