@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div style="display: flex; flex-direction: column; gap: 2rem;">
    <!-- Welcome Panel -->
    <div class="panel" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(6, 182, 212, 0.05) 100%);">
        <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem; letter-spacing: -0.025em;">
            Welcome, {{ Auth::user()->name }}
        </h1>
        <p style="color: var(--text-secondary); font-size: 1.1rem;">
            Track shared expenses, manage flatmate tenures, handle multi-currency trips, and simplify your group debts.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem;">
        <!-- Groups List -->
        <div>
            <div class="panel-title" style="border-bottom: none; margin-bottom: 1.5rem; padding-bottom: 0;">
                <h2 style="font-size: 1.5rem; font-weight: 600;">Your Groups</h2>
            </div>
            
            @if($groups->isEmpty())
                <div class="panel" style="text-align: center; padding: 4rem 2rem;">
                    <p style="color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 1.5rem;">
                        You are not a member of any expense group yet.
                    </p>
                    <p style="font-size: 0.95rem; color: var(--text-secondary);">
                        Create a group on the right to get started!
                    </p>
                </div>
            @else
                <div class="dashboard-grid">
                    @foreach($groups as $group)
                        <div class="group-card">
                            <h3 class="group-card-title">
                                <a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a>
                            </h3>
                            <p class="group-card-desc">
                                {{ $group->description ?: 'No description provided.' }}
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; font-size: 0.85rem; color: var(--text-secondary);">
                                <span>Members: {{ $group->members->count() }}</span>
                                <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                    View Workspace
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Create Group Side Panel -->
        <div>
            <div class="panel">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    Create New Group
                </h3>
                
                <form action="{{ route('groups.store') }}" method="POST">
                    @csrf
                    
                    <div class="form-group">
                        <label for="group_name">Group Name</label>
                        <input type="text" name="name" id="group_name" class="form-control" placeholder="e.g. 221B Baker St Flat" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="group_desc">Description</label>
                        <textarea name="description" id="group_desc" class="form-control" rows="3" placeholder="Describe the purpose of this group..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        Create Group
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
