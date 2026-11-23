# UPI hand-off: manual tests on real phones (Phase 6)

Automated tests prove the link, QR code, amounts and the pending → confirmed flow (tests U1–U13, E2E3–E2E5). They **cannot** prove what each UPI app does with the link. Do these by hand before anyone relies on the feature. Use **your own** UPI IDs and tiny amounts (₹1) between team members; never the demo `@example` IDs.

SplitWise+ never collects, holds or moves money. Opening a UPI app proves nothing: a payment counts only after the receiver confirms it in SplitWise+. No claim is made here about which regulations do or don't apply; get advice before a public launch.

## Setup
1. Two accounts in one group, A owes B.
2. B adds a real UPI ID on Profile.
3. A opens the group → **Pay** next to the suggestion (or Settle up).

## Checklist (record app + version + phone + result)
| # | Step | Expected |
|---|---|---|
| 1 | Android, Chrome: tap **Open UPI app** | App chooser or default UPI app opens with B's name, the amount and the note |
| 2 | Repeat with GPay, PhonePe, Paytm, BHIM as default | Note which apps accept the link, which refuse (some block links from unverified payees) |
| 3 | iPhone, Safari: tap **Open UPI app** | iOS may not open any app for `upi://`: record the result |
| 4 | Desktop: scan the QR with each UPI app | Payee, amount and note pre-filled |
| 5 | **Copy UPI ID**, paste into the app manually | Works when 1–4 don't |
| 6 | Change the amount (part payment), tap again | App shows the new amount |
| 7 | Pay ₹1, return to SplitWise+ without recording | Nothing changes in balances |
| 8 | Record with the UPI reference in the note: **I've paid: mark as sent** | Status *pending*; balances unchanged |
| 9 | B checks their bank/UPI app, then **Received** | Status *confirmed*; balances updated |
| 10 | B presses **Received** again (old tab) | Refused: "Only pending settlements can be answered" |
| 11 | A marks a payment as sent but never pays; B presses **Not received** | Status *rejected*; never counted |
| 12 | A tries to pay more than owed | Refused with the outstanding amount |

Results go into `docs/TESTING_REPORT.md` (Phase 6 section).
