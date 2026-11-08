# TESTING_REPORT.md — SplitWise+

## Phase 2 — Database testing

| Item | Value |
|---|---|
| Date | 2026-11-08 |
| DBMS | MySQL 8.0.46 (Ubuntu build), `sql_mode` default (`ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, …`) |
| Client | PHP 8.3 PDO (`ATTR_EMULATE_PREPARES=false`), mysql CLI with `--default-character-set=utf8mb4` |
| How to reproduce | `tests/database/run.sh` (imports `database/splitwise_plus.sql`, runs all SQL scripts, runs `tests/database/run_db_tests.php`, re-imports) |
| Result | **All 9 SQL scripts executed without error. 67 / 67 automated tests passed.** |

Results below are copied from the actual run output, not written by hand.

### Test groups
- **S** structure (tables, views, triggers, routines, no float columns, UTF-8)
- **F** financial correctness (hand-computed balances, Σ = 0 per group, rounding, pairwise reconciliation, spec example)
- **C** constraints (UNIQUE, CHECK, FK, generated-column unique, strict-mode behaviour)
- **T** triggers (all six timing/event pairs)
- **V** views (updatable vs non-updatable, CHECK OPTION)
- **Q** subquery NULL pitfall
- **P** stored procedures (validation, idempotency, atomic rollback)
- **X** concurrency (row lock on settlements)
- **W** committing flows (confirm settlement, payee-recorded settlement)

### Hand-verified expected balances (sample data)
| Group | Member | Paid | Owed (shares) | Sent | Received | Net |
|---|---|---:|---:|---:|---:|---:|
| Goa Trip 2026 | Aarav | 28000.00 | 13008.33 | 0 | 5000.00 | **+9991.67** |
| | Priya | 19000.00 | 12570.84 | 0 | 3000.00 | **+3429.16** |
| | Rohan | 4750.00 | 16037.50 | 5000.00 | 0 | **−6287.50** |
| | Sneha | 2400.00 | 12533.33 | 3000.00 | 0 | **−7133.33** |
| Flat 302 | Priya | 110780.00 | 46983.50 | 0 | 37400.00 | **+26396.50** |
| | Kabir | 4449.00 | 36182.51 | 21600.00 | 0 | **−10133.51** |
| | Ananya | 4120.50 | 36183.49 | 15800.00 | 0 | **−16262.99** |
| College Friends | Rohan / Aarav / Kabir / Vikram | | | | | **+450 / +100 / +600 / −1150** |
| Weekend Trek | all three (debt cycle) | 500 each | 500 each | | | **0** |

Pending (₹2,000, ₹4,000), rejected (₹1,000) and cancelled (₹500) settlements and the soft-deleted ₹1,200 expense are correctly excluded.

### Issues found and fixed during testing
| # | Issue | Fix |
|---|---|---|
| 1 | mysql client defaulted to `latin1`: `₹`, `–`, `é` were stored double-encoded (tests F6, T15 failed) | `SET NAMES utf8mb4;` at the top of every script; test S6 guards it |
| 2 | Assumed strict mode rejects `10.005` in `DECIMAL(12,2)`; MySQL actually rounds to `10.01` with a note | Test C14 now documents this; PHP must reject > 2 decimals (Phase 3 requirement) |
| 3 | Assumed only 2 views updatable; MySQL marks 4 (inner-join view, merged derived table) | Docs corrected; tests V4/V5 pin the real behaviour |

### Results
| Script | Result |
|---|---|
| `database/queries/01_basic_select.sql` | OK |
| `database/queries/02_filtering_and_sorting.sql` | OK |
| `database/queries/03_joins.sql` | OK |
| `database/queries/04_aggregates.sql` | OK |
| `database/queries/05_subqueries.sql` | OK |
| `database/queries/06_business_reports.sql` | OK |
| `database/queries/07_explain.sql` | OK |
| `database/views/demo_views.sql` | OK |
| `database/transactions/demo_acid.sql` | OK |

| ID | Test | Result |
|---|---|---|
| S1 | 14 base tables exist | PASS |
| S2 | 11 views exist | PASS |
| S3 | 11 triggers covering all 6 timing/event pairs | PASS |
| S4 | 5 procedures + 3 functions exist | PASS |
| S5 | No FLOAT/DOUBLE columns anywhere | PASS |
| S6 | Text stored as real UTF-8 (no double-encoded "Ã"/"â€" sequences) | PASS |
| F1 | Every group: sum of net balances = 0 | PASS |
| F2 | All 17 member balances equal hand-computed values | PASS |
| F3 | Every expense: shares sum to total (gap = 0) | PASS |
| F4 | fn_member_net_balance agrees with view for every member | PASS |
| F5 | fn_budget_spent agrees with v_budget_utilization | PASS |
| F6 | Equal 1000/3: payer gets 333.34, others 333.33 | PASS |
| F7 | Equal 2780/3: leftover paise to payer then lowest id | PASS |
| F8 | Pairwise debts reconcile to net balances for every member | PASS |
| F9 | Overall balances across all users sum to 0 | PASS |
| F10 | Soft-deleted expense excluded from balances (Goa total = 54150) | PASS |
| F11 | Spec example: B owes 500 -> pays 300 -> owes 200; A +1000 -> +700; pair outstanding 200 | PASS |
| C1 | Duplicate email rejected | PASS |
| C2 | Zero/negative expense amount rejected | PASS |
| C3 | Settlement to self rejected | PASS |
| C4 | Budget with both user and group owner rejected | PASS |
| C5 | Budget end before start rejected | PASS |
| C6 | Second pending invitation for same user/group rejected | PASS |
| C7 | New pending invite allowed after previous one was answered | PASS |
| C8 | FK: unknown category rejected | PASS |
| C9 | FK RESTRICT: user with history cannot be deleted | PASS |
| C10 | Percentage over 100 rejected | PASS |
| C11 | Receipt with disallowed MIME type rejected | PASS |
| C12 | Upper-case tag rejected | PASS |
| C13 | Active member with left_at set rejected | PASS |
| C14 | DECIMAL(12,2) silently rounds 10.005 -> 10.01 (so PHP must validate decimals) | PASS |
| C15 | Amount above DECIMAL(12,2) range rejected in strict mode | PASS |
| T1 | BEFORE INSERT expenses: future date rejected | PASS |
| T2 | BEFORE INSERT expenses: non-member payer rejected | PASS |
| T3 | BEFORE INSERT expenses: archived group rejected | PASS |
| T4 | BEFORE INSERT splits: non-member participant rejected | PASS |
| T5 | BEFORE INSERT splits: personal expense split to another user rejected | PASS |
| T6 | BEFORE INSERT splits: share above total rejected | PASS |
| T7 | BEFORE UPDATE settlements: confirmed is final | PASS |
| T8 | BEFORE UPDATE settlements: amount of pending cannot change | PASS |
| T9 | BEFORE DELETE settlements: confirmed cannot be deleted | PASS |
| T10 | BEFORE DELETE settlements: pending one may be deleted | PASS |
| T11 | AFTER INSERT settlements: payee notified of pending request | PASS |
| T12 | AFTER UPDATE settlements: payer told of rejection; responded_at set | PASS |
| T13 | AFTER UPDATE expenses: soft delete logged as SOFT_DELETE | PASS |
| T14 | AFTER DELETE splits: 3 old splits of the edited expense archived | PASS |
| T15 | AFTER INSERT splits: participants (not payer/creator) notified of new expense | PASS |
| T16 | AFTER DELETE expenses: hard delete audited (admin cleanup) | PASS |
| T17 | audit_logs records the acting user from @app_user_id | PASS |
| V1 | WITH CHECK OPTION blocks soft delete through v_active_expenses | PASS |
| V2 | Aggregate view is not updatable | PASS |
| V3 | Simple view is updatable and writes through to base table | PASS |
| V4 | information_schema: updatable = 2 simple views + inner-join view + merged-derived-table view | PASS |
| V5 | Computed column of an "updatable" view cannot be updated | PASS |
| Q1 | NOT IN with NULL in subquery returns 0 rows; NOT EXISTS returns Office Team | PASS |
| P1 | sp_record_settlement: over-settlement rejected | PASS |
| P2 | sp_record_settlement: third party cannot record | PASS |
| P3 | sp_record_settlement: pending amounts count toward limit (Sneha has 2000 pending) | PASS |
| P4 | sp_record_settlement: non-member rejected | PASS |
| P5 | sp_record_settlement: same request_token returns existing id, no new row | PASS |
| P6 | sp_respond_settlement: only payee can confirm | PASS |
| P7 | sp_add_equal_expense: non-member participant -> whole expense rolled back | PASS |
| P8 | sp_add_equal_expense: malformed participant list rejected | PASS |
| X1 | Concurrent settlement in same group blocks on FOR UPDATE lock | PASS |
| X2 | Blocked call left no partial row | PASS |
| W1 | Aarav confirms Sneha's pending 2000: Sneha -5133.33, Aarav +7991.67, group sum 0 | PASS |
| W2 | Payee-recorded settlement is confirmed immediately; Kabir settled in College Friends | PASS |

67 / 67 passed
### EXPLAIN evidence (`database/queries/07_explain.sql`)
| Query | Plan with index | Without index |
|---|---|---|
| Group history `WHERE group_id=1 AND is_deleted=0 ORDER BY expense_date DESC` | `ref` on `idx_expenses_group_deleted_date`, backward index scan, no filesort | `ALL` (full scan) + `Using filesort` |
| User's shares `WHERE user_id=3` | `ref` on `idx_expense_splits_user` | — |
| Unread notifications | `ref` on `idx_notifications_user_read_date`, no filesort | — |
| Membership check | `const` on `uq_group_members_group_user`, `Using index` | — |

### Known limitations
- Sample data is small (23 expenses), so EXPLAIN shows plan shape, not timing gains.
- Trigger notifications are not deduplicated if an expense is edited many times quickly (acceptable for an in-app feed).
