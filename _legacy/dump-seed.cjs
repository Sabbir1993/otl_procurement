// Runs the legacy prototype engine headless and dumps its seeded demo database (buildDB) to JSON.
// usage: node _legacy/dump-seed.js  -> database/seeders/data/prototype-db.json
const fs=require('fs'),path=require('path');
const dir=path.join(__dirname,'oilpms-js-engine');
const order=['00-foundation.js','01-data.js','02-ui-engine.js','03-router.js','04-widgets-logic.js'];
const man=fs.readFileSync(path.join(__dirname,'oilpms.config.php'),'utf8');
const files=[...man.matchAll(/'(menu\/[^']+\.js)'/g)].map(m=>m[1]);
let src=[...order,...files].map(f=>fs.readFileSync(path.join(dir,f),'utf8')).join('\n');
const store={};
const g={window:{OILPMS_BASE:'',OILPMS_ROUTES:{},OILPMS_DETAIL:[]},localStorage:{getItem:k=>store[k]||null,setItem:(k,v)=>{store[k]=v},removeItem:k=>{delete store[k]}},
 document:{body:{},querySelector:()=>null,querySelectorAll:()=>[],createElement:()=>({}),documentElement:{dataset:{}},addEventListener:()=>{}},
 location:{pathname:'/',search:'',hash:''},history:{pushState(){},replaceState(){}},MutationObserver:class{observe(){}},matchMedia:()=>({matches:false}),URLSearchParams,Intl,console,Blob:class{},URL};
g.window.document=g.document;g.window.location=g.location;g.window.addEventListener=()=>{};
const body=`with(g){ ${'' } }`;
const fn=new Function('g',`with(g){return (function(){'use strict';${src}\nreturn {get DB(){return DB},buildDB,PAYM,BANKCH,EXTRA_REQ,SUPTERMS,MATS,SUPS,SPMAP,QT,CALS,POLICY,TPL0,ROLES,REASONS,PERM,NAV,ROLE_NAV,ACS,STAGES,IMPDOCS,FDEF:FDEF.map(f=>({...f,rule:f.rule?f.rule.toString():null}))};})()}`);
const api=fn(g);
api.buildDB();
const out={db:api.DB,static:{PAYM:api.PAYM,BANKCH:api.BANKCH,EXTRA_REQ:api.EXTRA_REQ,FDEF:api.FDEF.map(f=>{const o={...f};o.rule=!!f.rule;return o}),SUPTERMS:api.SUPTERMS,MATS:api.MATS,SUPS:api.SUPS,ROLES:api.ROLES,REASONS:api.REASONS,PERM:api.PERM,ROLE_NAV:api.ROLE_NAV,NAV:api.NAV,ACS:api.ACS,STAGES:api.STAGES,IMPDOCS:api.IMPDOCS,CALS:api.CALS,POLICY:api.POLICY,TPL0:api.TPL0}};
fs.writeFileSync(path.join(__dirname,'..','database','seeders','data','prototype-db.json'),JSON.stringify(out,null,1));
console.log('cases',api.DB.cases.map(c=>c.id+':'+Object.keys(c.pis||{}).length),'exc',api.DB.exc.length,'audit',api.DB.audit.length,'mails',api.DB.mails.length,'notifs',api.DB.notifs.length);
