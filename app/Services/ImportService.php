<?php

namespace App\Services;

use App\Models\Anomaly;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\ImportLog;
use App\Models\Settlement;
use App\Models\User;
use App\Models\ExchangeRate;
use App\Services\AnomalyDetectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportService
{
    protected $anomalyService;

    public function __construct(AnomalyDetectionService $anomalyService)
    {
        $this->anomalyService = $anomalyService;
    }

    /**
     * Import expenses from a CSV file.
     *
     * @param string $filePath Absolute path to CSV
     * @param Group $group
     * @return ImportLog
     */
    public function importCsv(string $filePath, Group $group): ImportLog
    {
        if (!file_exists($filePath)) {
            throw new \Exception("CSV file not found at path: {$filePath}");
        }

        // Initialize rates for USD/INR if empty
        $this->seedHistoricalRates();

        $importLog = ImportLog::create([
            'file_name' => basename($filePath),
            'status' => 'success',
            'rows_processed' => 0,
        ]);

        $file = fopen($filePath, 'r');
        $headers = fgetcsv($file); // Date, Description, Amount, Paid By, Currency, Split Type, Split Details

        // Standardize headers
        $headers = array_map(function($h) {
            return trim($h, "\xEF\xBB\xBF \t\n\r\0\x0B"); // Strip BOM and spaces
        }, $headers);

        $rowNumber = 1; // Row 1 is headers
        $hasCriticalAnomalies = false;
        $hasWarningAnomalies = false;

        DB::beginTransaction();

        try {
            while (($rowRaw = fgetcsv($file)) !== false) {
                $rowNumber++;
                
                // Combine headers and raw data
                if (count($headers) !== count($rowRaw)) {
                    Anomaly::create([
                        'import_log_id' => $importLog->id,
                        'row_number' => $rowNumber,
                        'raw_data' => $rowRaw,
                        'anomaly_type' => 'Malformed Row',
                        'severity' => 'critical',
                        'description' => 'Column count does not match header count.',
                        'policy_applied' => 'Skip row',
                        'status' => 'pending_review',
                    ]);
                    $hasCriticalAnomalies = true;
                    continue;
                }

                $row = array_combine($headers, $rowRaw);

                // Run anomaly detection
                $result = $this->anomalyService->analyzeRow($row, $rowNumber, $importLog->id, $group);

                if (!empty($result['anomalies'])) {
                    foreach ($result['anomalies'] as $anomaly) {
                        if ($anomaly->severity === 'critical') {
                            $hasCriticalAnomalies = true;
                        } else {
                            $hasWarningAnomalies = true;
                        }
                    }
                }

                if ($result['skip']) {
                    continue;
                }

                $corrected = $result['corrected'];
                if (!$corrected) {
                    continue;
                }

                // Retrieve exchange rate
                $rate = $this->getExchangeRate($corrected['Currency'], 'INR', $corrected['parsed_date'], $importLog->id, $rowNumber, $row);

                if ($result['is_settlement']) {
                    $this->importSettlement($corrected, $group, $rate);
                } else {
                    $this->importExpense($corrected, $group, $rate);
                }

                $importLog->increment('rows_processed');
            }

            fclose($file);

            // Update ImportLog status
            if ($hasCriticalAnomalies) {
                $importLog->update(['status' => 'completed_with_anomalies']);
            } elseif ($hasWarningAnomalies) {
                $importLog->update(['status' => 'completed_with_anomalies']);
            } else {
                $importLog->update(['status' => 'success']);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            $importLog->update(['status' => 'failed']);
            Log::error("CSV import failed: " . $e->getMessage());
            throw $e;
        }

        return $importLog;
    }

    /**
     * Import a settlement transaction.
     */
    private function importSettlement(array $data, Group $group, float $rate)
    {
        // For settlements, Split Details contains the receiver name
        $receiverName = trim($data['Split Details'] ?? '');
        
        // If split details is empty, try to parse from description (e.g. "Priya to Rohan")
        if (empty($receiverName)) {
            $desc = $data['Description'];
            if (preg_match('/to\s+(\w+)/i', $desc, $matches)) {
                $receiverName = trim($matches[1]);
            }
        }

        $receiver = User::where('name', 'like', $receiverName)->first();
        if (!$receiver) {
            $receiver = User::create([
                'name' => $receiverName,
                'email' => strtolower($receiverName) . '@spreetail.com',
                'password' => bcrypt('password123'),
            ]);
            $group->members()->attach($receiver->id, ['joined_at' => $data['parsed_date']]);
        }

        Settlement::create([
            'group_id' => $group->id,
            'sender_id' => $data['payer_id'],
            'receiver_id' => $receiver->id,
            'amount' => $data['Amount'],
            'currency' => $data['Currency'],
            'exchange_rate' => $rate,
            'settlement_date' => $data['parsed_date'],
        ]);
    }

    /**
     * Import an expense and split it.
     */
    private function importExpense(array $data, Group $group, float $rate)
    {
        $expense = Expense::create([
            'group_id' => $group->id,
            'paid_by' => $data['payer_id'],
            'description' => $data['Description'],
            'amount' => $data['Amount'],
            'currency' => $data['Currency'],
            'exchange_rate' => $rate,
            'expense_date' => $data['parsed_date'],
            'split_type' => $data['Split Type'],
            'is_settlement' => false,
            'status' => 'active',
        ]);

        $participants = $data['participants'] ?? [];
        $splitType = $data['Split Type'];
        $totalAmount = $data['Amount'];

        $splits = [];
        $allocatedSum = 0;

        if ($splitType === 'equal') {
            $count = count($participants);
            $share = round($totalAmount / $count, 4);
            
            foreach ($participants as $p) {
                $splits[$p['user']->id] = [
                    'amount' => $share,
                    'percentage' => round(100 / $count, 2),
                ];
                $allocatedSum += $share;
            }

            // Adjust rounding discrepancy to the first participant
            $diff = $totalAmount - $allocatedSum;
            if (abs($diff) > 0.0001 && count($participants) > 0) {
                $firstId = $participants[0]['user']->id;
                $splits[$firstId]['amount'] += $diff;
            }
        } elseif ($splitType === 'percentage') {
            foreach ($participants as $p) {
                $share = round(($totalAmount * $p['value']) / 100, 4);
                $splits[$p['user']->id] = [
                    'amount' => $share,
                    'percentage' => $p['value'],
                ];
                $allocatedSum += $share;
            }

            // Adjust rounding discrepancy
            $diff = $totalAmount - $allocatedSum;
            if (abs($diff) > 0.0001 && count($participants) > 0) {
                $firstId = $participants[0]['user']->id;
                $splits[$firstId]['amount'] += $diff;
            }
        } elseif ($splitType === 'exact') {
            foreach ($participants as $p) {
                $splits[$p['user']->id] = [
                    'amount' => $p['value'],
                    'percentage' => round(($p['value'] / $totalAmount) * 100, 2),
                ];
            }
        }

        foreach ($splits as $userId => $splitData) {
            ExpenseSplit::create([
                'expense_id' => $expense->id,
                'user_id' => $userId,
                'amount_owed' => $splitData['amount'],
                'percentage' => $splitData['percentage'],
            ]);
        }
    }

    /**
     * Retrieve historical exchange rate.
     */
    private function getExchangeRate(string $base, string $target, string $date, int $importLogId, int $rowNumber, array $row): float
    {
        if ($base === $target) {
            return 1.0;
        }

        $rateRecord = ExchangeRate::firstOrCreate(
            [
                'base_currency' => $base,
                'target_currency' => $target,
                'rate_date' => $date
            ],
            [
                'rate' => 83.50 // Fallback
            ]
        );

        if ($rateRecord->wasRecentlyCreated) {
            Anomaly::create([
                'import_log_id' => $importLogId,
                'row_number' => $rowNumber,
                'raw_data' => $row,
                'anomaly_type' => 'Missing Exchange Rate',
                'severity' => 'warning',
                'description' => "Exchange rate for {$base} to {$target} on {$date} not found. Used fallback rate of {$rateRecord->rate}.",
                'policy_applied' => 'Fallback exchange rate',
                'status' => 'pending_review',
            ]);
        }

        return (float)$rateRecord->rate;

    }

    /**
     * Seed initial historical exchange rates.
     */
    private function seedHistoricalRates()
    {
        // Seeding typical rates for Feb, March, April, May 2026
        $rates = [
            '2026-02-10' => 83.10,
            '2026-02-15' => 83.15,
            '2026-02-28' => 83.25,
            '2026-03-05' => 83.30,
            '2026-03-10' => 83.40,
            '2026-03-15' => 83.35,
            '2026-03-20' => 83.45,
            '2026-04-05' => 83.50,
            '2026-04-08' => 83.55,
            '2026-04-10' => 83.60,
            '2026-04-18' => 83.62,
            '2026-04-19' => 83.65,
            '2026-04-20' => 83.70,
            '2026-04-22' => 83.68,
            '2026-04-25' => 83.72,
        ];

        foreach ($rates as $date => $rate) {
            ExchangeRate::firstOrCreate(
                ['base_currency' => 'USD', 'target_currency' => 'INR', 'rate_date' => $date],
                ['rate' => $rate]
            );
        }
    }
}
