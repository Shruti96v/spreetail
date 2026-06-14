@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="auth-container">
    <div class="panel">
        <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 1.5rem; text-align: center;">Welcome Back</h2>
        
        <form action="{{ route('login') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem;">
                <input type="checkbox" name="remember" id="remember" style="cursor: pointer; width: 16px; height: 16px;">
                <label for="remember" style="margin-bottom: 0; cursor: pointer; user-select: none;">Remember me</label>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem; padding: 0.85rem;">
                Log In
            </button>
        </form>
        
        <div style="margin-top: 1.5rem; text-align: center; color: var(--text-secondary);">
            Don't have an account? <a href="{{ route('register') }}" style="font-weight: 600;">Sign Up</a>
        </div>
    </div>
</div>
@endsection
