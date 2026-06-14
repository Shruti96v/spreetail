# AI Usage Log — Shared Expenses App

This document details the usage of AI coding tools during development, key prompts, and concrete case studies of corrections.

---

## 1. AI Tools & Key Prompts
- **AI Tool**: Gemini 3.5 Flash via Antigravity Agent.
- **Key Prompt**: "Generate code incrementally. Never skip steps. Always explain: What file to create, Where to create it, Why it exists, How it works, and How it connects with the rest of the system."

---

## 2. Case Studies of AI Corrections

### Case 1: Test Compilation Import Omission
- **What the AI did wrong**: The AI generated the unit test file `AnomalyDetectionTest.php` but forgot to import `App\Models\Expense`. This resulted in a compilation failure when calling `Expense::create()`, throwing `Class "Tests\Unit\Expense" not found`.
- **How it was caught**: Running `php artisan test` in the terminal.
- **What was changed**: Added the missing namespace import statement `use App\Models\Expense;` to the top of the test file and restored other deleted imports.

### Case 2: Database-Specific Date String Mismatches
- **What the AI did wrong**: The AI queried database dates using strict equality, e.g., `where('expense_date', $expenseDate->toDateString())`. Because SQLite casts dates to `'Y-m-d H:i:s'` string representations, querying with a `'Y-m-d'` format returned null.
- **How it was caught**: Running tests showed that duplicates weren't detected and exchange rates failed checks.
- **What was changed**: Refactored date queries to use Laravel's database-agnostic `whereDate('expense_date', ...)` builder method, and updated model cast parameters to `'date:Y-m-d'`.

### Case 3: Logical Anomaly Conflict in Test CSV
- **What the AI did wrong**: The AI wrote a CSV row where "Sam" paid for a gym session on April 8, despite Sam's join date being April 15. The anomaly engine correctly caught that the date was before his join date, but its fallback policy was to shift Sam's join date back. This caused the test assertion (ensuring Sam had no splits before April 15) to fail.
- **How it was caught**: The integration test `BalanceAndImportTest` failed.
- **What was changed**: Changed the payer of that row in the CSV to "Aisha". The anomaly engine then correctly kept Sam's join date at April 15, logged the anomaly, and excluded Sam from the split, resolving the test check.
