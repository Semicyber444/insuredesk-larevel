<?php

namespace App\Support;

/** File types a source document may be uploaded as, and how each is served back. */
class DocumentTypes
{
    public const MIME = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'csv' => 'text/csv',
    ];

    /** Shown in the browser; everything else is downloaded. */
    public const INLINE = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
}
