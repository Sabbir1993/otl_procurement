/* ===== PART 2: UI engine ===== */
const ACT={},PAGES={};
let CONFIRM=null;
function toast(msg,type='',a){const t=document.createElement('div');t.className='toast '+type;t.textContent=msg;if(a){const b=document.createElement('button');b.className='btn sm';b.style.marginLeft='10px';b.textContent=a.l;b.onclick=()=>{a.f();t.remove()};t.appendChild(b)}$('#toasts').appendChild(t);setTimeout(()=>t.remove(),a?7000:4200)}
function openDlg(title,body,o={}){closeDlg();const r=$('#dlg');r.innerHTML=`<div class="ov ${o.drawer?'dr':''}" data-ovclose="1"><div class="dlg ${o.wide?'wide':''}" role="dialog" aria-label="${esc(title)}"><div class="dh"><h3>${title}</h3><button class="btn gh sm" data-act="dlgclose" aria-label="Close">${ic('x')}</button></div>${o.act?`<form data-submit="${o.act}"><div class="db">${body}<div class="ferr" id="ferr"></div></div><div class="df"><button type="button" class="btn" data-act="dlgclose">Cancel</button><button class="btn ${o.danger?'bad':'pri'}" type="submit">${o.ok||'Save'}</button></div></form>`:`<div class="db">${body}</div>${o.foot?`<div class="df">${o.foot}</div>`:''}`}</div></div>`;const f=$('input:not([type=hidden]):not([readonly]),select',r);if(f&&!o.nofocus)setTimeout(()=>f.focus&&f.focus(),30)}
function closeDlg(){const r=$('#dlg');if(r)r.innerHTML=''}
function ask(msg,fn,o={}){CONFIRM=fn;openDlg(o.title||'Please confirm',`<p style="margin:0">${msg}</p>${o.extra||''}`,{foot:`<button class="btn" data-act="dlgclose">Cancel</button><button class="btn ${o.danger?'bad':'pri'}" data-act="confirmyes">${o.ok||'Confirm'}</button>`,nofocus:1})}
ACT.dlgclose=()=>closeDlg();ACT.confirmyes=()=>{const f=CONFIRM;CONFIRM=null;closeDlg();f&&f()};
const fo=f=>{const o={};new FormData(f).forEach((v,k)=>{o[k]=typeof v==='string'?v.trim():v});return o};
const ferr=m=>{const e=$('#ferr');if(e)e.textContent=m};
function F(n,l,t='text',v='',o={}){const req=o.req?'required':'';let c;
 if(t==='select')c=`<select name="${n}" ${req} ${o.attr||''}>${(o.opts||[]).map(x=>{const[a,b]=Array.isArray(x)?x:[x,x];return`<option value="${esc(a)}" ${String(a)===String(v)?'selected':''}>${esc(b)}</option>`}).join('')}</select>`;
 else if(t==='textarea')c=`<textarea name="${n}" rows="${o.rows||3}" ${req}>${esc(v)}</textarea>`;
 else if(['machine','part','wo','user'].includes(t))c=`<input name="${n}" list="dl-${t}" value="${esc(v)}" autocomplete="off" placeholder="Type to search…" ${req} ${o.attr||''}>`;
 else c=`<input name="${n}" type="${t}" value="${esc(v)}" ${req} ${o.ro?'readonly':''} ${o.min!=null?`min="${o.min}"`:''} ${o.step?`step="${o.step}"`:''} ${o.attr||''}>`;
 return`<label class="f ${o.cls||''}"><span>${l}${o.req?' *':''}</span>${c}</label>`}
const hid=(n,v)=>`<input type="hidden" name="${n}" value="${esc(v)}">`;

/* ---------- navigation ---------- */
