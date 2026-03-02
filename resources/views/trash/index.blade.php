@extends('layout')

@section('content')

<a href="{{ route('ccr.index') }}" class="btn-back">← Kembali</a>

<div class="header-card">
  <h1>🗑️ Sampah CCR</h1>
  <p>Data akan dihapus permanen otomatis setelah 7 hari.</p>
</div>

<form method="GET" style="margin:12px 0;">
  <select name="type" onchange="this.form.submit()">
    <option value="">Semua</option>
    <option value="engine" @selected($type==='engine')>Engine</option>
    <option value="seat" @selected($type==='seat')>Operator Seat</option>
  </select>
</form>

<form action="{{ route('ccr.trash.restore') }}" method="POST">
  @csrf

  <table class="table">
    <thead>
      <tr>
        <th></th>
        <th>Component</th>
        <th>Customer</th>
        <th>Deleted At</th>
        <th>Sisa Hari</th>
      </tr>
    </thead>
    <tbody>
      @forelse($trashReports as $r)
        <tr>
          <td><input type="checkbox" name="ids[]" value="{{ $r->id }}"></td>
          <td>{{ $r->component }}</td>
          <td>{{ $r->customer }}</td>
          <td>{{ optional($r->deleted_at)->format('Y-m-d H:i') }}</td>
          <td>
            @if($r->purge_at)
              {{ now()->diffInDays($r->purge_at, false) }} hari
            @else
              -
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5">Trash kosong.</td></tr>
      @endforelse
    </tbody>
  </table>

  <button type="submit" class="btn-green">♻️ Restore Terpilih</button>
</form>

@endsection
