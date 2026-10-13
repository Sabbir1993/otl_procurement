/* =====================  SUPPLIER PORTAL — driven by the parameter dictionary  ===================== */
const pctx=()=>{const p=UI.p;let c=CASE(p.cid);if(!c||!c.inv.some(i=>i.sid===p.sid)){c=DB.cases.find(x=>x.inv.some(i=>i.sid===p.sid))||C();p.cid=c.id}return c};
const PTABS=[['general','Company'],['commercial','Commercial'],['technical','Technical'],['delivery','Delivery & packing'],['sample','Sample & documents'],['review','Review & submit']];
const lineVals=q=>({...(q.spec||{}),listPrice:q.listPrice,tiers:q.tiers||[],disc:q.disc,moq:q.moq});
function pf(c,sid){
  if(c.portal[sid])return c.portal[sid];
  const sp=supOf(sid),iv=c.inv.find(i=>i.sid===sid)||{pids:[]},H=hdrOf(c,sid),qs=c.quotes[sid]||{};
  const h=Object.keys(H).length?clone(H):{legal:sp.name,country:sp.cc==='CN'?'China':sp.cc,address:sp.address||'',kind:'Manufacturer',site:'',contactName:sp.contact,desig:sp.desig||'',email:sp.email,phone:sp.phone,quoteRef:'',quoteDate:'',currency:'USD',payMethod:PAYM[0],payDetail:'',advPct:'',creditDays:'',bankCh:'Applicant (OIL)',incoterm:'FOB',place:'',validity:'',claims:'',loadPort:'',dischargePort:'Chattogram Port',partial:'',transship:'',origin:'',compliance:'',exceptions:'',decName:''};
  const l={};
  iv.pids.forEach(pid=>{const q=qs[pid],p=prod(pid);l[pid]=q?clone(lineVals(q)):{quoteUnit:p.uom,qtyQuoted:reqQty(c,pid),listPrice:'',tiers:[],disc:'',moq:'',grade:p.meta,code:spName(sid,pid),comp:p.comp,sampleAvail:''}});
  return c.portal[sid]={h,l,decl:{ok:false}};
}
function pfErr(c,sid){
  const F=pf(c,sid),iv=c.inv.find(i=>i.sid===sid),e=[];
  FDEF.forEach(f=>{
    const pids=f.s==='h'?['']:iv.pids;
    pids.forEach(pid=>{
      if(f.s==='l'&&!fApplies(f,pid))return;
      const v=fGet(F,f,pid),tag=f.s==='l'?`${pid}: `:'';
      if(fReq(c,f,pid,F)&&!hasVal(v))return e.push({tab:f.tab,k:f.k,pid,m:`${tag}${f.l} is required`});
      if(!hasVal(v))return;
      if(f.t==='number'&&!(+v>=0))e.push({tab:f.tab,k:f.k,pid,m:`${tag}${f.l} must be a valid number`});
      if(['listPrice','moq','qtyQuoted','prodDays','shipDays'].includes(f.k)&&!(+v>0))e.push({tab:f.tab,k:f.k,pid,m:`${tag}${f.l} must be greater than zero`});
      if((f.k==='advPct'||f.k==='disc')&&(+v<0||+v>100))e.push({tab:f.tab,k:f.k,pid,m:`${f.l} must be between 0 and 100`});
      if(f.k==='email'&&!/^\S+@\S+\.\S+$/.test(v))e.push({tab:f.tab,k:f.k,pid,m:'Email address is not valid'});
      if(f.k==='validity'&&Date.parse(v+'T00:00:00Z')<=NOW())e.push({tab:f.tab,k:f.k,pid,m:'Quotation validity must be a future date'});
    });
  });
  iv.pids.forEach(pid=>{const t=(F.l[pid].tiers||[]).filter(x=>hasVal(x.min)||hasVal(x.price));t.forEach(x=>{if(!(+x.min>0&&+x.price>0))e.push({tab:'commercial',k:'tiers',pid,m:`${pid}: each price tier needs a minimum quantity and a price`})});if(t.length>1&&t.some((x,i)=>i&&+x.min<=+t[i-1].min))e.push({tab:'commercial',k:'tiers',pid,m:`${pid}: tier minimum quantities must increase`})});
  if(!F.decl.ok)e.push({tab:'review',k:'decl',pid:'',m:'Tick the declaration to confirm and submit'});
  return e;
}
PAGES.portal=()=>{
  const c=pctx(),p=UI.p,sid=p.sid,sp=supOf(sid),iv=c.inv.find(i=>i.sid===sid);
  const bar=`<div class="pbar"><b>Supplier External Portal</b><span class="pill p-warn">Preview — isolated from internal screens</span><span class="sp"></span><label>Case <select data-fsel="pCase">${DB.cases.map(x=>`<option value="${x.id}" ${x.id===c.id?'selected':''}>${x.id}</option>`).join('')}</select></label><label>Previewing as <select data-fsel="pSup">${DB.sup.map(s=>`<option value="${s.id}" ${s.id===sid?'selected':''}>${esc(s.name)}</option>`).join('')}</select></label>
   <div class="seg"><label data-act="pMode" data-m="rfq"><span style="padding:0 4px">${p.mode==='rfq'?'● ':''}RFQ</span></label><label data-act="pMode" data-m="po"><span style="padding:0 4px">${p.mode==='po'?'● ':''}PO</span></label></div><a class="btn sm" data-go="home">← Back to PMS</a></div>`;
  return{t:'Supplier portal',html:bar+`<div class="pbody">${p.mode==='rfq'?portalRfq(c,sid,sp,iv):portalPo(c,sid,sp)}</div>`};
};
ACT.pCase=v=>{UI.p.cid=v;render()};ACT.pSup=v=>{UI.p.sid=v;UI.p.tab='general';render()};ACT.pMode=d=>{UI.p.mode=d.m;render()};ACT.pTab=d=>{UI.p.tab=d.t;render()};
function fieldHtml(c,f,pid,F,errs,show){
  const req=fReq(c,f,pid,F),val=fGet(F,f,pid),bad=show&&errs.some(e=>e.k===f.k&&e.pid===(pid||''));
  const a=`data-s="${f.s}" data-k="${f.k}"${pid?` data-pid="${pid}"`:''}`;let ctl;
  if(f.t==='select')ctl=`<select data-fsel="pf" ${a} class="${bad?'bad':''}">${f.opts.map(o=>`<option ${String(val)===o?'selected':''}>${esc(o)}</option>`).join('')}</select>`;
  else if(f.t==='textarea')ctl=`<textarea rows="2" data-fsel="pf" ${a} class="${bad?'bad':''}">${esc(val)}</textarea>`;
  else if(f.t==='file')ctl=`<input type="file" data-fsel="pfile" ${a} accept=".pdf,.xlsx,.jpg,.png">${val?`<small>Attached: <b>${esc(val)}</b></small>`:''}`;
  else if(f.t==='tiers'){const T=[0,1,2].map(i=>(val||[])[i]||{min:'',price:''});ctl=`<div class="tiers">${T.map((t,i)=>`<div><span class="mut sm">Tier ${i+1} from</span><input type="number" min="0" placeholder="Min qty" data-fsel="ptier" data-pid="${pid}" data-i="${i}" data-f="min" value="${esc(t.min)}"><input type="number" min="0" step="0.0001" placeholder="Unit price" data-fsel="ptier" data-pid="${pid}" data-i="${i}" data-f="price" value="${esc(t.price)}"></div>`).join('')}</div>`}
  else ctl=`<input type="${f.t}" value="${esc(val)}" ${f.step?`step="${f.step}"`:''} ${f.ro?'disabled':''} data-fsel="pf" ${a} class="${bad?'bad':''}">`;
  return`<label class="f ${f.full?'full':''}"><span>${f.l}${req?' <b style="color:var(--bad)">*</b>':''}${!req&&f.req==='C'?' <small class="mut">(optional)</small>':''}</span>${ctl}${f.h?`<small class="mut">${f.h}</small>`:''}</label>`;
}
function secBlocks(c,fields,pid,F,errs,show){
  const secs=[];fields.forEach(f=>{let s=secs.find(x=>x.n===f.sec);if(!s)secs.push(s={n:f.sec,f:[]});s.f.push(f)});
  return secs.map(s=>`<div class="sec"><h4>${s.n}</h4><div class="fg">${s.f.map(f=>fieldHtml(c,f,pid,F,errs,show)).join('')}</div></div>`).join('');
}
function portalTabBody(c,sid,iv,tab,F,errs){
  const show=true,H=FDEF.filter(f=>f.tab===tab&&f.s==='h'),L=FDEF.filter(f=>f.tab===tab&&f.s==='l');
  let b=H.length?secBlocks(c,H,'',F,errs,show):'';
  if(L.length)b+=iv.pids.map(pid=>{const lf=L.filter(f=>fApplies(f,pid));return`<div class="card cp mcard"><div class="mh"><b>${pid} · ${esc(prod(pid).name)}</b><span class="mut sm">${esc(prod(pid).comp)} · supplier product ${esc(spName(sid,pid))} · required ${n0(reqQty(c,pid))} ${prod(pid).uom}</span></div>${secBlocks(c,lf,pid,F,errs,show)}</div>`}).join('');
  return b;
}
function portalRfq(c,sid,sp,iv){
  if(!iv)return emptyState('No RFQ invitation for this supplier in '+c.id,`${esc(sp.name)} has not been invited in this case.${DB.cal[sp.cal]&&DB.cal[sp.cal].approved?'':' Its working-day calendar is not approved.'}`);
  if(iv.state==='Sent'&&!iv.submitted&&!ivDone(c,iv)){iv.state='Viewed';iv.ip='[MASKED-IP]';log('Link viewed',iv.rfq,sp.name,{by:'Supplier',cid:c.id});save()}
  const F=pf(c,sid),closed=NOW()>iv.deadline,done=iv.submitted,tab=UI.p.tab,errs=pfErr(c,sid);
  const hdr=`<div class="phead"><div class="row" style="display:flex;gap:8px;flex-wrap:wrap"><span class="pill nd">${ic('lock',12)} Secure link · token ${esc(iv.tok)}</span><span class="pill">${esc(ivState(c,iv))}</span></div><h2 style="margin-top:8px">Request for Quotation ${esc(iv.rfq)}</h2><p>Osman Interlinings Limited (OIL) invites ${esc(sp.name)} to quote for ${iv.pids.length} material(s). Only your own response is visible here.</p>
   <div class="dlx"><div><small>Deadline · China time</small><b>${dCN(iv.deadline)}</b></div><div><small>Deadline · Bangladesh</small><b>${tzf(iv.deadline,'Asia/Dhaka',T24)} BST (UTC+06:00)</b></div><div><small>Time left</small><b>${closed?'Closed':Math.max(0,Math.floor((iv.deadline-NOW())/DAY))+' d '+Math.floor(((iv.deadline-NOW())%DAY)/3600e3)+' h'}</b></div></div>${iv.ext.length?`<p style="margin-top:8px">Deadline extended — original ${dCN(iv.origDeadline)}</p>`:''}</div><div style="height:12px"></div>`;
  if(iv.revoked)return hdr+alertBox('bad','This link has been revoked','Contact the OIL Procurement Department.');
  if(closed&&!done)return hdr+alertBox('bad','Submission closed','The deadline has passed and final submission is disabled server-side. Drafts are not evaluated. Contact Procurement to request an authorised extension.')+`<div style="height:10px"></div><div class="mut sm">Server time ${dBD(NOW())} is the authority — your device clock is ignored.</div>`;
  if(done)return hdr+`<div class="card cp">${alertBox('info','Quotation received',`Reference <b>${iv.quoRef}</b> · revision ${iv.rev} · ${dBD(iv.submittedAt)}. Status: received, under evaluation. This acknowledgment is not supplier selection or PO acceptance.`)}<div style="margin:12px 0">${quoteSheet(c,sid)}</div><div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn" onclick="window.print()">Print submission receipt</button>${closed?'':`<button class="btn pri" data-act="pRevise">Revise quotation (new timestamped version)</button>`}</div></div>`;
  const rf=(c.rfq.reqFields||[]).map(k=>(EXTRA_REQ.find(x=>x[0]===k)||[])[1]).filter(Boolean);
  const tabErr=k=>errs.filter(e=>e.tab===k).length;
  const body=tab==='review'?`${errs.filter(e=>e.tab!=='review'||e.k!=='decl').length?alertBox('bad',`${errs.filter(e=>e.k!=='decl').length} item(s) block final submission`,`<ul style="margin:6px 0 0 18px">${errs.filter(e=>e.k!=='decl').map(e=>`<li><a data-act="pTab" data-t="${e.tab}">${esc(e.m)}</a></li>`).join('')}</ul>`):alertBox('info','All required parameters are complete','Review the declaration and submit.')}<div style="margin-top:12px">${portalTabBody(c,sid,iv,'review',F,errs)}</div>
    <label style="display:flex;gap:8px;margin:14px 0"><input type="checkbox" data-fsel="pdecl" ${F.decl.ok?'checked':''}> <span>I confirm the information in this quotation is accurate, any deviation from the RFQ specification is listed above, and I am authorised to submit it on behalf of ${esc(sp.name)}.</span></label><button class="btn pri" data-act="pSubmit">Submit quotation</button>`:portalTabBody(c,sid,iv,tab,F,errs);
  return hdr+(rf.length?alertBox('warn','OIL requires these extra parameters for this RFQ',rf.join(' · ')):'')+`<div class="card" style="margin-top:${rf.length?'12px':'0'}"><div class="tabs" style="padding:0 10px">${PTABS.map(([k,l])=>`<div class="tab ${tab===k?'on':''}" data-act="pTab" data-t="${k}">${l}${tabErr(k)?` <span class="pill p-bad" style="padding:0 6px">${tabErr(k)}</span>`:''}</div>`).join('')}</div><div class="cp">${body}</div><div class="cp" style="border-top:1px solid var(--line);display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span class="mut sm">* required. Drafts are saved on this link but <b>not evaluated</b> — only a submitted version counts.</span><span style="flex:1"></span><button class="btn" data-act="pDraft">Save draft</button>${tab!=='review'?`<button class="btn pri" data-act="pTab" data-t="${PTABS[Math.min(5,PTABS.findIndex(x=>x[0]===tab)+1)][0]}">Next ›</button>`:''}</div></div>`;
}
const REFRESH_KEYS=['payMethod','compliance','sampleDate','sampleAvail','kind'];
ACT.pf=(v,el)=>{const c=pctx(),F=pf(c,UI.p.sid),k=el.dataset.k;if(el.dataset.s==='h')F.h[k]=v;else F.l[el.dataset.pid][k]=v;save();if(REFRESH_KEYS.includes(k))render()};
ACT.ptier=(v,el)=>{const c=pctx(),F=pf(c,UI.p.sid),L=F.l[el.dataset.pid];L.tiers=L.tiers||[];while(L.tiers.length<3)L.tiers.push({min:'',price:''});L.tiers[+el.dataset.i][el.dataset.f]=v;save()};
ACT.pfile=(v,el)=>{if(!el.files[0])return;const c=pctx(),F=pf(c,UI.p.sid);if(el.dataset.s==='h')F.h[el.dataset.k]=el.files[0].name;else F.l[el.dataset.pid][el.dataset.k]=el.files[0].name;save();render()};
ACT.pdecl=(v,el)=>{pf(pctx(),UI.p.sid).decl.ok=el.checked;save()};
ACT.pDraft=()=>{const c=pctx(),iv=c.inv.find(i=>i.sid===UI.p.sid);if(NOW()>iv.deadline)return toast('Submission closed — draft not saved.','bad');if(!iv.submitted)iv.state='Draft started';log('Draft saved',iv.rfq,supOf(UI.p.sid).name,{by:'Supplier',cid:c.id});save();toast('Draft saved (not evaluated)');render()};
ACT.pRevise=()=>{const c=pctx(),iv=c.inv.find(i=>i.sid===UI.p.sid);if(NOW()>iv.deadline)return toast('Deadline passed.','bad');iv.submitted=false;iv.state='Draft started';delete c.portal[UI.p.sid];toast('Editing creates revision '+(iv.rev+1));save();render()};
function buildLine(c,sid,pid,F,pv,H){
  const l=F.l[pid],qty=reqQty(c,pid),tiers=(l.tiers||[]).filter(x=>+x.min>0&&+x.price>0).map(x=>({min:+x.min,price:+x.price})).sort((a,b)=>a.min-b.min);
  const q={listPrice:+l.listPrice,tiers,disc:+l.disc||0,moq:+l.moq};q.price=unitPriceFor(q,qty);
  const mat=q.price*qty,days=(+l.prodDays)+(+l.shipDays),fpc=(DB.settings.freightPerCbm>0&&+l.cbm>0)?Math.round(+l.cbm*DB.settings.freightPerCbm):Math.round(qty*.055);
  const spec={...l};['listPrice','tiers','disc','moq'].forEach(k=>delete spec[k]);
  return{...q,orig:pv.orig!=null?pv.orig:q.price,days,freight:fpc,ins:Math.round(mat*.0065),duty:Math.round(mat*.15),bank:bankCost(H),q:pv.q||70,sample:pv.sample||'Pending',qty,incoterm:H.incoterm,place:H.place,payment:payText(H),terms:termsScore(H),hist:pv.hist||60,validity:Date.parse(H.validity+'T00:00:00Z'),rev:(pv.rev||0)+1,spec};
}
ACT.pSubmit=()=>{
  const c=pctx(),sid=UI.p.sid,iv=c.inv.find(i=>i.sid===sid),sp=supOf(sid),F=pf(c,sid);
  if(iv.revoked||NOW()>iv.deadline){raise('Late submission',iv.rfq+'/'+sid,`${sp.name} attempted to submit after cut-off — rejected server-side`,{cid:c.id});save();return toast('Rejected: deadline passed (server time).','bad')}
  const errs=pfErr(c,sid);if(errs.length){UI.p.tab=errs[0].tab;render();return toast(`${errs.length} required item(s) missing — final submit blocked.`,'bad')}
  const rev=(iv.rev||0)+1,prev=c.quotes[sid]||{},H={...F.h,decAt:DB.now+(DB.tick++)*1000};c.quotes[sid]={};c.quoteHdr[sid]=H;
  iv.pids.forEach(pid=>{c.quotes[sid][pid]=buildLine(c,sid,pid,F,prev[pid]||{},H);c.quotes[sid][pid].rev=rev});
  iv.submitted=true;iv.state='Submitted';iv.submittedAt=H.decAt;iv.rev=rev;iv.quoRef=iv.quoRef||nid('QUO');
  if(rev>1)raise('Quote revision',iv.quoRef,`${sp.name} revision ${rev} — new timestamped version`,{cid:c.id});
  if(H.email!==sp.email||H.contactName!==sp.contact){sp.email=H.email;sp.contact=H.contactName;sp.emailVerified=false;raise('Supplier contact changed',sp.id,`${sp.name} changed PO contact — callback verification needed before any PO is sent`,{cid:c.id})}
  if(sp.address&&H.address!==sp.address)raise('Supplier identity changed',sp.id,`${sp.name} registered address differs from the vendor master — confirm before award`,{cid:c.id});
  if(/except/i.test(H.compliance||''))raise('Spec deviation declared',iv.quoRef,`${sp.name} declared deviations: ${H.exceptions}`,{cid:c.id});
  mail(c,'C',`${sp.contact} <${sp.email}>`,{...rfqVars(c,iv),SUBMISSION_ID:iv.quoRef,SUBMITTED_AT:dBD(iv.submittedAt)});log('Quotation submitted',iv.quoRef,`${sp.name} · rev ${rev} · ${iv.pids.length} lines`,{by:'Supplier',cid:c.id});
  notify('ok',`${sp.name} submitted ${iv.quoRef}`,'src.quotes',{role:'exec'});delete c.portal[sid];save();toast(`Submitted — reference ${iv.quoRef}. Confirmation email sent.`);render()};
/* ---- complete read-only parameter sheet (portal receipt + internal quotation page) ---- */
function quoteSheet(c,sid){
  const H=hdrOf(c,sid),q=c.quotes[sid]||{},F0={h:H,l:{}};
  const show=(f,v,pid,F)=>{if(f.t==='tiers')return(v||[]).length?v.map(t=>`≥ ${n0(t.min)}: ${usd3(t.price)}`).join(' · '):'<span class="mut">—</span>';if(f.t==='file')return v?`📎 ${esc(v)}`:(fReq(c,f,pid,F)?'<span class="pill p-bad">missing</span>':'<span class="mut">—</span>');if(!hasVal(v))return fReq(c,f,pid,F)?'<span class="pill p-bad">blank</span>':'<span class="mut">—</span>';if(f.k==='listPrice')return usd3(v);if(f.k==='disc')return v+'%';return esc(v)};
  const group=(fields,get,pid,F)=>{const secs=[];fields.forEach(f=>{let s=secs.find(x=>x.n===f.sec);if(!s)secs.push(s={n:f.sec,f:[]});s.f.push(f)});return secs.map(s=>`<div class="sec"><h4>${s.n}</h4>${dl(s.f.map(f=>[f.l,show(f,get(f),pid,F)]))}</div>`).join('')};
  const hf=FDEF.filter(f=>f.s==='h');
  return`<div class="grid g2" style="align-items:start"><div>${group(hf.filter(f=>['general','commercial'].includes(f.tab)),f=>H[f.k],'',F0)}</div><div>${group(hf.filter(f=>['delivery','review'].includes(f.tab)),f=>H[f.k],'',F0)}</div></div>`+Object.entries(q).map(([pid,x])=>{const V=lineVals(x),F={h:H,l:{[pid]:V}};const lf=FDEF.filter(f=>f.s==='l'&&fApplies(f,pid));return`<div class="card cp mcard" style="margin-top:12px"><div class="mh"><b>${pid} · ${esc(prod(pid).name)}</b><span class="mut sm">effective unit price ${usd3(x.price)} at ${n0(x.qty)} ${prod(pid).uom} · landed ${usd(landed(x))} · ${pill(x.sample)}</span></div><div class="grid g2" style="align-items:start"><div>${group(lf.filter(f=>['commercial','technical'].includes(f.tab)),f=>V[f.k],pid,F)}</div><div>${group(lf.filter(f=>['delivery','sample'].includes(f.tab)),f=>V[f.k],pid,F)}</div></div></div>`}).join('');
}
function portalPo(c,sid,sp){
  const pos=c.pos.filter(p=>p.sid===sid&&p.sent&&p.status==='Sent');
  if(!pos.length)return emptyState('No purchase order for this supplier in '+c.id,'A PO appears here after Finance/ED approval and a successful automatic dispatch.');
  return`<div class="phead"><h2>Purchase order acknowledgment</h2><p>${esc(sp.name)} · you can only <b>Accept</b> or <b>Dispute</b> a PO — never modify it.</p></div><div style="height:12px"></div>`+pos.map(po=>`<div class="card" style="margin-bottom:14px"><h3>${poFull(po)} ${pill(po.ack)}<span class="sp"></span>${po.ack==='Awaiting'?`<button class="btn pri" data-act="pAccept" data-no="${po.no}">Accept PO</button><button class="btn bad" data-act="pDispute" data-no="${po.no}">Dispute…</button>`:''}</h3><div class="cp">${poDoc(c,po)}${po.ack==='Accepted'?`<div style="margin-top:10px">${alertBox('info','PO accepted','Please submit your Proforma Invoice referencing this PO number; it must match the approved terms.')}</div>`:''}${po.dispute?`<div style="margin-top:10px">${alertBox('warn','Dispute recorded',esc(po.dispute)+' — OIL Procurement will respond; updated terms require an approved revision.')}</div>`:''}</div></div>`).join('');
}
const poFind=no=>{for(const c of DB.cases){const p=c.pos.find(x=>x.no===no);if(p)return[c,p]}return[]};
ACT.pAccept=d=>{const[c,po]=poFind(d.no);poAck(c,po,'ok');save();toast('PO accepted');render()};
ACT.pDispute=d=>{const[c,po]=poFind(d.no);openDlg('Dispute PO '+po.no,`${hid('no',po.no)}<div class="fg">${F('t','Which term is incorrect?','select','',{opts:['Unit price','Quantity','Incoterms / place','Payment terms','Delivery date','Specification','Claims / warranty clause','Other'],cls:'full'})}${F('r','Details','textarea','',{req:1,cls:'full',rows:3})}</div>`,{act:'pDisputeSave',ok:'Submit dispute',danger:1})};
ACT.pDisputeSave=o=>{const[c,po]=poFind(o.no);poAck(c,po,'no',`${o.t}: ${o.r}`);save();closeDlg();render()};
