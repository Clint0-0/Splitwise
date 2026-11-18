# Experiment 11 — Cloud database with MongoDB Atlas and MongoDB Compass

**Aim:** create a MongoDB database and collection on MongoDB Atlas, connect with MongoDB Compass, and perform CRUD operations on student records.

Independent NoSQL lab: SplitWise+ stays on MySQL 8.0; the app has no MongoDB code or connection.

| File | Purpose |
|---|---|
| `ATLAS_SETUP.md` | step-by-step: project `DBMS-Lab`, cluster `StudentCluster` (free M0 or the available equivalent), database user, network access, connection string, Compass, `CollegeDB.Students`, CRUD in Compass, evidence list |
| `sample_students.json` | the 3 documents (Rahul 101, Priya 102, Amit 103) — paste into Compass *Insert Document* |
| `mongodb_crud_commands.js` | the same CRUD as mongosh commands: `insertOne`, `insertMany`, `find {}`, `find {name}`, `$gt`, `updateOne` + `$set`, `deleteOne` (+ `deleteMany` explained) |
| `compass_filters.json` | every filter/update to paste into the Compass Filter bar |
| `POST_LAB_QUESTIONS.md` | answers to the 7 post-lab questions |
| `screenshots/` | your real screenshots (none committed yet) |

## Expected results
| Operation | Result |
|---|---|
| insert 3 documents | 3 documents, each with a generated `_id: ObjectId(…)` |
| `{}` | 3 documents |
| `{ "name": "Rahul" }` | 1 document (rollNo 101) |
| `{ "cgpa": { "$gt": 8 } }` | Rahul (8.5), Priya (9.1) |
| `updateOne({rollNo:101}, {$set:{cgpa:9.0}})` | matched 1, modified 1 → Rahul cgpa 9 |
| `deleteOne({rollNo:103})` | deleted 1 → `{rollNo:103}` finds nothing, 2 documents left |

## Status
| Item | Status |
|---|---|
| Files, commands, filters, answers | **Implemented** |
| Local dry run of `mongodb_crud_commands.js` (`tests/experiments/exp11_dry_run.js`: real script executed against a minimal in-memory stand-in for `db.Students` — checks the sequence and expected counts, **not** a MongoDB server) | **Executed locally** |
| Atlas project / cluster / user / network / `CollegeDB.Students` / Compass CRUD | **Not executed — needs your Atlas account** (follow `ATLAS_SETUP.md`, then tick the evidence list) |

No credentials, connection strings or IP allowlists are stored in this folder; `.gitignore` blocks common secret files.
