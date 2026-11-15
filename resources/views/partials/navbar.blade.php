<nav class="nav">
    <span class="logo"><img src="{{ asset('images/logo.png') }}" alt="" width="36" height="36">Terra Echo</span>

    <div class="links">
        <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
        <a class="{{ request()->routeIs('plans*') ? 'active' : '' }}" href="{{ route('plans') }}">Farming Plans</a>
        <a class="{{ request()->routeIs('farm.*') ? 'active' : '' }}" href="{{ route('farm.index') }}">My Farm & Simulation</a>
        <a class="{{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About Us</a>
    </div>

    @auth
        @if(request()->routeIs('farm.index'))
            <button type="button" id="chatToggle" class="abu-btn">
                <img src="{{ asset('images/abu-qerdan.jpg') }}" alt="">
                <span>Abu Qerdan</span>
            </button>
        @endif
    @endauth

    @guest
        <a class="signin" href="{{ route('login') }}">Sign in</a>
    @endguest

    @auth
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="signin">Logout</button>
        </form>
    @endauth
</nav>