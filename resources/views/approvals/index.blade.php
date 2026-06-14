@extends('layouts.app')

@section('title', 'Approvals Queue')

@section('content')
<div style="display: flex; flex-direction: column; gap: 2rem;">
    
    <!-- Header -->
    <div class="panel-title" style="margin-bottom: 0;">
        <h2>Collaborative Approvals Queue</h2>
        <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
            &larr; Back
        </a>
    </div>

    <!-- Pending Requests -->
    <div class="panel">
        <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; color: var(--warning);">
            Pending Approval Requests
        </h3>
        
        @php
            $pending = $requests->where('status', 'pending');
        @endphp

        @if($pending->isEmpty())
            <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                No pending approval requests.
            </p>
        @else
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                @foreach($pending as $req)
                    <div style="background: rgba(255, 255, 255, 0.01); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.75rem;">
                            <div>
                                <span class="badge {{ $req->type === 'edit_expense' ? 'badge-info' : 'badge-danger' }}" style="text-transform: uppercase; font-size: 0.75rem;">
                                    {{ str_replace('_', ' ', $req->type) }}
                                </span>
                                <span style="font-size: 0.9rem; color: var(--text-secondary); margin-left: 0.5rem;">
                                    Requested by <strong>{{ $req->requester->name }}</strong> on {{ $req->created_at->format('M d, Y H:i') }}
                                </span>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <form action="{{ route('groups.approvals.approve', [$group, $req]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: var(--success); box-shadow: none;">
                                        Approve
                                    </button>
                                </form>
                                <form action="{{ route('groups.approvals.reject', [$group, $req]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Proposed Diff Details -->
                        @if($req->type === 'edit_expense')
                            @php
                                $original = \App\Models\Expense::find($req->target_id);
                                $proposed = $req->proposed_data;
                            @endphp
                            @if($original)
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; font-size: 0.9rem;">
                                    <div>
                                        <h4 style="font-size: 0.85rem; text-transform: uppercase; color: var(--danger); margin-bottom: 0.5rem;">
                                            Original Details
                                        </h4>
                                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.25rem; color: var(--text-secondary);">
                                            <li>Description: <strong>{{ $original->description }}</strong></li>
                                            <li>Amount: <strong>{{ $original->amount }} {{ $original->currency }}</strong></li>
                                            <li>Date: <strong>{{ $original->expense_date->format('Y-m-d') }}</strong></li>
                                            <li>Split: <strong>{{ $original->split_type }}</strong></li>
                                        </ul>
                                    </div>
                                    <div>
                                        <h4 style="font-size: 0.85rem; text-transform: uppercase; color: var(--success); margin-bottom: 0.5rem;">
                                            Proposed Details
                                        </h4>
                                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.25rem;">
                                            <li>Description: <strong style="color: var(--text-primary);">{{ $proposed['description'] }}</strong></li>
                                            <li>Amount: <strong style="color: var(--text-primary);">{{ $proposed['amount'] }} {{ $proposed['currency'] }}</strong></li>
                                            <li>Date: <strong style="color: var(--text-primary);">{{ $proposed['expense_date'] }}</strong></li>
                                            <li>Split: <strong style="color: var(--text-primary);">{{ $proposed['split_type'] }}</strong></li>
                                        </ul>
                                    </div>
                                </div>
                            @else
                                <p style="color: var(--text-secondary); font-size: 0.9rem;">Original expense was deleted.</p>
                            @endif
                        @elseif($req->type === 'delete_expense')
                            @php
                                $original = \App\Models\Expense::find($req->target_id);
                            @endphp
                            @if($original)
                                <div style="font-size: 0.9rem; color: var(--text-secondary);">
                                    Request to permanently delete expense: <strong>{{ $original->description }}</strong> worth <strong>{{ $original->amount }} {{ $original->currency }}</strong> on {{ $original->expense_date->format('Y-m-d') }}.
                                </div>
                            @else
                                <p style="color: var(--text-secondary); font-size: 0.9rem;">Expense not found.</p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Historical Requests -->
    <div class="panel">
        <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            Completed Requests History
        </h3>
        
        @php
            $history = $requests->where('status', '!=', 'pending');
        @endphp

        @if($history->isEmpty())
            <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                No historical approval requests.
            </p>
        @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Requester</th>
                            <th>Request Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $h)
                            <tr>
                                <td>{{ $h->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $h->requester->name }}</td>
                                <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $h->type) }}</td>
                                <td>
                                    <span class="badge {{ $h->status === 'approved' ? 'badge-success' : 'badge-danger' }}">
                                        {{ ucfirst($h->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
