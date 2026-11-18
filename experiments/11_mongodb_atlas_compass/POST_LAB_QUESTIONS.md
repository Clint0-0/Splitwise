# Experiment 11 — Post-lab questions

**1. What is the difference between SQL and MongoDB?**
SQL databases (MySQL, used by SplitWise+) are *relational*: data lives in tables with a fixed schema of rows and columns, relationships are expressed with foreign keys and joins, and the language is SQL. MongoDB is a *NoSQL document database*: data lives in collections of JSON-like (BSON) documents, documents in one collection may have different fields (flexible schema), related data is often embedded in one document, and it is queried with methods such as `find()`, `insertOne()`, `updateOne()` instead of SQL statements. Relational DBs suit strongly structured, transactional data; MongoDB suits fast-changing or semi-structured data and scales out easily.

| SQL (MySQL) | MongoDB |
|---|---|
| database | database |
| table | collection |
| row | document |
| column | field |
| primary key | `_id` |
| `SELECT … WHERE` | `find({ filter })` |

**2. What is a collection?**
A group of MongoDB documents stored together inside a database — the counterpart of a table. It does not enforce one fixed structure. Here: `Students` in `CollegeDB`.

**3. What is a document?**
One record in a collection: a set of field–value pairs in JSON-like BSON format, e.g. `{ rollNo: 101, name: "Rahul", branch: "Computer", semester: 5, cgpa: 8.5 }`. Values can be numbers, strings, dates, arrays or nested documents. Counterpart of a row.

**4. What is the purpose of `_id`?**
`_id` is the primary key of every document: it must be unique within the collection and is indexed automatically. If you do not supply it, MongoDB generates an `ObjectId` (12 bytes: timestamp + random value + counter), so each document can always be identified, updated or deleted exactly.

**5. Difference between `insertOne()` and `insertMany()`?**
`insertOne(doc)` inserts a single document and returns its `insertedId`. `insertMany([doc1, doc2, …])` inserts an array of documents in one operation and returns all `insertedIds`; by default it is *ordered* — it stops at the first error, keeping the documents already inserted.

**6. Purpose of the `$set` operator?**
In an update, `$set` changes the value of the given field(s) only (adding the field if it is missing) and leaves the rest of the document unchanged. `updateOne({ rollNo: 101 }, { $set: { cgpa: 9.0 } })` changed Rahul's CGPA from 8.5 to 9.0 without touching name, branch or semester. Without an operator, a replacement would overwrite the whole document.

**7. Difference between `deleteOne()` and `deleteMany()`?**
`deleteOne(filter)` deletes only the first document matching the filter (`deletedCount` 0 or 1). `deleteMany(filter)` deletes *every* matching document; `deleteMany({})` empties the collection. Use `deleteOne` with a unique field such as `rollNo` when exactly one record must go.
