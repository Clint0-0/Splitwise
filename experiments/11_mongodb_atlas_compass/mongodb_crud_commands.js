// Experiment 11 — CRUD on CollegeDB.Students
// Run in the mongosh shell built into MongoDB Compass (bottom ">_MONGOSH" bar), or in
// a terminal:  mongosh "<YOUR-ATLAS-CONNECTION-STRING>" --file mongodb_crud_commands.js
// (never commit a connection string that contains a password).
// Re-runnable: step 0 empties the collection first, and `var` (not const) lets you paste
// it into the same Compass shell session again without "already declared" errors.

db = db.getSiblingDB("CollegeDB");      // switch to CollegeDB (created on first insert); same as `use CollegeDB`

// 0. Start clean so the script can be run again (lab collection only)
db.Students.deleteMany({});

// ---------------------------------------------------------------- CREATE
// insertOne(): inserts ONE document; returns { acknowledged, insertedId }
var r1 = db.Students.insertOne({ rollNo: 101, name: "Rahul", branch: "Computer", semester: 5, cgpa: 8.5 });
print("insertOne ->", JSON.stringify(r1));

// insertMany(): inserts an ARRAY of documents in one call; returns { acknowledged, insertedIds }
var r2 = db.Students.insertMany([
  { rollNo: 102, name: "Priya", branch: "ECS",        semester: 5, cgpa: 9.1 },
  { rollNo: 103, name: "Amit",  branch: "Mechanical", semester: 6, cgpa: 7.8 }
]);
print("insertMany ->", JSON.stringify(r2));
// Every document got an automatically generated _id (ObjectId) because none was supplied.

// ---------------------------------------------------------------- READ
print("All students:");                               // filter {}
printjson(db.Students.find({}).toArray());           // 3 documents, each with _id

print("Find Rahul:");                                 // filter { name: "Rahul" }
printjson(db.Students.find({ name: "Rahul" }).toArray());

print("CGPA greater than 8:");                        // $gt = "greater than" (strict)
printjson(db.Students.find({ cgpa: { $gt: 8 } }).toArray());   // Rahul 8.5, Priya 9.1

// ---------------------------------------------------------------- UPDATE
// $set changes only the named field; the rest of the document is kept.
var u = db.Students.updateOne({ rollNo: 101 }, { $set: { cgpa: 9.0 } });
print("updateOne ->", JSON.stringify(u));           // matchedCount 1, modifiedCount 1
printjson(db.Students.findOne({ rollNo: 101 }));     // cgpa: 9

// ---------------------------------------------------------------- DELETE
// deleteOne(): removes the FIRST document that matches the filter.
var d = db.Students.deleteOne({ rollNo: 103 });
print("deleteOne ->", JSON.stringify(d));           // deletedCount 1
print("Amit still there? ", db.Students.countDocuments({ rollNo: 103 }));   // 0
print("Students left: ", db.Students.countDocuments({}));                   // 2

// deleteMany() would remove EVERY matching document, e.g. all semester-5 students:
//   db.Students.deleteMany({ semester: 5 })     // not run: keeps the lab result intact
