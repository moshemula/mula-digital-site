/* Mula Digital · מגה מניו - שכבת נגישות ותצוגה מקדימה.
   הפתיחה והסגירה נשארות בקוד של כל דף (.mega-open);
   הקובץ הזה מסנכרן aria-expanded, מוסיף Esc ומקלדת, ומפעיל את התצוגה המקדימה. */
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
  /* תצוגה מקדימה של תיק עבודות: מעבר עכבר או פוקוס על כרטיס מחליף את התמונה והכיתוב */
  document.querySelectorAll('header.nav .mx-port').forEach(function(panel){
    var prev=panel.querySelector('.mx-prev'); if(!prev) return;
    var img=prev.querySelector('.mx-prev-img img'), cap=prev.querySelector('.mx-prev-cap');
    var cards=panel.querySelectorAll('.mx-case[data-img], .mx-ws[data-img]'), cur=null, loaded=false, t;
    function preload(){ if(loaded) return; loaded=true; cards.forEach(function(c){ var i=new Image(); i.src=c.getAttribute('data-img'); }); }
    function show(c){
      if(cur===c) return; cur=c;
      cards.forEach(function(o){o.classList.toggle('is-on',o===c);});
      var isWs=c.classList.contains('mx-ws');
      var name=isWs?'הרצאות וסדנאות':(c.querySelector('.mx-ctx b')||{}).textContent;
      var cat=isWs?'הראל · מנורה מבטחים · בית המיזוג':(c.querySelector('.mx-ctx small')||{}).textContent;
      var res=isWs?'סדנאות לסוכנים, מפקחים וטכנאים':(c.querySelector('.mx-ctx em')||{}).textContent;
      img.classList.add('mx-out'); cap.classList.add('mx-out');
      clearTimeout(t);
      t=setTimeout(function(){
        img.src=c.getAttribute('data-img');
        cap.querySelector('b').textContent=name; cap.querySelector('small').textContent=cat; cap.querySelector('em').textContent=res;
        cap.querySelector('.mx-prev-go').firstChild.textContent=isWs?'לכל ההרצאות ':'לצפייה בתיק ';
        prev.setAttribute('href',c.getAttribute('href'));
        img.classList.remove('mx-out'); cap.classList.remove('mx-out');
      },160);
    }
    if(cards[0]){cards[0].classList.add('is-on'); cur=cards[0];}
    panel.addEventListener('mouseenter',preload);
    panel.addEventListener('focusin',preload);
    cards.forEach(function(c){
      c.addEventListener('mouseenter',function(){show(c);});
      c.addEventListener('focus',function(){show(c);});
    });
  });

  /* סוגר את הלוח אחרי בחירת קישור (חשוב בדף הבית שעובד כאפליקציה) */
  document.querySelectorAll('header.nav .mega.mx a').forEach(function(a){
    a.addEventListener('click',function(){
      items.forEach(function(item){item.classList.remove('mega-open');});
    });
  });
})();
