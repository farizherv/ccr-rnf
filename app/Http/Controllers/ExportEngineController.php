<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use App\Support\CcrHeavyJobBroker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Process\Process;
use Throwable;

class ExportEngineController extends Controller
{
    private const EXPORT_TIMEOUT_SECONDS = 240;
    private const PREVIEW_CONVERT_TIMEOUT_SECONDS = 120;
    private const PREVIEW_MAX_DOCX_BYTES = 62914560; // 60 MB
    private const PREVIEW_MAX_PDF_BYTES = 94371840;  // 90 MB
    private const PREVIEW_FAIL_COOLDOWN_SECONDS = 30;
    private const PREVIEW_TMP_SWEEP_SECONDS = 21600; // 6 hours

    private function safeFolder($text): string
    {
        $text = trim((string) $text);
        $text = preg_replace('/[^\pL\pN\s\-_\.]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text !== '' ? $text : 'UNKNOWN';
    }

    private function findEngineReportOrFail($id): CcrReport
    {
        return CcrReport::query()
            ->where('type', 'engine')
            ->findOrFail($id);
    }

    private function envInt(string $key, int $default, int $min = 1, ?int $max = null): int
    {
        $raw = env($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (!is_numeric($raw)) {
            return $default;
        }

        $value = (int) $raw;
        if ($value < $min) {
            $value = $min;
        }

        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }

    private function previewConvertTimeoutSeconds(): int
    {
        return $this->envInt('ENGINE_PREVIEW_TIMEOUT_SECONDS', self::PREVIEW_CONVERT_TIMEOUT_SECONDS, 20, 600);
    }

    private function previewMaxDocxBytes(): int
    {
        return $this->envInt('ENGINE_PREVIEW_MAX_DOCX_BYTES', self::PREVIEW_MAX_DOCX_BYTES, 1048576);
    }

    private function previewMaxPdfBytes(): int
    {
        return $this->envInt('ENGINE_PREVIEW_MAX_PDF_BYTES', self::PREVIEW_MAX_PDF_BYTES, 1048576);
    }

    private function previewFailCooldownSeconds(): int
    {
        return $this->envInt('ENGINE_PREVIEW_FAIL_COOLDOWN_SECONDS', self::PREVIEW_FAIL_COOLDOWN_SECONDS, 5, 300);
    }

    private function previewTmpSweepSeconds(): int
    {
        return $this->envInt('ENGINE_PREVIEW_TMP_SWEEP_SECONDS', self::PREVIEW_TMP_SWEEP_SECONDS, 600, 604800);
    }

    private function normalizeGroupFolder($group): string
    {
        $group = $this->safeFolder($group);
        if (strtolower($group) === 'operator seat') return 'Seat Operator';
        return $group;
    }

    private function publicDiskAbsPath($maybePath): ?string
    {
        $raw = ltrim((string) $maybePath, '/');

        // normalisasi prefix yang sering kejadian
        $raw = preg_replace('#^(storage/|public/)#', '', $raw);
        $raw = preg_replace('#^storage/public/#', '', $raw);

        // kalau path relatif disk public
        if ($raw !== '' && Storage::disk('public')->exists($raw)) {
            return Storage::disk('public')->path($raw);
        }

        // kalau ternyata DB nyimpan absolute path
        if (is_file($maybePath)) {
            return $maybePath;
        }

        return null;
    }

    private function expectedRelativePath(CcrReport $report): string
    {
        $groupFolder = $this->normalizeGroupFolder($report->group_folder);
        $customer    = $this->safeFolder($report->customer);
        $component   = $this->safeFolder($report->component);
        $model       = $this->safeFolder($report->model);
        $reportId    = $report->id;

        $fileName = "CCR_ENGINE.docx";

        return "ccr_files/{$groupFolder}/{$customer}/{$component}/{$model}/{$reportId}/Export/{$fileName}";
    }

    private function shouldRegenerate(CcrReport $report): bool
    {
        $expected = $this->expectedRelativePath($report);

        // path kosong / beda folder karena field berubah
        if (!$report->docx_path || $report->docx_path !== $expected) return true;

        // file hilang
        if (!Storage::disk('public')->exists($report->docx_path)) return true;

        // belum pernah generate
        if (!$report->docx_generated_at) return true;

        // data berubah setelah terakhir generate
        if ($report->updated_at && $report->updated_at->gt($report->docx_generated_at)) return true;

        return false;
    }

    private function jobBroker(): CcrHeavyJobBroker
    {
        return app(CcrHeavyJobBroker::class);
    }

    private function previewReadyPath(CcrReport $report): ?string
    {
        if ($this->shouldRegenerate($report)) {
            return null;
        }

        if (!$report->docx_path || !Storage::disk('public')->exists($report->docx_path)) {
            return null;
        }

        $docxAbs = Storage::disk('public')->path($report->docx_path);
        $pdfRelative = $this->previewPdfRelativePath($report);
        $pdfAbs = Storage::disk('public')->path($pdfRelative);

        return $this->isPreviewPdfFresh($pdfAbs, $docxAbs) ? $pdfAbs : null;
    }

    private function queuePreviewBuild(int $reportId): void
    {
        $this->jobBroker()->enqueue('preview', 'engine', $reportId);
    }

    private function queueWordBuild(int $reportId): void
    {
        $this->jobBroker()->enqueue('word', 'engine', $reportId);
    }

    private function previewInlineFallbackEnabled(): bool
    {
        $raw = env('CCR_HEAVY_PREVIEW_INLINE_FALLBACK');
        if ($raw === null || $raw === '') {
            return true;
        }

        $enabled = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return true;
        }

        // Lokal tetap boleh fallback inline supaya preview tidak mentok saat worker belum jalan.
        return app()->environment('local');
    }

    private function tryInlinePreviewFallback(CcrReport $report): ?string
    {
        if (!$this->previewInlineFallbackEnabled()) {
            return null;
        }

        try {
            $pdfAbs = $this->ensurePreviewPdf($report);
            $this->jobBroker()->markSuccess('preview', 'engine', (int) $report->id);
            return $pdfAbs;
        } catch (Throwable $e) {
            Log::warning('ENGINE preview fallback inline gagal', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            $this->jobBroker()->markFailure('preview', 'engine', (int) $report->id, $e->getMessage());
            return $this->existingPreviewPdfPath($report);
        }
    }

    private function existingPreviewPdfPath(CcrReport $report): ?string
    {
        if (!$report->docx_path) {
            return null;
        }

        $pdfRelative = $this->previewPdfRelativePath($report);
        if (!Storage::disk('public')->exists($pdfRelative)) {
            return null;
        }

        $pdfAbs = Storage::disk('public')->path($pdfRelative);
        if (!is_file($pdfAbs)) {
            return null;
        }

        if ((@filesize($pdfAbs) ?: 0) <= 0) {
            return null;
        }

        return $pdfAbs;
    }

    private function photoScaleByCount(int $photoCount): float
    {
        if ($photoCount <= 1) {
            return 1.00;
        }

        if ($photoCount === 2) {
            return 0.95;
        }

        if ($photoCount === 3) {
            return 0.90;
        }

        if ($photoCount === 4) {
            return 0.84;
        }

        if ($photoCount === 5) {
            return 0.80;
        }

        // Lebih dari 5 foto: kecilkan perlahan, dengan batas bawah yang tetap nyaman dibaca.
        return max(0.76, 0.80 - (($photoCount - 5) * 0.02));
    }

    private function fitPhotoDimensions(int $width, int $height, int $photoCount): array
    {
        $width = max(1, $width);
        $height = max(1, $height);
        $ratio = $width / $height;

        $baseMaxWidth = 350;
        $baseMaxHeight = 455;
        $scale = $this->photoScaleByCount($photoCount);

        $maxWidth = (int) round($baseMaxWidth * $scale);
        $maxHeight = (int) round($baseMaxHeight * $scale);

        // Foto portrait cenderung makan tinggi halaman, jadi diperkecil lagi.
        if ($ratio < 0.95) {
            $maxHeight = (int) round($maxHeight * 0.82);
        }

        $fitWidth = $maxWidth;
        $fitHeight = (int) round($fitWidth / $ratio);

        if ($fitHeight > $maxHeight) {
            $fitHeight = $maxHeight;
            $fitWidth = (int) round($fitHeight * $ratio);
        }

        // Jaga agar tidak terlalu kecil.
        $minWidth = $photoCount >= 4 ? 120 : 140;
        $fitWidth = max($minWidth, $fitWidth);
        $fitHeight = max(120, $fitHeight);

        // Re-clamp sesudah minimum guard.
        if ($fitWidth > $maxWidth) {
            $fitWidth = $maxWidth;
            $fitHeight = (int) round($fitWidth / $ratio);
        }

        if ($fitHeight > $maxHeight) {
            $fitHeight = $maxHeight;
            $fitWidth = (int) round($fitHeight * $ratio);
        }

        return [
            'width' => max(1, $fitWidth),
            'height' => max(1, $fitHeight),
        ];
    }

    private function ensureFreshDocx(CcrReport $report): string
    {
        $reportId = (int) $report->id;

        return $this->withReportLock($reportId, function () use ($reportId) {
            $fresh = $this->findEngineReportOrFail($reportId);

            if ($this->shouldRegenerate($fresh)) {
                $abs = $this->generateEngine($reportId);
                $fresh->refresh();
                return $abs;
            }

            return Storage::disk('public')->path($fresh->docx_path);
        });
    }

    private function previewPdfRelativePath(CcrReport $report): string
    {
        $docxRelative = $report->docx_path ?: $this->expectedRelativePath($report);
        return preg_replace('/\.docx$/i', '.preview.pdf', $docxRelative);
    }

    private function sofficeBinary(): string
    {
        $preferred = (string) env('SOFFICE_BINARY', '/opt/homebrew/bin/soffice');
        if (is_executable($preferred)) {
            return $preferred;
        }

        $fallback = trim((string) @shell_exec('command -v soffice'));
        if ($fallback !== '' && is_executable($fallback)) {
            return $fallback;
        }

        throw new \RuntimeException('LibreOffice (soffice) tidak ditemukan di server.');
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanupDir($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function isPreviewPdfFresh(string $pdfAbs, string $docxAbs): bool
    {
        if (!is_file($pdfAbs) || !is_file($docxAbs)) {
            return false;
        }

        $pdfMtime = @filemtime($pdfAbs);
        $docxMtime = @filemtime($docxAbs);
        if ($pdfMtime === false || $docxMtime === false) {
            return false;
        }

        return $pdfMtime >= $docxMtime;
    }

    private function assertFileSizeWithinLimit(string $path, int $maxBytes, string $message): void
    {
        $size = @filesize($path);
        if ($size === false) {
            return;
        }

        if ($size > $maxBytes) {
            throw new \RuntimeException($message);
        }
    }

    private function maybeSweepOldPreviewTempDirs(): void
    {
        // Jalankan sampling sweep supaya tidak menambah overhead di setiap request preview.
        try {
            $shouldSweep = random_int(1, 20) === 1;
        } catch (Throwable $e) {
            $shouldSweep = false;
        }

        if (!$shouldSweep) {
            return;
        }

        $baseDir = storage_path('app/tmp');
        if (!is_dir($baseDir)) {
            return;
        }

        $cutoff = time() - $this->previewTmpSweepSeconds();
        foreach (glob($baseDir . '/ccr-preview-engine-*', GLOB_NOSORT) ?: [] as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $mtime = @filemtime($path);
            if ($mtime !== false && $mtime > $cutoff) {
                continue;
            }

            $this->cleanupDir($path);
        }
    }

    private function previewFailureMarkerPath(int $reportId): string
    {
        return storage_path('app/tmp/export-locks/engine-preview-' . $reportId . '.failed.json');
    }

    private function readPreviewFailureState(int $reportId): ?array
    {
        $path = $this->previewFailureMarkerPath($reportId);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function rememberPreviewFailure(int $reportId, Throwable $e): void
    {
        $path = $this->previewFailureMarkerPath($reportId);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = json_encode([
            'failed_at' => time(),
            'message' => Str::limit($e->getMessage(), 400),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (is_string($payload)) {
            @file_put_contents($path, $payload, LOCK_EX);
        }
    }

    private function clearPreviewFailure(int $reportId): void
    {
        $path = $this->previewFailureMarkerPath($reportId);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function isInPreviewFailureCooldown(int $reportId): bool
    {
        $state = $this->readPreviewFailureState($reportId);
        $failedAt = (int) ($state['failed_at'] ?? 0);
        if ($failedAt <= 0) {
            return false;
        }

        return (time() - $failedAt) < $this->previewFailCooldownSeconds();
    }

    private function ensurePreviewPdf(CcrReport $report): string
    {
        $reportId = (int) $report->id;

        return $this->withPreviewLock($reportId, function () use ($reportId) {
            $fresh = $this->findEngineReportOrFail($reportId);
            $docxAbs = $this->ensureFreshDocx($fresh);
            if (!is_file($docxAbs)) {
                throw new \RuntimeException('File DOCX tidak ditemukan saat membangun preview.');
            }

            $this->assertFileSizeWithinLimit(
                $docxAbs,
                $this->previewMaxDocxBytes(),
                'Ukuran DOCX terlalu besar untuk dipreview. Silakan unduh file Word.'
            );

            $pdfRelative = $this->previewPdfRelativePath($fresh);
            Storage::disk('public')->makeDirectory(dirname($pdfRelative));
            $pdfAbs = Storage::disk('public')->path($pdfRelative);

            if ($this->isPreviewPdfFresh($pdfAbs, $docxAbs)) {
                return $pdfAbs;
            }

            if ($this->isInPreviewFailureCooldown($reportId)) {
                if (is_file($pdfAbs) && (@filesize($pdfAbs) ?: 0) > 0) {
                    return $pdfAbs;
                }

                throw new \RuntimeException('Preview sedang recovery setelah kegagalan terakhir. Silakan coba beberapa detik lagi.');
            }

            $this->maybeSweepOldPreviewTempDirs();

            $tmpDir = storage_path('app/tmp/ccr-preview-engine-' . $reportId . '-' . Str::random(8));
            if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
                throw new \RuntimeException('Gagal membuat folder sementara preview.');
            }

            try {
                $process = new Process([
                    $this->sofficeBinary(),
                    '--headless',
                    '--convert-to',
                    'pdf:writer_pdf_Export',
                    '--outdir',
                    $tmpDir,
                    $docxAbs,
                ]);
                $process->setTimeout($this->previewConvertTimeoutSeconds());
                if (method_exists($process, 'setIdleTimeout')) {
                    $process->setIdleTimeout(min($this->previewConvertTimeoutSeconds(), 60));
                }
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new \RuntimeException('Konversi DOCX ke PDF gagal: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
                }

                $tmpPdf = $tmpDir . DIRECTORY_SEPARATOR . pathinfo($docxAbs, PATHINFO_FILENAME) . '.pdf';
                if (!is_file($tmpPdf)) {
                    throw new \RuntimeException('PDF hasil konversi tidak ditemukan.');
                }

                $this->assertFileSizeWithinLimit(
                    $tmpPdf,
                    $this->previewMaxPdfBytes(),
                    'Ukuran PDF preview terlalu besar untuk ditampilkan. Silakan unduh file Word.'
                );

                $tmpTarget = $pdfAbs . '.tmp-' . Str::random(8);
                if (!@copy($tmpPdf, $tmpTarget)) {
                    throw new \RuntimeException('Gagal menulis file sementara PDF preview.');
                }

                if (is_file($pdfAbs)) {
                    @unlink($pdfAbs);
                }

                if (!@rename($tmpTarget, $pdfAbs)) {
                    if (!@copy($tmpTarget, $pdfAbs)) {
                        @unlink($tmpTarget);
                        throw new \RuntimeException('Gagal menyimpan PDF preview.');
                    }
                    @unlink($tmpTarget);
                }

                $this->clearPreviewFailure($reportId);
            } catch (Throwable $e) {
                $this->rememberPreviewFailure($reportId, $e);

                if (is_file($pdfAbs) && (@filesize($pdfAbs) ?: 0) > 0) {
                    Log::warning('ENGINE preview memakai PDF lama karena regenerate gagal', [
                        'report_id' => $reportId,
                        'error' => $e->getMessage(),
                    ]);

                    return $pdfAbs;
                }

                throw $e;
            } finally {
                $this->cleanupDir($tmpDir);
            }

            return $pdfAbs;
        });
    }

    public function warmWordExport(int $id): string
    {
        $report = $this->findEngineReportOrFail($id);
        return $this->ensureFreshDocx($report);
    }

    public function warmPreviewPdf(int $id): string
    {
        $report = $this->findEngineReportOrFail($id);
        return $this->ensurePreviewPdf($report);
    }

    /**
     * ==========================================
     * 🔥 GENERATE WORD ENGINE
     * ==========================================
     */
    public function generateEngine($id): string
    {
        $this->tuneRuntimeForLargeExport();
        $report = $this->findEngineReportOrFail($id)->load('items.photos');

        $template = new TemplateProcessor(
            public_path('templates/TEMPLATE CCR ENGINE.docx')
        );

        // HEADER
        $template->setValue('COMPONENT', (string) $report->component);
        $template->setValue('MAKE', (string) $report->make);
        $template->setValue('MODEL', (string) $report->model);
        $template->setValue('SN', (string) $report->sn);
        $template->setValue('SMU', (string) $report->smu);
        $template->setValue('CUSTOMER', (string) $report->customer);
        $template->setValue(
            'INSPECTION_DATE',
            optional($report->inspection_date)->format('Y-m-d')
        );

        $itemsData = [];

        foreach ($report->items as $item) {
            $photoRows = [];
            $validPhotos = [];

            foreach ($item->photos as $photo) {
                $path = $this->publicDiskAbsPath($photo->path);
                if (!$path) {
                    continue;
                }

                try {
                    [$w, $h] = getimagesize($path);
                } catch (\Throwable $e) {
                    continue;
                }

                if ((int) $h <= 0 || (int) $w <= 0) {
                    continue;
                }

                $validPhotos[] = [
                    'path' => $path,
                    'width' => (int) $w,
                    'height' => (int) $h,
                ];
            }

            $photoCount = count($validPhotos);

            foreach ($validPhotos as $photoData) {
                $fit = $this->fitPhotoDimensions(
                    $photoData['width'],
                    $photoData['height'],
                    $photoCount
                );

                $photoRows[] = [
                    'photo' => [
                        'path'   => $photoData['path'],
                        'width'  => $fit['width'],
                        'height' => $fit['height'],
                        'ratio'  => true,
                    ]
                ];
            }

            if (empty($photoRows)) {
                $photoRows[] = ['photo' => null];
            }

            $itemsData[] = [
                'description' => $item->description ?: '-',
                'photos'      => $photoRows
            ];
        }

        // CLONE ITEM TABLE
        $template->cloneBlock('ITEM_TABLE', max(count($itemsData), 1), true, true);

        foreach ($itemsData as $i => $itemData) {
            $n = $i + 1;

            $template->setValue("description#{$n}", $itemData['description']);

            $template->cloneBlock(
                "PHOTO_BLOCK#{$n}",
                count($itemData['photos']),
                true,
                true
            );

            $blank = public_path('blank.png');

            foreach ($itemData['photos'] as $k => $photo) {
                $m = $k + 1;

                if ($photo['photo'] === null) {
                    $template->setImageValue("photo#{$n}#{$m}", [
                        'path'   => $blank,
                        'width'  => 1,
                        'height' => 1,
                        'ratio'  => true,
                    ]);
                    $template->setValue("photo_text#{$n}#{$m}", "NO PHOTO");
                    continue;
                }

                $template->setImageValue("photo#{$n}#{$m}", [
                    'path'   => $photo['photo']['path'],
                    'width'  => $photo['photo']['width'],
                    'height' => $photo['photo']['height'],
                    'ratio'  => true,
                ]);
                $template->setValue("photo_text#{$n}#{$m}", "");
            }
        }

        // SAVE WORD FILE (atomic)
        $relativePath = $this->expectedRelativePath($report);
        Storage::disk('public')->makeDirectory(dirname($relativePath));

        $savePath = storage_path('app/public/' . $relativePath);
        $tmpSavePath = $savePath . '.tmp-' . Str::random(8);

        try {
            $template->saveAs($tmpSavePath);

            if (is_file($savePath)) {
                @unlink($savePath);
            }

            if (!@rename($tmpSavePath, $savePath)) {
                if (!@copy($tmpSavePath, $savePath)) {
                    throw new \RuntimeException('Gagal menyimpan file DOCX export.');
                }
                @unlink($tmpSavePath);
            }
        } catch (Throwable $e) {
            @unlink($tmpSavePath);
            throw $e;
        }

        $report->docx_path = $relativePath;
        $report->docx_generated_at = now();
        $report->save();

        return $savePath;
    }

    /**
     * ✅ PREVIEW ENGINE (alias biar route aman)
     */
    public function preview($id)
    {
        $report = $this->findEngineReportOrFail($id);

        $previewError = null;
        if ($this->jobBroker()->queueEnabled()) {
            if ($this->previewReadyPath($report) === null) {
                if ($this->tryInlinePreviewFallback($report) === null) {
                    $this->queuePreviewBuild((int) $report->id);
                    $retryAfter = $this->jobBroker()->retryAfterSeconds('preview', 'engine', (int) $report->id);
                    $previewError = 'Preview sedang diproses di antrian. Coba lagi dalam ' . $retryAfter . ' detik.';
                }
            }
        } else {
            try {
                $this->ensurePreviewPdf($report);
            } catch (\Throwable $e) {
                Log::warning('ENGINE preview PDF gagal dibangun', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
                $previewError = 'Preview Word belum bisa ditampilkan saat ini. Silakan unduh file Word.';
            }
        }

        return view('ccr.word_preview', [
            'report' => $report,
            'title' => 'PREVIEW CCR - ENGINE',
            'subtitle' => 'Preview ini berasal dari file Word yang sama dengan file download.',
            'backUrl' => route('ccr.manage.engine'),
            'pdfPreviewUrl' => route('engine.preview.pdf', $report->id),
            'downloadWordUrl' => route('engine.export.word', $report->id),
            'previewError' => $previewError,
        ]);
    }

    // kalau ada route lama yang manggil previewPdf, tetap aman
    public function previewPdf($id)
    {
        return $this->preview($id);
    }

    public function previewPdfFile($id)
    {
        $report = $this->findEngineReportOrFail($id);

        if ($this->jobBroker()->queueEnabled()) {
            $pdfAbs = $this->previewReadyPath($report);
            if ($pdfAbs === null) {
                $pdfAbs = $this->tryInlinePreviewFallback($report);
                if ($pdfAbs === null) {
                    $this->queuePreviewBuild((int) $report->id);
                    return response('Preview PDF sedang diproses di antrian. Silakan coba lagi sebentar.', 503, [
                        'Content-Type' => 'text/plain; charset=UTF-8',
                        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                        'Pragma' => 'no-cache',
                        'Expires' => '0',
                        'Retry-After' => (string) $this->jobBroker()->retryAfterSeconds('preview', 'engine', (int) $report->id),
                    ]);
                }
            }
        } else {
            try {
                $pdfAbs = $this->ensurePreviewPdf($report);
            } catch (Throwable $e) {
                Log::warning('ENGINE preview file gagal diakses', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);

                return response('Preview PDF belum tersedia. Silakan download file Word.', 503, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                    'Retry-After' => (string) $this->previewFailCooldownSeconds(),
                ]);
            }
        }

        if (!is_file($pdfAbs)) {
            abort(404, 'File preview PDF tidak ditemukan.');
        }

        return response()->file($pdfAbs, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * ✅ DOWNLOAD WORD ENGINE (AUTO-REGENERATE kalau data berubah)
     */
    public function downloadEngine($id)
    {
        $report = $this->findEngineReportOrFail($id);
        if ($this->jobBroker()->queueEnabled()) {
            if ($this->shouldRegenerate($report) || !$report->docx_path || !Storage::disk('public')->exists($report->docx_path)) {
                $this->queueWordBuild((int) $report->id);
                try {
                    $abs = $this->warmWordExport((int) $report->id);
                    $this->jobBroker()->markSuccess('word', 'engine', (int) $report->id);
                } catch (Throwable $e) {
                    Log::warning('ENGINE word download fallback inline gagal', [
                        'report_id' => (int) $report->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->jobBroker()->markFailure('word', 'engine', (int) $report->id, $e->getMessage());

                    $report->refresh();
                    if (!$report->docx_path || !Storage::disk('public')->exists($report->docx_path)) {
                        return response('File Word sedang diproses di antrian. Silakan coba lagi sebentar.', 503, [
                            'Content-Type' => 'text/plain; charset=UTF-8',
                            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                            'Pragma' => 'no-cache',
                            'Expires' => '0',
                            'Retry-After' => (string) $this->jobBroker()->retryAfterSeconds('word', 'engine', (int) $report->id),
                        ]);
                    }

                    $abs = Storage::disk('public')->path($report->docx_path);
                }
            } else {
                $abs = Storage::disk('public')->path($report->docx_path);
            }
        } else {
            $abs = $this->ensureFreshDocx($report);
        }

        if (!is_file($abs)) {
            abort(404, 'File Word tidak ditemukan.');
        }

        $componentName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim((string) $report->component));
        $downloadName = 'CCR_ENGINE' . ($componentName !== '' ? '-' . $componentName : '') . '.docx';

        return response()->download(
            $abs,
            $downloadName,
            [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    // alias biar kalau route beda nama tetap gak 500
    public function download($id)
    {
        return $this->downloadEngine($id);
    }

    private function tuneRuntimeForLargeExport(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::EXPORT_TIMEOUT_SECONDS);
        }
        @ini_set('max_execution_time', (string) self::EXPORT_TIMEOUT_SECONDS);

        $memory = ini_get('memory_limit');
        if (is_string($memory) && $memory !== '' && $memory !== '-1') {
            @ini_set('memory_limit', '1024M');
        }
    }

    private function withReportLock(int $reportId, callable $callback)
    {
        return $this->withNamedLock('engine-word-' . $reportId, $callback);
    }

    private function withPreviewLock(int $reportId, callable $callback)
    {
        return $this->withNamedLock('engine-preview-' . $reportId, $callback);
    }

    private function withNamedLock(string $name, callable $callback)
    {
        $lockDir = storage_path('app/tmp/export-locks');
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $lockName = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $name) ?: 'default';
        $lockPath = $lockDir . '/' . $lockName . '.lock';
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            return $callback();
        }

        try {
            if (!@flock($handle, LOCK_EX)) {
                return $callback();
            }

            return $callback();
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
}
