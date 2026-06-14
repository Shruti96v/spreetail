# Scope & Anomaly Log — Shared Expenses App

This document logs every data anomaly found in the `expenses_export.csv` file, the resolution policy applied by the engine, and the resulting database schema.

---

## 1. CSV Anomaly Log & Policy Application

| Row # | Raw CSV Data Problem | Anomaly Detected | Severity | Action & Policy Applied |
| :--- | :--- | :--- | :--- | :--- |
| **2** | Duplicate of Groceries (Row 1). | Duplicate Expense | Warning | **Skipped row**. Skipped importing duplicate transactions with same date, paid_by, description, and amount. |
| **4** | Description "Priya to Rohan", split "settlement". | Settlement Recorded as Expense | Info | **Routed to Settlements**. Bypassed expense splits, recorded payment from Priya to Rohan. |
| **6** | Dinner in USD. Payer is "Dev" (non-member). | Missing Members | Warning | **Auto-created member**. Created guest account "Dev", added to group, and converted amount using historical rate. |
| **8** | Date 2026-04-05. Split details includes "Meera" (left March 31). | Date Outside Membership | Critical | **Excluded member**. Excluded Meera from splits, recalculated split equally among Aisha, Rohan, and Priya. |
| **12** | Amount is -150.00. | Negative Amount | Warning | **Applied absolute value**. Converted amount to +150.00. |
| **13** | Date is 2026-07-10 (future). | Future Date | Warning | **Normalized date**. Reset date to current date. |
| **14** | Date 2026-04-08. Split details includes "Sam" (joined April 15). | Date Outside Membership | Critical | **Excluded member**. Excluded Sam, split equally among active members (Aisha, Rohan, Priya). |
| **15** | Amount field is empty. | Empty Required Fields | Critical | **Skipped row**. Row lacks transaction value. |
| **16** | Currency is EUR. | Invalid Currency | Critical | **Defaulted to INR**. Logged anomaly, fallback to INR. |
| **17** | Split Type is "magic". | Unknown Split Type | Critical | **Defaulted to Equal**. Recalculated split equally. |

---

## 2. Database Schema Details

The system operates on a relational SQLite/MySQL database with the following table layouts:

### Core Tables
1.  **`users`**: Manages credentials and names (Aisha, Rohan, Priya, Meera, Sam, Dev).
2.  **`groups`**: The shared flatmate workspace.
3.  **`group_members`**: Tracks active periods via `joined_at` and `left_at`.
4.  **`expenses`**: Stores total transaction records, currency, and split types.
5.  **`expense_splits`**: Stores the exact amount owed by each participant.
6.  **`settlements`**: Logs direct payments between flatmates to reduce outstanding debt.
7.  **`exchange_rates`**: Caches daily exchange rates for USD/INR.

### Ingestion & Audit Tables
8.  **`import_logs`**: Logs CSV upload runs.
9.  **`anomalies`**: Connects anomalies to rows to generate the Import Report.
10. **`approval_requests`**: Stores queued modifications and deletions for dual-control validation.
11. **`audit_logs`**: Capture all security actions (creates, updates, deletes).
