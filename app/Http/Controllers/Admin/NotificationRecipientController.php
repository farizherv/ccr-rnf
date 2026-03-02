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
            ->with('user:id,name,role')
            ->orderByDesc('is_active')
            ->orderBy('email')
            ->get();

        $takenUserIds = $recipients
            ->pluck('user_id')
            ->filter(fn ($id) => $id !== null && $id > 0)
            ->values()
            ->all();

        $availableUsers = User::query()
            ->select(['id', 'name', 'username', 'email', 'role'])
            ->whereNotIn('id', $takenUserIds)
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return view('admin.notifications.index', [
            'recipients' => $recipients,
            'maxRecipients' => self::MAX_RECIPIENTS,
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
                Rule::unique('notification_recipients', 'user_id'),
            ],
            'email'    => ['required', 'email', 'max:191'],
            'notify_waiting' => ['nullable', 'boolean'],
            'notify_approved' => ['nullable', 'boolean'],
            'notify_rejected' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $selectedUser = User::query()->findOrFail((int) $data['user_id']);
        $targetEmail = strtolower(trim($data['email']));
        if ($targetEmail === '') {
            return back()->withInput()->with('error', 'Email tidak boleh kosong.');
        }

        $currentCount = NotificationRecipient::query()->count();
        if ($currentCount >= self::MAX_RECIPIENTS) {
            return back()->withInput()->with('error', 'Batas recipient email tercapai (' . self::MAX_RECIPIENTS . ').');
        }

        $emailExists = NotificationRecipient::query()
            ->whereRaw('LOWER(email) = ?', [$targetEmail])
            ->exists();

        if ($emailExists) {
            return back()->withInput()->with('error', 'Email ini sudah terdaftar sebagai recipient.');
        }

        [$waiting, $approved, $rejected] = $this->normalizeStatusFlags($request);
        if (!$waiting && !$approved && !$rejected) {
            return back()->withInput()->with('error', 'Pilih minimal satu status notifikasi.');
        }

        NotificationRecipient::query()->create([
            'user_id' => (int) $selectedUser->id,
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

            $targetEmail = strtolower(trim((string) ($row['email'] ?? '')));
            if ($targetEmail === '' || filter_var($targetEmail, FILTER_VALIDATE_EMAIL) === false) {
                return back()->with('error', 'Email tidak valid pada salah satu recipient.');
            }

            $waiting = $this->toBool($row['notify_waiting'] ?? false);
            $approved = $this->toBool($row['notify_approved'] ?? false);
            $rejected = $this->toBool($row['notify_rejected'] ?? false);

            if (!$waiting && !$approved && !$rejected) {
                return back()->with('error', 'Setiap recipient wajib pilih minimal satu status notifikasi.');
            }

            $prepared[$recipientId] = [
                'email' => $targetEmail,
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
                    return back()->with('error', 'Email duplikat terdeteksi di form: ' . $email);
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
