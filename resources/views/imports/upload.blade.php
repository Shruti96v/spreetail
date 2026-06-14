@extends('layouts.app')

@section('title', 'Import CSV')

@section('content')
<div class="auth-container" style="max-width: 550px; margin: 2rem auto;">
    <div class="panel">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            Import Shared Expenses CSV
        </h2>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Upload your expense spreadsheet export. Our anomaly engine will automatically detect duplicates, negative amounts, out-of-tenure transactions, and apply our resolution policies.
        </p>
        
        <form action="{{ route('groups.imports.store', $group) }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="form-group" style="border: 2px dashed var(--glass-border); border-radius: 8px; padding: 2rem; text-align: center; background: rgba(255, 255, 255, 0.01); transition: var(--transition); margin-bottom: 1.5rem;" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='var(--glass-border)'">
                <label for="csv_file" style="cursor: pointer; display: block; font-weight: 600; font-size: 1.1rem; color: var(--accent-primary); margin-bottom: 0.5rem;">
                    Select CSV File
                </label>
                <input type="file" name="csv_file" id="csv_file" style="margin: 0 auto; color: var(--text-secondary);" accept=".csv" required>
                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 1rem;">
                    Required columns: Date, Description, Amount, Paid By, Currency, Split Type, Split Details
                </div>
            </div>
            
            <div style="background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.1); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem;">
                <h4 style="font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">
                    Resolution Policies Applied:
                </h4>
                <ul style="padding-left: 1.25rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 0.25rem;">
                    <li><strong>Duplicates</strong>: Automatically skipped.</li>
                    <li><strong>Negative Amounts</strong>: Converted to absolute positive values.</li>
                    <li><strong>Future Dates</strong>: Normalized to today's date.</li>
                    <li><strong>Out of Tenure</strong>: Members are excluded from splits and shares recalculated.</li>
                    <li><strong>Settlements in CSV</strong>: Routed to direct settlements instead of expenses.</li>
                </ul>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary" style="flex: 1;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 1;">Upload & Process</button>
            </div>
        </form>
    </div>
</div>
@endsection
