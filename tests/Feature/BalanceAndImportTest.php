<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\ImportService;
use App\Services\BalanceService;
use App\Services\AnomalyDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceAndImportTest extends TestCase
{
    use RefreshDatabase;

    protected $importService;
    protected $balanceService;
    protected $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importService = new ImportService(new AnomalyDetectionService());
        $this->balanceService = new BalanceService();

        // 1. Create flatmates with their tenures
        // Aisha, Rohan, Priya joined Feb 1st
        $this->aisha = User::create(['name' => 'Aisha', 'email' => 'aisha@spreetail.com', 'password' => bcrypt('password')]);
        $this->rohan = User::create(['name' => 'Rohan', 'email' => 'rohan@spreetail.com', 'password' => bcrypt('password')]);
        $this->priya = User::create(['name' => 'Priya', 'email' => 'priya@spreetail.com', 'password' => bcrypt('password')]);
        // Meera joined Feb 1st, left March 31st
        $this->meera = User::create(['name' => 'Meera', 'email' => 'meera@spreetail.com', 'password' => bcrypt('password')]);
        // Sam joined April 15th
        $this->sam = User::create(['name' => 'Sam', 'email' => 'sam@spreetail.com', 'password' => bcrypt('password')]);

        $this->group = Group::create([
            'name' => 'Flatmates Shared Group',
            'created_by' => $this->aisha->id,
        ]);

        // Setup memberships
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->aisha->id, 'joined_at' => '2026-02-01']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->rohan->id, 'joined_at' => '2026-02-01']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->priya->id, 'joined_at' => '2026-02-01']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->meera->id, 'joined_at' => '2026-02-01', 'left_at' => '2026-03-31']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->sam->id, 'joined_at' => '2026-04-15']);
    }

    /** @test */
    public function it_imports_csv_and_correctly_calculates_balances()
    {
        // Use our generated expenses_export.csv file
        $csvPath = '/Users/shruti/spreetail/expenses_export.csv';

        $importLog = $this->importService->importCsv($csvPath, $this->group);

        $this->assertNotNull($importLog);
        $this->assertEquals('completed_with_anomalies', $importLog->status);

        // Fetch balances
        $balances = $this->balanceService->getGroupBalances($this->group);

        // Assert Meera is not included in April expenses (e.g. Row 8 Post-exit Groceries on 2026-04-05)
        // Let's verify Meera has no splits linked to expenses on April 5
        $meeraOwedAprilExpenses = \App\Models\ExpenseSplit::join('expenses', 'expense_splits.expense_id', '=', 'expenses.id')
            ->where('expense_splits.user_id', $this->meera->id)
            ->where('expenses.expense_date', '>=', '2026-04-01')
            ->exists();

        $this->assertFalse($meeraOwedAprilExpenses, "Meera should not owe money for expenses after March 31.");

        // Assert Sam is not included in March/February expenses (e.g. Row 6 USD Dinner on 2026-03-10)
        $samOwedMarchExpenses = \App\Models\ExpenseSplit::join('expenses', 'expense_splits.expense_id', '=', 'expenses.id')
            ->where('expense_splits.user_id', $this->sam->id)
            ->where('expenses.expense_date', '<', '2026-04-15')
            ->exists();

        $this->assertFalse($samOwedMarchExpenses, "Sam should not owe money for expenses before April 15.");

        // Assert Simplified settlements are produced
        $simplified = $this->balanceService->getSimplifiedSettlements($this->group);
        $this->assertNotEmpty($simplified);

        // Verify Rohan's ledger transparency
        $ledger = $this->balanceService->getUserLedger($this->group, $this->rohan);
        $this->assertNotEmpty($ledger);
    }
}
