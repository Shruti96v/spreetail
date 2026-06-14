@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="auth-container">
    <div class="panel">
        <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 1.5rem; text-align: center;">Create Account</h2>
        
        <form action="{{ route('register') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem; padding: 0.85rem;">
                Register
            </button>
        </form>
        
        <div style="margin-top: 1.5rem; text-align: center; color: var(--text-secondary);">
            Already have an account? <a href="{{ route('login') }}" style="font-weight: 600;">Log In</a>
        </div>
    </div>
</div>
@endsection
