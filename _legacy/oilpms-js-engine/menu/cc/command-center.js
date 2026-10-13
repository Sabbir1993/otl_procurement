/* =====================  COMMAND CENTER  ===================== */
function tasksFor(role){
  const T=[],add=(t,go,k,cid)=>T.push({t,go,k:k||'',cid});
  DB.cases.forEach(c=>{
    const cnt=csCounts(c),A=c.appr,pend=c.demand.filter(d=>d.status==='pending').length,i=flowIdx(c);if(i>=8)return;
    if(role==='exec'||role==='sup'){
      if(!c.frozen&&pend)add(`Verify ${pend} pending requisition line(s)`,'rq.board','warn',c.id);
      else if(!c.frozen)add('Freeze the validated requisition','rq.board','ok',c.id);
      if(c.frozen&&!c.booking.confirmed)add('Confirm book qty — signed document required','rq.booking','warn',c.id);
      if(c.booking.confirmed&&!c.drafts.length)add('Create the RFQ from the confirmed booking','src.rfq','warn',c.id);
      if(c.drafts.some(d=>d.status==='Draft'))add('Review and send RFQ drafts','src.rfq','warn',c.id);
      if(c.drafts.some(d=>d.status==='Blocked'))add('RFQ draft blocked — supplier calendar not approved','ad.cal','bad',c.id);
      DB.exc.filter(e=>e.type==='No bid'&&e.status==='Open'&&e.cid===c.id).forEach(e=>add(`No bid for ${e.ref} — extend deadline or re-tender`,'src.track','bad',c.id));
      if(c.inv.length&&cnt.pend&&rfqClosed(c))add(`${cnt.pend} product(s) to decide in the comparative statement`,'src.cs','warn',c.id);
      if(c.inv.length&&cnt.total&&!cnt.pend&&!c.csLocked&&A.status!=='approved')add('Comparison complete — submit the award recommendation','aw.rec','ok',c.id);
      if(['returned','rejected'].includes(A.status))add(`${A.id} ${A.status} — revise and resubmit`,'src.cs','bad',c.id);
      c.pos.filter(p=>!p.sent&&!p.frozen).forEach(p=>add(`${p.no}: ${poStatus(c,p)==='Ready to Dispatch'?'dispatch to supplier':'fix correction-queue items'}`,'po.reg',poStatus(c,p)==='Ready to Dispatch'?'warn':'bad',c.id));
      c.pos.filter(p=>p.ack==='Disputed').forEach(p=>add(`${p.no} disputed — resolve`,'po.disp','bad',c.id));
      c.pos.filter(p=>p.ack==='Accepted'&&!c.pis[p.no]).forEach(p=>add(`${p.no} accepted — record supplier PI`,'imp.pi','warn',c.id));
    }
    if(role==='mkt'&&A.status==='mkt')add(`Acknowledge ${A.id} v${A.version}`,'aw.appr','warn',c.id);
    if(role==='fin'){if(A.status==='fin')add(`Finance approval for ${A.id} v${A.version}`,'aw.appr','warn',c.id);c.pos.forEach(p=>{const l=c.lc[p.no],pi=c.pis[p.no];if(l&&l.docsDone&&!l.appr)add(`Approve LC for ${p.no}`,'imp.lc','warn',c.id);if(pi&&pi.bankChanged&&!pi.bankV.fin)add(`Verify changed bank details (${p.no})`,'imp.pi','bad',c.id)})}
    if(role==='ed'&&A.status==='ed')add(`Final bulk validation of ${A.id} v${A.version}`,'aw.appr','warn',c.id);
    if(role==='qa'){let n=0;Object.values(c.quotes).forEach(q=>Object.values(q).forEach(x=>{if(['Pending','Conditional'].includes(x.sample))n++}));if(n)add(`${n} sample result(s) pending or conditional`,'src.samples','warn',c.id)}
    if(role==='imp'){c.pos.filter(p=>p.ack==='Accepted'&&!c.pis[p.no]).forEach(p=>add(`${p.no}: record supplier PI`,'imp.pi','warn',c.id));c.pos.forEach(p=>{const pi=c.pis[p.no],l=c.lc[p.no];if(pi&&pi.approved&&!(l&&l.docsDone))add(`${p.no}: complete import & banking checks`,'imp.pi','warn',c.id);if(l&&l.appr&&!l.open)add(`${p.no}: open LC`,'imp.lc','ok',c.id)})}
  });
  if(role==='admin'){const n=Object.values(DB.cal).filter(x=>!x.approved).length;if(n)add(`${n} working-day calendar(s) awaiting approval`,'ad.cal','warn');add(`${DB.policy.filter(p=>p.st==='Open').length} open policy decisions`,'ad.policy')}
  return T;
}
const spendBySup=()=>{const m={};DB.cases.forEach(c=>lineAwards(c).forEach(x=>m[x.sid]=(m[x.sid]||0)+x.amt));return Object.entries(m).sort((a,b)=>b[1]-a[1])};
PAGES.home=()=>{
  const c=C(),T=tasksFor(UI.role),open=DB.exc.filter(e=>e.status==='Open'),act=DB.cases.filter(x=>flowIdx(x)<8);
  const dl=[];DB.cases.forEach(x=>x.inv.filter(i=>!ivDone(x,i)).forEach(i=>dl.push({c:x,i})));dl.sort((a,b)=>a.i.deadline-b.i.deadline);
  const po=allPOs(),spend=spendBySup(),mx=Math.max(1,...spend.map(s=>s[1]));
  const col=FLOW.map(f=>f[0]).concat(['Completed']);
  return{t:'Command Center',sub:'Every import purchase case, from requisition to LC opening — what is waiting, who must act, and what is next.',crumbs:['Command Center'],
  acts:can('case.new')?`<button class="btn pri" data-act="caseForm">${ic('plus',15)} New purchase case</button>`:'',
  html:`<div class="hero"><h2>Import purchases · ${act.length} active case${act.length===1?'':'es'}</h2><p>${T.length?`You have <b>${T.length}</b> item(s) waiting as ${ROLES[UI.role].n}.`:`Nothing is waiting for ${ROLES[UI.role].n}. Switch role to see other queues.`}</p>
   <div class="hs"><div><b>${act.length}</b><small>Active cases</small></div><div><b>${po.filter(p=>p.sent&&p.ack==='Awaiting').length}</b><small>POs awaiting acknowledgment</small></div><div><b>${DB.cases.reduce((a,x)=>a+x.inv.filter(i=>!ivDone(x,i)).length,0)}</b><small>RFQ links open</small></div><div><b>${open.length}</b><small>Open exceptions</small></div></div></div>
   ${kpiRow([['Working case',c.id,{sub:c.month,go:'case/'+c.id}],['Requisition lines pending',c.demand.filter(d=>d.status==='pending').length,{cls:c.demand.some(d=>d.status==='pending')?'warn':'',go:'rq.board'}],['Quotes received',`${c.inv.filter(i=>i.submitted).length}/${c.inv.length}`,{go:'src.track'}],['Products decided',`${csCounts(c).done}/${csCounts(c).total}`,{go:'src.cs'}],['Awarded value',usd(sum(lineAwards(c),x=>x.amt)),{sub:'material, USD',go:'aw.rec'}],['Open exceptions',open.length,{cls:open.length?'bad':'ok',go:'exc'}]])}
   <div style="margin-top:12px">${flowBar(c,'')}${nextCard(c)}</div>
   <div class="grid g21" style="margin-top:12px"><div class="grid" style="gap:12px">
    <div class="card"><h3>Needs your attention <span class="sp"></span><span class="pill">${T.length}</span></h3><div class="cp">${T.length?T.slice(0,9).map(t=>`<div class="chk"><span><b>${esc(t.t)}</b><br><small class="mut">${t.cid||''}</small></span><button class="btn sm ${t.k==='bad'?'bad':'pri'}" data-act="goCase" data-c="${t.cid||''}" data-to="${t.go}">Open</button></div>`).join(''):'<div class="empty"><b>All clear</b>Nothing is waiting for this role.</div>'}</div></div>
    <div class="card"><h3>Case pipeline</h3><div class="cp"><div class="kan" style="grid-auto-columns:minmax(150px,1fr)">${col.map((n,i)=>{const L=DB.cases.filter(x=>Math.min(flowIdx(x),8)===i);return`<div class="kc"><h4>${n}<span>${L.length}</span></h4><div class="kb">${L.map(x=>`<div class="kcard" data-go="case/${x.id}" style="cursor:pointer"><b>${x.id}</b><div class="sm">${esc(x.month)}</div><small>${esc(nextAction(x).t.slice(0,60))}</small></div>`).join('')||'<div class="mut sm" style="padding:6px">—</div>'}</div></div>`}).join('')}</div></div></div>
   </div><div class="grid" style="gap:12px">
    <div class="card"><h3>Upcoming deadlines</h3><div class="cp">${dl.length?dl.slice(0,6).map(d=>`<div class="chk"><span><b>${esc(supOf(d.i.sid).name)}</b><br><small class="mut">${d.c.rfq.no} · ${bothTz(d.i.deadline)}</small></span></div>`).join(''):'<div class="empty">No open RFQ deadlines.</div>'}</div></div>
    <div class="card"><h3>Spend by supplier <span class="sp"></span><small class="mut">awarded, USD</small></h3><div class="cp">${spend.length?spend.map(s=>`<div class="hrow"><span>${esc(supOf(s[0]).name)}</span><div class="bar"><i style="width:${Math.round(s[1]/mx*100)}%"></i></div><span class="r">${usd(s[1])}</span></div>`).join(''):'<div class="empty">Appears after awards are decided.</div>'}</div></div>
    <div class="card"><h3>Recent activity <span class="sp"></span><a data-go="audit" class="sm">All</a></h3><div class="cp tl">${DB.audit.slice(0,7).map(a=>`<div><b class="sm">${esc(a.a)}</b> <span class="mut sm">${esc(a.ref)}</span><small>${esc(a.who)} · ${dS(a.ts)}</small></div>`).join('')}</div></div>
   </div></div>`};
};
ACT.goCase=d=>{if(d.c)UI.caseId=d.c;go(d.to)};
