# Technical Interview Preparation Guide — SplitwisePro

This document contains 50 technical interview questions and detailed answers based on the architecture, database design, business logic, and security workflows of this Shared Expenses application.

---

## Section 1: System Architecture & Design Patterns

### Q1: What architecture does this application follow and why?
- **Answer**: The application follows the Model-View-Controller (MVC) architecture with an isolated Service Layer. Business-critical procedures like CSV import (`ImportService`), anomaly audits (`AnomalyDetectionService`), and balance math (`BalanceService`) are placed in dedicated Service classes.
- **Why**: Keeps controllers thin, simplifies unit/feature testing, and adheres to the Single Responsibility Principle (SRP).
- **Code Ref**: `app/Services/` directory.

### Q2: Why did you separate AnomalyDetectionService from ImportService?
- **Answer**: Ingestion and validation are two distinct concerns. `ImportService` handles file operations, transactions, database inserts, and row iteration. `AnomalyDetectionService` inspects the raw array fields and returns corrected data or skips flags.
- **Why**: Allows reusing anomaly checks in manual entry forms if needed, without duplicating logic.
- **Code Ref**: [ImportService.php](file:///Users/shruti/spreetail/app/Services/ImportService.php) calls [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L20-L30).

### Q3: How do you handle collaborative approvals for modifications/deletions (Meera's Rule)?
- **Answer**: Direct updates or deletes on the `expenses` table are blocked. Instead, we insert a record into the `approval_requests` table containing the `proposed_data` encoded as a JSON payload, leaving the original transaction untouched until group members click "Approve".
- **Why**: Protects the finality of transactions, satisfies audit compliance, and prevents unauthorized changes.
- **Code Ref**: [ExpenseController.php](file:///Users/shruti/spreetail/app/Http/Controllers/ExpenseController.php#L80-L100).

### Q4: Why did you store proposed edits as a JSON column instead of duplicate tables?
- **Answer**: A JSON payload allows us to capture complex state changes (such as custom participant splits) dynamically in a single row without creating temp tables or duplicate schema layouts.
- **Why**: Keeps database migrations clean and easy to maintain.
- **Code Ref**: `proposed_data` column in `approval_requests` table.

### Q5: How is user authentication managed?
- **Answer**: Managed securely using Laravel's built-in session-based authentication guarded by the `auth` middleware. Password strings are encrypted using BCrypt (handled by Laravel's `'hashed'` cast).
- **Why**: Prevents session hijacking, enforces CSRF token validations, and hashes credentials out of the box.
- **Code Ref**: [AuthController.php](file:///Users/shruti/spreetail/app/Http/Controllers/AuthController.php) and [routes/web.php](file:///Users/shruti/spreetail/routes/web.php#L17).

---

## Section 2: Database Architectures & Eloquent ORM

### Q6: Why did you choose Decimal instead of Float or Double for monetary columns?
- **Answer**: Float and Double use floating-point binary representation, which leads to precision loss and rounding errors (e.g. `0.1 + 0.2 = 0.30000000004`). Decimal stores values as exact strings. We use `DECIMAL(15, 4)` for amounts and `DECIMAL(18, 6)` for exchange rates.
- **Why**: Guarantees financial ledger accuracy.
- **Code Ref**: [create_expenses_table.php](file:///Users/shruti/spreetail/database/migrations/2026_06_14_151308_create_expenses_table.php#L20).

### Q7: What is the purpose of the unique constraint on `exchange_rates`?
- **Answer**: The unique constraint is placed on `(base_currency, target_currency, rate_date)`.
- **Why**: Prevents duplicate daily rates for the same currency pair on a specific day, preserving database integrity.
- **Code Ref**: [create_exchange_rates_table.php](file:///Users/shruti/spreetail/database/migrations/2026_06_14_151309_create_exchange_rates_table.php#L21).

### Q8: How did you implement membership history in the relational model?
- **Answer**: Via the `group_members` pivot table, which contains a `joined_at` date and a nullable `left_at` date.
- **Why**: Supports computing membership states on any date, allowing us to enforce Sam and Meera's timeline rules.
- **Code Ref**: [GroupMember.php](file:///Users/shruti/spreetail/app/Models/GroupMember.php).

### Q9: Why did you disable standard timestamps on the `audit_logs` table?
- **Answer**: Audit logs are append-only. They are never updated. Thus, we set `public $timestamps = false;` in the model and only store a single `created_at` timestamp.
- **Why**: Saves database storage space and write overhead.
- **Code Ref**: [AuditLog.php](file:///Users/shruti/spreetail/app/Models/AuditLog.php#L11).

### Q10: What indexes were created and why?
- **Answer**: 
  - `expenses`: Index on `(group_id, expense_date)` to speed up ledger loading and balance queries.
  - `expense_splits`: Unique index on `(expense_id, user_id)` to prevent double-splitting.
  - `group_members`: Unique index on `(group_id, user_id)` to avoid duplicate memberships.
- **Why**: Optimizes read performance as group histories grow.

### Q11: Explain the relationships defined in the Group model.
- **Answer**:
  - `creator`: `belongsTo` User.
  - `members`: `belongsToMany` User through `group_members` pivot table.
  - `expenses`: `hasMany` Expense.
  - `settlements`: `hasMany` Settlement.
- **Code Ref**: [Group.php](file:///Users/shruti/spreetail/app/Models/Group.php).

### Q12: How do you handle cascade deletes when deleting a group or expense?
- **Answer**: We defined `onDelete('cascade')` on foreign key constraints (e.g. `expense_splits` references `expenses` with cascade delete).
- **Why**: Avoids orphaned records in child tables, maintaining referential integrity.
- **Code Ref**: [create_expense_splits_table.php](file:///Users/shruti/spreetail/database/migrations/2026_06_14_151308_create_expense_splits_table.php#L15).

### Q13: What does the `'date:Y-m-d'` cast format do?
- **Answer**: It forces Eloquent to serialize Carbon date instances strictly to the string format `'Y-m-d'` during database reads and writes.
- **Why**: Fixes SQLite date-time mismatch errors where database drivers append `'00:00:00'` to date columns.
- **Code Ref**: [ExchangeRate.php](file:///Users/shruti/spreetail/app/Models/ExchangeRate.php#L21).

### Q14: How does `whereDate` differ from standard `where` in Eloquent queries?
- **Answer**: `whereDate` translates the query to extract only the date portion (ignoring hours/minutes) in a database-agnostic format.
- **Why**: Ensures that string queries for date equality succeed even if stored as datetime strings.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L248).

### Q15: Why is `firstOrCreate` safer than querying `first()` and then calling `create()`?
- **Answer**: `firstOrCreate` performs database checks in an atomic block. If two parallel processes run, standard check-then-create code can create race conditions that cause double entries or constraint errors.
- **Code Ref**: [ImportService.php](file:///Users/shruti/spreetail/app/Services/ImportService.php#L265).

---

## Section 3: Business Logic & Timeline Rules

### Q16: How does Sam's mid-April join date affect balance computations?
- **Answer**: In `AnomalyDetectionService`, if an expense is dated before Sam's join date (April 15), he is excluded from the split.
- **Why**: Satisfies Sam's requirement: "I should not be affected by expenses before I joined."
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L198-L201).

### Q17: How is Meera's March 31st exit date handled for April expenses?
- **Answer**: For expenses occurring after March 31, Meera is flagged as out-of-tenure and excluded from the split.
- **Why**: Prevents billing former flatmates for expenses after they move out.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L202-L205).

### Q18: What happens if an expense is split 4 ways, but one member was not active on that date?
- **Answer**: The member is excluded from the split array, and the amount is split equally/re-allocated among the remaining 3 active members.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L197-L206).

### Q19: Can a member receive settlements after their leave date?
- **Answer**: Yes. A member can settle debts that accumulated *during* their tenure even after they leave.
- **Why**: Allows former flatmates to pay or receive final settlement balances.

### Q20: What is the default policy if a payer is not a member of the group on the expense date?
- **Answer**: The payer is added to the group with their join date shifted back to the expense date, and a warning anomaly is logged.
- **Why**: Keeps the CSV import running without halting.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L162-L165).

---

## Section 4: Currency & Conversions

### Q21: What is the base currency of the application and why?
- **Answer**: INR. All balances are calculated and aggregated in INR.
- **Why**: Allows simplifying debts into a single sheet without mixing currencies.

### Q22: How is USD converted to INR during imports?
- **Answer**: `Amount_INR = Amount_USD * Exchange_Rate`. The rate is loaded from `exchange_rates` on the date of the transaction.
- **Code Ref**: [ImportService.php](file:///Users/shruti/spreetail/app/Services/ImportService.php#L265).

### Q23: What happens if an exchange rate is missing for a date during import?
- **Answer**: The engine uses a default mock rate (e.g. 83.50), saves it in the database for future lookup, and logs a warning anomaly.
- **Code Ref**: [ImportService.php](file:///Users/shruti/spreetail/app/Services/ImportService.php#L274-L290).

### Q24: Why is the exchange rate stored on the expense record instead of always doing dynamic lookups?
- **Answer**: Historical currency conversion rates change daily. Storing the exchange rate directly on the `expenses` record preserves the absolute value at the moment the transaction occurred.
- **Code Ref**: `exchange_rate` column in `expenses` table.

### Q25: How does Priya's USD trip affect Rohan's balance?
- **Answer**: Rohan's share of the USD dinner is calculated in USD, multiplied by the exchange rate of that date to convert it to INR, and added to his outstanding debt.
- **Code Ref**: [ImportService.php](file:///Users/shruti/spreetail/app/Services/ImportService.php#L210).

---

## Section 5: Anomaly Detection Engine

### Q26: How does the engine detect duplicate expenses?
- **Answer**: It checks for another active expense in the same group with the same date, description, payer, and amount.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L246-L252).

### Q27: What is the policy for negative amounts in the CSV?
- **Answer**: Converted to positive absolute values (`abs()`), and a warning anomaly is logged.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L58-L63).

### Q28: How does the engine handle future dates?
- **Answer**: Normalized to the current system date, and a warning anomaly is logged.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L68-L72).

### Q29: What is the action taken when an unknown currency is parsed?
- **Answer**: Fallback to INR, and log a critical anomaly.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L81-L85).

### Q30: How are "settlements logged as expenses" handled?
- **Answer**: Checked via split type or description string patterns (e.g., "Aisha to Rohan"). They are routed to the `settlements` table instead of the `expenses` table.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L88-L94).

### Q31: What happens if split details contain unknown split types?
- **Answer**: Default to equal split, and log a critical anomaly.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L97-L101).

### Q32: What occurs if a row is completely missing required fields?
- **Answer**: The row is skipped, and a critical anomaly is logged.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L39-L43).

### Q33: How does the engine handle conflicting duplicates?
- **Answer**: If two rows have the same date/description/payer but different amounts, both are imported but a critical anomaly is logged.
- **Code Ref**: [AnomalyDetectionService.php](file:///Users/shruti/spreetail/app/Services/AnomalyDetectionService.php#L258-L260).

---

## Section 6: Balance Engine & Debt Simplification

### Q34: What is the formula for calculating a member's net balance?
- **Answer**: 
  $$\text{Net Balance} = (\text{Total Paid} - \text{Total Owed}) + (\text{Total Settlements Received} - \text{Total Settlements Sent})$$
- **Code Ref**: [BalanceService.php](file:///Users/shruti/spreetail/app/Services/BalanceService.php#L30-L33).

### Q35: Explain the simplified debt settlement algorithm.
- **Answer**: It calculates net balances for all members, separates them into Debtors (negative) and Creditors (positive), sorts them, and greedily matches the largest debtor with the largest creditor to minimize transactions.
- **Code Ref**: [BalanceService.php](file:///opens/BalanceService.php#L106).

### Q36: Walk through: A owes 500, B owes 200, C is owed 700. How does it settle?
- **Answer**: 
  - Debtors: A (-500), B (-200). Creditor: C (+700).
  - Largest debtor A settles with largest creditor C: A pays C 500. C's balance becomes +200.
  - Remaining debtor B settles with C: B pays C 200. All settled.
- **Transactions**: "A pays C 500", "B pays C 200". Total 2 transactions.

### Q37: How do you address rounding errors when dividing odd amounts (e.g. 10.00 among 3 people)?
- **Answer**: The equal split amount is rounded to 4 decimal places (3.3333). The allocated sum is calculated (9.9999). Any remainder (0.0001) is added to the first split participant.
- **Code Ref**: [ImportService.php](file:///Users/shruti/spreetail/app/Services/ImportService.php#L202-L206).

### Q38: How does Rohan's detailed ledger provide transparency?
- **Answer**: It aggregates all active expenses and settlements involving Rohan, lists his exact share (converted to INR), and calculates the net impact.
- **Code Ref**: [BalanceService.php](file:///Users/shruti/spreetail/app/Services/BalanceService.php#L43-L100).

---

## Section 7: Security & Audit Compliance

### Q39: What is stored in the `audit_logs` table?
- **Answer**: User ID, action performed (e.g., "APPROVED_EDIT"), descriptive details, IP address, and creation date.
- **Code Ref**: [AuditLog.php](file:///Users/shruti/spreetail/app/Models/AuditLog.php).

### Q40: How does your code protect against SQL injection?
- **Answer**: By using Laravel Eloquent ORM parameter bindings for all queries.
- **Why**: Escapes inputs automatically.

### Q41: How do you prevent CSRF attacks in forms?
- **Answer**: By using the `@csrf` Blade directive which inserts a hidden token validated by Laravel's middleware.
- **Code Ref**: [create.blade.php](file:///Users/shruti/spreetail/resources/views/expenses/create.blade.php#L10).

### Q42: What middleware is applied to protect authenticated routes?
- **Answer**: The built-in `auth` middleware.
- **Code Ref**: [routes/web.php](file:///Users/shruti/spreetail/routes/web.php#L17).

### Q43: How do you authorize that a user can only view their own groups?
- **Answer**: In the controller, we verify group membership:
  `$group->members()->where('users.id', Auth::id())->exists()`
  If false, abort with a 403 status.
- **Code Ref**: [GroupController.php](file:///Users/shruti/spreetail/app/Http/Controllers/GroupController.php#L40-L42).

---

## Section 8: Testing, Scaling & Production

### Q44: What test traits did you use and why?
- **Answer**: `RefreshDatabase`.
- **Why**: Resets database states after each test run, ensuring test isolation.
- **Code Ref**: [AnomalyDetectionTest.php](file:///Users/shruti/spreetail/tests/Unit/AnomalyDetectionTest.php#L16).

### Q45: How would you optimize the database queries for 10 million transactions?
- **Answer**: 
  - Add composite indexes on `expense_splits (user_id, amount_owed)`.
  - Introduce pre-aggregated caching for group balances (e.g., storing group balances in Redis).
  - Use database cursor pagination instead of offset pagination.

### Q46: How would you scale the CSV import for a file containing 1 million rows?
- **Answer**: Implement Laravel Queues and process the CSV file in chunks in the background, updating progress via WebSockets.
- **Why**: Prevents HTTP timeouts.

### Q47: What error handling wraps the CSV import process?
- **Answer**: Wrapped inside database transactions: `DB::beginTransaction()` and `DB::commit()` / `DB::rollBack()`.
- **Why**: Ensures that if a row crashes or imports fail midway, all changes are rolled back, keeping the database consistent.
- **Code Ref**: [ImportService.php](file:///Users/shruti/spreetail/app/Services/ImportService.php#L62-L113).

### Q48: How would you handle exchange rates dynamically in production?
- **Answer**: Integrate a scheduled console task (Laravel Scheduler) that fetches currency rates daily from an external exchange API and stores them in the `exchange_rates` table.

### Q49: What is the exit code meaning of PHPUnit tests?
- **Answer**: Exit code `0` means all tests passed. Exit code `1` or `2` indicates assertion or compilation failures.

### Q50: How does your UI satisfy accessibility (a11y) standards?
- **Answer**: By using semantic HTML5 elements (`header`, `main`, `footer`, `table`), clear label-to-input association, high contrast text color schemes, and descriptive titles.
- **Code Ref**: [app.blade.php](file:///Users/shruti/spreetail/resources/views/layouts/app.blade.php).
