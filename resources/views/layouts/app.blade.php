<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Dashboard') · TalentFlow</title><link rel="icon" href="{{ asset('favicon.ico') }}?v=1" sizes="64x64"><link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><script src="{{ asset('js/app.js') }}" defer></script></head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="sidebar-backdrop" data-close-nav></div>
<aside class="sidebar" id="sidebar">
    <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark">t<span>f</span></span>TalentFlow<span class="brand-dot">.</span></a>
    <div class="workspace-switch"><span class="workspace-icon">TF</span><div><strong>{{ DB::table('agency_settings')->value('name') ?? 'TalentFlow' }}</strong><small>Recruitment workspace</small></div><span class="muted">⌄</span></div>
    <div class="nav-caption">WORKSPACE</div>
    <nav aria-label="Main navigation">
        <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><x-icon name="dashboard"/>Overview</a>
        @foreach(config('recruitment.entities') as $key => $config)
        <a class="nav-item {{ request()->route('entity') === $key ? 'active' : '' }}" href="{{ route('records.index', $key) }}"><x-icon :name="$key"/>{{ $config['label'] }}@if($key === 'applications')<span class="nav-tag">ATS</span>@endif</a>
        @endforeach
        <div class="nav-caption">INSIGHTS & MANAGEMENT</div>
        <a class="nav-item {{ request()->routeIs('activity') ? 'active' : '' }}" href="{{ route('activity') }}"><x-icon name="activity"/>Activity timeline</a>
        <a class="nav-item {{ request()->routeIs('reports') ? 'active' : '' }}" href="{{ route('reports') }}"><x-icon name="reports"/>Performance reports</a>
        @if(auth()->user()->role === 'admin')<a class="nav-item {{ request()->routeIs('admin') ? 'active' : '' }}" href="{{ route('admin') }}"><x-icon name="settings"/>Team & settings</a>@endif
    </nav>
    <div class="sidebar-bottom"><div class="workspace-note"><span class="live-dot"></span> Your next great hire starts here.<small>Built for people who connect people.</small></div><div class="profile"><span class="avatar purple">{{ collect(explode(' ', auth()->user()->name))->map(fn($n) => mb_substr($n,0,1))->take(2)->implode('') }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }}</small></div><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Sign out" title="Sign out"><x-icon name="logout"/></button></form></div></div>
</aside>
<div class="app-shell">
    <header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" aria-controls="sidebar" aria-expanded="false" data-toggle-nav aria-label="Open navigation"><x-icon name="menu"/></button><span>Workspace</span><x-icon name="chevron"/><strong>@yield('title', 'Overview')</strong></div><div class="topbar-actions"><form action="{{ route('records.index', 'candidates') }}" class="global-search"><x-icon name="search"/><input name="q" aria-label="Search candidates" placeholder="Search talent, skills, or email..."></form><a class="icon-button notification" href="{{ route('records.index', ['entity'=>'tasks', 'status'=>'To do']) }}" aria-label="View follow-up tasks"><x-icon name="bell"/><span></span></a><span class="role-label">{{ ucfirst(auth()->user()->role) }} workspace</span></div></header>
    <main id="main" tabindex="-1">
        @if(session('success'))<div class="flash success" role="status">✓ {{ session('success') }}</div>@endif
        @if($errors->any())<div class="flash error" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
        <footer class="page-footer"><span>TalentFlow · People. Possibilities. Progress.</span><span>All times Asia/Karachi · Fees in USD</span></footer>
    </main>
</div><div id="toast" class="toast" role="status" aria-live="polite"></div>
</body></html>
