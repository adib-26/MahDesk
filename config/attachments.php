<?php

/*
|--------------------------------------------------------------------------
| Ticket attachments
|--------------------------------------------------------------------------
|
| Files are stored on a private disk under random names and are only served
| through an authorized download route, always as a download (never rendered
| in the browser).
|
| Each upload must pass every check:
|   - its extension is in the allowlist below
|   - the type detected from the file's *content* (libmagic/finfo, not the
|     browser-supplied Content-Type) is one of the types allowed for that
|     extension, so a script renamed to "photo.png" is rejected
|   - images must also decode as real images
|   - it is within the per-file, per-message and total size limits
|
| Types that browsers can execute or render as active content (HTML, SVG,
| JavaScript, executables, archives with macros such as .docm) are not allowed.
|
| PHP's own limits must be at least as large as these values:
|   upload_max_filesize >= max_file_kb, post_max_size >= max_total_kb
| The effective per-file limit is the smaller of max_file_kb and PHP's
| upload_max_filesize, and that is what the UI shows.
*/

return [

    'disk' => env('ATTACHMENTS_DISK', 'local'),

    'max_file_kb' => (int) env('ATTACHMENTS_MAX_FILE_KB', 10 * 1024),

    'max_total_kb' => (int) env('ATTACHMENTS_MAX_TOTAL_KB', 25 * 1024),

    'max_files' => (int) env('ATTACHMENTS_MAX_FILES', 5),

    // extension => MIME types finfo may report for genuine files of that type
    'allowed' => [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'log' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'zip' => ['application/zip'],
    ],

    'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],

];
