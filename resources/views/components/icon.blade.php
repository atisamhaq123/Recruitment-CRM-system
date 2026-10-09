@php
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'clients' => '<path d="M4 21V5l8-3v19M12 8h8v13M2 21h20M7 7v1m0 3v1m0 3v1m9-4h1m-1 4h1"/>',
        'candidates' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 3a4 4 0 0 1 0 8m2 3a6 6 0 0 1 3 5v2"/>',
        'jobs' => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12a24 24 0 0 0 18 0m-9 0v4"/>',
        'applications' => '<rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="10" rx="1"/><rect x="17" y="4" width="4" height="13" rx="1"/>',
        'interviews' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-14 4h2m4 0h2m-8 3h2"/>',
        'tasks' => '<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M9 4V2h6v2M8 11l2 2 5-5m-7 9h8"/>',
        'placements' => '<path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/>',
        'reports' => '<path d="M3 3v18h18M7 16v-5m5 5V7m5 9V4"/>',
        'activity' => '<path d="M2 12h5l3-8 4 16 3-8h5"/>',
        'settings' => '<path d="m10 3-1 3-3 1-3 3 2 2-1 4 3 3 3-1 2 3 4-1 1-3 3-1-1-4 2-2-3-3-3 1-2-3z"/><circle cx="12" cy="12" r="3"/>',
        'search' => '<circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>', 'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9m4 12h4"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>', 'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'logout' => '<path d="M9 4H3v16h6m6-13 5 5-5 5m-8-5h13"/>', 'money' => '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M5 9v6m14-6v6"/>',
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->class(['icon']) }}>{!! $paths[$name] ?? $paths['jobs'] !!}</svg>
