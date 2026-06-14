<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Shared Expenses') | Spreetail</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <header>
        <div class="nav-container">
            <a href="{{ route('dashboard') }}" class="logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                SplitwisePro
            </a>
            <div class="nav-links">
                @auth
                    <div class="nav-user">
                        <span class="nav-username">{{ Auth::user()->name }}</span>
                        <a href="{{ route('dashboard') }}" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Dashboard</a>
                        <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-danger" style="padding: 0.5rem 1rem;">Logout</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-secondary">Login</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="container">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul style="list-style: none; margin: 0; padding: 0;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer style="text-align: center; padding: 2rem; color: var(--text-secondary); border-top: 1px solid var(--border-color); margin-top: 4rem;">
        <p>&copy; 2026 Shared Expenses App. Production Ready.</p>
    </footer>
</body>
</html>
