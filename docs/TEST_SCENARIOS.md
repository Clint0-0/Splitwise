# TEST_SCENARIOS.md — SplitWise+ money scenarios (Phase 5)

Whole stories run through the real app over HTTP by `tests/functional/run_scenarios.php` (23 checks).
Every amount below was worked out by hand first; the test asserts the database **and** the wording users see.
Balance convention: **+ gets back, − owes**, net = paid − share + sent − received.

People: **Asha** (group admin), **Bala**, **Chetan** — new accounts registered through the sign-up form, all in group "Spec checks".

## A. The examples from the project brief

| Step | What happens | Asha | Bala | Chetan | Checks |
|---|---|---:|---:|---:|---|
| A1 | Dinner ₹1,500 paid by Asha, split equally | +1,000 | −500 | −500 | SC-A1, SC-A2 ("You owe Asha Kulkarni ₹500.00") |
| A2 | Bala records paying Asha ₹300 | +1,000 | −500 | −500 | SC-A3: still pending, nothing changes |
| A3 | Asha confirms on her dashboard ("Did you receive this money?") | +700 | −200 | −500 | SC-A4, SC-A5: Bala sees "₹200.00 of ₹500.00" and "₹300.00 paid back so far" |
| A4 | Movie ₹1,200 paid by Chetan at 50 / 30 / 20 % → 600 / 360 / 240 | +100 | −560 | +460 | SC-A6, SC-A7 |
| A5 | Asha's monthly budget ₹10,000; her shares reach ₹7,500 | | | | SC-A8: "₹2,500.00 left (75% used)", no warning |
| A6 | One more ₹600 → ₹8,100 (81 %) | | | | SC-A9: exactly one budget warning |

## B. Settle everything, then change history

| Step | What happens | Asha | Bala | Chetan | Checks |
|---|---|---:|---:|---:|---|
| B1 | Suggested payments: Bala pays Chetan ₹460, Bala pays Asha ₹100 | +100 | −560 | +460 | SC-B1 (2 payments, not 3) |
| B2 | Chetan and Asha record receiving them (confirmed at once) | 0 | 0 | 0 | SC-B2: "Everyone is settled up" |
| B3 | Asha edits the dinner to ₹1,800 (₹600 each) | +200 | −100 | −100 | SC-B3: only the ₹300 difference re-opens; SC-B4: Bala and Chetan notified |
| B4 | Chetan deletes the movie | +800 | +260 | −1,060 | SC-B5: directions flip, the 3 confirmed payments stay; SC-B6: Chetan told "You pay Asha ₹800.00", "You pay Bala ₹260.00" |
| B5 | Asha (admin) restores the movie | +200 | −100 | −100 | SC-B7; SC-B8: history shows ₹1,500 → ₹1,800 and who did it |

Why B4 flips: Chetan had been paid ₹460 for his share of the movie; with the movie gone, that money is now owed back. Nothing is lost — payments are never deleted, and restoring the expense puts every balance back.

## C. Rounding
| Step | What happens | Result | Checks |
|---|---|---|---|
| C1 | Auto ₹1,000 paid by Chetan, split by 3 | Chetan 333.34, Asha 333.33, Bala 333.33; gap 0.00 | SC-C1 |
| C2 | Group total of all balances | exactly ₹0.00 | SC-C2 |

## D. Debt cycle (sample group "Weekend Trek")
Kabir paid ₹500 for Sneha, Sneha paid ₹500 for Rohan, Rohan paid ₹500 for Kabir. Three debts are listed, every net balance is 0, and **no** payment is suggested (SC-D1).

## E. Guard rails inside the story
| Step | Attempt | Result | Checks |
|---|---|---|---|
| E1 | Bala records paying Asha ₹5,000 while he owes ₹433.33 | refused: "Amount exceeds what the payer owes" | SC-E1 |
| E2 | Bala tries to leave the group while owing | refused: "must be settled up first" | SC-E2 |

## Where the other edge cases are tested
| Edge case (master prompt §14) | Test IDs |
|---|---|
| One participant / payer not a participant | unit "single participant", "payer not a participant"; DB seed E18–E20 |
| Participant leaves / removed | K1–K6, L1–L2 |
| Group archived (and still settleable) | P5, P5b |
| Expense modified / deleted / restored | E20–E25, SC-B3–B7, K2–K4 |
| Receipt attached / rejected | R1–R5, T7–T8 |
| Multiple / partial settlements | S1–S5, SC-A2–A5, SC-B2 |
| Rejected / cancelled settlement | DB T12, seed S4–S5 |
| Duplicate settlement request | S1 (same token), DB P5 |
| Rounding | unit property test (3,000 splits), F6–F7, SC-C1 |
| Zero / positive / negative balances | SC-B2, SC-B5, H6 |
| Group with no expenses / settlements | DB Q1, Office Team sample group |
| Budget without spending / outside period | DB F5, sample budget "Entertainment – September" |
| Invalid date / amount | E7–E8, unit Money tests, DB C2/C14/C15 |
