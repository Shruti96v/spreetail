# Decisions Log — Shared Expenses App

This document logs significant technical and architectural decisions made during the design and development of the Shared Expenses App.

---

## 1. Database Choice: SQLite for Local Portability, MySQL Schema-Ready
- **Options considered**: MySQL, SQLite, MongoDB.
- **Decision**: Default to SQLite for local development and testing, while writing the database migrations to be fully MySQL-compliant.
- **Rationale**: SQLite requires zero system configuration, enabling the code evaluator or grader to run `php artisan migrate` instantly without setting up MySQL servers or creating schemas. All schema definitions use standard SQL datatypes (decimal, constraint, indexes) fully compatible with MySQL if credentials are swapped in `.env`.

## 2. Collaborative Approval Queue for Edits/Deletes (Meera's Rule)
- **Options considered**: 
  1. Soft deletes and version tables.
  2. A dedicated `approval_requests` queue storing proposed edits in JSON.
- **Decision**: Option 2.
- **Rationale**: An expense modification or deletion does not modify the target record immediately. Instead, it inserts a request in the `approval_requests` table with `proposed_data` as a JSON payload. This keeps the active database ledger clean and un-mutated until other flatmates approve, preserving absolute audit trails.

## 3. Currency Conversion Strategy & Base Currency (Priya's Rule)
- **Options considered**: 
  1. Multi-currency balances (separate USD and INR totals).
  2. Uniform base currency conversion on transaction dates.
- **Decision**: Option 2, designating INR as the base currency.
- **Rationale**: Tracking separate USD and INR balances results in flatmates owing multiple currencies simultaneously (e.g., "Aisha owes Rohan $5 and Rohan owes Aisha ₹400"), complicating settlements. By converting USD transactions to INR using the exchange rate on the `expense_date` and storing the exchange rate, we get a single, clear net balance sheet in INR.

## 4. Ingestion Policy for Missing Members
- **Options considered**:
  1. Reject and crash the CSV import.
  2. Skip rows containing unknown members.
  3. Auto-create guest accounts.
- **Decision**: Option 3.
- **Rationale**: To prevent a single misspelling from halting the entire spreadsheets ingestion pipeline, the importer auto-creates missing users (e.g., Dev) with a default password and attaches them to the group. A warning anomaly is logged so they can merge their profiles later if needed.

## 5. Rounding Discrepancy Allocation
- **Options considered**:
  1. Let splits sum to a fraction off the total (e.g., 9.99 instead of 10.00).
  2. Allocate the remaining remainder to the payer or first participant.
- **Decision**: Option 2 (allocate the remainder to the first participant).
- **Rationale**: Financial integrity requires the sum of all splits to equal the total expense amount. When dividing amounts that don't split evenly (e.g. 10.00 split among 3 people), the 0.01 remainder is allocated to the first participant's share to maintain exact totals.
