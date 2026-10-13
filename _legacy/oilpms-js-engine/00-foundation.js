/* =====================================================================
   OIL-PMS · Import Purchase Module — workable prototype (v3)
   UI engine and visual language follow the Factory EOS reference module.
   All data is MOCK. Sensitive values use [PLACEHOLDER] format.
   ===================================================================== */
const $=(s,r=document)=>r.querySelector(s);
const $$=(s,r=document)=>[...r.querySelectorAll(s)];
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const KEY='oil_pms_import_v4';
const pad=(n,l=2)=>String(n).padStart(l,'0');
const DAY=864e5;
const n0=n=>Math.round(Number(n)||0).toLocaleString('en-US');
const usd=n=>'$'+n0(n);
const usd2=n=>'$'+(Number(n)||0).toFixed(2);
const usd3=n=>'$'+(Number(n)||0).toFixed(3);
const sum=(a,f)=>a.reduce((s,x)=>s+(f?f(x):x),0);
const uniq=a=>[...new Set(a)];
const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
const sortBy=(a,f,dir=1)=>[...a].sort((x,y)=>{const A=f(x),B=f(y);return(A>B?1:A<B?-1:0)*dir});
const clone=o=>JSON.parse(JSON.stringify(o));
const hash=s=>{let h=5381;for(const c of String(s))h=((h<<5)+h+c.charCodeAt(0))>>>0;return h.toString(16).padStart(8,'0')};
const rnd=(n=8)=>[...Array(n)].map(()=>'0123456789abcdef'[Math.floor(Math.random()*16)]).join('');
const MON=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const tzf=(ts,tz,o)=>new Intl.DateTimeFormat('en-GB',{timeZone:tz,...o}).format(ts);
const T24={hour:'2-digit',minute:'2-digit',hour12:false};
const dBD=ts=>ts?tzf(ts,'Asia/Dhaka',{day:'2-digit',month:'short',year:'numeric',...T24}).replace(',','')+' BST':'—';
const dCN=ts=>tzf(ts,'Asia/Shanghai',{weekday:'short',day:'2-digit',month:'short',year:'numeric',...T24}).replace(/,/g,'')+' CST';
const dS=ts=>ts?tzf(ts,'Asia/Dhaka',{day:'2-digit',month:'short',...T24}).replace(',',''):'—';
const dD=ts=>ts?tzf(ts,'Asia/Dhaka',{day:'2-digit',month:'short',year:'numeric'}):'—';
const isoD=d=>d.toISOString().slice(0,10);
const dIn=ts=>isoD(new Date(ts));
const NOW0=Date.UTC(2026,9,13,8,0);          /* Tue 13 Oct 2026, 14:00 Bangladesh */
const NOW=()=>DB?DB.now:NOW0;
const bothTz=ts=>`${dCN(ts)} · ${tzf(ts,'Asia/Dhaka',T24)} BST`;

/* ---------- icons ---------- */
const IC={home:'M3 11l9-8 9 8M5 10v10h14V10',box:'M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8',clip:'M9 3h6v3H9zM6 5h12v16H6zM9 11h6M9 15h6',eye:'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6',doc:'M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6',users:'M16 11a3 3 0 1 0 0-6M8 11a3 3 0 1 0 0-6M2 20c0-3 3-5 6-5s6 2 6 5M14 15c3 0 6 2 6 5',shield:'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',chart:'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-8',bell:'M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7M10 20a2 2 0 0 0 4 0',cfg:'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0M14 4v4M8 10v4M16 16v4',list:'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',search:'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14M21 21l-5-5',plus:'M12 5v14M5 12h14',chev:'M9 6l6 6-6 6',menu:'M4 6h16M4 12h16M4 18h16',alert:'M12 3l10 18H2zM12 10v5M12 18h.01',cal:'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',x:'M6 6l12 12M18 6L6 18',check:'M5 12l5 5 9-10',more:'M5 12h.01M12 12h.01M19 12h.01',user:'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21c0-4 4-6 8-6s8 2 8 6',sun:'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19',dl:'M12 4v11M7 11l5 5 5-5M5 20h14',up:'M12 20V9M7 13l5-5 5 5M5 4h14',send:'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z',mail:'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM21 6l-9 7-9-7',lock:'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',link:'M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1',bank:'M3 21h18M5 10h14M12 3l8 5H4zM6 10v8M10 10v8M14 10v8M18 10v8',clock:'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18M12 7v5l3 2',edit:'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',refresh:'M20 11a8 8 0 0 0-14-4L4 9M4 4v5h5M4 13a8 8 0 0 0 14 4l2-2M20 20v-5h-5',trash:'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13',tag:'M3 12V3h9l9 9-9 9zM7.5 7.5h.01',file:'M6 3h9l4 4v14H6zM14 3v5h5',globe:'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18',layers:'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5M3 17l9 5 9-5',pkg:'M3 7l9-4 9 4-9 4zM3 7v10l9 4 9-4V7M12 11v10',flag:'M5 21V4M5 4h11l-2 4 2 4H5',zap:'M13 2L4 14h7l-1 8 9-12h-7z',filter:'M3 5h18l-7 8v6l-4 2v-8z'};
const ic=(n,s=16)=>`<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="${IC[n]||IC.doc}"/></svg>`;

/* ---------- roles & permissions ---------- */
const ROLES={exec:{n:'Procurement Executive',u:'Imran K.'},sup:{n:'Procurement Supervisor',u:'Farhana S.'},mkt:{n:'Marketing',u:'Nasrin A.'},qa:{n:'QA / Technical',u:'Rubel M.'},fin:{n:'Finance & Accounts',u:'Kamal U.'},ed:{n:'ED / Final Approver',u:'ED (delegate)'},imp:{n:'Import / Logistics',u:'Sabbir R.'},admin:{n:'System Administrator',u:'Sysadmin'},audit:{n:'Internal Audit / MD (read-only)',u:'Audit viewer'}};
const EXE=['demand.edit','booking.edit','sp.edit','sup.edit','rfq.create','rfq.send','rfq.remind','cs.select','cs.negotiate','cs.submit','po.edit','po.dispatch','pi.record','pi.match','case.new'];
const PERM={exec:EXE,sup:[...EXE,'demand.freeze','booking.confirm','rfq.extend','rfq.close','rfq.revoke','exc.resolve','po.verify','po.correct','bank.verify2','cs.waiver'],mkt:['appr.mkt'],qa:['sample.edit'],fin:['appr.fin','fin.remark','lc.approve','bank.verify1','exc.resolve','pi.match'],ed:['appr.ed','appr.change'],imp:['pi.record','pi.match','imp.docs','lc.open'],admin:['admin.cfg','sup.edit'],audit:[]};
const can=a=>(PERM[UI.role]||[]).includes(a);
function need(a){
  if(can(a))return true;
  const who=Object.keys(PERM).filter(r=>PERM[r].includes(a));
  toast(`${ROLES[UI.role].n} can't do this (segregation of duties). Needs: ${who.map(r=>ROLES[r].n).join(' or ')||'—'}.`,'warn',who[0]?{l:'Switch role',f:()=>{UI.role=who[0];render()}}:null);
  return false;
}
const user=()=>ROLES[UI.role].u;
const NAV=[
 {k:'cc',t:'Command Center',i:'home',items:[['home','Command Center']]},
 {k:'cs',t:'Purchase Cases',i:'layers',items:[['cases','All Cases']]},
 {k:'rq',t:'Requisitions',i:'clip',items:[['pr','Create / Edit PR (ERP)'],['rq.board','Requisition Board'],['rq.booking','Confirmed Book Qty']]},
 {k:'src',t:'Sourcing / RFQ',i:'send',items:[['src.supp','Supplier Shortlist'],['src.rfq','RFQs'],['src.track','Link & Deadline Admin'],['src.quotes','Submitted Quotations'],['src.cs','Comparative Statement'],['src.samples','Samples & QA']]},
 {k:'aw',t:'Negotiation & Awards',i:'flag',items:[['aw.neg','Negotiation History'],['aw.rec','Final Recommendation'],['aw.appr','Approval Queue']]},
 {k:'po',t:'Purchase Orders',i:'doc',items:[['po.reg','PO Register'],['po.ack','Acknowledgments'],['po.disp','Amendments & Disputes']]},
 {k:'imp',t:'Import & Warehouse',i:'bank',items:[['imp.pi','PI & Banking Documents'],['imp.lc','LC Register'],['ship','Shipment Tracking (ERP)'],['grn','GRN / Inspection (ERP)'],['pay','Invoice & Payment (ERP)']]},
 {k:'sd',t:'Suppliers',i:'users',items:[['sup.dir','Supplier Directory'],['portal','Supplier Portal Preview']]},
 {k:'an',t:'Reports',i:'chart',items:[['an.perf','Savings & Performance'],['an.pipe','Pipeline & Cycle Time']]},
 {k:'ctl',t:'Control',i:'alert',items:[['exc','Exceptions'],['notif','Notifications'],['audit','Audit Trail']]},
 {k:'adm',t:'Administration',i:'cfg',items:[['ad.roles','Users & Roles'],['ad.master','Materials & Masters'],['ad.cal','Working-Day Calendars'],['ad.tpl','Email Templates'],['ad.policy','Policy & Approval Authority'],['ad.tests','Acceptance Tests'],['ad.demo','Demo Utilities']]}];
const ALL=true;
const ROLE_NAV={
 exec:{cc:ALL,cs:ALL,rq:ALL,src:ALL,aw:ALL,po:ALL,imp:ALL,sd:ALL,an:ALL,ctl:ALL},
 sup:{cc:ALL,cs:ALL,rq:ALL,src:ALL,aw:ALL,po:ALL,imp:ALL,sd:ALL,an:ALL,ctl:ALL},
 mkt:{cc:ALL,cs:ALL,rq:['rq.board','rq.booking'],src:['src.cs','src.samples'],aw:ALL,po:['po.reg'],an:['an.perf'],ctl:['notif','audit']},
 qa:{cc:ALL,cs:ALL,src:['src.quotes','src.cs','src.samples'],aw:['aw.rec','aw.appr'],ctl:['notif']},
 fin:{cc:ALL,cs:ALL,src:['src.cs'],aw:ALL,po:ALL,imp:ALL,an:ALL,ctl:['exc','notif','audit']},
 ed:{cc:ALL,cs:ALL,src:['src.cs'],aw:ALL,po:['po.reg'],imp:['imp.lc'],an:ALL,ctl:['notif','audit']},
 imp:{cc:ALL,cs:ALL,po:['po.reg','po.ack'],imp:ALL,ctl:['notif']},
 admin:{cc:ALL,cs:ALL,rq:ALL,src:ALL,aw:ALL,po:ALL,imp:ALL,sd:ALL,an:ALL,ctl:ALL,adm:ALL},
 audit:{cc:ALL,cs:ALL,rq:ALL,src:ALL,aw:ALL,po:ALL,imp:ALL,sd:['sup.dir'],an:ALL,ctl:ALL}};
const navFor=r=>{const m=ROLE_NAV[r]||{};return NAV.filter(g=>m[g.k]).map(g=>({...g,items:g.items.filter(i=>m[g.k]===true||m[g.k].includes(i[0]))})).filter(g=>g.items.length)};

/* ---------- state ---------- */
let DB=null,UI={role:'exec',tbl:{},col:false,open:{},loading:false,menu:null,mobNav:false,tab:{},theme:'',caseId:'',p:{sid:'SUP-00004',mode:'rfq',tab:'general'},csPid:'',strat:'cost'};
function save(){try{localStorage.setItem(KEY,JSON.stringify(DB))}catch(e){}}
const nextSeq=(k)=>{DB.seq[k]=(DB.seq[k]||0)+1;return DB.seq[k]};
const nid=(k,y=2026)=>`${k}-${y}-${pad(nextSeq(k),5)}`;
function log(a,ref,detail,o={}){DB.audit.unshift({ts:o.ts||DB.now+(DB.tick++)*1000,role:o.by||UI.role,who:o.by?(o.by==='Supplier'?'Supplier':'System'):user(),a,ref,detail:detail||'',cid:o.cid||''});if(DB.audit.length>1500)DB.audit.length=1500}
function audit(mod,act,txt){log(act,mod,txt)}
function notify(sev,txt,link,o={}){DB.notifs.unshift({id:'N'+DB.tick+rnd(3),t:o.ts||DB.now+(DB.tick++)*1000,sev,txt,link:link||'',read:false,role:o.role||''})}
const EXT={'No bid':['Procurement approver','High'],'Late submission':['Authorized Procurement manager','Medium'],'Calendar unavailable':['System admin / Procurement','High'],'Quote incomplete':['Supplier','Low'],'Single source':['Procurement / Management','Medium'],'Sample rejected':['QA / Marketing','High'],'Incoterms without place':['Procurement','Medium'],'Quote revision':['Supplier / Procurement','Low'],'Change after approval':['Finance / Final approver','High'],'PO email failed':['Procurement / IT','High'],'PO dispute':['Procurement / Finance','High'],'PO vs PI mismatch':['Import / Finance','High'],'Bank details changed':['Finance','High'],'MOQ waiver':['Procurement supervisor','Medium'],'Supplier contact changed':['Procurement supervisor','Medium'],'Supplier identity changed':['Procurement supervisor','Medium'],'Spec deviation declared':['QA / Marketing','Medium']};
function raise(type,ref,detail,o={}){
  if(DB.exc.some(e=>e.type===type&&e.ref===ref&&e.status!=='Resolved'))return;
  const [owner,sev]=EXT[type]||['Procurement','Medium'];
  DB.exc.unshift({id:nid('EXC'),type,ref,detail,owner,sev,status:'Open',at:o.ts||DB.now,res:'',cid:o.cid||''});
  log('Exception raised',ref,`${type}: ${detail}`,o);
  notify(sev==='High'?'bad':'warn',`${type} — ${ref}`,'exc',o);
}

/* ---------- master data (MOCK) ---------- */
const MATS=[
 {id:'P-1001',name:'Fusible Nonwoven 50 GSM',meta:'NW-PA-50 · PA dot',comp:'PES 100% · PA dot coating',uom:'m',type:'fabric'},
 {id:'P-1002',name:'Fusible Woven 90 GSM',meta:'WV-PES-90 · PES paste',comp:'Cotton/PES blend · PES coating',uom:'m',type:'fabric'},
 {id:'P-1003',name:'Tricot Knit Fusible 40 GSM',meta:'TK-PA-40 · PA dot',comp:'Nylon tricot · PA dot',uom:'m',type:'fabric'},
 {id:'P-1004',name:'Sew-in Nonwoven 35 GSM',meta:'NW-SI-35',comp:'PES/PP spunbond',uom:'m',type:'fabric'},
 {id:'P-1005',name:'Shirt Collar Interlining 110 GSM',meta:'CL-HD-110 · HDPE dot',comp:'Cotton/PES · HDPE dot',uom:'m',type:'fabric'},
 {id:'P-1006',name:'Waistband Interlining 180 GSM',meta:'WB-PA-180 · PA coat',comp:'PES woven · PA coating',uom:'m',type:'fabric'},
 {id:'P-1007',name:'Adhesive Powder PA 80–200 µm',meta:'PW-PA-80',comp:'Polyamide co-polymer powder',uom:'kg',type:'powder'},
 {id:'P-1008',name:'Stretch Fusible Knit 60 GSM',meta:'ST-PA-60 · PA dot',comp:'PES/Elastane knit · PA dot',uom:'m',type:'fabric'}];
const SUPS=[
 {id:'SUP-00001',name:'Shanghai Weiwen Interlining Co., Ltd',tag:'A',cc:'CN',cal:'CN',contact:'Lin Mei',desig:'Sales Manager',email:'lin.mei@weiwen.example',phone:'[SUPPLIER-PHONE]',emailVerified:true,status:'Active',since:'2019',address:'[SUPPLIER-REGISTERED-ADDRESS], China'},
 {id:'SUP-00002',name:'Suzhou Nova Nonwoven Co., Ltd',tag:'B',cc:'CN',cal:'CN',contact:'Chen Hao',desig:'Export Manager',email:'chen.hao@novanw.example',phone:'[SUPPLIER-PHONE]',emailVerified:true,status:'Active',since:'2020',address:'[SUPPLIER-REGISTERED-ADDRESS], China'},
 {id:'SUP-00003',name:'Kunshan Huanan Textile Co., Ltd',tag:'C',cc:'CN',cal:'CN',contact:'Wu Jun',desig:'Sales Director',email:'wu.jun@huanan.example',phone:'[SUPPLIER-PHONE]',emailVerified:true,status:'Active',since:'2018',address:'[SUPPLIER-REGISTERED-ADDRESS], China'},
 {id:'SUP-00004',name:'Dongguan ProBond Chemicals Co., Ltd',tag:'D',cc:'CN',cal:'CN',contact:'Zhou Yan',desig:'Account Manager',email:'zhou.yan@probond.example',phone:'[SUPPLIER-PHONE]',emailVerified:true,status:'Active',since:'2021',address:'[SUPPLIER-REGISTERED-ADDRESS], China'},
 {id:'SUP-00005',name:'Daegu FuseTech Co., Ltd',tag:'E',cc:'KR',cal:'KR',contact:'Park Jisoo',desig:'Overseas Sales',email:'jisoo.park@fusetech.example',phone:'[SUPPLIER-PHONE]',emailVerified:true,status:'Active',since:'2024',address:'[SUPPLIER-REGISTERED-ADDRESS], South Korea'}];
const SUPTERMS={'SUP-00001':{incoterm:'FOB',place:'Shanghai Port',payment:'LC at sight',terms:80,hist:90},'SUP-00002':{incoterm:'FOB',place:'Ningbo Port',payment:'TT 30% advance, 70% against BL copy',terms:75,hist:78},'SUP-00003':{incoterm:'FOB',place:'Qingdao Port',payment:'LC usance 60 days',terms:70,hist:70},'SUP-00004':{incoterm:'FCA',place:'Dongguan Factory',payment:'TT 100% advance',terms:60,hist:65}};
const SPMAP=[['SUP-00001','P-1001','WW SG 50'],['SUP-00001','P-1002','WW WV 90'],['SUP-00001','P-1003','WW TK 40'],['SUP-00001','P-1005','WW CL 110'],['SUP-00001','P-1006','WW WB 180'],['SUP-00001','P-1008','WW ST 60'],
 ['SUP-00002','P-1001','SN 90 FF'],['SUP-00002','P-1002','SN WV 95'],['SUP-00002','P-1004','SN SW 35'],['SUP-00002','P-1005','SN CL 105'],['SUP-00002','P-1006','SN WB 180',false],['SUP-00002','P-1008','SN ST 60'],
 ['SUP-00003','P-1001','KH NN 52'],['SUP-00003','P-1003','KH TK 42'],['SUP-00003','P-1004','KH SW 36'],['SUP-00003','P-1006','KH WB 175'],['SUP-00003','P-1008','KH ST 62'],
 ['SUP-00004','P-1007','PB PA 80-200'],['SUP-00005','P-1007','DF PA 100-250']].map(([sid,pid,name,a])=>({sid,pid,name,active:a!==false}));
const QT={
 'P-1001':{'SUP-00001':{price:.82,moq:10000,days:25,freight:1150,ins:110,duty:2450,q:88,sample:'Pass'},'SUP-00002':{price:.79,moq:25000,days:35,freight:1580,ins:120,duty:2480,q:82,sample:'Pass'},'SUP-00003':{price:.84,moq:15000,days:22,freight:900,ins:105,duty:2470,q:79,sample:'Pass'}},
 'P-1002':{'SUP-00001':[.96,15000,28,.045,.15,90,'Pass'],'SUP-00002':[.93,20000,32,.05,.155,84,'Pass']},
 'P-1003':{'SUP-00001':[.64,8000,24,.04,.14,85,'Pass'],'SUP-00003':[.61,10000,30,.05,.14,78,'Conditional']},
 'P-1004':{'SUP-00002':[.41,20000,26,.03,.12,80,'Pass'],'SUP-00003':[.38,12000,21,.03,.12,75,'Fail']},
 'P-1005':{'SUP-00001':[1.12,5000,27,.06,.15,89,'Pass'],'SUP-00002':[1.08,6000,30,.065,.15,83,'Pass']},
 'P-1006':{'SUP-00001':[1.48,8000,24,.08,.15,91,'Pass'],'SUP-00003':[1.45,5000,24,.075,.15,88,'Pass']},
 'P-1007':{'SUP-00004':[4.2,1000,20,.12,.1,80,'Pass']},
 'P-1008':{'SUP-00001':[.78,10000,26,.05,.15,87,'Pass'],'SUP-00002':[.75,12000,33,.055,.15,82,'Pass'],'SUP-00003':[.79,8000,21,.05,.15,80,'Pass']}};
const W={cost:.35,tech:.30,del:.20,terms:.10,hist:.05};   /* ILLUSTRATIVE — Open Policy Item 7 */
const REASONS=['Sufficient stock in warehouse','Buyer order not confirmed','Specification pending approval','Below MOQ — consolidate next month','Budget not available','Duplicate requisition'];
const BUYERS=['Nordic Apparel Group','Atlas Denim Co.','Kestrel Outdoor','Veloura Fashion'],CUSTS=['Evergreen Garments Ltd','Meghna Apparels Ltd','Padma Knit Composite','Jamuna Fashions Ltd'],MKTS=['Nasrin A.','Rakib H.','Sumaiya T.'];
const DEM0=[[1,0,0,'P-1001',8000],[2,1,1,'P-1001',7000],[3,2,2,'P-1001',5000],[4,0,0,'P-1002',15000],[5,3,3,'P-1002',12000],[6,1,1,'P-1002',8000],[7,2,2,'P-1003',10000],[8,3,3,'P-1003',8000],[9,2,2,'P-1003',6000],[10,0,0,'P-1004',14000],[11,1,1,'P-1004',11000],[12,3,3,'P-1005',7000],[13,2,2,'P-1005',5000],[14,1,1,'P-1006',9000],[15,0,0,'P-1006',6000],[16,3,3,'P-1006',5000],[17,2,2,'P-1007',2500],[18,3,3,'P-1007',1500],[19,0,0,'P-1008',12000],[20,1,1,'P-1008',10000]];
const POLICY=['Procurement authority matrix and approval thresholds (incl. whether ultimate approver is ED, MD or delegate)','Counting convention for China RFQ deadlines — is the invitation date excluded in every circumstance?','Default closing hour; supplier calendars per supplier or per RFQ','Sample mandatory / waiver criteria by RM type; acceptable deviations and tolerances','Vendor prequalification, quotation confidentiality and bid-opening policy','RFQ extensions, re-tendering, emergency sourcing and single-source approval rights','Supplier performance scoring weights and tie-break logic','PO acknowledgment SLA, PO cancellation and amendment authority','Freight, FX and landed-cost standard assumptions incl. bank / finance charges','Finance three-way-match tolerances, advance-payment policy, settlement controls','Required documents, mandatory legal terms and standard supplier PO clauses','ERP / email / bank integrations, data migration, retention and operating support owners'].map((t,i)=>({n:i+1,t,owner:'',out:'',date:'',st:'Open'}));
const CALS={
 CN:{name:'China (CN)',tz:'Asia/Shanghai',off:8,close:17,approved:true,years:[2026],holidays:['2026-10-01','2026-10-02','2026-10-03','2026-10-04','2026-10-05','2026-10-06','2026-10-07'],makeup:['2026-09-20','2026-10-10']},
 KR:{name:'South Korea (KR)',tz:'Asia/Seoul',off:9,close:17,approved:false,years:[],holidays:[],makeup:[]}};

/* ---------- supplier-country working-day engine ---------- */
function isWork(cal,d){const s=isoD(d),w=d.getUTCDay();if(cal.makeup.includes(s))return true;if(cal.holidays.includes(s))return false;return w!==0&&w!==6}
function localMid(cal,ts){const l=new Date(ts+cal.off*3600e3);return new Date(Date.UTC(l.getUTCFullYear(),l.getUTCMonth(),l.getUTCDate()))}
function calcDeadline(key,issueTs,n){
  const cal=DB.cal[key];
  if(!cal||!cal.approved)return{err:`Working-day calendar “${key}” is not approved — deadline can't be computed.`};
  let d=localMid(cal,issueTs),c=0,g=0;
  if(!cal.years.includes(d.getUTCFullYear()))return{err:`Holiday data for ${d.getUTCFullYear()} not verified for ${key}.`};
  while(c<n){d=new Date(d.getTime()+DAY);if(!cal.years.includes(d.getUTCFullYear()))return{err:`Holiday data for ${d.getUTCFullYear()} not verified for ${key} — publication blocked.`};if(isWork(cal,d))c++;if(++g>60)return{err:'Calendar range error'}}
  return{ts:Date.UTC(d.getUTCFullYear(),d.getUTCMonth(),d.getUTCDate(),cal.close,0)-cal.off*3600e3};
}
function prevWork(key,ts){const cal=DB.cal[key];let d=localMid(cal,ts),g=0;do{d=new Date(d.getTime()-DAY)}while(!isWork(cal,d)&&++g<30);return Date.UTC(d.getUTCFullYear(),d.getUTCMonth(),d.getUTCDate(),9,0)-cal.off*3600e3}

/* ---------- case helpers ---------- */
const CASE=id=>DB.cases.find(c=>c.id===id);
const C=()=>CASE(UI.caseId)||DB.cases[0];
const prod=id=>MATS.concat(DB.mats||[]).find(p=>p.id===id)||{id,name:id,meta:'',comp:'',uom:'',type:'fabric'};
const supOf=sid=>DB.sup.find(s=>s.id===sid)||{id:sid,name:sid,tag:'?'};
const spName=(sid,pid)=>(DB.sp.find(x=>x.sid===sid&&x.pid===pid)||{}).name||'—';
const dem=c=>c.demand;
const demandSum=(c,pid,st)=>c.demand.filter(d=>d.pid===pid&&(!st||d.status===st)).reduce((a,d)=>a+d.qty,0);
const reqQty=(c,pid)=>(c.booking.lines.find(l=>l.pid===pid)||{}).qty||0;
const allPOs=()=>DB.cases.flatMap(c=>c.pos.map(p=>({...p,_c:c})));
function newCase(o){
  return{id:o.id,name:o.name,month:o.month,mon:o.mon,t0:o.t0||NOW0,created:o.t0||NOW0,by:o.by||'Imran K.',demand:[],frozen:false,booking:{lines:[],confirmed:false,serial:null,doc:null},rfq:{no:null,ver:1,reqFields:[]},drafts:[],inv:[],quotes:{},quoteHdr:{},portal:{},cs:{},csLocked:false,appr:{status:'none',version:0,id:null,rec:null,mkt:null,fin:null,ed:null,chk:null,hist:[]},pos:[],pis:{},lc:{},sender:'Procurement Desk — import@[oil-domain]',notes:o.notes||''};
}
function seedDemand(c,scale=1,pre='D',startN=1){
  c.demand=DEM0.map(([n,b,cu,pid,q],i)=>({id:`${pre}${pad(n)}`,buyer:BUYERS[b],customer:CUSTS[cu],pid,qty:Math.round(q*scale/100)*100||100,mkt:MKTS[i%3],need:'',status:'pending',reason:'',remark:''}));
}

/* ---------- quotes, CS maths ---------- */
const landed=q=>q.price*q.qty+q.freight+q.ins+q.duty+q.bank;
function bids(c,pid){
  return DB.sup.map(sp=>{const q=(c.quotes[sp.id]||{})[pid],iv=c.inv.find(i=>i.sid===sp.id);return(q&&iv&&iv.submitted)?{sid:sp.id,s:sp,q}:null}).filter(Boolean);
}
function elig(c,b,pid){
  if(b.q.sample==='Fail')return{ok:false,why:'Sample failed — blocked from award'};
  if(b.q.sample==='Pending')return{ok:false,why:'Sample result pending (QA)'};
  if(b.q.validity<NOW())return{ok:false,why:'Quote validity expired'};
  const rq=reqQty(c,pid);
  const w=[];if(b.q.sample==='Conditional')w.push('Conditional pass — needs approved deviation');if(b.q.moq>rq)w.push(`MOQ ${n0(b.q.moq)} exceeds required ${n0(rq)}`);const dv=devFor(c,b.sid,pid);if(dv)w.push('Declared deviation: '+dv);
  return{ok:true,warn:w.join(' · ')};
}
const csOf=(c,pid)=>c.cs[pid]||(c.cs[pid]={alloc:{},just:'',excl:false,exclWhy:'',fin:'',neg:[],waiver:{}});
const allocSum=(c,pid)=>Object.values(csOf(c,pid).alloc).reduce((a,b)=>a+b,0);
function csStat(c,pid){const x=csOf(c,pid);if(x.excl)return'Excluded';const a=allocSum(c,pid),r=reqQty(c,pid);return(a>=r&&r>0)?'Complete':a>0?'Partial':'Pending'}
const csCounts=c=>{const L=c.booking.lines,d=L.filter(l=>['Complete','Excluded'].includes(csStat(c,l.pid))).length;return{done:d,pend:L.length-d,total:L.length}};
function scored(c,pid){
  const bs=bids(c,pid).filter(b=>b.q.sample!=='Fail');if(!bs.length)return{};
  const mL=Math.min(...bs.map(b=>landed(b.q))),mD=Math.min(...bs.map(b=>b.q.days));
  return Object.fromEntries(bs.map(b=>[b.sid,W.cost*mL/landed(b.q)*100+W.tech*b.q.q+W.del*mD/b.q.days*100+W.terms*b.q.terms+W.hist*b.q.hist]));
}
const STRAT={cost:'Lowest landed cost',quality:'Highest quality rank',lead:'Lowest lead time',index:'Highest index score'};
function pick(c,pid,k){
  const rq=reqQty(c,pid),sc=scored(c,pid);
  const cand=bids(c,pid).filter(b=>elig(c,b,pid).ok&&b.q.moq<=rq&&b.q.sample!=='Conditional');
  if(!cand.length)return null;
  const key={cost:b=>landed(b.q),quality:b=>-b.q.q,lead:b=>b.q.days,index:b=>-sc[b.sid]}[k];
  return cand.sort((a,b)=>key(a)-key(b)||landed(a.q)-landed(b.q))[0];
}
const lowestLanded=(c,pid)=>{const x=bids(c,pid).filter(b=>elig(c,b,pid).ok);return x.length?x.sort((a,b)=>landed(a.q)-landed(b.q))[0].sid:null};
function lineAwards(c){
  const out=[];c.booking.lines.forEach(l=>{const x=csOf(c,l.pid);Object.entries(x.alloc).forEach(([sid,qty])=>{const q=c.quotes[sid][l.pid];const up=unitPriceFor(q,qty);out.push({pid:l.pid,sid,qty,price:up,amt:up*qty,est:landed(q)*qty/q.qty,moq:q.moq,sample:q.sample,days:q.days})})});return out;
}
const awardChk=c=>hash(JSON.stringify(lineAwards(c).map(x=>[x.pid,x.sid,x.qty,x.price])));
const savings=c=>c.booking.lines.reduce((a,l)=>a+Object.entries(csOf(c,l.pid).alloc).reduce((s,[sid,qty])=>{const q=c.quotes[sid][l.pid];return s+(q.orig-q.price)*qty},0),0);
function needsJust(c,pid){
  const x=csOf(c,pid),ids=Object.keys(x.alloc);if(!ids.length)return'';const low=lowestLanded(c,pid);
  if(ids.some(s=>devFor(c,s,pid)))return'Supplier declared a specification deviation — record the approved deviation';
  if(ids.some(s=>c.quotes[s][pid].sample==='Conditional'))return'Conditional sample pass — record the approved deviation';
  if(Object.keys(x.waiver).length)return'MOQ waiver in use — state the commercial reason';
  if(ids.length>1)return'Split award — state the reason for splitting';
  if(low&&ids[0]!==low)return'Selected supplier is not the lowest landed cost — state the rationale';return'';
}
function setFull(c,pid,sid,by){const x=csOf(c,pid);x.alloc={};x.waiver={};x.alloc[sid]=reqQty(c,pid);const q=c.quotes[sid][pid];if(q.moq>x.alloc[sid])x.waiver[sid]='';log('CS selected (full)',pid,`${supOf(sid).name} · ${n0(x.alloc[sid])}`,{cid:c.id,...(by?{by}:{})})}
const altFor=(c,pid)=>{const sel=Object.keys(csOf(c,pid).alloc);return bids(c,pid).filter(b=>elig(c,b,pid).ok&&!sel.includes(b.sid)).sort((a,b)=>landed(a.q)-landed(b.q))[0]};

/* ---------- RFQ / invitations ---------- */
const ivState=(c,iv)=>iv.submitted?'Submitted':iv.revoked?'Revoked':(NOW()>iv.deadline?'Expired':iv.state);
const ivDone=(c,iv)=>iv.submitted||iv.revoked||NOW()>iv.deadline;
const rfqClosed=c=>c.inv.length>0&&c.inv.every(i=>ivDone(c,i));
const rfqVars=(c,iv)=>{const sp=supOf(iv.sid);return{SUPPLIER_CONTACT_NAME:sp.contact,RFQ_NUMBER:iv.rfq||c.rfq.no,PRODUCTS:iv.pids.map(id=>`${prod(id).name} (${n0(reqQty(c,id))} ${prod(id).uom})`).join('; '),RFQ_DEADLINE_CHINA:dCN(iv.deadline),RFQ_DEADLINE_BD:tzf(iv.deadline,'Asia/Dhaka',T24)+' BST',RFQ_LINK:`https://rfq.[oil-domain]/s/${iv.tokPlain||'[TOKEN]'}`}};
const poolSup=c=>{const pids=new Set(c.booking.lines.map(l=>l.pid));const m={};DB.sp.filter(x=>x.active&&pids.has(x.pid)).forEach(x=>(m[x.sid]=m[x.sid]||[]).push(x.pid));return m};
function genDrafts(c,opt={}){
  if(!c.rfq.no)c.rfq.no=nid('RFQ');
  const pool=opt.pool||poolSup(c),by=opt.by,urgent=!!opt.urgent;
  Object.entries(pool).forEach(([sid,pids])=>{
    const ex=c.drafts.find(d=>d.sid===sid);if(ex&&['Sent','Scheduled'].includes(ex.status))return;
    const dl=calcDeadline(supOf(sid).cal,NOW(),urgent?2:3);
    const d={id:'DR-'+sid.slice(-2),sid,pids,status:dl.err?'Blocked':'Draft',why:dl.err||'',urgent,sender:opt.sender||c.sender};
    if(ex)Object.assign(ex,d);else c.drafts.push(d);
    if(dl.err)raise('Calendar unavailable',sid,dl.err,{cid:c.id,...(by?{by}:{})});
  });
  c.rfq.urgent=urgent;if(opt.notes!==undefined)c.rfq.notes=opt.notes;
  log('RFQ drafts generated',c.rfq.no,`${c.drafts.length} supplier drafts`,{cid:c.id,...(by?{by}:{})});
}
function sendDraft(c,d,ts,by){
  const sp=supOf(d.sid),dl=calcDeadline(sp.cal,ts,d.urgent?2:3);
  if(dl.err){d.status='Blocked';d.why=dl.err;raise('Calendar unavailable',d.sid,dl.err,{cid:c.id,...(by?{by}:{})});return false}
  const iv={sid:d.sid,rfq:c.rfq.no,pids:d.pids,state:'Sent',sentAt:ts,deadline:dl.ts,origDeadline:dl.ts,ext:[],submitted:false,tok:'sha256:'+rnd(10)+'…',tokPlain:rnd(10),ip:null,rev:0,revoked:false};
  c.inv=c.inv.filter(i=>i.sid!==d.sid);c.inv.push(iv);d.status='Sent';d.sentAt=ts;d.why='';
  mail(c,'RFQ',`${sp.contact} <${sp.email}>`,rfqVars(c,iv),{ts});log('RFQ sent',c.rfq.no,`${sp.name} · link ${iv.tok}`,{cid:c.id,ts,...(by?{by}:{})});
  return true;
}
function checkNoBid(c,by){if(!rfqClosed(c))return;c.booking.lines.forEach(l=>{if(!bids(c,l.pid).length)raise('No bid',l.pid,`No valid quotation received for ${prod(l.pid).name} (${c.id}) — re-tender or extend.`,{cid:c.id,...(by?{by}:{})})})}

/* ---------- email templates + archive ---------- */
const fill=(s,v)=>s.replace(/\{(\w+)\}/g,(m,k)=>v[k]??m);
const TPL0={
 RFQ:{n:'RFQ invitation',s:'Request for Quotation — {RFQ_NUMBER} | Osman Interlinings Limited (OIL)',b:`Dear {SUPPLIER_CONTACT_NAME},

Greetings from Osman Interlinings Limited (OIL).

We invite you to submit your quotation for our procurement requirements under RFQ Reference No. {RFQ_NUMBER}.

Please review the RFQ details and submit your competitive offer through the secure link provided below.

RFQ Details:
RFQ Reference: {RFQ_NUMBER}
Product/Material: {PRODUCTS}
Quotation Deadline: {RFQ_DEADLINE_CHINA} (China Standard Time) · {RFQ_DEADLINE_BD}
Quotation Submission Link: {RFQ_LINK}

Quotation Requirements — please provide through the RFQ portal:
1. Unit Price and Currency
2. Minimum Order Quantity (MOQ)
3. Available Stock and Production Lead Time
4. Delivery Schedule
5. Incoterms and Port of Shipment
6. Payment Terms
7. Quotation Validity
8. Product Specifications and Supporting Documents

Important Instructions:
• Submit your quotation within the specified deadline.
• The secure RFQ link is exclusively assigned to your organization.
• Quotations submitted after the deadline may not be considered.
• All quotations will be evaluated on commercial terms, product quality, delivery capability and other relevant criteria.

Best regards,
Procurement Department
Osman Interlinings Limited (OIL)
{CONTACT_EMAIL}
This is a system-generated RFQ invitation from the OIL Procurement Management System.`},
 B:{n:'B · RFQ reminder',s:'Reminder — RFQ {RFQ_NUMBER} closes on {RFQ_DEADLINE_CHINA}',b:`Dear {SUPPLIER_CONTACT_NAME},

This is a reminder that your quotation for {PRODUCTS} under RFQ {RFQ_NUMBER} is due by {RFQ_DEADLINE_CHINA} (China time).

Please complete the submission through: {RFQ_LINK}. The secure link will expire at the deadline. If you have already submitted, please disregard this message.

Best regards,
Procurement Department | Osman Interlinings Limited (OIL)`},
 C:{n:'C · Submission confirmation',s:'Quotation received — RFQ {RFQ_NUMBER} / Submission {SUBMISSION_ID}',b:`Dear {SUPPLIER_CONTACT_NAME},

Thank you. We confirm receipt of your quotation submitted on {SUBMITTED_AT} for RFQ {RFQ_NUMBER}. Your submission reference is {SUBMISSION_ID}.

Status: Received — under evaluation. This acknowledgment does not constitute supplier selection or PO acceptance.

Best regards,
Procurement Department | Osman Interlinings Limited (OIL)`},
 D:{n:'D · RFQ extension',s:'Revised RFQ submission deadline — {RFQ_NUMBER}',b:`Dear {SUPPLIER_CONTACT_NAME},

Please note that the submission deadline for RFQ {RFQ_NUMBER} has been extended to {RFQ_DEADLINE_CHINA} (China time).

Your secure quotation link is: {RFQ_LINK}. Please ensure that all mandatory details and documents are submitted before the revised cutoff.

Best regards,
Procurement Department | Osman Interlinings Limited (OIL)`},
 E:{n:'E · RFQ closure',s:'RFQ submission closed — {RFQ_NUMBER}',b:`Dear {SUPPLIER_CONTACT_NAME},

The submission window for RFQ {RFQ_NUMBER} has closed at {RFQ_DEADLINE_CHINA}. The secure link is now inactive. Any request to extend or reopen an RFQ is subject to internal authorization.

Thank you for your interest.

Best regards,
Procurement Department | Osman Interlinings Limited (OIL)`},
 PO:{n:'F · Official PO',s:'Official Purchase Order — {PO_NUMBER} | Osman Interlinings Limited (OIL)',b:`Dear {SUPPLIER_CONTACT_NAME},

Greetings from Osman Interlinings Limited (OIL).

We are pleased to confirm that your quotation has been approved following our procurement evaluation and approval process.

Please find attached the Purchase Order (PO) for the supply of the specified materials.

Purchase Order Details:
PO Number: {PO_NUMBER}
RFQ Reference: {RFQ_NUMBER}
Product: {PRODUCTS}
Order Quantity: {QUANTITY}
Total PO Value: {PO_TOTAL}
Delivery Terms: {INCOTERMS_NAMED_PLACE}
Expected Delivery Date: {SHIPMENT}
Payment Terms: {PAYMENT_TERMS}

Required Actions:
1. Review the attached Purchase Order and confirm your acceptance at {PO_ACK_LINK}.
2. Submit your official Proforma Invoice (PI) referencing the PO number.
3. Ensure that the PI details — product specifications, quantity, price, payment terms and delivery terms — match the approved PO.

Purchase Order Attachment: {PO_NUMBER}.pdf

Please acknowledge receipt and confirm your acceptance by {ACK_DEADLINE}. Any required changes or discrepancies should be communicated to our Procurement Department before proceeding.

Best regards,
Procurement Department
Osman Interlinings Limited (OIL)
{CONTACT_EMAIL}
This is a system-generated email from the OIL Procurement Management System. Please refer to the attached Purchase Order for complete terms and conditions.`},
 G:{n:'G · PO acknowledgment reminder',s:'Action required — acknowledge PO {PO_NUMBER}',b:`Dear {SUPPLIER_CONTACT_NAME},

Our records show that acknowledgment of PO {PO_NUMBER} remains pending. Please review the PO and confirm acceptance or report a discrepancy at: {PO_ACK_LINK}.

Kindly submit the PI consistent with the approved PO terms.

Best regards,
Procurement Department | Osman Interlinings Limited (OIL)`},
 H:{n:'H · Internal approval assignment',s:'Procurement approval required — {REQUEST_ID} / {STAGE}',b:`Dear {APPROVER_NAME},

An OIL procurement case requires your {STAGE} action.

• Reference: {REQUEST_ID}
• Recommended suppliers: {SUPPLIER_NAME}
• Proposed value: {AMOUNT}
• Requested action: {ACTION_LABEL}
• Review link: {INTERNAL_APPROVAL_LINK}

Please review the supporting RFQ, CS, quality record and commercial justification, then approve, return or reject with comments as applicable.

OIL Procurement Management System`}};
const TPL=()=>DB.tpl||TPL0;
function mail(c,code,to,vars,o={}){
  const t=TPL()[code],v={CONTACT_EMAIL:'[OIL_CONTACT_EMAIL]',INTERNAL_APPROVAL_LINK:'https://pms.[oil-domain]/approvals/[CASE-TOKEN]',...vars};
  const m={id:'MSG-'+rnd(6),code,to,subject:fill(t.s,v),body:fill(t.b,v),ts:o.ts||DB.now+(DB.tick++)*1000,status:o.status||'Sent',cid:c?c.id:'',ref:o.pid||''};
  DB.mails.unshift(m);return m;
}

