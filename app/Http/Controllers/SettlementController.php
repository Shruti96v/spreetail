<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Settlement;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettlementController extends Controller
{
    public function create(Group $group)
    {
        $members = $group->members;
        return view('settlements.create', compact('group', 'members'));
    }

    public function store(Request $request, Group $group)
    {
        $request->validate([
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id|different:sender_id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:INR,USD',
            'settlement_date' => 'required|date',
        ]);

        $settlementDate = Carbon::parse($request->settlement_date);

        // Fetch exchange rate if USD
        $rate = 1.0;
        if ($request->currency === 'USD') {
            $rateRecord = ExchangeRate::where('base_currency', 'USD')
                ->where('target_currency', 'INR')
                ->where('rate_date', $settlementDate->toDateString())
                ->first();
            $rate = $rateRecord ? (float)$rateRecord->rate : 83.50; // Fallback
        }

        Settlement::create([
            'group_id' => $group->id,
            'sender_id' => $request->sender_id,
            'receiver_id' => $request->receiver_id,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'exchange_rate' => $rate,
            'settlement_date' => $request->settlement_date,
        ]);

        return redirect()->route('groups.show', $group)->with('success', 'Settlement recorded successfully!');
    }
}
