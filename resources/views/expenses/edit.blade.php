@extends('layouts.app')

@section('title', 'Edit Expense')

@section('content')
<div class="auth-container" style="max-width: 600px; margin: 2rem auto;">
    <div class="panel">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            Edit Expense Details
        </h2>
        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
            Meera's Rule: Submitting modifications will generate a change request that requires group approval before taking effect.
        </p>
        
        <form action="{{ route('expenses.update', [$group, $expense]) }}" method="POST" id="expense-form">
            @csrf
            @method('PUT')
            
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" name="description" id="description" class="form-control" value="{{ old('description', $expense->description) }}" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="amount">Total Amount</label>
                    <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0.01" value="{{ old('amount', $expense->amount) }}" required>
                </div>
                <div class="form-group">
                    <label for="currency">Currency</label>
                    <select name="currency" id="currency" class="form-control">
                        <option value="INR" {{ $expense->currency === 'INR' ? 'selected' : '' }}>INR (₹)</option>
                        <option value="USD" {{ $expense->currency === 'USD' ? 'selected' : '' }}>USD ($)</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="expense_date">Date</label>
                    <input type="date" name="expense_date" id="expense_date" class="form-control" value="{{ old('expense_date', $expense->expense_date->toDateString()) }}" required>
                </div>
                <div class="form-group">
                    <label for="split_type">Split Type</label>
                    <select name="split_type" id="split_type" class="form-control" onchange="adjustSplitInputs()">
                        <option value="equal" {{ $expense->split_type === 'equal' ? 'selected' : '' }}>Equal Split</option>
                        <option value="percentage" {{ $expense->split_type === 'percentage' ? 'selected' : '' }}>Percentage Split</option>
                        <option value="exact" {{ $expense->split_type === 'exact' ? 'selected' : '' }}>Exact Amount Split</option>
                    </select>
                </div>
            </div>

            <!-- Splits Input Section -->
            <div style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.75rem;">
                    Split Details
                </h4>
                
                <div id="split-participants-container">
                    @foreach($members as $m)
                        @php
                            $isParticipant = array_key_exists($m->user->id, $activeSplits);
                            $splitVal = $isParticipant 
                                ? ($expense->split_type === 'percentage' ? ($percentages[$m->user->id] ?? '') : $activeSplits[$m->user->id]) 
                                : '';
                        @endphp
                        <div class="split-member-row">
                            <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0; cursor: pointer; user-select: none;">
                                <input type="checkbox" class="participant-check" data-user-id="{{ $m->user->id }}" {{ $isParticipant ? 'checked' : '' }} style="width: 16px; height: 16px;">
                                <span>{{ $m->user->name }}</span>
                            </label>
                            
                            <div class="split-input-wrapper" style="display: none;">
                                <input type="number" name="splits[{{ $m->user->id }}]" class="form-control split-value-input" style="width: 100px; padding: 0.25rem 0.5rem; text-align: right;" step="0.01" min="0" value="{{ $splitVal }}" placeholder="0">
                                <span class="split-unit" style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary" style="flex: 1;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 1;">Submit Edit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function adjustSplitInputs() {
    const splitType = document.getElementById('split_type').value;
    const rows = document.querySelectorAll('.split-member-row');
    
    rows.forEach(row => {
        const checkbox = row.querySelector('.participant-check');
        const inputWrapper = row.querySelector('.split-input-wrapper');
        const input = row.querySelector('.split-value-input');
        const unit = row.querySelector('.split-unit');
        
        if (splitType === 'equal') {
            inputWrapper.style.display = 'none';
            input.removeAttribute('required');
            input.name = `splits[${checkbox.dataset.userId}]`;
            input.value = checkbox.checked ? '1' : '';
        } else {
            inputWrapper.style.display = 'flex';
            inputWrapper.style.alignItems = 'center';
            inputWrapper.style.gap = '0.25rem';
            input.name = `splits[${checkbox.dataset.userId}]`;
            
            if (checkbox.checked) {
                input.removeAttribute('disabled');
                input.setAttribute('required', 'required');
            } else {
                input.setAttribute('disabled', 'disabled');
                input.removeAttribute('required');
                input.value = '';
            }
            
            if (splitType === 'percentage') {
                unit.textContent = '%';
            } else if (splitType === 'exact') {
                const currency = document.getElementById('currency').value;
                unit.textContent = currency === 'INR' ? '₹' : '$';
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    adjustSplitInputs();
    
    document.querySelectorAll('.participant-check').forEach(check => {
        check.addEventListener('change', () => {
            adjustSplitInputs();
        });
    });
    
    document.getElementById('expense-form').addEventListener('submit', function(e) {
        const splitType = document.getElementById('split_type').value;
        const checks = document.querySelectorAll('.participant-check:checked');
        
        if (checks.length === 0) {
            e.preventDefault();
            alert('Please select at least one member to split the expense.');
            return;
        }

        if (splitType === 'percentage') {
            let sum = 0;
            checks.forEach(check => {
                const row = check.closest('.split-member-row');
                const val = parseFloat(row.querySelector('.split-value-input').value) || 0;
                sum += val;
            });
            if (Math.abs(sum - 100) > 0.01) {
                e.preventDefault();
                alert('Total percentage must sum to exactly 100%. Current sum: ' + sum + '%');
            }
        } else if (splitType === 'exact') {
            let sum = 0;
            checks.forEach(check => {
                const row = check.closest('.split-member-row');
                const val = parseFloat(row.querySelector('.split-value-input').value) || 0;
                sum += val;
            });
            const totalAmount = parseFloat(document.getElementById('amount').value) || 0;
            if (Math.abs(sum - totalAmount) > 0.01) {
                e.preventDefault();
                alert('Total split amounts must sum to the expense amount (' + totalAmount + '). Current sum: ' + sum);
            }
        }
    });

    document.getElementById('currency').addEventListener('change', () => {
        adjustSplitInputs();
    });
});
</script>
@endsection
