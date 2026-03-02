@extends('layout')

@section('content')

@php
    $recipients = $recipients ?? collect();
    $maxRecipients = (int) ($maxRecipients ?? 100);
    $users = $users ?? collect();
    $availableUsers = $availableUsers ?? collect();
    $usersByEmail = $users->mapWithKeys(fn($user) => [strtolower(trim((string) $user->email)) => $user]);
    $listEditMode = $errors->any();
@endphp

<div data-page="notification-recipients">
    <div class="top-toolbar">
        <a href="{{ route('admin.users.index') }}" class="btn-back">← Kembali ke User Management</a>
    </div>

    <div class="box page-head">
        <div class="head-left">
            <h1 class="title">Setting Email Notifikasi CCR</h1>
            <p class="subtitle">
                Atur email akun user untuk menerima notifikasi <b>waiting</b>, <b>approved</b>, dan <b>rejected</b>.
            </p>
        </div>
        <div class="meta-pill">{{ $recipients->count() }} / {{ $maxRecipients }} recipient</div>
    </div>

    <div class="box add-box">
        <div class="section-title">Tambah Recipient Baru</div>
        <form action="{{ route('admin.notifications.store') }}" method="POST" class="add-form">
            @csrf
            <input type="hidden" name="is_active" value="0">

            <div class="grid">
                <div class="field">
                    <label for="addUserId">Akun User</label>
                    <select id="addUserId" name="user_id" class="input js-user-select" data-email-target="addEmail" data-role-target="addRoleBadge" required>
                        <option value="">Pilih nama akun user</option>
                        @foreach($availableUsers as $user)
                            <option
                                value="{{ $user->id }}"
                                data-email="{{ strtolower(trim((string) $user->email)) }}"
                                data-role="{{ strtoupper($user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role) }}"
                                @selected((string) old('user_id') === (string) $user->id)
                            >
                                {{ $user->name }} ({{ strtoupper($user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="addEmail">Email</label>
                    <input id="addEmail" type="email" class="input" value="" readonly>
                    <div class="meta-row">Role: <span id="addRoleBadge" class="role-pill role-empty">-</span></div>
                </div>
            </div>
            <p class="hint-text">Nama dan email mengikuti akun user. Tidak perlu isi nama manual lagi.</p>

            <div class="flag-row">
                <label class="flag"><input type="hidden" name="notify_waiting" value="0"><input id="addNotifyWaiting" type="checkbox" name="notify_waiting" value="1" {{ old('notify_waiting', '1') ? 'checked' : '' }}> Waiting</label>
                <label class="flag"><input type="hidden" name="notify_approved" value="0"><input id="addNotifyApproved" type="checkbox" name="notify_approved" value="1" {{ old('notify_approved', '1') ? 'checked' : '' }}> Approved</label>
                <label class="flag"><input type="hidden" name="notify_rejected" value="0"><input id="addNotifyRejected" type="checkbox" name="notify_rejected" value="1" {{ old('notify_rejected', '1') ? 'checked' : '' }}> Rejected</label>
                <label class="flag"><input type="checkbox" name="is_active" value="1" checked> Aktif</label>
            </div>

            <div class="actions">
                <button type="submit" class="btn-save">Tambah Recipient</button>
            </div>
        </form>
    </div>

    <div class="box list-box" data-edit-mode="{{ $listEditMode ? '1' : '0' }}">
        <div class="list-head">
            <div class="section-title">Daftar Recipient</div>
            @if($recipients->isNotEmpty())
                <button
                    id="toggleRecipientsEdit"
                    type="button"
                    class="btn-toggle-edit {{ $listEditMode ? 'is-active' : '' }}"
                    aria-pressed="{{ $listEditMode ? 'true' : 'false' }}"
                    data-label-default="Edit"
                    data-label-active="Simpan Perubahan"
                >
                    {{ $listEditMode ? 'Simpan Perubahan' : 'Edit' }}
                </button>
            @endif
        </div>

        @if($recipients->isEmpty())
            <p class="empty-state">Belum ada recipient email notifikasi.</p>
        @else
            <form id="bulkUpdateRecipientsForm" action="{{ route('admin.notifications.bulkUpdate') }}" method="POST">
                @csrf
                <div class="rows {{ $listEditMode ? '' : 'is-locked' }}">
                    @foreach($recipients as $recipient)
                        @php
                            $recipientUser = $usersByEmail->get(strtolower(trim((string) $recipient->email)));
                            $selectedUserId = old('recipients.' . $recipient->id . '.user_id', optional($recipientUser)->id);
                            $checkedWaiting = (bool) old('recipients.' . $recipient->id . '.notify_waiting', $recipient->notify_waiting ? 1 : 0);
                            $checkedApproved = (bool) old('recipients.' . $recipient->id . '.notify_approved', $recipient->notify_approved ? 1 : 0);
                            $checkedRejected = (bool) old('recipients.' . $recipient->id . '.notify_rejected', $recipient->notify_rejected ? 1 : 0);
                            $checkedActive = (bool) old('recipients.' . $recipient->id . '.is_active', $recipient->is_active ? 1 : 0);
                            $markedDelete = (bool) old('recipients.' . $recipient->id . '._delete', 0);
                        @endphp
                        <article id="recipient-row-{{ $recipient->id }}" class="row-item {{ $markedDelete ? 'is-marked-delete' : '' }}">
                            <div class="row-form">
                                <input type="hidden" id="delete-flag-{{ $recipient->id }}" name="recipients[{{ $recipient->id }}][_delete]" value="{{ $markedDelete ? 1 : 0 }}">
                                <div class="grid">
                                    <div class="field">
                                        <label for="user-{{ $recipient->id }}">Akun User</label>
                                        <select
                                            id="user-{{ $recipient->id }}"
                                            name="recipients[{{ $recipient->id }}][user_id]"
                                            class="input js-user-select"
                                            data-email-target="email-{{ $recipient->id }}"
                                            data-role-target="role-{{ $recipient->id }}"
                                            required
                                            @disabled(!$listEditMode)
                                        >
                                            <option value="">Pilih nama akun user</option>
                                            @foreach($users as $user)
                                                <option
                                                    value="{{ $user->id }}"
                                                    data-email="{{ strtolower(trim((string) $user->email)) }}"
                                                    data-role="{{ strtoupper($user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role) }}"
                                                    @selected((string) $selectedUserId === (string) $user->id)
                                                >
                                                    {{ $user->name }} ({{ strtoupper($user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label for="email-{{ $recipient->id }}">Email</label>
                                        <input
                                            id="email-{{ $recipient->id }}"
                                            type="email"
                                            class="input"
                                            value="{{ strtolower(trim((string) ($recipientUser->email ?? $recipient->email))) }}"
                                            readonly
                                        >
                                        <div class="meta-row">
                                            Role:
                                            <span id="role-{{ $recipient->id }}" class="role-pill {{ $recipientUser ? '' : 'role-empty' }}">
                                                @php $roleDisplay = $recipientUser ? ($recipientUser->role instanceof \App\Enums\UserRole ? $recipientUser->role->value : (string) $recipientUser->role) : '-'; @endphp
                                                {{ strtoupper($roleDisplay) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flag-row">
                                    <label class="flag">
                                        <input type="hidden" name="recipients[{{ $recipient->id }}][notify_waiting]" value="0">
                                        <input type="checkbox" name="recipients[{{ $recipient->id }}][notify_waiting]" value="1" {{ $checkedWaiting ? 'checked' : '' }} @disabled(!$listEditMode)>
                                        Waiting
                                    </label>
                                    <label class="flag">
                                        <input type="hidden" name="recipients[{{ $recipient->id }}][notify_approved]" value="0">
                                        <input type="checkbox" name="recipients[{{ $recipient->id }}][notify_approved]" value="1" {{ $checkedApproved ? 'checked' : '' }} @disabled(!$listEditMode)>
                                        Approved
                                    </label>
                                    <label class="flag">
                                        <input type="hidden" name="recipients[{{ $recipient->id }}][notify_rejected]" value="0">
                                        <input type="checkbox" name="recipients[{{ $recipient->id }}][notify_rejected]" value="1" {{ $checkedRejected ? 'checked' : '' }} @disabled(!$listEditMode)>
                                        Rejected
                                    </label>
                                    <label class="flag">
                                        <input type="hidden" name="recipients[{{ $recipient->id }}][is_active]" value="0">
                                        <input type="checkbox" name="recipients[{{ $recipient->id }}][is_active]" value="1" {{ $checkedActive ? 'checked' : '' }} @disabled(!$listEditMode)>
                                        Aktif
                                    </label>
                                </div>
                                <div id="delete-state-{{ $recipient->id }}" class="delete-state {{ $markedDelete ? '' : 'is-hidden' }}">
                                    Recipient ini akan dihapus saat klik <b>Simpan Perubahan</b>.
                                </div>
                            </div>

                            <div class="row-delete-wrap">
                                <button
                                    id="delete-btn-{{ $recipient->id }}"
                                    type="button"
                                    class="btn-delete js-mark-delete {{ $markedDelete ? 'is-undo' : '' }}"
                                    data-recipient-id="{{ $recipient->id }}"
                                    @disabled(!$listEditMode)
                                >{{ $markedDelete ? 'Batal Hapus' : 'Hapus' }}</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </form>
        @endif
    </div>
</div>

<style>
[data-page="notification-recipients"] * { box-sizing: border-box; }
[data-page="notification-recipients"] .top-toolbar{
    display:flex;align-items:center;gap:12px;margin-bottom:16px;
}
[data-page="notification-recipients"] .btn-back{
    display:inline-flex;align-items:center;justify-content:center;text-decoration:none;
    border-radius:12px;font-weight:900;padding:10px 18px;background:#5f656a;color:#fff;
    box-shadow:0 10px 18px rgba(0,0,0,.10);transition:.18s;
}
[data-page="notification-recipients"] .btn-back:hover{ background:#2f3336;transform:translateY(-1px); }
[data-page="notification-recipients"] .box{
    background:#f8fbff;border:1px solid #dbe5f3;border-radius:18px;padding:16px;
    box-shadow:0 10px 28px rgba(15,23,42,.05);margin-bottom:14px;
}
[data-page="notification-recipients"] .page-head{
    display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;
}
[data-page="notification-recipients"] .title{ margin:0;font-size:28px;line-height:1.1;font-weight:1000;color:#0f1b3a; }
[data-page="notification-recipients"] .subtitle{ margin:10px 0 0;color:#5f6e8a;font-size:14px;font-weight:700; }
[data-page="notification-recipients"] .meta-pill{
    display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:8px 12px;
    font-size:12px;font-weight:900;color:#324567;background:#eff3fb;border:1px solid #d6e0ef;
}
[data-page="notification-recipients"] .section-title{
    margin:0 0 10px;font-size:14px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;color:#0f1b3a;
}
[data-page="notification-recipients"] .list-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
}
[data-page="notification-recipients"] .list-head .section-title{
    margin:0;
}
[data-page="notification-recipients"] .btn-toggle-edit{
    border:1px solid #9fb3cf;
    background:#fff;
    color:#1e293b;
    min-height:40px;
    padding:0 14px;
    border-radius:10px;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
    transition:.16s ease;
}
[data-page="notification-recipients"] .btn-toggle-edit:hover{
    border-color:#6f8fb8;
    background:#f2f7ff;
}
[data-page="notification-recipients"] .btn-toggle-edit:disabled{
    opacity:.75;
    cursor:not-allowed;
}
[data-page="notification-recipients"] .btn-toggle-edit.is-active{
    background:#1f2937;
    border-color:#1f2937;
    color:#fff;
}
[data-page="notification-recipients"] .grid{
    display:grid;grid-template-columns:1.2fr 1fr;gap:12px;
}
[data-page="notification-recipients"] .field{ display:flex;flex-direction:column;gap:7px;min-width:0; }
[data-page="notification-recipients"] .field label{ font-size:13px;font-weight:900;color:#1f2937; }
[data-page="notification-recipients"] .meta-row{
    margin-top:2px;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    display:flex;
    align-items:center;
    gap:8px;
}
[data-page="notification-recipients"] .role-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:999px;
    border:1px solid #cfd9e8;
    background:#fff;
    padding:3px 9px;
    font-size:11px;
    font-weight:900;
    color:#334155;
}
[data-page="notification-recipients"] .role-empty{
    color:#94a3b8;
}
[data-page="notification-recipients"] .input{
    width:100%;min-height:44px;border-radius:12px;border:1px solid #cfd9e8;background:#fff;padding:10px 12px;
    font-size:15px;color:#0f172a;outline:none;
}
[data-page="notification-recipients"] .input:focus{
    border-color:#2f65d8;box-shadow:0 0 0 3px rgba(47,101,216,.15);
}
[data-page="notification-recipients"] .flag-row{
    display:flex;flex-wrap:wrap;gap:12px;margin-top:12px;
}
[data-page="notification-recipients"] .hint-text{
    margin:10px 0 0;
    color:#64748b;
    font-size:12px;
    font-weight:700;
}
[data-page="notification-recipients"] .flag{
    display:inline-flex;align-items:center;gap:7px;border:1px solid #d3dce9;background:#fff;
    border-radius:999px;padding:8px 12px;font-size:13px;font-weight:800;color:#334155;
}
[data-page="notification-recipients"] .actions{
    margin-top:12px;display:flex;align-items:center;justify-content:flex-end;
}
[data-page="notification-recipients"] .btn-save,
[data-page="notification-recipients"] .btn-update{
    border:0;cursor:pointer;min-height:40px;padding:0 14px;border-radius:10px;font-size:14px;font-weight:900;color:#fff;
    background:#1f6fe5;box-shadow:0 10px 20px rgba(31,111,229,.24);
}
[data-page="notification-recipients"] .btn-delete{
    border:0;cursor:pointer;min-height:40px;padding:0 14px;border-radius:10px;font-size:14px;font-weight:900;color:#fff;
    background:#ef4444;box-shadow:0 10px 20px rgba(239,68,68,.24);
}
[data-page="notification-recipients"] .btn-delete.is-undo{
    background:#475569;
    box-shadow:0 10px 20px rgba(71,85,105,.22);
}
[data-page="notification-recipients"] .rows{ display:flex;flex-direction:column;gap:12px; }
[data-page="notification-recipients"] .row-item{
    border:1px solid #dbe5f3;background:#fff;border-radius:14px;padding:12px;
    display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:end;
}
[data-page="notification-recipients"] .row-item.is-marked-delete{
    border-color:#f2b4b4;
    background:#fff5f5;
}
[data-page="notification-recipients"] .row-form{
    min-width:0;
}
[data-page="notification-recipients"] .row-delete-wrap{
    display:flex;
    align-items:flex-end;
    justify-content:flex-end;
}
[data-page="notification-recipients"] .rows.is-locked{
    opacity:.92;
}
[data-page="notification-recipients"] .rows.is-locked .row-item{
    cursor:not-allowed;
    background:#f8fafc;
}
[data-page="notification-recipients"] .rows.is-locked .row-item *{
    cursor:not-allowed !important;
}
[data-page="notification-recipients"] .rows.is-locked .flag{
    opacity:.72;
}
[data-page="notification-recipients"] .delete-state{
    margin-top:10px;
    border:1px solid #f3c3c3;
    border-radius:10px;
    background:#fff0f0;
    color:#b42318;
    font-size:12px;
    font-weight:800;
    padding:8px 10px;
}
[data-page="notification-recipients"] .delete-state.is-hidden{
    display:none;
}
[data-page="notification-recipients"] .input:disabled{
    background:#eef2f7;
    color:#64748b;
    border-color:#d3dce9;
}
[data-page="notification-recipients"] .btn-delete:disabled{
    background:#e88d8d;
    box-shadow:none;
    color:#fff;
    opacity:.90;
    transform:none;
    cursor:not-allowed;
}
[data-page="notification-recipients"] .btn-delete:disabled:hover{
    background:#e88d8d;
}
[data-page="notification-recipients"] .empty-state{
    margin:0;padding:16px;border:1px dashed #cfd9e8;border-radius:12px;background:#fff;color:#5f6e8a;font-weight:700;
}
@media (max-width:900px){
    [data-page="notification-recipients"] .grid{ grid-template-columns:1fr; }
    [data-page="notification-recipients"] .row-item{ grid-template-columns:1fr; }
}
</style>

<script>
(function () {
    const page = document.querySelector('[data-page="notification-recipients"]');
    if (!page) return;

    const applySelectionMeta = (selectEl) => {
        if (!selectEl) return;
        const selected = selectEl.options[selectEl.selectedIndex];
        const emailTargetId = selectEl.dataset.emailTarget || '';
        const roleTargetId = selectEl.dataset.roleTarget || '';
        const emailInput = emailTargetId ? document.getElementById(emailTargetId) : null;
        const roleNode = roleTargetId ? document.getElementById(roleTargetId) : null;

        const email = selected ? (selected.dataset.email || '') : '';
        const role = selected ? (selected.dataset.role || '') : '';

        if (emailInput) emailInput.value = email;
        if (roleNode) {
            roleNode.textContent = role || '-';
            roleNode.classList.toggle('role-empty', !role);
        }
    };

    const applyDefaultFlagsByRole = (roleUpper) => {
        const waiting = document.getElementById('addNotifyWaiting');
        const approved = document.getElementById('addNotifyApproved');
        const rejected = document.getElementById('addNotifyRejected');
        if (!waiting || !approved || !rejected) return;

        const role = (roleUpper || '').toUpperCase();
        if (role === 'DIRECTOR') {
            waiting.checked = true;
            approved.checked = false;
            rejected.checked = false;
            return;
        }

        if (role === 'ADMIN' || role === 'OPERATOR') {
            waiting.checked = false;
            approved.checked = true;
            rejected.checked = true;
            return;
        }

        waiting.checked = true;
        approved.checked = true;
        rejected.checked = true;
    };

    const listBox = page.querySelector('.list-box');
    const toggleRecipientsEditBtn = page.querySelector('#toggleRecipientsEdit');
    const listRows = listBox ? listBox.querySelector('.rows') : null;
    const bulkUpdateForm = document.getElementById('bulkUpdateRecipientsForm');
    const editableNodes = listBox
        ? Array.from(listBox.querySelectorAll('.row-item select, .row-item input[type="checkbox"], .row-item .btn-delete'))
        : [];
    const markDeleteButtons = listBox
        ? Array.from(listBox.querySelectorAll('.js-mark-delete'))
        : [];

    const setRowDeleteState = (recipientId, markedDelete) => {
        if (!recipientId) return;
        const row = document.getElementById(`recipient-row-${recipientId}`);
        const deleteFlag = document.getElementById(`delete-flag-${recipientId}`);
        const deleteState = document.getElementById(`delete-state-${recipientId}`);
        const deleteBtn = document.getElementById(`delete-btn-${recipientId}`);
        if (!row || !deleteFlag || !deleteBtn) return;

        deleteFlag.value = markedDelete ? '1' : '0';
        row.classList.toggle('is-marked-delete', markedDelete);
        deleteBtn.classList.toggle('is-undo', markedDelete);
        deleteBtn.textContent = markedDelete ? 'Batal Hapus' : 'Hapus';
        if (deleteState) {
            deleteState.classList.toggle('is-hidden', !markedDelete);
        }
    };

    const applyRecipientsEditMode = (enabled) => {
        if (!listBox || !listRows) return;
        listBox.dataset.editMode = enabled ? '1' : '0';
        listRows.classList.toggle('is-locked', !enabled);
        editableNodes.forEach((node) => {
            node.disabled = !enabled;
        });

        if (toggleRecipientsEditBtn) {
            const defaultLabel = toggleRecipientsEditBtn.dataset.labelDefault || 'Edit';
            const activeLabel = toggleRecipientsEditBtn.dataset.labelActive || 'Selesai Edit';
            toggleRecipientsEditBtn.textContent = enabled ? activeLabel : defaultLabel;
            toggleRecipientsEditBtn.classList.toggle('is-active', enabled);
            toggleRecipientsEditBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        }
    };

    page.querySelectorAll('.js-user-select').forEach((selectEl) => {
        applySelectionMeta(selectEl);
        if (selectEl.id === 'addUserId') {
            const selected = selectEl.options[selectEl.selectedIndex];
            applyDefaultFlagsByRole(selected ? (selected.dataset.role || '') : '');
        }
        selectEl.addEventListener('change', () => {
            applySelectionMeta(selectEl);
            if (selectEl.id === 'addUserId') {
                const selected = selectEl.options[selectEl.selectedIndex];
                applyDefaultFlagsByRole(selected ? (selected.dataset.role || '') : '');
            }
        });
    });

    markDeleteButtons.forEach((btn) => {
        const recipientId = btn.dataset.recipientId || '';
        btn.addEventListener('click', () => {
            const deleteFlag = document.getElementById(`delete-flag-${recipientId}`);
            if (!deleteFlag) return;
            const currentlyMarked = deleteFlag.value === '1';
            setRowDeleteState(recipientId, !currentlyMarked);
        });
    });

    if (toggleRecipientsEditBtn && listBox) {
        const initialEditMode = listBox.dataset.editMode === '1';
        applyRecipientsEditMode(initialEditMode);
        toggleRecipientsEditBtn.addEventListener('click', () => {
            const current = listBox.dataset.editMode === '1';
            if (!current) {
                applyRecipientsEditMode(true);
                return;
            }

            if (!bulkUpdateForm) {
                applyRecipientsEditMode(false);
                return;
            }

            toggleRecipientsEditBtn.disabled = true;
            toggleRecipientsEditBtn.textContent = 'Menyimpan...';
            bulkUpdateForm.submit();
        });
    }
})();
</script>

@endsection
