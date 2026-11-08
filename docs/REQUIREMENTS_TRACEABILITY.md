# REQUIREMENTS_TRACEABILITY.md — SplitWise+

Status legend: D = designed · **DB** = database part implemented and tested (Phase 2) · I = app implemented · T = app tested. Current phase: 2 done.

| Req | Description | Module / service | Tables | DB objects | Planned tests | Status |
|---|---|---|---|---|---|---|
| FR-1.1 | Register, unique email/username | AuthController | users | UNIQUE | duplicate email rejected | DB |
| FR-1.2 | Login/logout, hashing, session | AuthController, Auth mw | users | — | wrong password; session fixation | DB |
| FR-1.3 | Profile, settings, change password | ProfileController | users | — | edit profile | DB |
| FR-2.1 | Create/edit/archive group | GroupController | expense_groups, group_members | — | creator becomes admin | DB |
| FR-2.2 | Invite, accept/decline | GroupController | group_invitations, group_members, notifications | generated `pending_flag` UNIQUE | second pending invite rejected | DB |
| FR-2.3 | Members, remove, assign admin, leave | GroupController, BalanceService | group_members | `fn_member_net_balance` | leave blocked when balance ≠ 0 | DB |
| FR-3.1 | Add/edit expense | ExpenseController, SplitCalculator | expenses, expense_splits, receipts, expense_tags | `trg_expenses_before_insert`, `trg_expense_splits_before_insert`, `fn_expense_split_gap` | non-member participant rejected; future date rejected | DB |
| FR-3.2 | Soft delete / restore | ExpenseController | expenses, audit_logs | `trg_expenses_after_update`, `v_active_expenses` | deleted excluded from balances | DB |
| FR-3.3 | Search, filter, paginate history | ExpenseController | expenses, categories, tags | indexes | LIMIT/OFFSET pages | DB |
| FR-3.4 | Receipt upload | UploadService | receipts | CHECK mime/size | .php upload rejected | DB |
| FR-4 | Five split methods + rounding | SplitCalculator | expense_splits | `sp_add_equal_expense` | % ≠ 100 rejected; exact ≠ total rejected; ₹1000/3 sums to 1000 | DB |
| FR-5 | Balances (group, overall, pairwise) | BalanceService | expenses, expense_splits, settlements | `v_group_member_balances`, `v_user_overall_balance`, `v_pairwise_debts`, `fn_member_net_balance` | Σ net = 0 per group; spec ₹1500 example | DB |
| FR-6 | Settlements + confirm flow | SettlementService | settlements, notifications, audit_logs | `sp_record_settlement`, `sp_respond_settlement`, 3 settlement triggers | partial ₹300 of ₹500 → ₹200; over-settle rejected; double-submit rejected; confirmed immutable | DB |
| FR-7 | Debt simplification | DebtSimplifier | (reads views) | `v_group_member_balances` | cycle → 0 transfers; Σ preserved | DB |
| FR-8 | Budgets + warnings | BudgetService, NotificationService | budgets, notifications | `v_budget_utilization`, `fn_budget_spent` | ₹7,500 of ₹10,000 → ₹2,500 left, 75% | DB |
| FR-9 | Analytics | AnalyticsController (JSON) | expenses, expense_splits, settlements, budgets | `v_monthly_user_spending`, `v_group_expense_summary`, `sp_spending_summary` | chart totals = SQL totals | DB |
| FR-10 | Notifications | NotificationService | notifications | `trg_settlements_after_insert` | unread count | DB |
| FR-11 | Admin area | AdminController, Admin mw | users, expense_groups, audit_logs | — | non-admin gets 403 | DB |
| NFR-1 | Security | middleware, helpers | — | — | SQLi, XSS, CSRF, IDOR tests | DB |
| NFR-2/3 | Correctness & integrity | all | all | CHECK/FK/triggers | constraint test script | DB |
| EXP 1–9 | Lab experiments | — | see `SQL_EXPERIMENT_MAPPING.md` | queries, triggers, views | each script runs clean | DB |
