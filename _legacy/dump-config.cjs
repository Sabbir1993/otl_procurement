// Emits PHP config files from the prototype static tables (roles, permissions, menu visibility).
const fs = require('fs'), path = require('path');
const s = require('../database/seeders/data/prototype-db.json').static;
const BS = String.fromCharCode(92);
const esc = (x) => x.split(BS).join(BS + BS).split("'").join(BS + "'");
const php = (v, ind = 0) => {
  const p = '    '.repeat(ind), q = '    '.repeat(ind + 1);
  if (v === null) return 'null';
  if (typeof v === 'boolean') return v ? 'true' : 'false';
  if (typeof v === 'number') return String(v);
  if (typeof v === 'string') return "'" + esc(v) + "'";
  if (Array.isArray(v)) {
    if (!v.length) return '[]';
    if (v.every((x) => typeof x !== 'object' || x === null) && JSON.stringify(v).length < 110) return '[' + v.map((x) => php(x)).join(', ') + ']';
    return '[\n' + v.map((x) => q + php(x, ind + 1) + ',').join('\n') + '\n' + p + ']';
  }
  const k = Object.keys(v);
  if (!k.length) return '[]';
  return '[\n' + k.map((x) => q + php(x) + ' => ' + php(v[x], ind + 1) + ',').join('\n') + '\n' + p + ']';
};
const out = (f, c, doc) => fs.writeFileSync(path.join(__dirname, '..', 'config', f), '<?php\n\n// ' + doc + '\n// Generated from the prototype by _legacy/dump-config.cjs, then maintained by hand.\nreturn ' + php(c) + ';\n');
out('roles.php', { roles: s.ROLES, perm: s.PERM, role_nav: s.ROLE_NAV }, 'Roles, permission map (segregation of duties) and per-role menu visibility (group => true | [page keys]).');
out('quote_fields.php',{paym:s.PAYM,bank_charges:s.BANKCH,extra_req:s.EXTRA_REQ,fields:s.FDEF},'Supplier quotation parameter dictionary (process manual section 5). s: h=header/once, l=per material line. req: R required, Rw required where applicable, C conditional. rule=true means the field is conditionally required by a rule coded in App/Domain/QuoteSpec.php.');
console.log('ok');
