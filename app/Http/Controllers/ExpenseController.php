<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function create(Group $group)
    {
        $members = $group->members;
        return view('expenses.create', compact('group', 'members'));
    }

    public function store(Request $request, Group $group)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:INR,USD',
            'expense_date' => 'required|date',
            'split_type' => 'required|string|in:equal,percentage,exact',
            'splits' => 'required|array', // user_id => split_value
        ]);

        $expenseDate = Carbon::parse($request->expense_date);

        // Fetch exchange rate if USD
        $rate = 1.0;
        if ($request->currency === 'USD') {
            $rateRecord = ExchangeRate::where('base_currency', 'USD')
                ->where('target_currency', 'INR')
                ->where('rate_date', $expenseDate->toDateString())
                ->first();
            $rate = $rateRecord ? (float)$rateRecord->rate : 83.50; // Fallback
        }

        $expense = Expense::create([
            'group_id' => $group->id,
            'paid_by' => Auth::id(),
            'description' => $request->description,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'exchange_rate' => $rate,
            'expense_date' => $request->expense_date,
            'split_type' => $request->split_type,
            'is_settlement' => false,
            'status' => 'active',
        ]);

        $this->saveSplits($expense, $request->splits, $request->split_type, $request->amount);

        return redirect()->route('groups.show', $group)->with('success', 'Expense logged successfully!');
    }

    public function edit(Group $group, Expense $expense)
    {
        $members = $group->members;
        $activeSplits = $expense->splits->pluck('amount_owed', 'user_id')->toArray();
        $percentages = $expense->splits->pluck('percentage', 'user_id')->toArray();
        return view('expenses.edit', compact('group', 'expense', 'members', 'activeSplits', 'percentages'));
    }

    public function update(Request $request, Group $group, Expense $expense)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:INR,USD',
            'expense_date' => 'required|date',
            'split_type' => 'required|string|in:equal,percentage,exact',
            'splits' => 'required|array',
        ]);

        // Instead of updating immediately, create an ApprovalRequest
        $proposedData = [
            'description' => $request->description,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'expense_date' => $request->expense_date,
            'split_type' => $request->split_type,
            'splits' => $request->splits,
        ];

        ApprovalRequest::create([
            'group_id' => $group->id,
            'requester_id' => Auth::id(),
            'type' => 'edit_expense',
            'target_type' => Expense::class,
            'target_id' => $expense->id,
            'proposed_data' => $proposedData,
            'status' => 'pending',
        ]);

        return redirect()->route('groups.show', $group)->with('success', 'Change request submitted for group approval.');
    }

    public function destroy(Group $group, Expense $expense)
    {
        ApprovalRequest::create([
            'group_id' => $group->id,
            'requester_id' => Auth::id(),
            'type' => 'delete_expense',
            'target_type' => Expense::class,
            'target_id' => $expense->id,
            'proposed_data' => null,
            'status' => 'pending',
        ]);

        return redirect()->route('groups.show', $group)->with('success', 'Delete request submitted for group approval.');
    }

    /**
     * Compute and save split records.
     */
    private function saveSplits(Expense $expense, array $splitInputs, string $splitType, float $totalAmount)
    {
        $splits = [];
        $allocatedSum = 0;
        $userIds = array_keys($splitInputs);

        if ($splitType === 'equal') {
            $count = count($userIds);
            $share = round($totalAmount / $count, 4);
            foreach ($userIds as $id) {
                $splits[$id] = [
                    'amount' => $share,
                    'percentage' => round(100 / $count, 2),
                ];
                $allocatedSum += $share;
            }
            $diff = $totalAmount - $allocatedSum;
            if (abs($diff) > 0.0001 && $count > 0) {
                $splits[$userIds[0]]['amount'] += $diff;
            }
        } elseif ($splitType === 'percentage') {
            foreach ($splitInputs as $id => $val) {
                $share = round(($totalAmount * $val) / 100, 4);
                $splits[$id] = [
                    'amount' => $share,
                    'percentage' => $val,
                ];
                $allocatedSum += $share;
            }
            $diff = $totalAmount - $allocatedSum;
            if (abs($diff) > 0.0001 && count($userIds) > 0) {
                $splits[$userIds[0]]['amount'] += $diff;
            }
        } elseif ($splitType === 'exact') {
            foreach ($splitInputs as $id => $val) {
                $splits[$id] = [
                    'amount' => $val,
                    'percentage' => round(($val / $totalAmount) * 100, 2),
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
}
