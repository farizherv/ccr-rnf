@extends('layout')

@section('content')

<h2>Engine CCR Detail</h2>
<p>Report ID: {{ $report->id }}</p>

{{-- ================== EXPORT BUTTONS ================== --}}
<div style="margin: 15px 0;">
    <a href="{{ route('engine.export.word', $report->id) }}" class="btn-modern btn-success">
        Download Word
    </a>

    <a href="{{ route('engine.export.pdf', $report->id) }}" class="btn-modern btn-primary">
        Download PDF
    </a>
</div>

@endsection
