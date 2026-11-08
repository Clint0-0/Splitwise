# PROJECT_SPECIFICATION.md — SplitWise+

**SplitWise+ — Personal Expense Sharing and Settlement System** · DBMS Mini Project · Team of 3
Status: Phase 1 design approved 2026-11-08 (all proposals in §9 accepted). Phase 2 database implemented.

## 1. Overview
Web application for recording personal and group expenses, splitting them five ways, deriving who owes whom, recording settlements, simplifying debts, tracking budgets and analysing spending. Built to demonstrate all nine DBMS lab experiments on its real database.

## 2. Problem statement
Shared spending among friends, roommates, families and travel groups is tracked by hand or in chat, causing calculation errors, disputes over who owes whom, lost history of partial repayments and no view of spending patterns.

## 3. Objectives
1. Reliable expense-sharing and settlement app (PHP + MySQL, runs locally).
2. Mathematically consistent accounting (Σ balances in a group = 0, always).
3. Normalized (3NF/BCNF) schema with full integrity constraints.
4. Demonstrate Experiments 1–9 plus transactions, procedures, functions, indexing, ACID on real data.

## 4. Scope
**In scope:** modules 1–11 below, single currency (INR), single payer per expense, in-app notifications, local XAMPP deployment.
**Out of scope (initial):** multi-payer expenses, multi-currency, real payment gateways, real email/SMS, mobile apps, sharing with non-registered users, cross-group settlements. Optional list in master prompt §15 only after core is done.

## 5. User roles
| Role | How represented | Can |
|---|---|---|
| Registered user | `users.role='user'` | manage own profile, groups they belong to, expenses, settlements, budgets |
| Group admin | `group_members.role='admin'` (creator is first admin) | invite/remove members, assign admins, edit any group expense, restore deleted expenses, archive group |
| System admin | `users.role='admin'` | admin area: users, groups, activity (audit log), stats, deactivate users, clean invalid records |

## 6. Functional requirements
IDs are used in `REQUIREMENTS_TRACEABILITY.md`.

- **FR-1 Auth & profile:** register, login, logout, `password_hash()`/`password_verify()`, sessions, profile view/edit, change password, settings (`notify_email`), unique email/username, validation.
- **FR-2 Groups:** create/edit, list mine, invite registered users, accept/decline invitation, list members, remove member (admin, zero balance), assign admin, leave (zero balance), group expense history, archive.
- **FR-3 Expenses:** add/edit/soft-delete (authorized), view details, category, description, amount, payer, participants, notes, date, optional receipt (JPG/PNG/PDF ≤ 5 MB), tags, search & filter (date range, category, payer, amount, text), paginated history. Personal expense = no group.
- **FR-4 Splits:** equal, percentage (Σ=100), exact (Σ=total), shares, adjustment ("custom"); largest-remainder rounding; Σ shares = total.
- **FR-5 Balances:** paid, owed, net, receivable, payable — per group, overall, pairwise; transaction history.
- **FR-6 Settlements:** record (payer/payee/amount/date/method/note), partial, pending→confirm/reject/cancel, history, outstanding tracking, duplicate protection.
- **FR-7 Debt simplification:** suggested transfers from net balances; never auto-recorded.
- **FR-8 Budgets:** personal and group, optional category, period, limit, spent, remaining, utilization %, threshold warning.
- **FR-9 Analytics:** personal/group totals, category breakdown, monthly trend, group comparison, top categories, largest expenses, paid vs owed, settlement summary, budget utilization (Chart.js, live DB data).
- **FR-10 Notifications:** invitations, new/updated/deleted expense, settlement request/confirmation/rejection, budget warning; unread count, mark read.
- **FR-11 Admin:** list/search users and groups, deactivate users, audit-log activity viewer, system stats.

## 7. Non-functional requirements
| ID | Requirement |
|---|---|
| NFR-1 Security | PDO prepared statements only; output escaping; CSRF tokens on all POST; session cookie HttpOnly + SameSite=Lax + id regeneration on login; server-side authorization on every group/expense/settlement access; uploads validated by MIME (finfo) + size, stored outside web root with random names; no secrets in Git (`.env`) |
| NFR-2 Correctness | DECIMAL money, integer-paise math, group balance invariant tested |
| NFR-3 Integrity | PK/FK/UNIQUE/CHECK/NOT NULL, triggers, transactions |
| NFR-4 Performance | indexed queries; dashboard queries < 200 ms on sample data; paginated lists |
| NFR-5 Usability | responsive Bootstrap 5, consistent components, empty states, clear errors |
| NFR-6 Portability | runs on XAMPP (PHP 8.2) + MySQL 8.0; one import script |
| NFR-7 Maintainability | MVC-inspired structure, no framework, documented decisions |
| NFR-8 Auditability | financial changes logged to `audit_logs` |

## 8. Technology stack
HTML5, CSS3, JavaScript, Bootstrap 5, Bootstrap Icons, Chart.js, PHP 8.2+ (PDO), MySQL 8.0, XAMPP (Apache), Git/GitHub. No other framework. Frontend libraries loaded from local `public/assets/vendor/` copies (works offline at viva).

## 9. Ambiguities and conflicts found (proposed resolution)
| # | Issue | Proposal |
|---|---|---|
| A1 | **XAMPP bundles MariaDB, not MySQL 8.0.** Differences: `CHECK`/FK rules, JSON is an alias for LONGTEXT, some functions differ, lab syntax is MySQL | Install **MySQL 8.0 Community Server** alongside XAMPP (Apache + PHP from XAMPP, MySQL 8.0 on port 3306; disable XAMPP's MariaDB). Scripts target MySQL 8.0 only. Approved |
| A2 | "Custom split" undefined; overlaps "exact" | Implement as **adjustment split** (equal base ± per-person adjustment). Approved |
| A3 | Spec lists "Expense Participants" and "Expense Splits" separately | Merged into `expense_splits` (participant = split row) |
| A4 | Multiple payers per expense? | Single payer (spec says "the person who paid") |
| A5 | Individual tracking vs groups | Personal expense = `group_id NULL`; sharing always happens inside a group (a 2-person group for 1:1) |
| A6 | Settlement confirmation flow unspecified | Payee-recorded → confirmed; payer-recorded → pending until payee confirms |
| A7 | Who may edit/delete an expense | Creator, payer, or group admin |
| A8 | Leaving with unpaid balance | Blocked until net = 0 |
| A9 | Self-join needs a "genuine" scenario | Co-members (group_members self-join) and category hierarchy; no artificial referral column |
| A10 | Target 16–22 tables | 14 tables proposed; each justified. Possible additions only if needed: `budget_categories` (multi-category budgets), `recurring_expenses` (optional feature). Approved |
| A11 | Debt simplification optimality | Greedy (≤ n−1 transfers), not guaranteed minimum; documented |
| A12 | Testing tool | Plain-PHP test runner + SQL test scripts (no Composer/PHPUnit) to respect "no unnecessary dependencies". Approved |

## 10. Risks
| Risk | Impact | Mitigation |
|---|---|---|
| MariaDB vs MySQL mismatch on team laptops | Scripts fail at viva | A1; `SELECT VERSION()` check in setup guide |
| Rounding errors in splits | Wrong balances | integer paise + largest remainder + `fn_expense_split_gap` assertion |
| Concurrent duplicate settlements | Over-settlement | `request_token` UNIQUE + `FOR UPDATE` lock |
| Editing old expenses after settlements | Balance flips sign | allowed but audited and notified; balances are always recomputed so remain consistent |
| Scope creep (optional features) | Core late | Phase gating |
| Uneven team understanding | Viva weakness | each member explains schema + one cross-module flow |
| Unauthorized data access via IDs in URL | Data leak | membership middleware on every group-scoped route |

## 11. Edge cases and rules
| Case | Rule |
|---|---|
| One participant (= payer) | Valid; no debt created |
| Payer not a participant | Valid; payer owed full total |
| Participant leaves/removed | Only at zero balance; history kept |
| Group deleted | Archive only if it has financial records |
| Expense modified / deleted | Versioned edit, soft delete, audited |
| Receipt attached | Deleted with expense only on hard delete (admin) |
| Multiple settlements for one debt | Allowed while outstanding > 0 |
| Settlement rejected/cancelled | Ignored in balances, kept in history |
| Duplicate settlement request | `request_token` + pending amounts count toward limit |
| Rounding | Largest remainder, payer first on ties |
| Zero / positive / negative balances | Displayed as "settled up" / "gets back ₹x" / "owes ₹x" |
| Group with no expenses / settlements | Empty states; queries use LEFT JOIN / COALESCE |
| Budget without spending | 0% used |
| Expense outside budget period | Not counted |
| Invalid date (future) | Rejected (app + trigger) |
| Invalid money (≤0, >2 decimals, > 9,999,999,999.99) | Rejected (app + CHECK + DECIMAL) |
