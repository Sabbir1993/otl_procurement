/* =====================  INIT  ===================== */
function loadDB(){try{const s=localStorage.getItem(KEY);if(s){const d=JSON.parse(s);if(d&&d.v===4){DB=d;return}}}catch(e){}buildDB()}
function init(){
  loadDB();if(!UI.caseId||!CASE(UI.caseId))UI.caseId=(DB.cases.find(c=>c.id==='IC-2026-10')||DB.cases[0]).id;
  const app=document.createElement('div');app.id='app';document.body.appendChild(app);const t=document.createElement('div');t.id='toasts';document.body.appendChild(t);const d=document.createElement('div');d.id='dlg';document.body.appendChild(d);
  readQ();UI.fast=true;render();UI.fast=false;
}

init();
