@extends('layouts.app')

@section('title', 'Record Payment')

@section('content')
<div class="auth-container" style="max-width: 500px; margin: 2rem auto;">
    <div class="panel">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            Record Direct Payment
        </h2>
        
        <form action="{{ route('settlements.store', $group) }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="sender_id">Payer (Who Paid)</label>
                <select name="sender_id" id="sender_id" class="form-control" required>
                    <option value="" disabled selected>Select paying member...</option>
                    @foreach($members as $m)
                        <option value="{{ $m->user->id }}" {{ old('sender_id') == $m->user->id ? 'selected' : '' }}>
                            {{ $m->user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div class="form-group">
                <label for="receiver_id">Receiver (Who Received)</label>
                <select name="receiver_id" id="receiver_id" class="form-control" required>
                    <option value="" disabled selected>Select receiving member...</option>
                    @foreach($members as $m)
                        <option value="{{ $m->user->id }}" {{ old('receiver_id') == $m->user->id ? 'selected' : '' }}>
                            {{ $m->user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="amount">Amount Paid</label>
                    <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" value="{{ old('amount') }}" required>
                </div>
                <div class="form-group">
                    <label for="currency">Currency</label>
                    <select name="currency" id="currency" class="form-control">
                        <option value="INR" selected>INR (₹)</option>
                        <option value="USD">USD ($)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="settlement_date">Payment Date</label>
                <input type="date" name="settlement_date" id="settlement_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary" style="flex: 1;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 1;">Record Payment</button>
            </div>
        </form>
    </div>
</div>
@endsection
