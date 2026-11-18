# Experiment 11 — MongoDB Atlas + Compass setup (manual, uses your own account)

Atlas menu names change from time to time; where the screen differs, use the nearest equivalent and note it in your record. **Never** paste your password or a full connection string into the repo, a screenshot or a chat.

## 1. Account and project
1. Open <https://cloud.mongodb.com> and sign in (or sign up — Google sign-in works).
2. Top-left project menu → **New Project** → name **`DBMS-Lab`** → *Next* → *Create Project*.

## 2. Free cluster `StudentCluster`
1. In `DBMS-Lab` → **Create** (or *Build a Database*).
2. Choose the **free tier (M0)**. If your account/region no longer offers M0, choose the nearest free option shown and write down exactly what you picked — do not claim M0.
3. Provider/region: any; nearest to you is best (e.g. AWS Mumbai `ap-south-1` if listed).
4. Cluster name: **`StudentCluster`** → *Create Deployment*. Provisioning takes a few minutes.

## 3. Database user
1. When prompted (or **Security → Database Access → Add New Database User**):
   - Authentication: *Password*
   - Username: e.g. `lab_student` (any)
   - Password: *Autogenerate Secure Password* → store it in your password manager, **not** in this repo.
   - Role: *Read and write to any database* (built-in) is enough for the lab.
2. *Add User*.

## 4. Network access
1. **Security → Network Access → Add IP Address**.
2. Prefer **Add Current IP Address** (only your machine can connect).
3. `0.0.0.0/0` ("allow from anywhere") works on college Wi-Fi with changing IPs, but then only the password protects the cluster — if you use it, delete the entry after the lab.

## 5. Connection string
1. Cluster card → **Connect** → **Compass**.
2. Copy the string. It looks like `mongodb+srv://<username>:<password>@studentcluster.xxxxx.mongodb.net/` — keep the `<password>` placeholder in anything you save or show; type the real password only into Compass.

## 6. MongoDB Compass
1. Install Compass from <https://www.mongodb.com/products/tools/compass> (free).
2. *New Connection* → paste the string → put your password in place of `<password>` → *Save & Connect*.
3. Success = left sidebar lists the cluster's databases (`admin`, `local`, …).

## 7. Database and collection
1. Sidebar **+** next to the connection → *Create Database* → Database name **`CollegeDB`**, Collection name **`Students`** → *Create Database*.
2. Open `CollegeDB.Students` → **ADD DATA → Insert Document**.
   - Paste the JSON array from `sample_students.json` (Compass accepts an array = several documents at once, like `insertMany`), or paste one object at a time (like `insertOne`).
3. The list shows 3 documents, each with an `_id: ObjectId('…')` Compass/the server generated.

## 8. CRUD in Compass
| Step | Where | Value (from `compass_filters.json`) | Expected |
|---|---|---|---|
| Read all | Filter bar | `{}` | 3 documents |
| Find Rahul | Filter bar | `{ "name": "Rahul" }` | 1 document |
| CGPA > 8 | Filter bar | `{ "cgpa": { "$gt": 8 } }` | Rahul, Priya |
| Update Rahul | filter `{ "rollNo": 101 }` → pencil icon on the document → change `cgpa` to `9.0` → *Update*; or shell: `db.Students.updateOne({rollNo:101},{$set:{cgpa:9.0}})` | | cgpa 9 |
| Delete Amit | filter `{ "rollNo": 103 }` → bin icon → *Delete*; or shell: `db.Students.deleteOne({rollNo:103})` | | filter returns 0 documents; total 2 |

All of it in one go: open the **mongosh** panel at the bottom of Compass and paste `mongodb_crud_commands.js`.

## 9. Evidence (screenshots — hide passwords/connection strings)
- [ ] Atlas project `DBMS-Lab` · [ ] cluster `StudentCluster` (tier visible) · [ ] Database user list (password not visible) · [ ] Network Access list (blur your IP if you prefer)
- [ ] Compass connected · [ ] `CollegeDB` → `Students` · [ ] 3 inserted documents with `_id`
- [ ] filter `{}` · [ ] filter Rahul · [ ] filter `$gt` · [ ] Rahul cgpa 9 · [ ] Amit deleted (filter returns nothing)

Save them in `screenshots/` (e.g. `01_project.png` …). Only real screenshots of your own run.

## 10. Afterwards
Delete the `0.0.0.0/0` entry if you added one. You may pause or delete the cluster when the lab is graded.
