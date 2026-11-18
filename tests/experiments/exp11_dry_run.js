// Experiment 11 — LOCAL DRY RUN (not MongoDB).
// Executes experiments/11_mongodb_atlas_compass/mongodb_crud_commands.js unchanged inside a
// Node vm, against a tiny in-memory stand-in for db.Students that supports only what the
// lab uses (equality and $gt filters, $set, insertOne/Many, find, findOne, updateOne,
// deleteOne/Many, countDocuments). It proves the script runs in order and gives the
// expected counts, and that sample_students.json matches the script. Cloud execution on
// Atlas is a separate, manual step.
'use strict';
const fs = require('fs'), vm = require('vm'), path = require('path');
const dir = path.join(__dirname, '../../experiments/11_mongodb_atlas_compass');
let pass = 0, fail = 0;
const check = (id, label, ok) => { console.log(`${ok ? 'PASS' : 'FAIL'}  ${id}  ${label}`); ok ? pass++ : fail++; };

let seq = 0;
const oid = () => (Date.now().toString(16).slice(-8) + (++seq).toString(16).padStart(16, '0')).slice(0, 24);
const matches = (doc, f) => Object.entries(f).every(([k, v]) =>
  v !== null && typeof v === 'object' && '$gt' in v ? doc[k] > v.$gt : doc[k] === v);
function collection() {
  const docs = [];
  return {
    docs,
    insertOne(d) { const x = { _id: oid(), ...d }; docs.push(x); return { acknowledged: true, insertedId: x._id }; },
    insertMany(a) { const ids = {}; a.forEach((d, i) => { ids[i] = this.insertOne(d).insertedId; }); return { acknowledged: true, insertedIds: ids }; },
    find(f = {}) { const r = docs.filter(d => matches(d, f)).map(d => ({ ...d })); return { toArray: () => r }; },
    findOne(f = {}) { return this.find(f).toArray()[0] ?? null; },
    countDocuments(f = {}) { return docs.filter(d => matches(d, f)).length; },
    updateOne(f, u) {
      const d = docs.find(x => matches(x, f)); if (!d) return { acknowledged: true, matchedCount: 0, modifiedCount: 0 };
      let mod = 0; for (const [k, v] of Object.entries(u.$set || {})) { if (d[k] !== v) { d[k] = v; mod = 1; } }
      return { acknowledged: true, matchedCount: 1, modifiedCount: mod };
    },
    deleteOne(f) { const i = docs.findIndex(x => matches(x, f)); if (i >= 0) docs.splice(i, 1); return { acknowledged: true, deletedCount: i >= 0 ? 1 : 0 }; },
    deleteMany(f) { let n = 0; for (let i = docs.length - 1; i >= 0; i--) if (matches(docs[i], f)) { docs.splice(i, 1); n++; } return { acknowledged: true, deletedCount: n }; },
  };
}

const dbs = {}; let current = null; const out = [];
const dbProxy = name => new Proxy({}, { get: (_, k) => k === 'getSiblingDB' ? (n => { current = n; return dbProxy(n); }) : ((dbs[name] ??= {})[k] ??= collection()) });
current = 'test';
const ctx = { db: dbProxy('test'), print: (...a) => out.push(a.join(' ')), printjson: o => out.push(JSON.stringify(o)), JSON };
let ran = true;
const script = fs.readFileSync(path.join(dir, 'mongodb_crud_commands.js'), 'utf8');
try { vm.createContext(ctx); vm.runInContext(script, ctx); vm.runInContext(script, ctx); }   // twice = pasted again in the same shell
catch (e) { ran = false; console.log(e.message); }
const S = dbs.CollegeDB?.Students;

check('M1', 'mongodb_crud_commands.js runs start to finish, and again in the same session (re-runnable)', ran);
check('M2', 'uses database CollegeDB, collection Students', current === 'CollegeDB' && !!S);
check('M3', 'insertOne + insertMany report acknowledged inserts with generated _id',
  /insertOne -> .*"insertedId"/.test(out.join('\n')) && /insertMany -> .*"insertedIds"/.test(out.join('\n')));
const second = out.slice(out.lastIndexOf('All students:') - 2);   // check the 2nd run's output
out.splice(0, out.length, ...second);
const allLine = out[out.indexOf('All students:') + 1] || '[]';
const all = JSON.parse(allLine);
check('M4', 'find({}) returned 3 documents, every one with an _id', all.length === 3 && all.every(d => typeof d._id === 'string'));
check('M5', 'find({name:"Rahul"}) returned only rollNo 101', JSON.parse(out[out.indexOf('Find Rahul:') + 1]).map(d => d.rollNo).join() === '101');
check('M6', 'find({cgpa:{$gt:8}}) returned Rahul and Priya', JSON.parse(out[out.indexOf('CGPA greater than 8:') + 1]).map(d => d.name).sort().join() === 'Priya,Rahul');
check('M7', 'updateOne + $set: matched 1, modified 1, Rahul cgpa now 9',
  /updateOne -> .*"matchedCount":1.*"modifiedCount":1/.test(out.join('\n')) && S.findOne({ rollNo: 101 }).cgpa === 9.0 && S.findOne({ rollNo: 101 }).branch === 'Computer');
check('M8', 'deleteOne: Amit removed, 2 documents left', S.countDocuments({ rollNo: 103 }) === 0 && S.countDocuments({}) === 2);
const sample = JSON.parse(fs.readFileSync(path.join(dir, 'sample_students.json'), 'utf8'));
const inserted = all.map(({ _id, ...r }) => r);
check('M9', 'sample_students.json = the 3 documents the script inserts', JSON.stringify(sample) === JSON.stringify(inserted));
const filters = JSON.parse(fs.readFileSync(path.join(dir, 'compass_filters.json'), 'utf8'));
check('M10', 'compass_filters.json has the lab filters ({}, Rahul, $gt 8, rollNo 101 $set 9.0, rollNo 103)',
  JSON.stringify(filters.read_all.filter) === '{}' && filters.read_rahul.filter.name === 'Rahul'
  && filters.read_cgpa_gt_8.filter.cgpa.$gt === 8 && filters.update_rahul.update.$set.cgpa === 9.0 && filters.delete_amit.filter.rollNo === 103);
const secrets = fs.readdirSync(dir, { recursive: true }).filter(f => fs.statSync(path.join(dir, f)).isFile())
  .some(f => /mongodb(\+srv)?:\/\/[^<\s"'`]+:[^<\s"'`]+@/.test(fs.readFileSync(path.join(dir, f), 'utf8')));
check('M11', 'no credential-bearing connection string in the experiment folder', !secrets);

console.log(`\n${pass} / ${pass + fail} passed (local dry run — Atlas not contacted)`);
process.exit(fail ? 1 : 0);
