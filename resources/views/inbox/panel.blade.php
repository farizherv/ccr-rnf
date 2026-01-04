@php
    // $items sudah dikirim dari controller
@endphp

@if($items->isEmpty())
    <div class="notif-empty">Belum ada notifikasi.</div>
@else
    @foreach($items as $n)
        <div class="notif-item {{ $n->is_read ? 'is-read' : 'is-unread' }}">
            <div class="notif-dot {{ $n->is_read ? 'dot-read' : 'dot-unread' }}"></div>

            <div class="notif-content">
                <div class="notif-item-title">{{ $n->title }}</div>

                {{-- aman pakai escaped text (JS kamu akan decorate kata Approved/Rejected jadi pill) --}}
                <div class="notif-item-msg">{{ $n->message }}</div>

                <div class="notif-item-meta">
                    {{ $n->created_at ? $n->created_at->format('d M Y H:i') : '' }}
                    @if($n->url)
                        <a class="notif-open" href="{{ $n->url }}">Open</a>
                    @endif
                </div>
            </div>

            @if($n->is_read)
                <span class="notif-readpill">Read</span>
            @else
                {{-- tombol read via ajax --}}
                <button type="button" class="notif-readpill notif-readbtn" data-read-id="{{ $n->id }}">
                    Read
                </button>
            @endif
        </div>
    @endforeach
@endif
