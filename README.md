# Shared Expenses App — SplitwisePro

A production-ready shared expenses tracker built in Laravel, designed to resolve flatmate expenses (USD/INR conversion), manage membership changes over time, audit changes collaboratively, and ingest spreadsheet exports with full anomaly detection.

---

## 1. Features Implemented
- **Group Workspace**: View active flatmates, net balances, simplified settlements, and transaction histories.
- **Rohan's Transparency Engine**: Detailed personal ledgers showing exact math and conversion rates for every expense.
- **Aisha's Simplified settlements**: One-click greedy debt settlement plan ("who pays whom and how much").
- **Priya's Multi-currency Support**: Automatic date-based USD/INR conversions caching rates in DB.
- **Sam & Meera's Membership Bounds**: Transactions are restricted to active member tenures.
- **Meera's Collaborative Audits**: Edits and deletions require approval from group members.
- **Anomaly Detection Import Engine**: Ingests `expenses_export.csv` and corrects data errors (duplicates, negatives, out-of-tenure, wrong split types) using default resolution policies, rendering an Import Report.

---

## 2. Prerequisites
- **PHP**: ^8.1
- **Composer**: ^2.0
- **SQLite** (default database, relational, requires no server configuration)

---

## 3. Quick Setup Instructions

### Step 1: Clone & Install Dependencies
```bash
composer install
```

### Step 2: Configure Environment
Copy `.env.example` to `.env` (preconfigured to use SQLite database in `database/database.sqlite`):
```bash
cp .env.example .env
php artisan key:generate
```

### Step 3: Initialize Database
Run migrations to generate tables:
```bash
php artisan migrate
```

### Step 4: Run Verification Tests
Verify all logic rules, balance engines, and anomaly detections are working:
```bash
php artisan test
```

### Step 5: Start Local Server
Run the local php development server:
```bash
php artisan serve
```
Open your browser and navigate to: `http://127.0.0.1:8000`

---

## 4. How to Import the CSV Data
1. Register and log in.
2. Create an expense group (e.g. "Spreetail Flat").
3. In the Group Workspace, click **Import CSV**.
4. Upload `expenses_export.csv` located in the project root.
5. Review the **Import Report** to see all detected anomalies and applied policies!
6. Return to the workspace to view net balances, simplified debt settlements, and Rohan's detailed ledgers.

---

## 5. AI Tools Used
- **AI Tool**: Gemini 3.5 Flash via Antigravity Agent.
- **Audit Logs**: Detailed usage and corrections can be reviewed in [AI_USAGE.md](file:///Users/shruti/spreetail/AI_USAGE.md).
