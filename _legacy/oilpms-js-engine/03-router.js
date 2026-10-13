/* ---------- path routing (Laravel) ---------- */
const BASE=(window.OILPMS_BASE||'').replace(/\/+$/,'');
const URLS=window.OILPMS_ROUTES||{home:''};            /* page key -> relative path (config/oilpms.php) */
const DETAIL=window.OILPMS_DETAIL||['case','rfq','quote','po','pi','sup','award'];
const urlKey=Object.fromEntries(Object.entries(URLS).map(([k,v])=>[v,k]));
function route(){
  const p=decodeURI(location.pathname).slice(BASE.length).replace(/^\/+|\/+$/g,'').replace(/^index\.php\/?/,'');
  if(urlKey[p]!==undefined)return{key:urlKey[p],arg:''};
  const i=p.indexOf('/'),k=i<0?p:p.slice(0,i);
  if(DETAIL.includes(k))return{key:k,arg:i<0?'':decodeURIComponent(p.slice(i+1))};
  return{key:'__404',arg:''};
}
/* link ("key" or "key/arg") -> path; working case travels as ?case= */
function pathOf(link){const i=link.indexOf('/');const k=i<0?link:link.slice(0,i),a=i<0?'':link.slice(i+1);
  const path=URLS[link]!==undefined?URLS[link]:(DETAIL.includes(k)?k+(a?'/'+encodeURIComponent(a):''):(URLS[k]!==undefined?URLS[k]:k));
  return BASE+'/'+path}
const caseQ=link=>{const k=link.split('/')[0];return UI.caseId&&!DETAIL.includes(k)?'?case='+encodeURIComponent(UI.caseId):''};
const hrefOf=link=>pathOf(link)+caseQ(link);
function go(link){const h=hrefOf(link);if(location.pathname+location.search===h)render();else{history.pushState(null,'',h);navigate()}}
/* click state in the URL: ?case=..&tab.<id>=<key> (restored on reload / back / forward / shared link) */
function setQ(k,v,replace){const p=new URLSearchParams(location.search);p.set(k,v);const u=location.pathname+'?'+p;replace?history.replaceState(null,'',u):history.pushState(null,'',u)}
function readQ(){const p=new URLSearchParams(location.search),c=p.get('case');if(c&&DB&&CASE(c))UI.caseId=c;UI.tab={};p.forEach((v,k)=>{if(k.startsWith('tab.'))UI.tab[k.slice(4)]=v})}
/* every [data-go] anchor becomes a real link (new tab, copy link, hover URL) */
function linkify(root){(root||document).querySelectorAll('a[data-go]').forEach(a=>{a.href=hrefOf(a.dataset.go)})}
new MutationObserver(m=>{if(m.some(x=>x.addedNodes.length))linkify()}).observe(document.body,{childList:true,subtree:true});
