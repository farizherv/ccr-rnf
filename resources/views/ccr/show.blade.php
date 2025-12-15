@extends('layout')

@section('content')

<div class="box">
    <h3>Detail CCR</h3>

    <p><b>Group:</b> {{ $report->group_folder }}</p>
    <p><b>Component:</b> {{ $report->component }}</p>
    <p><b>Unit:</b> {{ $report->unit }}</p>
    <p><b>Customer:</b> {{ $report->customer }}</p>
</div>

<div class="box">
    <h3>Item Kerusakan</h3>

    @foreach ($report->items as $i => $item)
        <div class="box" style="background:#f9f9f9">
            <p><b>Item {{ $i+1 }}</b></p>
            <p>{{ $item->description }}</p>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                @foreach ($item->photos as $photo)
                    <img src="{{ asset('storage/'.$photo->path) }}" width="200">
                @endforeach
            </div>
        </div>
    @endforeach

</div>

@endsection
