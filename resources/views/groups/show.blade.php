@extends('layouts.app')

@section('title', $group->name)

@section('content')
<div style="display: flex; flex-direction: column; gap: 2rem;">
    <!-- Group Header -->
    <div class="panel" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(6, 182, 212, 0.05) 100%); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; letter-spacing: -0.025em;">
                {{ $group->name }}
            </h1>
            <p style="color: var(--text-secondary); font-size: 1.05rem;">
                {{ $group->description ?: 'No description provided.' }}
            </p>
        </div>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="{{ route('expenses.create', $group) }}" class="btn btn-primary">
                + Log Expense
            </a>
            <a href="{{ route('settlements.create', $group) }}" class="btn btn-secondary">
                Record Payment
            </a>
            <a href="{{ route('groups.imports', $group) }}" class="btn btn-secondary">
                Import CSV
            </a>
            <a href="{{ route('groups.approvals', $group) }}" class="btn btn-secondary" style="position: relative;">
                Approvals Queue
                @if($pendingApprovals->count() > 0)
                    <span style="position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        {{ $pendingApprovals->count() }}
                    </span>
                @endif
            </a>
        </div>
    </div>

    <!-- main workspace layout -->
    <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 2rem;">
        
        <!-- Left Column: Balances, Settlements, and Members (5 cols) -->
        <div style="grid-column: span 5; display: flex; flex-direction: column; gap: 2rem;">
            
            <!-- Net Balances Panel -->
            <div class="panel">
                <h3 class="panel-title">Net Balances</h3>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    @foreach($balances as $userId => $data)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px dashed var(--border-color);">
                            <div>
                                <span style="font-weight: 600;">{{ $data['user']->name }}</span>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    Paid: ₹{{ number_format($data['paid'], 2) }} | Owed: ₹{{ number_format($data['owed'], 2) }}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="balance-item-value {{ $data['net'] > 0.01 ? 'balance-positive' : ($data['net'] < -0.01 ? 'balance-negative' : 'balance-neutral') }}">
                                    {{ $data['net'] > 0.01 ? '+' : '' }}₹{{ number_format($data['net'], 2) }}
                                </div>
                                <a href="?view_ledger={{ $userId }}" style="font-size: 0.75rem; font-weight: 600; color: var(--accent-primary);">
                                    View breakdown &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Simplified Settlements (Aisha: "One number per person") -->
            <div class="panel" style="background: rgba(99, 102, 241, 0.03);">
                <h3 class="panel-title" style="color: var(--accent-primary);">
                    Simplified Settlement Plan
                </h3>
                @if(empty($simplifiedDebts))
                    <p style="color: var(--text-secondary); text-align: center; padding: 1rem;">
                        All balances are settled! No payments needed.
                    </p>
                @else
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        @foreach($simplifiedDebts as $debt)
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 4px solid var(--accent-primary);">
                                <span>
                                    <strong style="color: var(--danger);">{{ $debt['sender']->name }}</strong> 
                                    owes 
                                    <strong style="color: var(--success);">{{ $debt['receiver']->name }}</strong>
                                </span>
                                <span style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">
                                    ₹{{ number_format($debt['amount'], 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Membership Management Panel -->
            <div class="panel">
                <h3 class="panel-title">Membership History</h3>
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                    @foreach($members as $m)
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem;">
                            <div>
                                <span style="font-weight: 600;">{{ $m->user->name }}</span>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    Joined: {{ $m->joined_at->format('M d, Y') }} 
                                    @if($m->left_at)
                                        | Left: <span style="color: var(--danger);">{{ $m->left_at->format('M d, Y') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                @if(!$m->left_at)
                                    <form action="{{ route('groups.members.remove', [$group, $m->user]) }}" method="POST" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                        @csrf
                                        <input type="date" name="left_at" class="form-control" style="padding: 0.25rem 0.5rem; font-size: 0.85rem; width: 120px;" required>
                                        <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem; color: var(--danger);">
                                            Mark Exit
                                        </button>
                                    </form>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <h4 style="font-weight: 600; margin-bottom: 0.75rem; font-size: 0.95rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    Add Group Member
                </h4>
                <form action="{{ route('groups.members.add', $group) }}" method="POST" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    @csrf
                    <input type="text" name="name" class="form-control" style="flex: 1; min-width: 120px;" placeholder="Member Name" required>
                    <input type="date" name="joined_at" class="form-control" style="width: 130px;" value="{{ date('Y-m-d') }}" required>
                    <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                        Add
                    </button>
                </form>
            </div>

        </div>

        <!-- Right Column: Ledger and Selected Ledger Breakdowns (7 cols) -->
        <div style="grid-column: span 7; display: flex; flex-direction: column; gap: 2rem;">
            
            <!-- Rohan's Transparency Panel (Conditional) -->
            @if($selectedLedgerUser)
                <div class="panel" style="border-color: var(--accent-primary); background: rgba(99, 102, 241, 0.02);">
                    <div class="panel-title" style="border-bottom: none; margin-bottom: 1rem;">
                        <h3>Ledger Breakdown for {{ $selectedLedgerUser->name }}</h3>
                        <a href="?" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Clear</a>
                    </div>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.9rem;">
                        Complete audit trail showing transactions, payments, and settlements affecting {{ $selectedLedgerUser->name }}'s balance.
                    </p>
                    
                    @if(empty($selectedLedger))
                        <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                            No transactions logged for this member.
                        </p>
                    @else
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Original Cost</th>
                                        <th>Paid</th>
                                        <th>Owe Share</th>
                                        <th>Impact (INR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedLedger as $item)
                                        <tr>
                                            <td style="font-size: 0.85rem;">{{ $item['date'] }}</td>
                                            <td>
                                                <div style="font-weight: 500;">{{ $item['description'] }}</div>
                                                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                                    By: {{ $item['paid_by'] }}
                                                </div>
                                            </td>
                                            <td style="font-size: 0.85rem;">
                                                {{ $item['original_amount'] }} {{ $item['currency'] }}
                                            </td>
                                            <td style="color: var(--success); font-size: 0.85rem;">
                                                {{ $item['user_paid_inr'] > 0 ? '₹' . number_format($item['user_paid_inr'], 2) : '-' }}
                                            </td>
                                            <td style="color: var(--danger); font-size: 0.85rem;">
                                                {{ $item['user_share_inr'] > 0 ? '₹' . number_format($item['user_share_inr'], 2) : '-' }}
                                            </td>
                                            <td style="font-weight: 600;" class="{{ $item['net_impact_inr'] > 0 ? 'balance-positive' : ($item['net_impact_inr'] < 0 ? 'balance-negative' : 'balance-neutral') }}">
                                                {{ $item['net_impact_inr'] > 0 ? '+' : '' }}₹{{ number_format($item['net_impact_inr'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Active Group Expenses Ledger -->
            <div class="panel">
                <h3 class="panel-title">Active Expenses Ledger</h3>
                
                @if($expenses->isEmpty())
                    <p style="color: var(--text-secondary); text-align: center; padding: 4rem 2rem;">
                        No active expenses logged in this group.
                    </p>
                @else
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Paid By</th>
                                    <th>Split Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($expenses as $e)
                                    <tr>
                                        <td style="font-size: 0.85rem;">{{ $e->expense_date->format('Y-m-d') }}</td>
                                        <td>
                                            <div style="font-weight: 500;">{{ $e->description }}</div>
                                            <!-- Detailed Splits Info for Rohan -->
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                                @foreach($e->splits as $split)
                                                    {{ $split->user->name }}: ₹{{ number_format($split->amount_owed * $e->exchange_rate, 2) }}@if(!$loop->last); @endif
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600;">
                                                @if($e->currency !== 'INR')
                                                    {{ $e->amount }} {{ $e->currency }}
                                                    <div style="font-size: 0.75rem; font-weight: 400; color: var(--text-secondary);">
                                                        (₹{{ number_format($e->amount * $e->exchange_rate, 2) }})
                                                    </div>
                                                @else
                                                    ₹{{ number_format($e->amount, 2) }}
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ $e->payer->name }}</td>
                                        <td>
                                            <span class="badge badge-info" style="text-transform: capitalize;">
                                                {{ $e->split_type }}
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <a href="{{ route('expenses.edit', [$group, $e]) }}" class="btn btn-secondary" style="padding: 0.35rem 0.7rem; font-size: 0.8rem;">
                                                    Edit
                                                </a>
                                                <form action="{{ route('expenses.destroy', [$group, $e]) }}" method="POST" onsubmit="return confirm('Request deletion approval from group?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.7rem; font-size: 0.8rem;">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Direct Settlements History -->
            <div class="panel">
                <h3 class="panel-title">Direct Payments & Settlements</h3>
                @if($settlements->isEmpty())
                    <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                        No settlements recorded yet.
                    </p>
                @else
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($settlements as $s)
                                    <tr>
                                        <td>{{ $s->settlement_date->format('Y-m-d') }}</td>
                                        <td style="font-weight: 500; color: var(--danger);">{{ $s->sender->name }}</td>
                                        <td style="font-weight: 500; color: var(--success);">{{ $s->receiver->name }}</td>
                                        <td style="font-weight: 600;">
                                            @if($s->currency !== 'INR')
                                                {{ $s->amount }} {{ $s->currency }}
                                                <div style="font-size: 0.75rem; font-weight: 400; color: var(--text-secondary);">
                                                    (₹{{ number_format($s->amount * $s->exchange_rate, 2) }})
                                                </div>
                                            @else
                                                ₹{{ number_format($s->amount, 2) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>

    </div>
</div>
@endsection
