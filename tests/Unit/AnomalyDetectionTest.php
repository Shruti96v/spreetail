<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\ImportLog;
use App\Models\Expense;
use App\Models\User;
use App\Models\GroupMember;
use App\Services\AnomalyDetectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnomalyDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected $anomalyService;
    protected $group;
    protected $importLog;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->anomalyService = new AnomalyDetectionService();
        
        // Setup initial user and group
        $creator = User::create([
            'name' => 'Aisha',
            'email' => 'aisha@spreetail.com',
            'password' => bcrypt('password123'),
        ]);

        $this->group = Group::create([
            'name' => 'Flat 202',
            'created_by' => $creator->id,
        ]);

        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $creator->id,
            'joined_at' => '2026-02-01',
        ]);

        $this->importLog = ImportLog::create([
            'file_name' => 'test_export.csv',
            'status' => 'success',
        ]);
    }

    /** @test */
    public function it_detects_negative_amounts_and_applies_absolute_value()
    {
        $row = [
            'Date' => '2026-03-01',
            'Description' => 'Groceries refund',
            'Amount' => '-150.00',
            'Paid By' => 'Aisha',
            'Currency' => 'INR',
            'Split Type' => 'equal',
            'Split Details' => 'Aisha',
        ];

        $result = $this->anomalyService->analyzeRow($row, 2, $this->importLog->id, $this->group);

        $this->assertFalse($result['skip']);
        $this->assertEquals(150.00, $result['corrected']['Amount']);
        $this->assertCount(1, $result['anomalies']);
        $this->assertEquals('Negative Amount', $result['anomalies'][0]->anomaly_type);
    }

    /** @test */
    public function it_detects_future_dates_and_resets_to_current_date()
    {
        $futureDate = Carbon::now()->addMonth()->toDateString();
        $row = [
            'Date' => $futureDate,
            'Description' => 'Future concert tickets',
            'Amount' => '500.00',
            'Paid By' => 'Aisha',
            'Currency' => 'INR',
            'Split Type' => 'equal',
            'Split Details' => 'Aisha',
        ];

        $result = $this->anomalyService->analyzeRow($row, 3, $this->importLog->id, $this->group);

        $this->assertFalse($result['skip']);
        $this->assertEquals(Carbon::now()->toDateString(), $result['corrected']['Date']);
        $this->assertCount(1, $result['anomalies']);
        $this->assertEquals('Future Date', $result['anomalies'][0]->anomaly_type);
    }

    /** @test */
    public function it_detects_invalid_currencies_and_defaults_to_inr()
    {
        $row = [
            'Date' => '2026-03-01',
            'Description' => 'Coffee',
            'Amount' => '10.00',
            'Paid By' => 'Aisha',
            'Currency' => 'EUR',
            'Split Type' => 'equal',
            'Split Details' => 'Aisha',
        ];

        $result = $this->anomalyService->analyzeRow($row, 4, $this->importLog->id, $this->group);

        $this->assertFalse($result['skip']);
        $this->assertEquals('INR', $result['corrected']['Currency']);
        $this->assertCount(1, $result['anomalies']);
        $this->assertEquals('Invalid Currency', $result['anomalies'][0]->anomaly_type);
    }

    /** @test */
    public function it_handles_settlements_separately_from_expenses()
    {
        $row = [
            'Date' => '2026-02-28',
            'Description' => 'Aisha to Rohan payment',
            'Amount' => '2000.00',
            'Paid By' => 'Aisha',
            'Currency' => 'INR',
            'Split Type' => 'settlement',
            'Split Details' => 'Rohan',
        ];

        $result = $this->anomalyService->analyzeRow($row, 5, $this->importLog->id, $this->group);

        $this->assertFalse($result['skip']);
        $this->assertTrue($result['is_settlement']);
        $this->assertEquals('settlement', $result['corrected']['Split Type']);
    }

    /** @test */
    public function it_skips_exact_duplicates()
    {
        // First, create the expense in the database to simulate duplication
        $payer = User::where('name', 'Aisha')->first();
        Expense::create([
            'group_id' => $this->group->id,
            'paid_by' => $payer->id,
            'description' => 'Duplicate expense check',
            'amount' => 100.00,
            'currency' => 'INR',
            'exchange_rate' => 1.0,
            'expense_date' => '2026-02-10',
            'split_type' => 'equal',
            'is_settlement' => false,
            'status' => 'active',
        ]);

        $row = [
            'Date' => '2026-02-10',
            'Description' => 'Duplicate expense check',
            'Amount' => '100.00',
            'Paid By' => 'Aisha',
            'Currency' => 'INR',
            'Split Type' => 'equal',
            'Split Details' => 'Aisha',
        ];

        $result = $this->anomalyService->analyzeRow($row, 6, $this->importLog->id, $this->group);

        $this->assertTrue($result['skip']);
        $this->assertCount(1, $result['anomalies']);
        $this->assertEquals('Duplicate Expense', $result['anomalies'][0]->anomaly_type);
    }
}
