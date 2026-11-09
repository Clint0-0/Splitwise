# TEAM_TEST_CHECKLIST.md — 15-minute check on each team laptop

Do this once on **each** member's laptop before the viva. Tick every box; write problems in the table at the end and post them in the team chat.

Tester: ____________   Laptop / OS: ____________   Date: ____________

## 1. Install (about 5 minutes)
- [ ] MySQL **8.0** Community Server installed; XAMPP's own MySQL/MariaDB is **stopped** (port 3306 free).
- [ ] `mysql -u root -p -e "SELECT VERSION();"` prints `8.0.x` (not MariaDB).
- [ ] Repo is at `C:\xampp\htdocs\Splitwise` and up to date (`git pull` on `main`).
- [ ] `C:\xampp\php\php.ini`: `upload_max_filesize` ≥ 6M, `post_max_size` ≥ 8M, `extension=pdo_mysql`, `extension=curl`, `extension=fileinfo` enabled. Apache restarted after editing.
- [ ] Import **as root**: `mysql --default-character-set=utf8mb4 -u root -p < database\splitwise_plus.sql` (triggers and procedures run as the account that imported them, so never import with a temporary account you later delete)
- [ ] App account: edit the password in `database\security\app_user.sql` (or keep the test one), then `mysql -u root -p < database\security\app_user.sql`
- [ ] `.env` created from `.env.example` with `DB_USER=splitwise_app` and the same password.
- [ ] `mysql -u root -p < database\verify_install.sql` → **every row PASS**.

## 2. Automated tests (about 3 minutes)
```bat
cd C:\xampp\htdocs\Splitwise
C:\xampp\php\php.exe tests\run_all.php --mysql="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" --password=YOUR_ROOT_PASSWORD
```
- [ ] Ends with **ALL SUITES PASSED** (unit 41, SQL scripts 11, database 74, functional 103, scenarios 23).
- [ ] If anything fails: copy the whole output into the problems table.

## 3. Click-through in a browser (about 7 minutes)
Open **http://localhost/Splitwise/public/**. Do it in **Chrome** and in one other browser (**Firefox** or **Edge**).

| # | Do this | Expect | Chrome | Other |
|---|---|---|---|---|
| 1 | Open `http://localhost/Splitwise/` | redirects to `/Splitwise/public/login` | ☐ | ☐ |
| 2 | Open `http://localhost/Splitwise/.env` | **403 Forbidden** | ☐ | ☐ |
| 3 | Log in as `priya` / `Password@123` | Dashboard: "you get back ₹29,825.66" | ☐ | ☐ |
| 4 | Groups → Goa Trip 2026 | 3 suggested settlements; "Who owes whom" list | ☐ | ☐ |
| 5 | Add expense in Goa Trip: ₹999, By percentage, 50/50 for two people | live preview shows ₹499.50 each and "Adds up to ₹999.00 ✓" | ☐ | ☐ |
| 6 | Same form, change to 50/40 | red message "add up to 90.00% (need 100%)"; Save shows the error again | ☐ | ☐ |
| 7 | Attach a JPG or PDF receipt (< 5 MB) and save | receipt link opens the file | ☐ | ☐ |
| 8 | Settle up → record a payment you **received** | balances change at once | ☐ | ☐ |
| 9 | Analytics | 5 charts draw; "Show as table" opens tables | ☐ | ☐ |
| 10 | Narrow the window to phone width (or DevTools device mode) | menu becomes ≡ drawer; nothing cut off | ☐ | ☐ |
| 11 | Press Tab through the expense form | every field and button shows a purple focus ring | ☐ | ☐ |
| 12 | Log out, log in as `admin` → Admin → Activity log | audit rows visible | ☐ | ☐ |

After the click-through, re-import (`splitwise_plus.sql`) to get clean sample data for the demo.

## Problems found
| # | Step | What happened (exact error text / screenshot) | Browser / OS |
|---|---|---|---|
| | | | |
