/* ---------- tables ---------- */
const TB={};
const tstate=id=>UI.tbl[id]||(UI.tbl[id]={q:'',f:{},sort:null,dir:1,page:1,per:10,sel:{},hide:{}});
function tfilter(id){const{cols,getRows,o}=TB[id],st=tstate(id);let rows=getRows();
 (o.filters||[]).forEach(f=>{const v=st.f[f.k];if(v)rows=rows.filter(r=>String(f.get(r))===v)});
 if(st.q){const q=st.q.toLowerCase();rows=rows.filter(r=>(cols.map(c=>c.s?c.s(r):r[c.k]).join(' ')+' '+Object.values(r).filter(x=>x==null||typeof x!=='object').join(' ')).toLowerCase().includes(q))}
 if(st.sort){const c=cols.find(c=>c.k===st.sort);if(c)rows=sortBy(rows,r=>{const v=c.s?c.s(r):r[c.k];return typeof v==='string'?v.toLowerCase():(v==null?'':v)},st.dir)}
 return rows}
function tbody(id){const{cols,o}=TB[id],st=tstate(id),rows=tfilter(id),tot=rows.length,pages=Math.max(1,Math.ceil(tot/st.per));if(st.page>pages)st.page=pages;
 const pr=rows.slice((st.page-1)*st.per,st.page*st.per),vc=cols.filter(c=>!st.hide[c.k]),key=o.key||(r=>r.id);
 if(!tot)return`<div class="empty"><b>${st.q||Object.values(st.f).some(Boolean)?'No results match your search or filters':(o.empty||'Nothing here yet')}</b>${st.q||Object.values(st.f).some(Boolean)?`<button class="btn sm" data-act="tclear" data-t="${id}">Clear search & filters</button>`:(o.emptyHint||'Records will appear here as they are created.')}</div>`;
 let h=`<div class="tw"><table><thead><tr>${o.bulk?`<th style="width:28px"><input type="checkbox" data-ta="${id}" ${pr.every(r=>st.sel[key(r)])?'checked':''}></th>`:''}${vc.map(c=>`<th data-ts="${id}|${c.k}" class="${c.cls||''}">${c.t}${st.sort===c.k?(st.dir>0?' ▲':' ▼'):''}</th>`).join('')}${o.act?'<th></th>':''}</tr></thead><tbody>`;
 pr.forEach(r=>{h+=`<tr class="${o.row?'clk':''}" ${o.row?`data-go="${esc(o.row(r))}"`:''}>${o.bulk?`<td><input type="checkbox" data-tsel="${id}|${esc(key(r))}" ${st.sel[key(r)]?'checked':''}></td>`:''}${vc.map(c=>`<td class="${c.cls||''}">${c.r?c.r(r):esc(r[c.k])}</td>`).join('')}${o.act?`<td class="nw r">${o.act(r)}</td>`:''}</tr>`});
 h+=`</tbody></table></div><div class="pg"><span>${(st.page-1)*st.per+1}–${Math.min(tot,st.page*st.per)} of ${tot}${o.bulk&&Object.values(st.sel).some(Boolean)?` · ${Object.values(st.sel).filter(Boolean).length} selected`:''}</span><span><select data-tper="${id}">${[10,25,50].map(n=>`<option ${n===st.per?'selected':''}>${n}</option>`).join('')}</select> <button class="btn sm" data-act="tpg" data-t="${id}" data-d="-1" ${st.page<=1?'disabled':''}>Prev</button> Page ${st.page}/${pages} <button class="btn sm" data-act="tpg" data-t="${id}" data-d="1" ${st.page>=pages?'disabled':''}>Next</button></span></div>`;return h}
function table(id,cols,getRows,o={}){TB[id]={cols,getRows,o};const st=tstate(id),views=DB.views[id]||[];
 return`<div class="card tbl" id="tw-${id}"><div class="tb-bar"><div class="srch"><input type="search" data-tq="${id}" placeholder="Search…" value="${esc(st.q)}"></div>${(o.filters||[]).map(f=>`<select data-tf="${id}|${f.k}" title="${f.label}"><option value="">${f.label}: All</option>${f.opts().map(x=>`<option ${st.f[f.k]===String(x)?'selected':''}>${esc(x)}</option>`).join('')}</select>`).join('')}${views.length?`<select data-tv="${id}"><option value="">Saved views</option>${views.map((v,i)=>`<option value="${i}">${esc(v.n)}</option>`).join('')}</select>`:''}<button class="btn sm" data-act="tcols" data-t="${id}">Columns</button><button class="btn sm" data-act="tsave" data-t="${id}">Save view</button><button class="btn sm" data-act="texp" data-t="${id}">${ic('dl',14)} Export</button>${o.bulk?`<button class="btn sm" data-act="tbulk" data-t="${id}">Bulk: export selected</button>`:''}${o.tools||''}</div><div id="${did(id)}">${tbody(id)}</div></div>`}
const did=id=>'tb-'+String(id).replace(/[^\w-]/g,'_');
const redraw=id=>{const e=document.getElementById(did(id));if(e)e.innerHTML=tbody(id)};
const csvEsc=v=>'"'+String(v==null?'':v).replace(/"/g,'""')+'"';
function downloadCSV(name,rows){const b=new Blob(['\ufeff'+rows.map(r=>r.map(csvEsc).join(',')).join('\n')],{type:'text/csv'});const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download=name;document.body.appendChild(a);a.click();setTimeout(()=>{a.remove();URL.revokeObjectURL(a.href)},500)}
function exportTable(id,only){const{cols}=TB[id],st=tstate(id);let rows=tfilter(id);if(only)rows=rows.filter(r=>st.sel[(TB[id].o.key||(x=>x.id))(r)]);const vc=cols.filter(c=>!st.hide[c.k]&&c.t);
 downloadCSV(id+'_export.csv',[vc.map(c=>c.t.replace(/<[^>]+>/g,'')),...rows.map(r=>vc.map(c=>c.csv?c.csv(r):c.s?c.s(r):r[c.k]))]);audit('Export','CSV',`Exported ${rows.length} rows from ${id}`);toast(`Exported ${rows.length} rows to CSV`)}
ACT.texp=d=>exportTable(d.t);ACT.tbulk=d=>{if(!Object.values(tstate(d.t).sel).some(Boolean))return toast('Select at least one row first','warn');exportTable(d.t,true)};
ACT.tclear=d=>{const s=tstate(d.t);s.q='';s.f={};s.page=1;render()};
ACT.tpg=d=>{const s=tstate(d.t);s.page+= +d.d;redraw(d.t)};
ACT.tcols=d=>{const{cols}=TB[d.t],st=tstate(d.t);openDlg('Select columns',`<div class="grid g2">${cols.filter(c=>c.t).map(c=>`<label><input type="checkbox" data-tcol="${d.t}|${c.k}" ${st.hide[c.k]?'':'checked'}> ${c.t.replace(/<[^>]+>/g,'')}</label>`).join('')}</div>`,{foot:`<button class="btn pri" data-act="dlgclose">Done</button>`})};
ACT.tsave=d=>{openDlg('Save current view',`${hid('t',d.t)}${F('n','View name','text','',{req:1})}<p class="mut sm">Saves the current search, filters, sorting and visible columns.</p>`,{act:'tsave2',ok:'Save view'})};
ACT.tsave2=o=>{const s=tstate(o.t);(DB.views[o.t]=DB.views[o.t]||[]).push({n:o.n,st:JSON.parse(JSON.stringify({q:s.q,f:s.f,sort:s.sort,dir:s.dir,hide:s.hide}))});save();closeDlg();toast(`View “${o.n}” saved`);render()};

/* ---------- common widgets ---------- */
const tabs=(id,list,def)=>{const cur=UI.tab[id]||def||list[0][0];return`<div class="tabs">${list.map(t=>`<div class="tab ${t[0]===cur?'on':''}" data-act="tab" data-id="${id}" data-k="${t[0]}">${t[1]}</div>`).join('')}</div>`};
const curTab=(id,def)=>UI.tab[id]||def;
ACT.tab=d=>{UI.tab[d.id]=d.k;setQ('tab.'+d.id,d.k);render()};
const kpi=(label,val,o={})=>`<div class="card kpi ${o.cls||''}" ${o.go?`data-act="drill" data-go="${o.go}" data-tid="${o.tid||''}" data-f='${esc(JSON.stringify(o.f||{}))}' data-q="${esc(o.q||'')}"`:''} title="Click to view details"><small>${label}</small><b>${val}</b>${o.sub?`<i>${o.sub}</i>`:''}</div>`;
ACT.drill=d=>{if(d.tid){const s=tstate(d.tid);s.f=JSON.parse(d.f||'{}');s.q=d.q||'';s.page=1}go(d.go)};
const barRows=(rows,o={})=>{const mx=Math.max(1,...rows.map(r=>r[1]));return rows.map(r=>`<div class="hrow"><span title="${esc(r[0])}">${r[3]?lk(r[3],esc(r[0])):esc(r[0])}</span><div class="bar ${r[1]/mx>.85&&o.hot?'b':''}"><i style="width:${Math.round(r[1]/mx*100)}%"></i></div><span class="r">${r[2]!=null?r[2]:r[1]}</span></div>`).join('')||'<div class="empty">No data</div>'};
function spark(vals,w=180,h=40,c='#14b8a6'){if(vals.length<2)return'';const mx=Math.max(...vals),mn=Math.min(...vals),rg=mx-mn||1;const pts=vals.map((v,i)=>`${(i/(vals.length-1)*w).toFixed(1)},${(h-4-(v-mn)/rg*(h-8)).toFixed(1)}`).join(' ');return`<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}"><polyline fill="none" stroke="${c}" stroke-width="2" points="${pts}"/></svg>`}
const colChart=(rows,h=110)=>{const mx=Math.max(1,...rows.map(r=>r[1]));return`<div style="display:flex;align-items:flex-end;gap:6px;height:${h+22}px">${rows.map(r=>`<div style="flex:1;text-align:center;font-size:10.5px;color:var(--mut)"><div style="font-size:11px;color:var(--ink)">${r[1]}</div><div style="height:${Math.max(2,r[1]/mx*h)}px;background:var(--acc2);border-radius:3px 3px 0 0"></div>${esc(r[0])}</div>`).join('')}</div>`};
const emptyState=(t,s)=>`<div class="empty"><b>${t}</b>${s||''}</div>`;
const dl=(rows)=>`<dl class="dl">${rows.map(r=>`<dt>${r[0]}</dt><dd>${r[1]==null||r[1]===''?'—':r[1]}</dd>`).join('')}</dl>`;


/* =====================  APPROVALS  ===================== */
const apprSteps={mkt:'Marketing ACK',fin:'Finance APP',ed:'ED final APP'};
function submitAward(c,by,summary){
  const A=c.appr;A.version++;if(!A.id)A.id=nid('AWD');A.status='mkt';A.rec={by:by||user(),at:DB.now};A.mkt=A.fin=A.ed=null;A.snap=lineAwards(c);A.chk=awardChk(c);A.summary=summary||A.summary||'';c.csLocked=true;
  A.hist.push({ts:DB.now+(DB.tick++)*1000,a:`Recommendation v${A.version} submitted`,by:A.rec.by,r:A.summary});
  mail(c,'H','Marketing approver',{REQUEST_ID:A.id,STAGE:'Marketing ACK',SUPPLIER_NAME:uniq(A.snap.map(x=>supOf(x.sid).name)).join(', '),AMOUNT:usd(sum(A.snap,x=>x.amt)),ACTION_LABEL:'Acknowledge / Return',APPROVER_NAME:'Marketing'});
  notify('info',`${A.id} v${A.version} needs Marketing ACK`,'aw.appr',{role:'mkt'});
  log('Award submitted',A.id,`v${A.version} · checksum ${A.chk}`,{cid:c.id,...(by?{by}:{})});
}
function decide(c,d,remark,by){
  const A=c.appr,key=A.status,now=DB.now+(DB.tick++)*1000;
  A[key]={d:d==='ok'?'ok':'no',by:by||user(),at:now,r:remark};
  A.hist.push({ts:now,a:`${apprSteps[key]} — ${d==='ok'?(key==='mkt'?'Acknowledged':'Approved'):d==='ret'?'Returned':'Rejected'}`,by:A[key].by,r:remark});
  log(`${apprSteps[key]}: ${d}`,A.id,remark||'',{cid:c.id,...(by?{by}:{})});
  if(d==='ok'){
    A.status=key==='mkt'?'fin':key==='fin'?'ed':'approved';
    if(A.status==='approved'){A.approvedChk=A.chk;generatePOs(c);notify('ok',`${A.id} approved — POs generated`,'po.reg',{role:'exec'})}
    else{const nx=A.status;mail(c,'H',`${nx==='fin'?'Finance':'ED'} approver`,{REQUEST_ID:A.id,STAGE:apprSteps[nx],SUPPLIER_NAME:'See award package',AMOUNT:usd(sum(A.snap,x=>x.amt)),ACTION_LABEL:'Approve / Return / Reject',APPROVER_NAME:nx==='fin'?'Finance':'ED'});notify('info',`${A.id} needs ${apprSteps[nx]}`,'aw.appr',{role:nx})}
  }else{A.status=d==='ret'?'returned':'rejected';c.csLocked=false;notify('bad',`${A.id} ${A.status} at ${apprSteps[key]}`,'aw.appr',{role:'exec'})}
}

/* =====================  PURCHASE ORDERS  ===================== */
function generatePOs(c){
  const by={};lineAwards(c).forEach(x=>(by[x.sid]=by[x.sid]||[]).push(x));
  const st=DB.settings;
  c.pos=Object.entries(by).map(([sid,lines])=>{const q0=c.quotes[sid][lines[0].pid];
    const H=hdrOf(c,sid);
    return{no:nid('PO'),cid:c.id,sid,rev:1,lines:lines.map(x=>({...x,name:spName(sid,x.pid),hs:(c.quotes[sid][x.pid].spec||{}).hs||'',pack:[(c.quotes[sid][x.pid].spec||{}).pkgType,(c.quotes[sid][x.pid].spec||{}).rollLen].filter(Boolean).join(', ')})),total:sum(lines,x=>x.amt),inc:q0.incoterm,place:q0.place,payment:q0.payment,claims:H.claims||'',partial:H.partial||'',transship:H.transship||'',origin:H.origin||'',dest:H.dischargePort||'',ship:DB.now+Math.max(...lines.map(x=>x.days))*DAY,
      shipTo:st.shipTo,billTo:st.billTo,tol:st.tol,packing:st.packing,docs:st.docs,notes:'',
      sent:false,status:'',ack:'—',sentAt:null,msgId:'',draft:false,frozen:false,chk:c.appr.chk,ver:c.appr.version,devOk:{},tries:0,created:DB.now}});
  log('POs generated',c.appr.id,`${c.pos.length} PO(s) from approved fields`,{cid:c.id,by:'System'});
}
const poDev=(c,po)=>po.lines.filter(l=>(csOf(c,l.pid).waiver||{})[po.sid]!==undefined&&!po.devOk[l.pid]);
function validatePO(c,po){
  const A=c.appr,sp=supOf(po.sid);
  return[
   {k:'Final approval valid',ok:A.status==='approved'&&A.approvedChk===A.chk&&!po.frozen,why:po.frozen?'Frozen — material change awaiting re-approval':'Final approval missing or pack changed'},
   {k:'PO source = final approved version',ok:po.ver===A.version&&po.chk===A.approvedChk,why:'PO built from a superseded version'},
   {k:'Required fields complete',ok:!!(po.inc&&po.place&&po.payment&&po.ship&&po.shipTo&&po.billTo&&po.tol&&po.claims&&po.lines.every(l=>l.qty>0&&l.price>0)),why:!po.place?'Incoterms named place missing':!po.shipTo||!po.billTo?'Ship-to / bill-to address missing':!po.claims?'Claims / replacement provisions missing':'Mandatory PO field empty'},
   {k:'Supplier email verified',ok:!!sp.emailVerified,why:'Contact changed — independent callback verification needed'},
   {k:'No unresolved blocking deviations',ok:po.lines.every(l=>l.sample!=='Fail')&&!poDev(c,po).length,why:poDev(c,po).length?'MOQ waiver needs supplier written acceptance':'Failed sample on a line'}];
}
const poStatus=(c,po)=>po.sent?po.status:po.frozen?'Frozen':po.status==='Scheduled'?'Scheduled':validatePO(c,po).every(x=>x.ok)?'Ready to Dispatch':'Correction queue';
const poFull=po=>po.rev>1?`${po.no}-R${po.rev}`:po.no;
const poVars=(c,po)=>{const sp=supOf(po.sid);return{PO_NUMBER:poFull(po),RFQ_NUMBER:c.rfq.no,SUPPLIER_CONTACT_NAME:sp.contact,PRODUCTS:po.lines.map(l=>`${prod(l.pid).name} (${l.name})`).join('; '),QUANTITY:po.lines.map(l=>`${n0(l.qty)} ${prod(l.pid).uom}`).join(' + '),PO_TOTAL:'USD '+n0(po.total),INCOTERMS_NAMED_PLACE:`${po.inc} ${po.place}`,SHIPMENT:dD(po.ship),PAYMENT_TERMS:po.payment,PO_ACK_LINK:`https://rfq.[oil-domain]/po/[PO-ACK-TOKEN]`,ACK_DEADLINE:'[ACK SLA — Policy Item 8]'}};
function dispatchPO(c,po,ts,by){
  if(DB.flags.emailFail&&!by){po.tries++;po.sent=true;po.status='Delivery Failed';po.sentAt=null;raise('PO email failed',po.no,'Email provider failure — PO held, safe retry available',{cid:c.id});log('PO delivery failed',po.no,'Held for retry (no new PO number)',{cid:c.id});return false}
  po.sent=true;po.status='Sent';po.sentAt=ts||DB.now;po.ack='Awaiting';po.msgId='MSG-'+rnd(8);po.tries++;
  DB.exc.filter(e=>e.type==='PO email failed'&&e.ref===po.no).forEach(e=>{e.status='Resolved';e.res='Retried successfully · same PO number'});
  const sp=supOf(po.sid);mail(c,'PO',`${sp.contact} <${sp.email}>`,poVars(c,po),{ts,pid:po.no});log('PO sent',po.no,`${sp.name} · ${po.msgId}`,{cid:c.id,ts,...(by?{by,ts}:{})});return true;
}
function poAck(c,po,decision,reason,by){
  const sp=supOf(po.sid);
  if(decision==='ok'){po.ack='Accepted';po.ackAt=DB.now;log('PO accepted',po.no,sp.name,{cid:c.id,by:'Supplier'})}
  else{po.ack='Disputed';po.ackAt=DB.now;po.dispute=reason;raise('PO dispute',po.no,`${sp.name}: ${reason}`,{cid:c.id});log('PO disputed',po.no,reason,{cid:c.id,by:'Supplier'})}
}

/* =====================  PI & LC  ===================== */
const IMPDOCS=['Proforma Invoice (PI) validated against PO','Approved PO — frozen approved version attached','Import permission / customs documentation','LC / TT application documents prepared','HS code and customs classification confirmed','Sanction / compliance screening completed','Import registration & permitted-goods check (IRC) — verify per Bangladesh Bank / AD-bank rules','LC authorization, margin and FX exposure check per policy'];
function piMatch(c,po,pi){
  const sp=supOf(po.sid),eq=(a,b)=>Math.abs(a-b)<.0005,ln=po.lines.map(l=>({l,i:pi.lines.find(x=>x.pid===l.pid)||{}}));
  const piTot=sum(pi.lines,x=>x.qty*x.price);
  return[
   ['Buyer / seller identities',pi.seller===sp.name&&pi.buyer==='Osman Interlinings Limited (OIL)',`${pi.seller} → ${pi.buyer}`],
   ['Description / specification / HS code',ln.every(x=>x.i.desc===x.l.name&&(!x.l.hs||!x.i.hs||x.i.hs===x.l.hs)),ln.filter(x=>x.i.desc!==x.l.name||(x.l.hs&&x.i.hs&&x.i.hs!==x.l.hs)).map(x=>x.i.desc!==x.l.name?`${x.l.pid}: “${x.i.desc}” vs “${x.l.name}”`:`${x.l.pid}: HS ${x.i.hs} vs ${x.l.hs}`).join('; ')||'Match'],
   ['Quantities',ln.every(x=>x.i.qty===x.l.qty),ln.filter(x=>x.i.qty!==x.l.qty).map(x=>`${x.l.pid}: PI ${n0(x.i.qty)} vs PO ${n0(x.l.qty)}`).join('; ')||'Match'],
   ['Unit price',ln.every(x=>eq(x.i.price,x.l.price)),ln.filter(x=>!eq(x.i.price,x.l.price)).map(x=>`${x.l.pid}: PI ${usd3(x.i.price)} vs PO ${usd3(x.l.price)}`).join('; ')||'Match'],
   ['Totals',eq(piTot,po.total),`PI ${usd(piTot)} vs PO ${usd(po.total)}`],
   ['Currency',pi.cur==='USD','PI '+pi.cur+' vs PO USD'],
   ['Shipping window',pi.shipBy<=po.ship,`PI ${dD(pi.shipBy)} vs PO by ${dD(po.ship)}`],
   ['Incoterms place',pi.inc===po.inc&&pi.place===po.place,`PI ${pi.inc} ${pi.place} vs PO ${po.inc} ${po.place}`],
   ['Payment terms',pi.payment===po.payment,`PI “${pi.payment}” vs PO “${po.payment}”`],
   ['Beneficiary bank details',!pi.bankChanged||(pi.bankV.fin&&pi.bankV.sup),pi.bankChanged?(pi.bankV.fin&&pi.bankV.sup?'Changed — dual control complete':`Changed vs vendor master — dual control ${pi.bankV.fin||pi.bankV.sup?'1/2':'0/2'}`):'Unchanged vs vendor master']].map(([n,ok,d])=>({n,ok,d}));
}
const piOk=(c,po,pi)=>piMatch(c,po,pi).every(x=>x.ok);
function seedPI(c,po,over={}){
  const sp=supOf(po.sid);
  return{no:'[SUPPLIER-PI-REF]',date:DB.now,seller:sp.name,buyer:'Osman Interlinings Limited (OIL)',lines:po.lines.map(l=>({pid:l.pid,desc:l.name,qty:l.qty,price:l.price,hs:l.hs||''})),cur:'USD',inc:po.inc,place:po.place,payment:po.payment,shipBy:po.ship,bankChanged:false,bankV:{fin:false,sup:false},approved:false,ver:1,sys:nid('PI'),...over};
}
const lcOf=(c,no)=>c.lc[no]||(c.lc[no]={docs:{},docsDone:false,appr:null,open:null});
const lcState=(c,po)=>{const l=c.lc[po.no],pi=c.pis[po.no];return l&&l.open?'LC opened':l&&l.appr?'LC approved':l&&l.docsDone?'Checks done':pi&&pi.approved?'PI matched':pi?'PI recorded':po.ack==='Accepted'?'Awaiting PI':'—'};

/* =====================  FLOW ENGINE (what happens next)  ===================== */
const FLOW=[['Requisition','rq.board'],['Booking','rq.booking'],['RFQ','src.rfq'],['Quotes','src.track'],['Comparison','src.cs'],['Approval','aw.appr'],['Purchase order','po.reg'],['PI & LC','imp.pi']];
function flowDone(c){
  const cnt=csCounts(c),L=c.pos;
  return[c.frozen,c.booking.confirmed,c.inv.length>0,rfqClosed(c),cnt.total>0&&cnt.pend===0,c.appr.status==='approved',L.length>0&&L.every(p=>p.ack==='Accepted'),L.length>0&&L.every(p=>c.lc[p.no]&&c.lc[p.no].open)];
}
function flow(c){const d=flowDone(c);let on=d.findIndex(x=>!x);return FLOW.map((f,i)=>({n:f[0],go:f[1],st:d[i]?'done':i===on?'on':'todo'}))}
const flowIdx=c=>{const d=flowDone(c),i=d.findIndex(x=>!x);return i<0?8:i};
function caseStatus(c){const i=flowIdx(c);return i>=8?'Completed':FLOW[i][0]}
function nextAction(c){
  const i=flowIdx(c),pend=c.demand.filter(d=>d.status==='pending').length,cnt=csCounts(c),A=c.appr;
  if(i===0)return pend?{t:`Verify ${pend} pending requisition line(s)`,go:'rq.board',who:'sup'}:{t:'Freeze the validated requisition',go:'rq.board',who:'sup'};
  if(i===1)return{t:'Confirm book qty — signed document is mandatory',go:'rq.booking',who:'sup'};
  if(i===2)return c.drafts.length?{t:'Review and send the RFQ drafts',go:'src.rfq',who:'exec'}:{t:'Create the RFQ from the confirmed booking',go:'src.rfq',who:'exec'};
  if(i===3){const n=c.inv.filter(x=>x.submitted).length;return{t:`Waiting for supplier quotations — ${n} of ${c.inv.length} submitted`,go:'src.track',who:'exec'}}
  if(i===4)return{t:`Decide ${cnt.pend} product(s) in the comparative statement`,go:'src.cs',who:'exec'};
  if(i===5){if(['none','returned','rejected'].includes(A.status))return{t:A.status==='none'?'Submit the award recommendation':`Award ${A.status} — revise and resubmit`,go:'aw.rec',who:'exec'};const nx={mkt:'mkt',fin:'fin',ed:'ed'}[A.status];return{t:`Waiting for ${apprSteps[A.status]} (${ROLES[nx].n})`,go:'aw.appr',who:nx}}
  if(i===6){const un=c.pos.filter(p=>!p.sent);if(un.length)return{t:`Dispatch ${un.length} purchase order(s) to suppliers`,go:'po.reg',who:'exec'};const aw=c.pos.filter(p=>p.ack!=='Accepted');return{t:`Waiting for ${aw.length} supplier PO acknowledgment(s)`,go:'po.ack',who:'exec'}}
  if(i===7){const need=c.pos.filter(p=>!(c.lc[p.no]&&c.lc[p.no].open));const p=need[0];const s=lcState(c,p);return{t:`${p.no}: ${s==='Awaiting PI'?'record supplier PI':s==='PI recorded'?'resolve PI match':s==='PI matched'?'complete import & banking checks':s==='Checks done'?'Finance LC approval':'open the LC'}`,go:'imp.pi',who:'imp'}}
  return{t:'Case complete — shipment, GRN and payment continue in the ERP modules',go:'ship',who:''};
}

/* =====================  SEED BUILDER  ===================== */
const STAGES=[['req','1 · Fresh case — verify requisitions'],['booking','2 · Requisition frozen → confirm booking'],['rfq','3 · Booking confirmed → create RFQ'],['quotes','4 · Quotations received'],['cs','5 · Comparison decided → submit award'],['appr','6 · In approval (Finance next)'],['po','7 · Approved → POs to dispatch'],['ack','8 · POs sent, one accepted'],['pi','9 · PI mismatch to resolve'],['lc','10 · PI matched → LC approval'],['done','11 · Completed (LC opened)']];
function negotiate(c,pid,sid,np,r,by){const q=c.quotes[sid][pid],k=np/q.price;q.listPrice=+((q.listPrice||q.price)*k).toFixed(4);(q.tiers||[]).forEach(t=>t.price=+(t.price*k).toFixed(4));csOf(c,pid).neg.push({id:nid('NEG'),sid,from:q.price,to:np,reason:r,by,at:DB.now});q.ins=Math.round(q.ins*k);q.duty=Math.round(q.duty*k);q.price=np;q.rev++;log('Negotiation round',pid,`${supOf(sid).name} → ${usd2(np)}`,{cid:c.id,by:'System'})}
const STEPS={
 req(c){[1,2,3,4,5,6].forEach(n=>{const x=c.demand.find(d=>d.id.slice(1)===pad(n));if(x)x.status='accepted'});const x=c.demand.find(d=>d.id.slice(1)==='09');if(x){x.status='rejected';x.reason=REASONS[0]}},
 booking(c,o){c.demand.forEach(d=>{if(/09$/.test(d.id)){d.status='rejected';d.reason=REASONS[0]}else if(/16$/.test(d.id)){d.status='rejected';d.reason=REASONS[1]}else d.status='accepted'});freezeCase(c,'System')},
 rfq(c,o){const ts=c.t0-DAY;c.booking.doc='IMPBOK_signed_scan.pdf';c.booking.serial=bookingSerial(c);c.booking.confirmed=true;c.booking.at=ts;c.booking.by='Farhana S.';c.booking.ref='[SIGNED-DOC-REF]';log('Booking confirmed',c.booking.serial,'Signed document attached',{by:'System',ts,cid:c.id});
   genDrafts(c,{by:'System',pool:poolSup(c)});c.rfq.reqFields=['peel','shrink'];c.drafts.forEach(d=>{if(d.status==='Draft')sendDraft(c,d,c.t0,'System')})},
 quotes(c,o){
   const sub=o.full?['SUP-00001','SUP-00002','SUP-00003','SUP-00004']:['SUP-00001','SUP-00002','SUP-00003'];
   c.inv.forEach((iv,n)=>{const sid=iv.sid;if(sub.includes(sid)){c.quotes[sid]={};iv.pids.forEach(pid=>{const q=mkQuote(pid,sid,reqQty(c,pid));if(q)c.quotes[sid][pid]=q});iv.submitted=true;iv.state='Submitted';iv.submittedAt=c.t0+(n+3)*DAY/2;iv.quoRef=nid('QUO');iv.rev=1;iv.ip='58.246.xxx.xxx';c.quoteHdr[sid]=seedQuoteHdr(c,sid);
     const sp=supOf(sid);mail(c,'C',`${sp.contact} <${sp.email}>`,{...rfqVars(c,iv),SUBMISSION_ID:iv.quoRef,SUBMITTED_AT:dBD(iv.submittedAt)},{ts:iv.submittedAt});log('Quotation submitted',iv.quoRef,sp.name,{by:'Supplier',ts:iv.submittedAt,cid:c.id})}
     else if(sid==='SUP-00004'){iv.state='Draft started';iv.ip='113.108.xxx.xxx';log('Draft saved',iv.rfq,supOf(sid).name,{by:'Supplier',ts:c.t0+DAY,cid:c.id})}});
   if(!o.full){const s3=supOf('SUP-00003');s3.contact='Zhao Wei';s3.email='zhao.wei@huanan.example';s3.emailVerified=false;raise('Supplier contact changed','SUP-00003','Kunshan Huanan changed PO contact during quotation — callback verification needed before any PO is sent.',{by:'System',cid:c.id})}
   checkNoBid(c,'System')},
 cs(c,o){
   c.booking.lines.forEach(l=>{const b=pick(c,l.pid,'cost');if(b){setFull(c,l.pid,b.sid,'System');if(needsJust(c,l.pid))csOf(c,l.pid).just='Selected after excluding cheaper offers that fail the sample test or MOQ gate.'}else{const x=csOf(c,l.pid);x.excl=true;x.exclWhy='No valid quotation — re-tender'}});
   const w=Object.keys(csOf(c,'P-1008').alloc)[0];if(w)negotiate(c,'P-1008',w,+(c.quotes[w]['P-1008'].price*.98).toFixed(3),'Volume commitment across the booking','Imran K.')},
 appr(c,o){submitAward(c,'Imran K.','Lowest eligible landed cost per product; unquoted product returned to re-tender.');decide(c,'ok','Specs and sample outcomes match requirement.','Nasrin A.')},
 po(c,o){csOf(c,'P-1001').fin='Landed-cost assumptions accepted; confirm FX rate before LC opening.';decide(c,'ok','Budget available; LC / advance exposure within limits.','Kamal U.');decide(c,'ok','Approved.','ED (delegate)')},
 ack(c,o){c.pos.forEach(p=>{p.draft=true;if(poStatus(c,p)==='Ready to Dispatch')dispatchPO(c,p,DB.now-3600e3*(o.full?400:1),'System')});const p=c.pos.find(x=>x.sent);if(p){p.ack='Accepted';p.ackAt=DB.now-3000e3;log('PO accepted',p.no,supOf(p.sid).name,{by:'Supplier',cid:c.id})}if(o.full)c.pos.forEach(q=>{if(q.sent&&q.ack!=='Accepted'){q.ack='Accepted';q.ackAt=DB.now-3000e3}})},
 pi(c,o){const p=c.pos.find(x=>x.ack==='Accepted');if(!p)return;const pi=seedPI(c,p,{no:'[SUPPLIER-PI-REF]'});pi.lines[0].price=+(pi.lines[0].price+.01).toFixed(3);pi.payment='TT 100% advance';c.pis[p.no]=pi;log('PI recorded',p.no,'Supplier PI rev 1',{by:'System',cid:c.id});raise('PO vs PI mismatch',p.no,'Price and payment terms differ',{by:'System',cid:c.id})},
 lc(c,o){const p=c.pos.find(x=>x.ack==='Accepted');if(!p)return;const pi=seedPI(c,p,{no:'[SUPPLIER-PI-REF]',approved:true});c.pis[p.no]=pi;DB.exc.filter(e=>e.ref===p.no&&e.type==='PO vs PI mismatch').forEach(e=>{e.status='Resolved';e.res='PI corrected (rev 2)'});c.lc[p.no]={docs:Object.fromEntries(IMPDOCS.map((_,i)=>[i,true])),docsDone:true,appr:null,open:null}},
 done(c,o){c.pos.forEach((p,n)=>{if(!p.sent){p.draft=true;dispatchPO(c,p,DB.now-3600e3*300,'System')}p.ack='Accepted';const pi=c.pis[p.no]||seedPI(c,p,{no:'[SUPPLIER-PI-REF]'});pi.approved=true;c.pis[p.no]=pi;const l=lcOf(c,p.no);l.docs=Object.fromEntries(IMPDOCS.map((_,i)=>[i,true]));l.docsDone=true;l.appr={by:'Kamal U.',at:DB.now-86400e3*3};l.open={ref:'[LC-REFERENCE]',bank:'[AD-BANK-NAME]',amt:sum(pi.lines,x=>x.qty*x.price),exp:Date.UTC(2027,0,15),at:DB.now-86400e3*2};DB.exc.filter(e=>e.ref===p.no).forEach(e=>{e.status='Resolved';e.res='Closed with case'})})}};
function buildCase(c,upTo,o={}){
  const order=STAGES.map(x=>x[0]),up=order.indexOf(upTo);
  if(upTo==='req'){STEPS.req(c,o)}else{for(let i=1;i<=up;i++)STEPS[order[i]](c,o)}
}
function bookingSerial(c){const k='IMPBOK-'+c.mon;return`IMPBOK/2026/${c.mon}/${pad(nextSeq(k))}`}
function freezeCase(c,by){
  c.frozen=true;
  c.booking.lines=MATS.map(p=>{const q=demandSum(c,p.id,'accepted');return q?{pid:p.id,demand:q,pct:100,qty:q}:null}).filter(Boolean);
  log('Requisition frozen',c.id,`${c.booking.lines.length} products → Import Booking Report`,{cid:c.id,...(by?{by}:{})});
}
function buildDB(){
  const old=DB;
  DB={v:4,now:NOW0,tick:0,seq:{RFQ:30,QUO:40,NEG:0,AWD:11,PO:7,PI:0,EXC:0,'IMPBOK-SEP':0,'IMPBOK-OCT':0,'IMPBOK-NOV':0},
   sup:clone(SUPS),sp:clone(SPMAP),mats:[],cal:clone(CALS),cases:[],exc:[],audit:[],mails:[],notifs:[],views:{},tests:{},reasons:[...REASONS],policy:clone(POLICY),tpl:clone(TPL0),
   authority:[{lvl:'Level 1',lim:'',who:''},{lvl:'Level 2',lim:'',who:''},{lvl:'Level 3',lim:'',who:''}],flags:{emailFail:false},
   settings:{freightPerCbm:0,billTo:'Osman Interlinings Limited (OIL), [OIL-REGISTERED-ADDRESS]',shipTo:'OIL Central Warehouse, [OIL-DELIVERY-ADDRESS]',tol:'±5%',packing:'Export standard rolls in cartons, marked with PO number',docs:'Commercial invoice, packing list, BL/AWB, certificate of origin, COA'},
   users:Object.entries(ROLES).map(([k,r],i)=>({id:'U'+pad(i+1,3),name:r.u,role:k,email:`user${i+1}@[oil-domain]`,status:'Active'}))};
  const sep=newCase({id:'IC-2026-09',name:'September 2026 import booking',month:'Sep 2026',mon:'SEP',t0:Date.UTC(2026,8,8,4,0),by:'Imran K.'});
  seedDemand(sep,.6,'S');DB.cases.push(sep);
  const oct=newCase({id:'IC-2026-10',name:'October 2026 import booking',month:'Oct 2026',mon:'OCT',t0:Date.UTC(2026,9,5,4,0)});
  seedDemand(oct,1,'D');DB.cases.push(oct);
  const nov=newCase({id:'IC-2026-11',name:'November 2026 import booking',month:'Nov 2026',mon:'NOV',t0:NOW0});
  seedDemand(nov,1.25,'N');DB.cases.push(nov);
  /* September: full history, then reset supplier contact flags so only October carries the live exceptions */
  buildCase(sep,'done',{full:true});
  buildCase(oct,'quotes',{full:false});
  buildCase(nov,'req',{});
  DB.sup.forEach(s=>{if(s.id==='SUP-00003'){}});
  UI.caseId='IC-2026-10';
  DB.tick=0;save();
}
function loadStage(cid,name){
  const c=CASE(cid),t0=c.t0,id=c.id,nm=c.name,mon=c.mon,month=c.month,scale=c.id==='IC-2026-09'?.6:c.id==='IC-2026-11'?1.25:1;
  /* reset the case and rebuild it to the requested stage */
  const fresh=newCase({id,name:nm,month,mon,t0});Object.keys(c).forEach(k=>delete c[k]);Object.assign(c,fresh);
  seedDemand(c,scale,c.id==='IC-2026-09'?'S':c.id==='IC-2026-11'?'N':'D');
  DB.exc=DB.exc.filter(e=>e.cid!==id);
  DB.sup.forEach(s=>{const o=SUPS.find(x=>x.id===s.id);if(o){s.contact=o.contact;s.email=o.email;s.emailVerified=true}});
  buildCase(c,name,{full:false});DB.tick=0;save();
}

/* =====================  SHELL (same patterns as the reference module)  ===================== */
const pill=(t,cls)=>`<span class="pill ${cls||PC[t]||''}">${esc(t)}</span>`;
const PC={Accepted:'p-ok',Pass:'p-ok',Submitted:'p-ok',Approved:'p-ok',Sent:'p-ok',Matched:'p-ok',Verified:'p-ok',Complete:'p-ok',Completed:'p-ok','LC opened':'p-ok',Resolved:'p-ok',Active:'p-ok',Closed:'p-ok','Ready to Dispatch':'p-ok',Open:'p-warn',Pending:'p-warn',Draft:'p-warn','Draft started':'p-warn',Scheduled:'p-warn',Awaiting:'p-warn',Conditional:'p-warn',Partial:'p-warn',Frozen:'p-warn','Awaiting PI':'p-warn','PI recorded':'p-warn',Viewed:'p-info',Extended:'p-info',Waived:'p-info',Low:'p-info','Checks done':'p-info','PI matched':'p-info','LC approved':'p-info',High:'p-bad',Medium:'p-warn',Rejected:'p-bad',Fail:'p-bad',Expired:'p-bad',Failed:'p-bad',Disputed:'p-bad',Blocked:'p-bad',Mismatch:'p-bad',Returned:'p-bad',Revoked:'p-bad','Delivery Failed':'p-bad','Correction queue':'p-bad',Inactive:'',Excluded:'',Unverified:'p-warn'};
const caseBadge=c=>{const s=caseStatus(c);return pill(s,s==='Completed'?'p-ok':'p-acc')};
function flowBar(c,here){
  const f=flow(c);
  return`<div class="steps flow">${f.map((x,i)=>`<div class="${x.st==='done'?'done':x.st==='on'?'on':''} ${x.go===here?'here':''}" data-go="${x.go}" title="${x.n}">${x.st==='done'?'✓ ':''}${i+1}. ${x.n}</div>`).join('')}</div>`;
}
const nextCard=(c,compact)=>{const n=nextAction(c);return`<div class="alert info nextc"><div class="t"><b>Next step · ${esc(c.id)}</b><span>${esc(n.t)}${n.who?` <span class="mut">· ${ROLES[n.who].n}</span>`:''}</span></div><div class="a"><button class="btn pri sm" data-go="${n.go}">Go there</button></div></div>`};
const noCase=()=>({t:'No purchase case',crumbs:[['Command Center','home'],'No case'],html:emptyState('No purchase case yet','<button class="btn pri" data-act="caseForm">New purchase case</button>')});
const alertBox=(cls,t,body,acts='')=>`<div class="alert ${cls}"><div class="t"><b>${t}</b>${body}${acts?`<div class="a">${acts}</div>`:''}</div></div>`;
const roBanner=()=>UI.role==='audit'?`<div class="alert info" style="margin-bottom:10px"><div class="t"><b>Read-only role</b>Internal Audit / MD can view everything but cannot change data.</div></div>`:'';
const bell=()=>DB.notifs.filter(n=>!n.read&&(!n.role||n.role===UI.role)).length;
const crumbHtml=c=>`<div class="crumbs">${c.map(x=>Array.isArray(x)?`<a data-go="${x[1]}">${esc(x[0])}</a>`:esc(x)).join(' / ')}</div>`;

const ROUTE_OK=['case','rfq','quote','po','pi','sup','award','ext','home','portal'];
function sideHtml(){const nav=navFor(UI.role),r=route().key;
 return`<div class="brand"><div class="logo">OI</div><div><b>OIL-PMS</b><small>Import Purchase · Prototype</small></div></div><div class="nav">${nav.map(g=>{
  const on=g.items.some(i=>i[0]===r);const open=UI.open[g.k]!==undefined?UI.open[g.k]:on;
  if(g.items.length===1)return`<a class="ng-h ${g.items[0][0]===r?'on':''}" data-go="${g.items[0][0]}" title="${g.t}">${ic(g.i,18)}<span>${g.t}</span></a>`;
  return`<div class="ng-h ${open?'open':''} ${on?'on':''}" data-act="ngtoggle" data-k="${g.k}" title="${g.t}">${ic(g.i,18)}<span>${g.t}</span><span class="chev">${ic('chev',14)}</span></div>${open?`<div class="ng-items">${g.items.map(i=>`<a class="ni ${i[0]===r?'on':''}" data-go="${i[0]}">${i[1]}${/ERP/.test(i[1])?'':''}${navBadge(i[0])}</a>`).join('')}</div>`:''}`}).join('')}</div><div class="sidefoot">${esc(user())} · ${ROLES[UI.role].n}<br>Demo data · saved in this browser</div>`}
function navBadge(k){const c=C();if(!c)return'';const n={'rq.board':()=>c.frozen?0:c.demand.filter(d=>d.status==='pending').length,'src.cs':()=>c.inv.length?csCounts(c).pend:0,'aw.appr':()=>({mkt:'mkt',fin:'fin',ed:'ed'}[c.appr.status]===UI.role?1:0),'exc':()=>DB.exc.filter(e=>e.status==='Open').length,'po.disp':()=>DB.cases.reduce((a,x)=>a+x.pos.filter(p=>p.ack==='Disputed').length,0)}[k];const v=n?n():0;return v?` <span class="nb">${v}</span>`:''}
ACT.ngtoggle=d=>{const nav=navFor(UI.role),g=nav.find(x=>x.k===d.k);const cur=UI.open[d.k]!==undefined?UI.open[d.k]:g.items.some(i=>i[0]===route().key);UI.open[d.k]=!cur;$('.side').innerHTML=sideHtml()};
function createItems(){return[['New purchase case','case.new','caseForm'],['Add requisition line','demand.edit','demandForm'],['New RFQ','rfq.create','rfqForm'],['New supplier','sup.edit','supForm'],['Record supplier PI','pi.record','piPick']].filter(x=>can(x[1]))}
function topHtml(){
 return`<div class="top"><button class="icon-btn hamb" data-act="mobnav" aria-label="Menu">${ic('menu')}</button><button class="icon-btn hide-m" data-act="sidetoggle" aria-label="Collapse sidebar">${ic('menu')}</button>
<div class="gs">${ic('search',15)}<input id="gsin" data-gs="1" placeholder="Search cases, RFQs, POs, suppliers, materials…" autocomplete="off"><div class="gsr" id="gsr" hidden></div></div>
<select class="fsel" data-case="1" title="Working case" style="max-width:230px">${DB.cases.map(c=>`<option value="${c.id}" ${c.id===UI.caseId?'selected':''}>${c.id} · ${esc(c.month)}</option>`).join('')}</select><div class="sp"></div>
<div class="rel"><button class="btn pri" data-act="menu" data-m="create">${ic('plus',15)} Create</button><div class="menu" id="menu-create" hidden>${createItems().map(x=>`<a data-act="${x[2]}">${x[0]}</a>`).join('')||'<a>Not available for this role</a>'}</div></div>
<button class="icon-btn" data-go="notif" title="Notifications">${ic('bell')}${bell()?`<span class="badge">${bell()}</span>`:''}</button>
<div class="rel"><div class="avatar" data-act="menu" data-m="user" title="${esc(user())}">${esc(user().replace(/[^A-Za-z ]/g,'').split(' ').map(x=>x[0]).slice(0,2).join(''))}</div><div class="menu" id="menu-user" hidden style="min-width:250px"><div style="padding:8px 10px"><b>${esc(user())}</b><div class="mut sm">${ROLES[UI.role].n}</div></div><hr><div style="padding:4px 10px" class="sm mut">Demo role switcher — test segregation of duties</div><div style="padding:0 10px 8px"><select data-role="1" style="width:100%">${Object.entries(ROLES).map(([k,r])=>`<option value="${k}" ${k===UI.role?'selected':''}>${r.n}</option>`).join('')}</select></div><hr><a data-act="theme">${ic('sun',15)} Toggle light / dark</a><a data-go="ad.demo">${ic('cfg',15)} Demo utilities</a></div></div></div>`}
function mbar(){return`<div class="mbar"><a data-go="home">${ic('home',20)}Home</a><a data-go="cases">${ic('layers',20)}Cases</a><a data-go="src.rfq">${ic('send',20)}RFQ</a><a data-go="src.cs">${ic('doc',20)}CS</a><a data-go="aw.appr">${ic('check',20)}Approve</a></div>`}
function skeleton(){return`<div class="skel" style="width:30%;height:22px"></div><div class="grid g4"><div class="card cp"><div class="skel"></div><div class="skel" style="width:50%"></div></div><div class="card cp"><div class="skel"></div></div><div class="card cp"><div class="skel"></div></div><div class="card cp"><div class="skel"></div></div></div><div class="card cp" style="margin-top:12px"><div class="skel"></div><div class="skel"></div><div class="skel"></div></div>`}
function render(){
  if(!DB)return;const{key,arg}=route();const pg=PAGES[key];const prev=$('.content');const sc=prev?prev.scrollTop:0;
  const nav=navFor(UI.role),allowed=nav.some(g=>g.items.some(i=>i[0]===key))||ROUTE_OK.includes(key)||key==='home';
  let P_;try{P_=pg?(allowed?pg(arg):{t:'Access restricted',sub:'Your demo role does not include this screen.',crumbs:['Restricted'],html:emptyState('Not available for '+ROLES[UI.role].n,'Use the role switcher (avatar menu, top right) to try a different role.')}):{t:'Page not found',crumbs:['Not found'],html:emptyState('This page does not exist','<button class="btn pri" data-go="home">Go to Command Center</button>')}}catch(e){console.error(e);P_={t:'Something went wrong',crumbs:['Error'],html:emptyState('Could not render this page',esc(e.message))}}
  if(key==='portal'){$('#app').innerHTML=`<div class="main" style="width:100%">${P_.html}</div>`;return}
  $('#app').innerHTML=`<aside class="side ${UI.col?'col':''} ${UI.mobNav?'openm':''}">${sideHtml()}</aside><div class="main">${topHtml()}<div class="content" id="content">${crumbHtml(P_.crumbs||[['Command Center','home']])}<div class="ph"><div><h1>${P_.t}</h1>${P_.sub?`<p>${P_.sub}</p>`:''}</div><div class="sp"></div><div class="acts">${P_.acts||''}</div></div>${roBanner()}<div id="pgbody">${P_.html}</div></div></div>${mbar()}`;
  const c=$('.content');if(c)c.scrollTop=UI.keepScroll?sc:0;UI.keepScroll=false;if(P_.mount)P_.mount();document.title=(P_.t||'').replace(/<[^>]+>/g,'')+' · OIL-PMS';
}
function renderKeep(){UI.keepScroll=true;render()}
function navigate(){readQ();UI.mobNav=false;if(UI.loading)return;UI.loading=true;const c=$('.content');if(c)c.innerHTML=skeleton();setTimeout(()=>{UI.loading=false;render()},UI.fast?0:90)}
/* ---------- events ---------- */
document.addEventListener('click',e=>{const el=e.target.closest('[data-act],[data-go],[data-ovclose]');
 if(!e.target.closest('.rel'))$$('.menu').forEach(m=>m.hidden=true);if(!e.target.closest('.gs')){const g=$('#gsr');if(g)g.hidden=true}
 const th=e.target.closest('th[data-ts]');if(th){const[i,k]=th.dataset.ts.split('|'),s=tstate(i);if(s.sort===k)s.dir*=-1;else{s.sort=k;s.dir=1}redraw(i);return}
 if(!el)return;
 if(el.dataset.ovclose&&e.target===el){closeDlg();return}
 if(el.dataset.act){if(el.tagName==='A')e.preventDefault();const f=ACT[el.dataset.act];if(f){try{f(el.dataset,el,e)}catch(er){console.error(er);toast('Action failed: '+er.message,'bad')}}else toast('Action not available: '+el.dataset.act,'warn');return}
 if(el.dataset.go){if(el.tagName==='A'&&(e.ctrlKey||e.metaKey||e.shiftKey||e.button===1))return;e.preventDefault();closeDlg();$$('.menu').forEach(m=>m.hidden=true);go(el.dataset.go)}});
document.addEventListener('submit',e=>{const f=e.target.closest('form[data-submit]');if(!f)return;e.preventDefault();const a=ACT[f.dataset.submit];if(a){try{a(fo(f),f)}catch(er){console.error(er);ferr(er.message)}}});
document.addEventListener('input',e=>{const t=e.target;
 if(t.dataset.tq){const s=tstate(t.dataset.tq);s.q=t.value;s.page=1;redraw(t.dataset.tq)}
 else if(t.dataset.gs){gsearch(t.value)}
 else if(t.dataset.chg){const f=t.closest('form');ACT[t.dataset.chg](fo(f),f,t)}});
document.addEventListener('change',e=>{const t=e.target;
 if(t.dataset.tf){const[i,k]=t.dataset.tf.split('|'),s=tstate(i);s.f[k]=t.value;s.page=1;redraw(i)}
 else if(t.dataset.tper){const s=tstate(t.dataset.tper);s.per=+t.value;s.page=1;redraw(t.dataset.tper)}
 else if(t.dataset.tv){const s=tstate(t.dataset.tv),v=DB.views[t.dataset.tv][+t.value];if(v){Object.assign(s,JSON.parse(JSON.stringify(v.st)),{page:1});render();toast('View “'+v.n+'” applied')}}
 else if(t.dataset.tcol){const[i,k]=t.dataset.tcol.split('|'),s=tstate(i);s.hide[k]=!t.checked;redraw(i)}
 else if(t.dataset.tsel){const[i,k]=t.dataset.tsel.split('|');tstate(i).sel[k]=t.checked;redraw(i)}
 else if(t.dataset.ta){const i=t.dataset.ta,s=tstate(i),key=TB[i].o.key||(r=>r.id);tfilter(i).slice((s.page-1)*s.per,s.page*s.per).forEach(r=>s.sel[key(r)]=t.checked);redraw(i)}
 else if(t.dataset.case!==undefined){UI.caseId=t.value;setQ('case',t.value,true);toast('Working case: '+t.value);render()}
 else if(t.dataset.role){UI.role=t.value;toast('Demo role: '+ROLES[UI.role].n+' ('+user()+')');const nav=navFor(UI.role);if(!(nav.some(g=>g.items.some(i=>i[0]===route().key))||ROUTE_OK.includes(route().key)))go('home');else render()}
 else if(t.dataset.chg){const f=t.closest('form');ACT[t.dataset.chg](fo(f),f,t)}
 else if(t.dataset.fsel){ACT[t.dataset.fsel](t.value,t)}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeDlg();$$('.menu').forEach(m=>m.hidden=true)}if(e.key==='Enter'&&e.target.dataset&&e.target.dataset.gs){const a=$('#gsr a');if(a)a.click()}});
window.addEventListener('popstate',navigate);
ACT.menu=(d,el)=>{const m=$('#menu-'+d.m);const was=m.hidden;$$('.menu').forEach(x=>x.hidden=true);m.hidden=!was};
ACT.sidetoggle=()=>{UI.col=!UI.col;$('.side').classList.toggle('col',UI.col)};ACT.mobnav=()=>{UI.mobNav=!UI.mobNav;$('.side').classList.toggle('openm',UI.mobNav)};
ACT.theme=()=>{const r=document.documentElement;const dark=r.dataset.theme==='dark'||(!r.dataset.theme&&matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches);r.dataset.theme=dark?'light':'dark'};
ACT.go=d=>go(d.to);
function gsearch(q){const box=$('#gsr');if(!box)return;q=q.trim().toLowerCase();if(q.length<1){box.hidden=true;return}
 const m=s=>String(s).toLowerCase().includes(q),G=[];
 const add=(t,l,f)=>{if(l.length)G.push(`<h5>${t}</h5>`+l.slice(0,5).map(f).join(''))};
 add('Purchase cases',DB.cases.filter(c=>m(c.id+c.name+c.month+(c.booking.serial||''))),c=>`<a data-go="case/${c.id}"><b>${esc(c.id)}</b> ${esc(c.name)}<small>${caseStatus(c)}</small></a>`);
 add('RFQs',DB.cases.filter(c=>c.rfq.no&&m(c.rfq.no)),c=>`<a data-go="rfq/${c.id}"><b>${c.rfq.no}</b><small>${c.id}</small></a>`);
 add('Purchase orders',allPOs().filter(p=>m(p.no+supOf(p.sid).name)),p=>`<a data-go="po/${p.no}"><b>${p.no}</b> ${esc(supOf(p.sid).name)}<small>${p._c.id}</small></a>`);
 add('Suppliers',DB.sup.filter(s=>m(s.id+s.name+s.contact)),s=>`<a data-go="sup/${s.id}"><b>${esc(s.name)}</b><small>${s.id}</small></a>`);
 add('Materials',MATS.filter(p=>m(p.id+p.name+p.meta)),p=>`<a data-go="ad.master"><b>${p.id}</b> ${esc(p.name)}</a>`);
 add('Exceptions',DB.exc.filter(e=>m(e.id+e.type+e.ref+e.detail)),e=>`<a data-go="exc"><b>${e.id}</b> ${esc(e.type)}<small>${esc(e.ref)}</small></a>`);
 box.innerHTML=G.join('')||`<div class="empty"><b>No results for “${esc(q)}”</b>Try a PO number, supplier or material.</div>`;box.hidden=false}
/* ---------- shared form helpers ---------- */
const caseOpts=()=>DB.cases.map(c=>[c.id,`${c.id} · ${c.month}`]);
const matOpts=()=>MATS.concat(DB.mats).map(p=>[p.id,`${p.id} · ${p.name}`]);
const supOpts=()=>DB.sup.map(s=>[s.id,s.name]);
const todayStr=()=>isoD(new Date(NOW()));
function guard(perm,fn){return(...a)=>{if(!need(perm))return;return fn(...a)}}
const tag=(t,cls='')=>`<span class="pill ${cls}">${esc(t)}</span>`;
const money=n=>usd(n);
function kpiRow(items){return`<div class="grid g${Math.min(6,Math.max(2,items.length))}">${items.map(i=>kpi(i[0],i[1],i[2]||{})).join('')}</div>`}
function exportJSON(){const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify(DB,null,2)],{type:'application/json'}));a.download='oil-pms-state.json';a.click()}

