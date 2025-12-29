@php
    use App\Support\Inbox;
    $unread = auth()->check() ? Inbox::unreadCount(auth()->user()) : 0;
@endphp

<div class="top-actions" x-data="{ open:false }">
    {{-- Inbox --}}
    <a href="{{ route('inbox.index') }}" class="icon-btn" title="Inbox">
        🔔
        @if($unread > 0)
            <span class="badge">{{ $unread }}</span>
        @endif
    </a>

    {{-- Settings dropdown --}}
    <button type="button" class="icon-btn" @click="open = !open" title="Settings">
        ⚙️
    </button>

    <div class="drop" x-show="open" @click.outside="open=false" x-cloak>
        <div class="drop-head">
            <div class="name">{{ auth()->user()->name ?? '-' }}</div>
            <div class="role">{{ strtoupper((auth()->user()->role ?? '')) }}</div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="drop-item" type="submit">Logout</button>
        </form>
    </div>
</div>

<style>
.top-actions{display:flex;align-items:center;gap:10px;position:relative}
.icon-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;background:#f3f4f6;border:1px solid #e5e7eb;text-decoration:none;font-size:18px;cursor:pointer}
.badge{position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#E40505;color:#fff;font-weight:1000;font-size:12px;display:flex;align-items:center;justify-content:center}
.drop{position:absolute;right:0;top:52px;width:210px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.14);overflow:hidden;z-index:9999}
.drop-head{padding:12px 14px;border-bottom:1px solid #eef2f7}
.drop-head .name{font-weight:1000}
.drop-head .role{font-weight:900;color:#6b7280;font-size:12px;margin-top:2px}
.drop-item{width:100%;text-align:left;border:none;background:#fff;padding:12px 14px;font-weight:1000;cursor:pointer}
.drop-item:hover{background:#f8fafc}
[x-cloak]{display:none!important}
</style>
