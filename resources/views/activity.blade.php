@extends('layouts.app')
@section('title','Activity timeline')
@section('content')
<div class="page-heading"><div><div class="eyebrow">THE STORY BEHIND THE PROGRESS</div><h1>Activity timeline</h1><p>Conversations, changes, and milestones across your accessible records.</p></div></div><section class="panel"><form class="toolbar" method="get"><div class="search-field"><x-icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="Search communication and activity..." aria-label="Search activities"></div><button class="button">Search history</button><a class="text-link" href="{{ route('activity') }}">Reset</a></form>@include('partials.timeline')@include('partials.pagination',['paginator'=>$activities])</section>
@endsection
