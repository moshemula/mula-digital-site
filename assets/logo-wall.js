/* Mula Digital: second logo row (branding projects), opposite direction.
   Fills any element with [data-wall-row]. data-wall-row="3" repeats the set 3 times (marquee rows that move by 1/3),
   data-wall-row="2" repeats twice (about page track that moves by 1/2). */
(function(){
  var W=[
    ['aba-winery','אבא יקב בוטיק'],['achayot-hateva','אחיות של הטבע'],['aguda','אגודה שיתופית לעזרה הדדית'],['alfa','Alfa'],
    ['alon-ezra','אלון עזרא'],['appscent','Appscent'],['bulwarx','BulwarX'],['chozrim-lomdim','חוזרים למדים'],['cpis','CPIS'],
    ['eichut-hamischak','איכות המשחק'],['flowers-tlv','Flowers TLV'],['hachashmalai-hamahir','החשמלאי המהיר'],['hachashmalai','החשמלאי'],
    ['hagarage','הגראז׳'],['hibur-tax','חיבור החזרי מס'],['higher-studies','Higher Studies'],['integrity','Integrity'],
    ['k-insurance','K סוכנות לביטוח'],['keshet','קשת הפקות'],['luxury-tours','Luxury Tours VIP'],['may-nechasim','מאי נכסים'],
    ['mayu','MAYU'],['move','Move'],['neta-drukman','נטע דרוקמן'],['oded-shomer','עודד שומר'],['officeli','OfficeLI'],
    ['osnat-amit','אסנת עמית'],['ototo','OTOTO'],['pelephizanti','פלאפיזנטי'],['queenstown','קווינסטאון'],['roy-ben-shushan','רועי בן שושן'],
    ['satec','SATEC'],['shirly-shahadi','Shirly Shahadi'],['snir','שניר נגרות אדריכלית'],['sofia','סופיה דיזיין'],['tiltan','Til-Tan'],
    ['tlvltv','TLV-LTV'],['tornado','טורנדו'],['twz-tours','TwZ Tours'],['vilco','וילקו נכסים'],['yavne-index','אינדקס עסקים יבנה'],['yeled-tov','ילד טוב']
  ];
  function fill(el){
    var n=parseInt(el.getAttribute('data-wall-row'),10)||3, cls=el.getAttribute('data-wall-class')||'brand brand-wall';
    var one=W.map(function(b,i){return '<span class="'+cls+'"><img src="assets/brand/wall/'+b[0]+'.webp" alt="'+b[1]+'" loading="lazy" decoding="async" /></span>';}).join('');
    var dup=W.map(function(b){return '<span class="'+cls+'" aria-hidden="true"><img src="assets/brand/wall/'+b[0]+'.webp" alt="" loading="lazy" decoding="async" /></span>';}).join('');
    var html=one; for(var i=1;i<n;i++) html+=dup;
    el.innerHTML=html;
  }
  function run(){ [].forEach.call(document.querySelectorAll('[data-wall-row]'),fill); }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run();
})();
