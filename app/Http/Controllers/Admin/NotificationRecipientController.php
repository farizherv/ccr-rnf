<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationRecipientController extends Controller
{
    private const MAX_RECIPIENTS = 100;

    public function index()
    {
        $recipients = NotificationRecipient::query()
            ->orderByDesc('is_active')
            ->orderBy('email')
            ->get();

        $users = User::query()
            ->select(['id', 'name', 'username', 'email', 'role'])
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        $takenEmails = $recipients
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '')
            ->values()
            ->all();

        $availableUsers = $users
            ->filter(fn (User $user) => !in_array(strtolower(trim((string) $user->email)), $takenEmails, true))
            ->values();

        return view('admin.notifications.index', [
            'recipients' => $recipients,
            'maxRecipients' => self::MAX_RECIPIENTS,
            'users' => $users,
            'availableUsers' => $availableUsers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'notify_waiting' => ['nullable', 'boolean'],
            'notify_approved' => ['nullable', 'boolean'],
            'notify_rejected' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $selectedUser = User::query()->findOrFail((int) $data['user_id']);
        $targetEmail = strtolower(trim((string) $selectedUser->email));
        if ($targetEmail === '') {
            return back()->withInput()->with('error', 'Email akun user belum valid.');
        }

        $currentCount = NotificationRecipient::query()->count();
        $alreadyExists = NotificationRecipient::query()
            ->whereRaw('LOWER(email) = ?', [$targetEmail])
            ->exists();

        if (!$alreadyExists && $currentCount >= self::MAX_RECIPIENTS) {
            return back()->withInput()->with('error', 'Batas recipient email tercapai (' . self::MAX_RECIPIENTS . ').');
        }

        if ($alreadyExists) {
            return back()->withInput()->with('error', 'Akun user ini sudah terdaftar sebagai recipient.');
        }

        [$waiting, $approved, $rejected] = $this->normalizeStatusFlags($request);
        if (!$waiting && !$approved && !$rejected) {
            return back()->withInput()->with('error', 'Pilih minimal satu status notifikasi.');
        }

        NotificationRecipient::query()->create([
            'email' => $targetEmail,
            'name' => $this->cleanText((string) ($selectedUser->name ?? ''), 120) ?: null,
            'notify_waiting' => $waiting,
            'notify_approved' => $approved,
            'notify_rejected' => $rejected,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => (int) auth()->id(),
            'updated_by' => (int) auth()->id(),
        ]);

        return back()->with('success', 'Recipient email notifikasi berhasil ditambahkan.');
    }

    public function update(Request $request, NotificationRecipient $recipient)
    {
        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'notify_waiting' => ['nullable', 'boolean'],
            'notify_approved' => ['nullable', 'boolean'],
            'notify_rejected' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $selectedUser = User::query()->findOrFail((int) $data['user_id']);
        $targetEmail = strtolower(trim((string) $selectedUser->email));
        if ($targetEmail === '') {
            return back()->with('error', 'Email akun user belum valid.');
        }

        $usedByOther = NotificationRecipient::query()
            ->whereRaw('LOWER(email) = ?', [$targetEmail])
            ->where('id', '!=', (int) $recipient->id)
            ->exists();

        if ($usedByOther) {
            return back()->with('error', 'Akun user tersebut sudah dipakai recipient lain.');
        }

        [$waiting, $approved, $rejected] = $this->normalizeStatusFlags($request);
        if (!$waiting && !$approved && !$rejected) {
            return back()->with('error', 'Pilih minimal satu status notifikasi.');
        }

        $recipient->fill([
            'email' => $targetEmail,
            'name' => $this->cleanText((string) ($selectedUser->name ?? ''), 120) ?: null,
            'notify_waiting' => $waiting,
            'notify_approved' => $approved,
            'notify_rejected' => $rejected,
            'is_active' => $request->boolean('is_active', false),
            'updated_by' => (int) auth()->id(),
        ]);
        $recipient->save();

        return back()->with('success', 'Recipient email notifikasi berhasil diupdate.');
    }

    public function bulkUpdate(Request $request)
    {
        $rawRows = $request->input('recipients', []);
        if (!is_array($rawRows) || empty($rawRows)) {
            return back()->with('error', 'Tidak ada data recipient untuk disimpan.');
        }

        $recipientIds = collect(array_keys($rawRows))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        if ($recipientIds->isEmpty()) {
            return back()->with('error', 'Format data recipient tidak valid.');
        }

        $recipients = NotificationRecipient::query()
            ->whereIn('id', $recipientIds->all())
            ->get()
            ->keyBy('id');

        if ($recipients->count() !== $recipientIds->count()) {
            return back()->with('error', 'Sebagian recipient tidak ditemukan. Muat ulang halaman lalu coba lagi.');
        }

        $userIds = collect($rawRows)
            ->map(fn ($row) => (int) (($row['user_id'] ?? 0)))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $usersById = User::query()
            ->whereIn('id', $userIds->all())
            ->get()
            ->keyBy('id');

        $prepared = [];
        $deleteRecipientIds = [];
        $targetEmails = [];

        foreach ($recipientIds as $recipientId) {
            $row = (array) ($rawRows[$recipientId] ?? []);
            $shouldDelete = $this->toBool($row['_delete'] ?? false);
            if ($shouldDelete) {
                $deleteRecipientIds[] = $recipientId;
                continue;
            }

            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                return back()->with('error', 'Pilih akun user pada semua recipient sebelum menyimpan.');
            }

            /** @var User|null $selectedUser */
            $selectedUser = $usersById->get($userId);
            if (!$selectedUser) {
                return back()->with('error', 'Akun user pada recipient tidak valid. Muat ulang halaman lalu coba lagi.');
            }

            $targetEmail = strtolower(trim((string) $selectedUser->email));
            if ($targetEmail === '' || filter_var($targetEmail, FILTER_VALIDATE_EMAIL) === false) {
                return back()->with('error', 'Ada akun user dengan email tidak valid.');
            }

            $waiting = $this->toBool($row['notify_waiting'] ?? false);
            $approved = $this->toBool($row['notify_approved'] ?? false);
            $rejected = $this->toBool($row['notify_rejected'] ?? false);

            if (!$waiting && !$approved && !$rejected) {
                return back()->with('error', 'Setiap recipient wajib pilih minimal satu status notifikasi.');
            }

            $prepared[$recipientId] = [
                'email' => $targetEmail,
                'name' => $this->cleanText((string) ($selectedUser->name ?? ''), 120) ?: null,
                'notify_waiting' => $waiting,
                'notify_approved' => $approved,
                'notify_rejected' => $rejected,
                'is_active' => $this->toBool($row['is_active'] ?? false),
            ];
            $targetEmails[] = $targetEmail;
        }

        if (!empty($targetEmails)) {
            $emailUsage = array_count_values($targetEmails);
            foreach ($emailUsage as $email => $count) {
                if ($count > 1) {
                    return back()->with('error', 'Email user duplikat terdeteksi di form: ' . $email);
                }
            }

            $conflictExists = NotificationRecipient::query()
                ->whereIn('email', array_keys($emailUsage))
                ->whereNotIn('id', $recipientIds->all())
                ->exists();

            if ($conflictExists) {
                return back()->with('error', 'Sebagian email sudah dipakai recipient lain. Muat ulang halaman dan coba lagi.');
            }
        }

        DB::transaction(function () use ($prepared, $recipients, $deleteRecipientIds): void {
            if (!empty($deleteRecipientIds)) {
                NotificationRecipient::query()
                    ->whereIn('id', $deleteRecipientIds)
                    ->delete();
            }

            foreach ($prepared as $recipientId => $data) {
                /** @var NotificationRecipient $recipient */
                $recipient = $recipients->get($recipientId);
                if (!$recipient) {
                    continue;
                }

                $recipient->fill([
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'notify_waiting' => $data['notify_waiting'],
                    'notify_approved' => $data['notify_approved'],
                    'notify_rejected' => $data['notify_rejected'],
                    'is_active' => $data['is_active'],
                    'updated_by' => (int) auth()->id(),
                ]);
                $recipient->save();
            }
        });

        return back()->with('success', 'Semua perubahan recipient berhasil disimpan.');
    }

    public function destroy(NotificationRecipient $recipient)
    {
        $recipient->delete();

        return back()->with('success', 'Recipient email notifikasi berhasil dihapus.');
    }

    /**
     * @return array{bool,bool,bool}
     */
    private function normalizeStatusFlags(Request $request): array
    {
        return [
            $request->boolean('notify_waiting'),
            $request->boolean('notify_approved'),
            $request->boolean('notify_rejected'),
        ];
    }

    private function cleanText(string $value, int $max): string
    {
        $text = strip_tags($value);
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max));
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        $text = strtolower(trim((string) $value));
        return in_array($text, ['1', 'true', 'on', 'yes'], true);
    }
}
