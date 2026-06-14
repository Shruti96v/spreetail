@extends('layouts.app')

@section('title', 'Import Report')

@section('content')
<div style="display: flex; flex-direction: column; gap: 2rem;">
    <!-- Report Summary Card -->
    <div class="panel" style="background: linear-gradient(135deg, rgba(6, 182, 212, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; letter-spacing: -0.025em;">
                    CSV Import Report
                </h1>
                <p style="color: var(--text-secondary); font-size: 1.05rem;">
                    File: <strong>{{ $importLog->file_name }}</strong> | Processed: <strong>{{ $importLog->rows_processed }} rows</strong>
                </p>
            </div>
            <div>
                <span class="badge {{ $importLog->status === 'success' ? 'badge-success' : 'badge-warning' }}" style="font-size: 1rem; padding: 0.5rem 1.25rem;">
                    Status: {{ str_replace('_', ' ', strtoupper($importLog->status)) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Anomalies List -->
    <div class="panel">
        <div class="panel-title" style="border-bottom: none; margin-bottom: 1.5rem; padding-bottom: 0;">
            <h2>Detected Anomalies & Corrections</h2>
        </div>
        
        @if($anomalies->isEmpty())
            <div style="text-align: center; padding: 4rem 2rem;">
                <div style="color: var(--success); font-size: 3rem; margin-bottom: 1rem;">&check;</div>
                <p style="color: var(--text-secondary); font-weight: 500; font-size: 1.1rem;">
                    Zero anomalies detected! The spreadsheet was imported perfectly.
                </p>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Row #</th>
                            <th>Anomaly Type</th>
                            <th>Severity</th>
                            <th>Description</th>
                            <th>Policy Applied</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anomalies as $a)
                            <tr>
                                <td style="font-weight: 600;">{{ $a->row_number }}</td>
                                <td>{{ $a->anomaly_type }}</td>
                                <td>
                                    <span class="badge {{ $a->severity === 'critical' ? 'badge-danger' : ($a->severity === 'warning' ? 'badge-warning' : 'badge-info') }}">
                                        {{ ucfirst($a->severity) }}
                                    </span>
                                </td>
                                <td>{{ $a->description }}</td>
                                <td>
                                    <strong style="color: var(--text-primary);">{{ $a->policy_applied }}</strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        
        <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; display: flex; justify-content: flex-end;">
            <a href="{{ route('groups.show', $group) }}" class="btn btn-primary">
                Return to Workspace
            </a>
        </div>
    </div>
</div>
@endsection
