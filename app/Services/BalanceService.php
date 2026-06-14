<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    /**
     * Get net balances for all members of a group in base currency (INR).
     *
     * @param Group $group
     * @return array Array of user details with their net balances
     */
    public function getGroupBalances(Group $group): array
    {
        $members = $group->members;
        $balances = [];

        foreach ($members as $member) {
            $paid = $this->getAmountPaid($group->id, $member->id);
            $owed = $this->getAmountOwed($group->id, $member->id);
            $sent = $this->getSettlementsSent($group->id, $member->id);
            $received = $this->getSettlementsReceived($group->id, $member->id);

            // Net balance = (Paid - Owed) + (Received - Sent)
            $net = ($paid - $owed) + ($received - $sent);

            $balances[$member->id] = [
                'user' => $member,
                'paid' => round($paid, 2),
                'owed' => round($owed, 2),
                'sent' => round($sent, 2),
                'received' => round($received, 2),
                'net' => round($net, 2),
            ];
        }

        return $balances;
    }

    /**
     * Get a detailed breakdown of what a user paid and owes for transparency.
     */
    public function getUserLedger(Group $group, User $user): array
    {
        $ledger = [];

        // 1. Fetch all active expenses in group
        $expenses = Expense::where('group_id', $group->id)
            ->where('status', 'active')
            ->orderBy('expense_date', 'asc')
            ->get();

        foreach ($expenses as $expense) {
            $split = ExpenseSplit::where('expense_id', $expense->id)
                ->where('user_id', $user->id)
                ->first();

            if ($expense->paid_by === $user->id || $split) {
                $convertedAmount = $expense->amount * $expense->exchange_rate;
                $owedAmount = $split ? ($split->amount_owed * $expense->exchange_rate) : 0;
                
                $ledger[] = [
                    'type' => 'expense',
                    'date' => $expense->expense_date->toDateString(),
                    'description' => $expense->description,
                    'paid_by' => $expense->payer->name,
                    'original_amount' => $expense->amount,
                    'currency' => $expense->currency,
                    'converted_amount_inr' => round($convertedAmount, 2),
                    'user_share_inr' => round($owedAmount, 2),
                    'user_paid_inr' => $expense->paid_by === $user->id ? round($convertedAmount, 2) : 0,
                    'net_impact_inr' => $expense->paid_by === $user->id ? round($convertedAmount - $owedAmount, 2) : round(-$owedAmount, 2),
                ];
            }
        }

        // 2. Fetch all settlements in group involving this user
        $settlements = Settlement::where('group_id', $group->id)
            ->where(function($q) use ($user) {
                $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
            })
            ->orderBy('settlement_date', 'asc')
            ->get();

        foreach ($settlements as $settlement) {
            $convertedAmount = $settlement->amount * $settlement->exchange_rate;
            $isSender = $settlement->sender_id === $user->id;

            $ledger[] = [
                'type' => 'settlement',
                'date' => $settlement->settlement_date->toDateString(),
                'description' => $isSender 
                    ? "Settlement payment to {$settlement->receiver->name}" 
                    : "Settlement payment received from {$settlement->sender->name}",
                'paid_by' => $settlement->sender->name,
                'original_amount' => $settlement->amount,
                'currency' => $settlement->currency,
                'converted_amount_inr' => round($convertedAmount, 2),
                'user_share_inr' => 0,
                'user_paid_inr' => $isSender ? round($convertedAmount, 2) : 0,
                'net_impact_inr' => $isSender ? round($convertedAmount, 2) : round(-$convertedAmount, 2), // Sent increases net, received decreases outstanding debt
            ];
        }

        // Sort ledger by date
        usort($ledger, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $ledger;
    }

    /**
     * Simplify debts within a group (Greedy Max-Flow Minimization).
     */
    public function getSimplifiedSettlements(Group $group): array
    {
        $balances = $this->getGroupBalances($group);
        
        // Prepare list of net values
        $netBalances = [];
        foreach ($balances as $userId => $data) {
            $netBalances[$userId] = $data['net'];
        }

        // Separate debtors and creditors
        $debtors = [];
        $creditors = [];

        foreach ($netBalances as $userId => $balance) {
            if ($balance < -0.01) {
                $debtors[$userId] = $balance;
            } elseif ($balance > 0.01) {
                $creditors[$userId] = $balance;
            }
        }

        $settlements = [];

        while (count($debtors) > 0 && count($creditors) > 0) {
            // Sort to grab largest debtor and creditor
            asort($debtors); // Most negative first (e.g. -5000 is less than -100)
            arsort($creditors); // Most positive first (e.g. 5000 is greater than 100)

            $debtorId = key($debtors);
            $creditorId = key($creditors);

            $debtVal = abs($debtors[$debtorId]);
            $credVal = $creditors[$creditorId];

            $settleAmount = min($debtVal, $credVal);

            $debtors[$debtorId] += $settleAmount;
            $creditors[$creditorId] -= $settleAmount;

            $settlements[] = [
                'sender' => User::find($debtorId),
                'receiver' => User::find($creditorId),
                'amount' => round($settleAmount, 2),
            ];

            // Remove zero balances
            if (abs($debtors[$debtorId]) < 0.01) {
                unset($debtors[$debtorId]);
            }
            if (abs($creditors[$creditorId]) < 0.01) {
                unset($creditors[$creditorId]);
            }
        }

        return $settlements;
    }

    /**
     * Helpers to calculate aggregate figures.
     */
    private function getAmountPaid(int $groupId, int $userId): float
    {
        return (float) Expense::where('group_id', $groupId)
            ->where('paid_by', $userId)
            ->where('status', 'active')
            ->select(DB::raw('SUM(amount * exchange_rate) as total'))
            ->value('total') ?? 0.0;
    }

    private function getAmountOwed(int $groupId, int $userId): float
    {
        return (float) ExpenseSplit::join('expenses', 'expense_splits.expense_id', '=', 'expenses.id')
            ->where('expenses.group_id', $groupId)
            ->where('expense_splits.user_id', $userId)
            ->where('expenses.status', 'active')
            ->select(DB::raw('SUM(expense_splits.amount_owed * expenses.exchange_rate) as total'))
            ->value('total') ?? 0.0;
    }

    private function getSettlementsSent(int $groupId, int $userId): float
    {
        return (float) Settlement::where('group_id', $groupId)
            ->where('sender_id', $userId)
            ->select(DB::raw('SUM(amount * exchange_rate) as total'))
            ->value('total') ?? 0.0;
    }

    private function getSettlementsReceived(int $groupId, int $userId): float
    {
        return (float) Settlement::where('group_id', $groupId)
            ->where('receiver_id', $userId)
            ->select(DB::raw('SUM(amount * exchange_rate) as total'))
            ->value('total') ?? 0.0;
    }
}
