/* =====================================================================
   SUPPLIER QUOTATION PARAMETERS — data dictionary from the process manual §5
   scope: h = once per quotation, l = per material line.  req: R required,
   Rw required where it applies to the material type, C conditional/optional.
   ===================================================================== */
const PAYM=['LC at sight','LC usance','TT advance','TT advance + balance against BL','TT after shipment (credit)'];
const BANKCH=['Beneficiary (supplier)','Applicant (OIL)','Shared 50/50'];
const FDEF=[
 /* ---- Company ---- */
 {k:'legal',s:'h',tab:'general',sec:'Identity',l:'Legal supplier name',t:'text',req:'R',ro:1},
 {k:'country',s:'h',tab:'general',sec:'Identity',l:'Country',t:'text',req:'R'},
 {k:'address',s:'h',tab:'general',sec:'Identity',l:'Registered address',t:'textarea',req:'R',full:1},
 {k:'kind',s:'h',tab:'general',sec:'Identity',l:'Manufacturer or trader',t:'select',opts:['Manufacturer','Trader'],req:'R'},
 {k:'site',s:'h',tab:'general',sec:'Identity',l:'Actual manufacturing site',t:'text',req:'R',h:'Supports source traceability.'},
 {k:'contactName',s:'h',tab:'general',sec:'Contact',l:'Contact name',t:'text',req:'R'},
 {k:'desig',s:'h',tab:'general',sec:'Contact',l:'Designation',t:'text',req:'R'},
 {k:'email',s:'h',tab:'general',sec:'Contact',l:'Email',t:'email',req:'R',h:'PO and clarifications are routed here. Changing it triggers independent verification at OIL.'},
 {k:'phone',s:'h',tab:'general',sec:'Contact',l:'Phone',t:'text',req:'R'},
 {k:'quoteRef',s:'h',tab:'general',sec:'Reference',l:'Your quotation reference',t:'text',req:'R'},
 {k:'quoteDate',s:'h',tab:'general',sec:'Reference',l:'Quotation date',t:'date',req:'R'},
 /* ---- Commercial: terms & conditions (header) ---- */
 {k:'currency',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Currency',t:'select',opts:['USD'],req:'R',h:'Other currencies need a controlled FX source configured by OIL Finance.'},
 {k:'payMethod',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Payment method',t:'select',opts:PAYM,req:'R'},
 {k:'payDetail',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Detailed payment terms',t:'text',req:'R',h:'e.g. 30% advance, 70% against copy of BL'},
 {k:'advPct',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Advance (%)',t:'number',req:'C',rule:(F)=>/advance/i.test(F.h.payMethod||''),h:'Required for TT advance terms.'},
 {k:'creditDays',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Usance / credit days',t:'number',req:'C',rule:(F)=>/usance|credit/i.test(F.h.payMethod||''),h:'Required for LC usance or credit terms.'},
 {k:'bankCh',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Bank charges borne by',t:'select',opts:BANKCH,req:'C',h:'Changes the landed-cost comparison.'},
 {k:'incoterm',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Incoterms 2020 rule',t:'select',opts:['FOB','CIF','CFR','FCA','CPT','CIP','DAP','EXW'],req:'R'},
 {k:'place',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Named place / port',t:'text',req:'R',h:'e.g. Shanghai Port. An Incoterm without a named place is blocked.'},
 {k:'validity',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Quotation valid until',t:'date',req:'R'},
 {k:'claims',s:'h',tab:'commercial',sec:'Terms & conditions',l:'Claims, replacement & warranty policy',t:'textarea',req:'R',full:1,h:'Copied into the PO claims clause if you are awarded.'},
 /* ---- Commercial: offer (line) ---- */
 {k:'quoteUnit',s:'l',tab:'commercial',sec:'Offer',l:'Quote unit',t:'select',opts:['m','kg','yd','roll'],req:'R'},
 {k:'qtyQuoted',s:'l',tab:'commercial',sec:'Offer',l:'Quoted quantity',t:'number',req:'R'},
 {k:'listPrice',s:'l',tab:'commercial',sec:'Offer',l:'Unit price (USD)',t:'number',req:'R',step:'0.0001'},
 {k:'tiers',s:'l',tab:'commercial',sec:'Offer',l:'Tiered quantity price',t:'tiers',req:'C',full:1,h:'Optional. Price applies from the minimum quantity shown. Partial awards use the tier of the awarded quantity.'},
 {k:'disc',s:'l',tab:'commercial',sec:'Offer',l:'Discount / rebate (%)',t:'number',req:'C',step:'0.01'},
 {k:'moq',s:'l',tab:'commercial',sec:'Offer',l:'Minimum order quantity (MOQ)',t:'number',req:'R'},
 {k:'stock',s:'l',tab:'commercial',sec:'Offer',l:'Production capacity / stock available',t:'text',req:'C'},
 {k:'prodDays',s:'l',tab:'commercial',sec:'Offer',l:'Production lead time (days)',t:'number',req:'R'},
 {k:'rts',s:'l',tab:'commercial',sec:'Offer',l:'Ready-to-ship date',t:'date',req:'R'},
 {k:'shipDays',s:'l',tab:'commercial',sec:'Offer',l:'Shipment lead time / dispatch commitment (days)',t:'number',req:'R'},
 /* ---- Technical (line) ---- */
 {k:'grade',s:'l',tab:'technical',sec:'Material',l:'Material family / grade',t:'text',req:'R'},
 {k:'code',s:'l',tab:'technical',sec:'Material',l:'Supplier product code',t:'text',req:'R'},
 {k:'comp',s:'l',tab:'technical',sec:'Material',l:'Composition (polymer / fibre %)',t:'text',req:'Rw',full:1},
 {k:'gsm',s:'l',tab:'technical',sec:'Fabric',l:'GSM / basis weight (g/m²)',t:'text',req:'Rw',types:['fabric']},
 {k:'gsmTol',s:'l',tab:'technical',sec:'Fabric',l:'GSM tolerance',t:'text',req:'Rw',types:['fabric'],h:'e.g. ±5%'},
 {k:'width',s:'l',tab:'technical',sec:'Fabric',l:'Usable width (cm)',t:'text',req:'Rw',types:['fabric']},
 {k:'widthTol',s:'l',tab:'technical',sec:'Fabric',l:'Width tolerance',t:'text',req:'Rw',types:['fabric'],h:'e.g. ±1 cm'},
 {k:'construction',s:'l',tab:'technical',sec:'Fabric',l:'Base fabric construction',t:'select',opts:['','Nonwoven','Woven','Knitted'],req:'C',types:['fabric']},
 {k:'structure',s:'l',tab:'technical',sec:'Fabric',l:'Structure / bonding method',t:'text',req:'C',types:['fabric']},
 {k:'resin',s:'l',tab:'technical',sec:'Coating & fusing',l:'Coating resin type',t:'select',opts:['','PA','PES','PE','EVA','HDPE','Other'],req:'C',types:['fabric']},
 {k:'coatWt',s:'l',tab:'technical',sec:'Coating & fusing',l:'Coating weight / dot density',t:'text',req:'C',types:['fabric']},
 {k:'fuseTemp',s:'l',tab:'technical',sec:'Coating & fusing',l:'Fusing temperature',t:'text',req:'C',types:['fabric'],grp:'fuse'},
 {k:'fuseTime',s:'l',tab:'technical',sec:'Coating & fusing',l:'Fusing time',t:'text',req:'C',types:['fabric'],grp:'fuse'},
 {k:'fusePress',s:'l',tab:'technical',sec:'Coating & fusing',l:'Fusing pressure',t:'text',req:'C',types:['fabric'],grp:'fuse'},
 {k:'peel',s:'l',tab:'technical',sec:'Performance tests',l:'Peel / bond strength (value + unit)',t:'text',req:'C',types:['fabric']},
 {k:'peelMethod',s:'l',tab:'technical',sec:'Performance tests',l:'Peel test method / conditioning',t:'text',req:'C',types:['fabric'],grp:'peel'},
 {k:'shrink',s:'l',tab:'technical',sec:'Performance tests',l:'Shrinkage / dimensional stability',t:'text',req:'C',types:['fabric']},
 {k:'color',s:'l',tab:'technical',sec:'Performance tests',l:'Colour / shade / whiteness',t:'text',req:'C',types:['fabric']},
 {k:'hand',s:'l',tab:'technical',sec:'Performance tests',l:'Hand feel / stiffness / thickness',t:'text',req:'C',types:['fabric']},
 {k:'stability',s:'l',tab:'technical',sec:'Performance tests',l:'Thermal / chemical stability',t:'text',req:'C'},
 {k:'particle',s:'l',tab:'technical',sec:'Adhesive powder',l:'Particle size (µm)',t:'text',req:'Rw',types:['powder']},
 {k:'melt',s:'l',tab:'technical',sec:'Adhesive powder',l:'Melt characteristics (melting range / MFI)',t:'text',req:'C',types:['powder']},
 {k:'visc',s:'l',tab:'technical',sec:'Chemical',l:'Viscosity',t:'text',req:'C',types:['chemical']},
 {k:'solids',s:'l',tab:'technical',sec:'Chemical',l:'Solids content',t:'text',req:'C',types:['chemical']},
 {k:'ph',s:'l',tab:'technical',sec:'Chemical',l:'pH',t:'text',req:'C',types:['chemical']},
 {k:'shelf',s:'l',tab:'technical',sec:'Chemical',l:'Shelf life',t:'text',req:'C',types:['chemical']},
 /* ---- Delivery & packing ---- */
 {k:'loadPort',s:'h',tab:'delivery',sec:'Shipment',l:'Port of loading',t:'text',req:'R'},
 {k:'dischargePort',s:'h',tab:'delivery',sec:'Shipment',l:'Port of discharge / destination',t:'text',req:'R'},
 {k:'partial',s:'h',tab:'delivery',sec:'Shipment',l:'Partial shipments permitted',t:'select',opts:['','Yes','No'],req:'C'},
 {k:'transship',s:'h',tab:'delivery',sec:'Shipment',l:'Transshipment permitted',t:'select',opts:['','Yes','No'],req:'C'},
 {k:'origin',s:'h',tab:'delivery',sec:'Shipment',l:'Country of origin',t:'text',req:'C'},
 {k:'hs',s:'l',tab:'delivery',sec:'Customs & freight',l:'Proposed HS code',t:'text',req:'C',h:'Customs planning only — OIL verifies independently.'},
 {k:'grossWt',s:'l',tab:'delivery',sec:'Customs & freight',l:'Estimated gross weight (kg)',t:'number',req:'C',grp:'cbm'},
 {k:'netWt',s:'l',tab:'delivery',sec:'Customs & freight',l:'Estimated net weight (kg)',t:'number',req:'C',grp:'cbm'},
 {k:'cbm',s:'l',tab:'delivery',sec:'Customs & freight',l:'Estimated volume (CBM)',t:'number',req:'C',step:'0.01',grp:'cbm'},
 {k:'rollLen',s:'l',tab:'delivery',sec:'Packing',l:'Roll length',t:'text',req:'C'},
 {k:'rollCount',s:'l',tab:'delivery',sec:'Packing',l:'Roll count',t:'number',req:'C'},
 {k:'pkgType',s:'l',tab:'delivery',sec:'Packing',l:'Package type',t:'text',req:'C'},
 {k:'cartonDim',s:'l',tab:'delivery',sec:'Packing',l:'Carton / bale dimensions',t:'text',req:'C'},
 {k:'labeling',s:'l',tab:'delivery',sec:'Packing',l:'Labelling',t:'text',req:'C'},
 /* ---- Sample & documents (line) ---- */
 {k:'sampleAvail',s:'l',tab:'sample',sec:'Sample',l:'Sample available',t:'select',opts:['','Yes','No'],req:'R'},
 {k:'sampleQty',s:'l',tab:'sample',sec:'Sample',l:'Sample quantity',t:'text',req:'C'},
 {k:'sampleDate',s:'l',tab:'sample',sec:'Sample',l:'Sample dispatch date',t:'date',req:'C'},
 {k:'sampleCost',s:'l',tab:'sample',sec:'Sample',l:'Sample cost',t:'text',req:'C'},
 {k:'sampleFreight',s:'l',tab:'sample',sec:'Sample',l:'Sample freight payer',t:'select',opts:['','Supplier','OIL'],req:'C'},
 {k:'courier',s:'l',tab:'sample',sec:'Sample',l:'Courier',t:'text',req:'C'},
 {k:'awb',s:'l',tab:'sample',sec:'Sample',l:'AWB / tracking number',t:'text',req:'C',rule:(F,pid)=>!!(F.l[pid]||{}).sampleDate,h:'Required once a dispatch date is entered.'},
 {k:'expRecv',s:'l',tab:'sample',sec:'Sample',l:'Expected receipt date',t:'date',req:'C'},
 {k:'tds',s:'l',tab:'sample',sec:'Quality documents',l:'Technical Data Sheet (TDS)',t:'file',req:'R'},
 {k:'coa',s:'l',tab:'sample',sec:'Quality documents',l:'Certificate of Analysis (COA) — batch reference',t:'file',req:'R'},
 {k:'report',s:'l',tab:'sample',sec:'Quality documents',l:'Test report / compliance certificate',t:'file',req:'C'},
 {k:'sds',s:'l',tab:'sample',sec:'Quality documents',l:'Safety Data Sheet (SDS)',t:'file',req:'Rw',types:['powder','chemical']},
 /* ---- Declaration ---- */
 {k:'compliance',s:'h',tab:'review',sec:'Declaration',l:'Compliance with RFQ specifications',t:'select',opts:['','Complies fully with the RFQ specification','Complies except for the deviations listed below'],req:'R',full:1},
 {k:'exceptions',s:'h',tab:'review',sec:'Declaration',l:'Exception / deviation list',t:'textarea',req:'C',rule:(F)=>/except/i.test(F.h.compliance||''),full:1,h:'One deviation per line. Start the line with the material ID (e.g. “P-1003: GSM 42 vs 40”). Silent acceptance is not allowed.'},
 {k:'decName',s:'h',tab:'review',sec:'Declaration',l:'Authorised signatory name',t:'text',req:'R'}];
const EXTRA_REQ=[['coatWt','Coating weight / dot density'],['fuse','Fusing temperature, time and pressure'],['peel','Peel / bond strength and method'],['shrink','Shrinkage / dimensional stability'],['color','Colour / shade / whiteness'],['hand','Hand feel / stiffness / thickness'],['construction','Base fabric construction'],['hs','HS code'],['cbm','Weight and CBM'],['stock','Stock / capacity']];
const fApplies=(f,pid)=>!f.types||f.types.includes(prod(pid).type);
function fReq(c,f,pid,F){
  if(f.s==='l'&&!fApplies(f,pid))return false;
  if(f.req==='R'||f.req==='Rw')return true;
  if(f.rule&&f.rule(F,pid))return true;
  const rf=(c.rfq&&c.rfq.reqFields)||[];return rf.includes(f.grp||f.k);
}
const fGet=(F,f,pid)=>f.s==='h'?F.h[f.k]:(F.l[pid]||{})[f.k];
const hasVal=v=>Array.isArray(v)?v.length>0:String(v==null?'':v).trim()!=='';
/* ---------- pricing, terms and cash-out impact ---------- */
function tierBase(q,qty){const t=(q.tiers||[]).filter(x=>+x.min>0&&+x.price>0).sort((a,b)=>b.min-a.min).find(x=>qty>=+x.min);return t?+t.price:q.listPrice!=null?q.listPrice:q.price}
const unitPriceFor=(q,qty)=>+(tierBase(q,qty)*(1-(+q.disc||0)/100)).toFixed(4);
const payText=h=>`${h.payMethod||''}${h.payDetail?' — '+h.payDetail:''}`.trim();
function termsScore(h){const m=h.payMethod||'';if(/at sight/i.test(m))return 85;if(/usance/i.test(m))return Math.round(70+Math.min(+h.creditDays||0,90)/9);if(/credit/i.test(m))return 78;if(/balance/i.test(m))return Math.round(62-(+h.advPct||0)/10);if(/advance/i.test(m))return 40;return 60}
const bankCost=h=>/Applicant/i.test(h.bankCh||'')?185:/Shared/i.test(h.bankCh||'')?93:/Beneficiary/i.test(h.bankCh||'')?0:185;
const advExposure=(q,h)=>/advance/i.test(h.payMethod||'')?Math.round((+h.advPct||0)/100*q.price*q.qty):0;
const cashOut=h=>{const m=h.payMethod||'';return /at sight/i.test(m)?'At document presentation':/usance/i.test(m)?`${h.creditDays||'?'} days after shipment`:/credit/i.test(m)?`${h.creditDays||'?'} days after shipment`:/balance/i.test(m)?`${h.advPct||'?'}% advance, rest against BL`:/advance/i.test(m)?`${h.advPct||100}% before shipment`:'—'};
const hdrOf=(c,sid)=>(c.quoteHdr||{})[sid]||{};
/* declared deviations → per material */
function devFor(c,sid,pid){
  const h=hdrOf(c,sid);if(!/except/i.test(h.compliance||''))return'';
  const L=String(h.exceptions||'').split(/\n+/).map(x=>x.trim()).filter(Boolean),m=L.filter(x=>/^P-\d+/i.test(x));
  const mine=m.filter(x=>x.toUpperCase().startsWith(pid)),gen=L.filter(x=>!/^P-\d+/i.test(x));
  return[...mine,...gen].join('; ');
}
/* missing required technical parameters (blanks) for a quote line */
function techBlanks(c,sid,pid){
  const q=(c.quotes[sid]||{})[pid];if(!q)return[];const F={h:hdrOf(c,sid),l:{[pid]:q.spec||{}}};
  return FDEF.filter(f=>f.s==='l'&&f.tab==='technical'&&fReq(c,f,pid,F)&&!hasVal(fGet(F,f,pid))).map(f=>f.l);
}
/* ---------- seeded (mock) quotation parameters ---------- */
function mkSpec(pid,sid,qty,days,over){
  const p=prod(pid),nm=p.name,m=nm.match(/(\d+)\s*GSM/),gsm=m?m[1]:'',mt=p.meta+' '+p.comp;
  const resin=/\bPA\b/.test(mt)?'PA':/HDPE/.test(mt)?'HDPE':/PES/.test(p.meta)?'PES':'';
  const pd=Math.round(days*.6),sd=days-pd,code=spName(sid,pid);
  const o={quoteUnit:p.uom,qtyQuoted:qty,stock:'Ready stock + production',prodDays:pd,shipDays:sd,rts:isoD(new Date(NOW0+pd*DAY)),grade:p.meta,code,comp:p.comp,hs:'[HS-CODE]',cbm:+(qty*.0004).toFixed(1),grossWt:Math.round(qty*.06),netWt:Math.round(qty*.055),rollLen:p.type==='fabric'?'100 m':'25 kg bag',rollCount:p.type==='fabric'?Math.round(qty/100):Math.round(qty/25),pkgType:p.type==='fabric'?'Roll in carton':'Bag on pallet',cartonDim:'120 × 40 × 40 cm',labeling:'PO no., batch, GSM, width',
   sampleAvail:'Yes',sampleQty:p.type==='fabric'?'3 m':'1 kg',sampleDate:isoD(new Date(NOW0-9*DAY)),sampleCost:'Free of charge',sampleFreight:'Supplier',courier:'DHL',awb:'[AWB-NUMBER]',expRecv:isoD(new Date(NOW0-5*DAY)),tds:`TDS_${code.replace(/\s/g,'')}.pdf`,coa:'COA_batch_2026.pdf',report:'',sds:p.type==='fabric'?'':'SDS_2026.pdf'};
  if(p.type==='fabric')Object.assign(o,{gsm,gsmTol:'±5%',width:'150',widthTol:'±1 cm',construction:/Knit|Tricot/.test(nm)?'Knitted':/Woven/.test(nm)?'Woven':'Nonwoven',structure:/Sew-in/.test(nm)?'Spunbond':'Thermal bonded',resin,coatWt:/Sew-in/.test(nm)?'':'12 g/m²',fuseTemp:/Sew-in/.test(nm)?'':'130–150 °C',fuseTime:/Sew-in/.test(nm)?'':'10–14 s',fusePress:/Sew-in/.test(nm)?'':'3–4 bar',peel:'≥ 6 N / 5 cm',peelMethod:'ISO 11339, 23 °C / 50% RH',shrink:'≤ 1.5%',color:'White',hand:'Medium',stability:''});
  else Object.assign(o,{particle:'80–200 µm',melt:'Melting range 110–125 °C',stability:'Stable below 40 °C'});
  return Object.assign(o,over||{});
}
function seedHdr(sid,sp,q0){
  const t=SUPTERMS[sid]||{},pay=t.payment||'LC at sight',tr=pay.match(/(\d+)% advance/),days=pay.match(/usance (\d+)/);
  const method=/at sight/.test(pay)?'LC at sight':/usance/.test(pay)?'LC usance':/70% against/.test(pay)?'TT advance + balance against BL':'TT advance';
  return{legal:sp.name,country:sp.cc==='CN'?'China':sp.cc==='KR'?'South Korea':sp.cc,address:`[SUPPLIER-REGISTERED-ADDRESS], ${sp.cc==='CN'?'China':sp.cc}`,kind:'Manufacturer',site:`${t.place||'Factory'} region plant`,contactName:sp.contact,desig:sp.desig||'Sales',email:sp.email,phone:sp.phone,quoteRef:`${sid.slice(-2)}-QT-2026-118`,quoteDate:isoD(new Date(NOW0-8*DAY)),currency:'USD',payMethod:method,payDetail:pay,advPct:tr?+tr[1]:(method==='TT advance'?100:''),creditDays:days?+days[1]:'',bankCh:sid==='SUP-00002'?'Shared 50/50':'Beneficiary (supplier)',incoterm:t.incoterm||'FOB',place:t.place||'',validity:isoD(new Date(Date.UTC(2026,10,25))),claims:'Confirmed defects replaced or credited within 30 days of receipt; 12-month warranty on bond strength.',loadPort:t.place||'',dischargePort:'Chattogram Port',partial:'Yes',transship:'No',origin:sp.cc==='CN'?'China':sp.cc,compliance:'Complies fully with the RFQ specification',exceptions:'',decName:sp.contact,decAt:NOW0-8*DAY};
}
function mkQuote(pid,sid,qty){
  const r=QT[pid]&&QT[pid][sid];if(!r)return null;let o;
  if(Array.isArray(r)){const [price,moq,days,fpu,dp,q,sample]=r,mat=price*qty;o={price,moq,days,freight:Math.round(qty*fpu),ins:Math.round(mat*.0065),duty:Math.round(mat*dp),bank:185,q,sample}}else o={...r,bank:185};
  const h=seedHdr(sid,supOf(sid)),q={...o,orig:o.price,qty,incoterm:h.incoterm,place:h.place,payment:payText(h),terms:termsScore(h),hist:(SUPTERMS[sid]||{}).hist||60,validity:Date.UTC(2026,10,25),rev:1,listPrice:o.price,tiers:[],disc:0};
  q.spec=mkSpec(pid,sid,qty,q.days);q.bank=bankCost(h);
  /* a few realistic price structures that still resolve to the quoted price at the required quantity */
  if(pid==='P-1002'&&sid==='SUP-00001')q.tiers=[{min:15000,price:.99},{min:30000,price:o.price}];
  if(pid==='P-1008'&&sid==='SUP-00003'){q.listPrice=.8;q.disc=1.25}
  if(pid==='P-1005'&&sid==='SUP-00002')q.tiers=[{min:6000,price:1.12},{min:10000,price:o.price}];
  /* parameter gaps and deviations that the comparison must surface */
  if(sid==='SUP-00003'){q.spec.peel='';q.spec.peelMethod='';q.spec.shrink=''}
  if(sid==='SUP-00003'&&pid==='P-1003'){q.spec.gsm='42'}
  return q;
}
function seedQuoteHdr(c,sid){
  const h=seedHdr(sid,supOf(sid));
  if(sid==='SUP-00003'){h.compliance='Complies except for the deviations listed below';h.exceptions='P-1003: GSM 42 g/m² vs specified 40 (±5%)'}
  return h;
}

