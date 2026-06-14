<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function index(Group $group)
    {
        $requests = ApprovalRequest::where('group_id', $group->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('approvals.index', compact('group', 'requests'));
    }

    public function approve(Group $group, ApprovalRequest $approvalRequest)
    {
        if ($approvalRequest->status !== 'pending') {
            return back()->with('error', 'This request has already been processed.');
        }

        DB::beginTransaction();

        try {
            if ($approvalRequest->type === 'edit_expense') {
                $expense = Expense::findOrFail($approvalRequest->target_id);
                $proposed = $approvalRequest->proposed_data;

                // Update expense fields
                $expense->update([
                    'description' => $proposed['description'],
                    'amount' => $proposed['amount'],
                    'currency' => $proposed['currency'],
                    'expense_date' => $proposed['expense_date'],
                    'split_type' => $proposed['split_type'],
                ]);

                // Delete old splits
                $expense->splits()->delete();

                // Re-save splits
                $this->saveSplits($expense, $proposed['splits'], $proposed['split_type'], $proposed['amount']);

            } elseif ($approvalRequest->type === 'delete_expense') {
                $expense = Expense::findOrFail($approvalRequest->target_id);
                $expense->update(['status' => 'deleted']);
            }

            $approvalRequest->update(['status' => 'approved']);

            DB::commit();
            return redirect()->route('groups.show', $group)->with('success', 'Request approved and applied successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error applying changes: ' . $e->getMessage());
        }
    }

    public function reject(Group $group, ApprovalRequest $approvalRequest)
    {
        if ($approvalRequest->status !== 'pending') {
            return back()->with('error', 'This request has already been processed.');
        }

        $approvalRequest->update(['status' => 'rejected']);

        return redirect()->route('groups.show', $group)->with('success', 'Request has been rejected.');
    }

    /**
     * Compute and save split records (Utility).
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
