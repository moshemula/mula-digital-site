/* Mula Digital · Mega menu v2 - accessibility layer.
   Opening/closing stays with each page's own script (.mega-open);
   this file keeps aria-expanded in sync and adds Escape + keyboard support. */
(function(){
  var items=document.querySelectorAll('header.nav .nav-item.has-mega');
  if(!items.length) return;
  function sync(item){
    var link=item.querySelector('.nav-link');
    if(link) link.setAttribute('aria-expanded',item.classList.contains('mega-open')?'true':'false');
  }
  items.forEach(function(item){
    sync(item);
    new MutationObserver(function(){sync(item);}).observe(item,{attributes:true,attributeFilter:['class']});
    var link=item.querySelector('.nav-link');
    if(!link) return;
    link.addEventListener('keydown',function(e){
      if(e.key==='ArrowDown'){
        e.preventDefault();
        items.forEach(function(o){if(o!==item)o.classList.remove('mega-open');});
        item.classList.add('mega-open');
        var first=item.querySelector('.mega a');
        if(first) first.focus();
      }
    });
    item.addEventListener('focusout',function(e){
      if(!item.contains(e.relatedTarget)) item.classList.remove('mega-open');
    });
  });
  document.addEventListener('keydown',function(e){
    if(e.key!=='Escape') return;
    items.forEach(function(item){
      if(item.classList.contains('mega-open')||item.contains(document.activeElement)){
        item.classList.remove('mega-open');
        var link=item.querySelector('.nav-link');
        if(item.contains(document.activeElement)&&link) link.focus();
      }
    });
  });
  /* close the panel after choosing a link (matters on the single-page home routes) */
  document.querySelectorAll('header.nav .mega.mx a').forEach(function(a){
    a.addEventListener('click',function(){
      items.forEach(function(item){item.classList.remove('mega-open');});
    });
  });
})();
