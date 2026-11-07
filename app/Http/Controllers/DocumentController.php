<?php

namespace App\Http\Controllers;

use App\Services\InsuranceStore;
use App\Support\DocumentTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function show(Request $request, string $filename): Response
    {
        // Strict name check: no path separators or traversal can reach the disk.
        abort_unless(preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $filename) && ! str_contains($filename, '..'), 400, 'ชื่อไฟล์ไม่ถูกต้อง');

        $path = InsuranceStore::DOCUMENT_DIR."/{$filename}";
        $user = $request->user();
        // Viewers only see published documents. Answer 404, not 403, so a draft's existence isn't revealed.
        $canSeeUnpublished = $user->can('canEditDraft') || $user->can('canReview');
        abort_unless($canSeeUnpublished || InsuranceStore::isPublishedDocument($path), 404, 'ไม่พบไฟล์เอกสารบนเซิร์ฟเวอร์');
        abort_unless(Storage::disk('local')->exists($path), 404, 'ไม่พบไฟล์เอกสารบนเซิร์ฟเวอร์');

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $inline = ! $request->boolean('download') && in_array($ext, DocumentTypes::INLINE, true);
        $type = DocumentTypes::MIME[$ext] ?? 'application/octet-stream';

        return Storage::disk('local')->response($path, $filename, [
            'Content-Type' => $type === 'text/csv' ? 'text/csv; charset=utf-8' : $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], $inline ? 'inline' : 'attachment');
    }
}
