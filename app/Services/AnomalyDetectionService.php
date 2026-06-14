<?php

namespace App\Services;

use App\Models\Anomaly;
use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use App\Models\GroupMember;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnomalyDetectionService
{
    /**
     * Detect anomalies in a single CSV row.
     *
     * @param array $row
     * @param int $rowNumber
     * @param int $importLogId
     * @param Group $group
     * @return array Array of detected anomalies and the corrected data
     */
    public function analyzeRow(array $row, int $rowNumber, int $importLogId, Group $group): array
    {
        $anomalies = [];
        $correctedData = $row;
        $shouldSkip = false;
        $isSettlement = false;

        // Extract raw fields
        $rawDate = trim($row['Date'] ?? '');
        $rawDescription = trim($row['Description'] ?? '');
        $rawAmount = trim($row['Amount'] ?? '');
        $rawPaidBy = trim($row['Paid By'] ?? '');
        $rawCurrency = trim($row['Currency'] ?? '');
        $rawSplitType = trim($row['Split Type'] ?? '');
        $rawSplitDetails = trim($row['Split Details'] ?? '');

        // 1. Check Empty Required Fields
        if (empty($rawDate) || empty($rawDescription) || empty($rawPaidBy) || empty($rawCurrency)) {
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Empty Required Fields', 'critical', 'One or more required fields (Date, Description, Paid By, Currency) are empty.', 'Skip row');
            return ['anomalies' => $anomalies, 'corrected' => null, 'skip' => true, 'is_settlement' => false];
        }

        if (empty($rawAmount) && strtolower($rawSplitType) !== 'settlement') {
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Empty Required Fields', 'critical', 'Amount field is empty for a non-settlement expense.', 'Skip row');
            return ['anomalies' => $anomalies, 'corrected' => null, 'skip' => true, 'is_settlement' => false];
        }

        // 2. Validate Amount
        $amountVal = floatval($rawAmount);
        if (!is_numeric($rawAmount) && !empty($rawAmount)) {
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Invalid Amount', 'critical', "Amount '{$rawAmount}' is not a valid number.", 'Skip row');
            return ['anomalies' => $anomalies, 'corrected' => null, 'skip' => true, 'is_settlement' => false];
        }

        // 3. Negative Amount
        if ($amountVal < 0) {
            $correctedData['Amount'] = abs($amountVal);
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Negative Amount', 'warning', "Amount '{$rawAmount}' is negative. Absolute value used.", 'Convert to positive');
            $amountVal = abs($amountVal);
        }

        // 4. Validate Date format & Future Dates
        try {
            $expenseDate = Carbon::parse($rawDate);
            if ($expenseDate->isFuture()) {
                $correctedData['Date'] = Carbon::now()->toDateString();
                $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Future Date', 'warning', "Date '{$rawDate}' is in the future. Reset to current date.", 'Use current date');
                $expenseDate = Carbon::now();
            }
        } catch (\Exception $e) {
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Invalid Date Format', 'critical', "Date '{$rawDate}' could not be parsed.", 'Skip row');
            return ['anomalies' => $anomalies, 'corrected' => null, 'skip' => true, 'is_settlement' => false];
        }

        // 5. Invalid Currency
        $currencyCode = strtoupper($rawCurrency);
        if ($currencyCode !== 'INR' && $currencyCode !== 'USD') {
            $correctedData['Currency'] = 'INR';
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Invalid Currency', 'critical', "Currency '{$rawCurrency}' is unsupported. Defaulted to INR.", 'Default to INR');
            $currencyCode = 'INR';
        }

        // 6. Settlement Detection
        $splitTypeClean = strtolower($rawSplitType);
        if ($splitTypeClean === 'settlement' || stripos($rawDescription, 'settle') !== false || stripos($rawDescription, 'to') !== false) {
            $isSettlement = true;
            $correctedData['Split Type'] = 'settlement';
            if ($splitTypeClean !== 'settlement') {
                $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Settlement Recorded As Expense', 'info', "Transaction description '{$rawDescription}' suggests a settlement.", 'Route to settlements table');
            }
        }

        // 7. Unknown Split Type
        if (!$isSettlement && !in_array($splitTypeClean, ['equal', 'exact', 'percentage'])) {
            $correctedData['Split Type'] = 'equal';
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Unknown Split Type', 'critical', "Split Type '{$rawSplitType}' is unknown. Defaulted to equal split.", 'Default to equal split');
            $splitTypeClean = 'equal';
        }

        // 8. Find or Auto-Create Paid By User
        $paidByEmail = strtolower($rawPaidBy) . '@spreetail.com';
        $payer = User::where('name', 'like', $rawPaidBy)->first();
        if (!$payer) {
            $payer = User::create([
                'name' => $rawPaidBy,
                'email' => $paidByEmail,
                'password' => bcrypt('password123'),
            ]);
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Missing Members', 'warning', "Payer '{$rawPaidBy}' not registered. Auto-created user.", 'Create member');
        }

        // Ensure payer is member of group
        $isPayerMember = $group->members()->where('users.id', $payer->id)->exists();
        if (!$isPayerMember) {
            $group->members()->attach($payer->id, [
                'joined_at' => $expenseDate->toDateString(),
            ]);
            $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Member Not In Group', 'warning', "Payer '{$rawPaidBy}' was not in group. Added to group.", 'Add member to group');
        } else {
            // Check tenure of payer
            $membership = GroupMember::where('group_id', $group->id)->where('user_id', $payer->id)->first();
            if ($membership) {
                if ($expenseDate->lt($membership->joined_at)) {
                    $membership->update(['joined_at' => $expenseDate->toDateString()]);
                    $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Date Outside Membership', 'warning', "Payer '{$rawPaidBy}' expense date is before join date. Shifted join date back.", 'Adjust join date');
                }
                if ($membership->left_at && $expenseDate->gt($membership->left_at)) {
                    $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Date Outside Membership', 'critical', "Payer '{$rawPaidBy}' expense date is after they left.", 'Flag for manual review');
                }
            }
        }

        // 9. Process Split Details & Membership / Out of tenure
        if (!$isSettlement) {
            $participants = [];
            $splitParts = empty($rawSplitDetails) ? [] : explode(';', $rawSplitDetails);
            
            // If split details is empty, default to active group members on that date
            if (empty($splitParts)) {
                $activeMemberNames = GroupMember::where('group_id', $group->id)
                    ->where('joined_at', '<=', $expenseDate->toDateString())
                    ->where(function($q) use ($expenseDate) {
                        $q->whereNull('left_at')->orWhere('left_at', '>=', $expenseDate->toDateString());
                    })
                    ->with('user')
                    ->get()
                    ->pluck('user.name')
                    ->toArray();

                $rawSplitDetails = implode(';', $activeMemberNames);
                $splitParts = $activeMemberNames;
                $correctedData['Split Details'] = $rawSplitDetails;
                $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Empty Required Fields', 'warning', 'Split Details field was empty. Defaulted to all active members.', 'Split among active members');
            }

            foreach ($splitParts as $part) {
                $part = trim($part);
                if (empty($part)) continue;

                $name = $part;
                $val = null;

                if (strpos($part, ':') !== false) {
                    $details = explode(':', $part);
                    $name = trim($details[0]);
                    $val = floatval(trim($details[1]));
                }

                $memberUser = User::where('name', 'like', $name)->first();
                if (!$memberUser) {
                    $memberUser = User::create([
                        'name' => $name,
                        'email' => strtolower($name) . '@spreetail.com',
                        'password' => bcrypt('password123'),
                    ]);
                    $group->members()->attach($memberUser->id, [
                        'joined_at' => $expenseDate->toDateString(),
                    ]);
                    $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Missing Members', 'warning', "Split member '{$name}' not registered. Auto-created and added to group.", 'Create and add member');
                }

                // Check group membership
                $isMember = $group->members()->where('users.id', $memberUser->id)->exists();
                if (!$isMember) {
                    $group->members()->attach($memberUser->id, [
                        'joined_at' => $expenseDate->toDateString(),
                    ]);
                    $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Member Not In Group', 'warning', "Split member '{$name}' was not in group. Added to group.", 'Add member');
                }

                // Check tenure constraints
                $memberMembership = GroupMember::where('group_id', $group->id)->where('user_id', $memberUser->id)->first();
                if ($memberMembership) {
                    $joinDate = \Carbon\Carbon::parse($memberMembership->joined_at);
                    $leftDate = $memberMembership->left_at ? \Carbon\Carbon::parse($memberMembership->left_at) : null;
                    
                    // Pre-join date anomaly
                    if ($expenseDate->lt($joinDate)) {
                        $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Date Outside Membership', 'critical', "Split member '{$name}' joined after this expense date.", 'Exclude from split');
                        continue; // Skip this user in the split
                    }
                    // Post-leave date anomaly
                    if ($leftDate && $expenseDate->gt($leftDate)) {
                        $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Date Outside Membership', 'critical', "Split member '{$name}' left the group before this expense date.", 'Exclude from split');
                        continue; // Skip this user in the split
                    }
                }

                $participants[] = [
                    'user' => $memberUser,
                    'value' => $val,
                    'name' => $name,
                ];
            }

            // Reconstruct splits if any members were excluded
            if (count($participants) === 0) {
                $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Invalid Split Details', 'critical', 'No valid active members left to split the expense.', 'Skip row');
                return ['anomalies' => $anomalies, 'corrected' => null, 'skip' => true, 'is_settlement' => false];
            }

            // Verify total exact split amounts or percentage total matches 100%
            if ($splitTypeClean === 'percentage') {
                $totalPct = array_sum(array_column($participants, 'value'));
                if (abs($totalPct - 100.0) > 0.01) {
                    $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Invalid Amount', 'critical', "Percentage split total {$totalPct}% does not equal 100%. Equal splits applied instead.", 'Fallback to equal split');
                    $correctedData['Split Type'] = 'equal';
                    foreach ($participants as &$p) {
                        $p['value'] = null;
                    }
                }
            } elseif ($splitTypeClean === 'exact') {
                $totalExact = array_sum(array_column($participants, 'value'));
                if (abs($totalExact - $amountVal) > 0.01) {
                    $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Invalid Amount', 'critical', "Exact split sum {$totalExact} does not match total amount {$amountVal}. Equal splits applied instead.", 'Fallback to equal split');
                    $correctedData['Split Type'] = 'equal';
                    foreach ($participants as &$p) {
                        $p['value'] = null;
                    }
                }
            }

            $correctedData['participants'] = $participants;
        }

        $duplicate = Expense::where('group_id', $group->id)
            ->whereDate('expense_date', $expenseDate->toDateString())
            ->where('description', $rawDescription)
            ->where('paid_by', $payer->id)
            ->where('is_settlement', $isSettlement)
            ->first();

        if ($duplicate) {
            if (abs($duplicate->amount - $amountVal) < 0.01 && $duplicate->currency === $currencyCode) {
                $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Duplicate Expense', 'warning', 'Duplicate row detected with identical details. Skipped row.', 'Skip row');
                return ['anomalies' => $anomalies, 'corrected' => null, 'skip' => true, 'is_settlement' => $isSettlement];
            } else {
                // 11. Conflicting Duplicate Entries
                $anomalies[] = $this->logAnomaly($importLogId, $rowNumber, $row, 'Conflicting Duplicate Entries', 'critical', "Same expense name and date but different amount/currency (DB: {$duplicate->amount} {$duplicate->currency}, CSV: {$rawAmount} {$rawCurrency}).", 'Import anyway, flag for review');
            }
        }

        $correctedData['payer_id'] = $payer->id;
        $correctedData['parsed_date'] = $expenseDate->toDateString();

        return [
            'anomalies' => $anomalies,
            'corrected' => $correctedData,
            'skip' => false,
            'is_settlement' => $isSettlement,
        ];
    }

    /**
     * Create and store anomaly in database.
     */
    private function logAnomaly(int $importLogId, int $rowNumber, array $rawData, string $type, string $severity, string $description, string $policy): Anomaly
    {
        return Anomaly::create([
            'import_log_id' => $importLogId,
            'row_number' => $rowNumber,
            'raw_data' => $rawData,
            'anomaly_type' => $type,
            'severity' => $severity,
            'description' => $description,
            'policy_applied' => $policy,
            'status' => 'pending_review',
        ]);
    }
}
