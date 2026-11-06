<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use App\Rules\SafeAttachment;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentService
{
    /**
     * Store already-validated uploads (see App\Rules\SafeAttachment) for a message.
     *
     * Files go to the private disk under a random name in a per-ticket folder.
     * The user's file name is kept only as display metadata, so it can never
     * affect the storage path.
     *
     * @param  list<UploadedFile>  $files
     * @return list<TicketAttachment>
     */
    public function store(TicketMessage $message, array $files, ?User $uploader): array
    {
        $disk = config('attachments.disk');
        $stored = [];
        $writtenPaths = [];

        try {
            foreach ($files as $file) {
                $uuid = (string) Str::uuid();
                $extension = strtolower($file->getClientOriginalExtension());
                $path = Storage::disk($disk)->putFileAs(
                    "attachments/{$message->ticket_id}",
                    $file,
                    "{$uuid}.{$extension}",
                );

                abort_if($path === false, 500, 'The attachment could not be saved.');
                $writtenPaths[] = $path;

                $stored[] = $message->attachments()->create([
                    'uuid' => $uuid,
                    'ticket_id' => $message->ticket_id,
                    'uploaded_by' => $uploader?->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $this->displayName($file->getClientOriginalName()),
                    'mime_type' => SafeAttachment::detectMimeType($file->getRealPath(), $extension),
                    'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);
            }
        } catch (\Throwable $e) {
            // Don't leave orphaned files behind when the surrounding transaction rolls back.
            Storage::disk($disk)->delete($writtenPaths);

            throw $e;
        }

        return $stored;
    }

    /**
     * Whether $user may download this attachment: they must be able to see
     * the ticket, and files on internal notes are limited to staff who can
     * read internal notes.
     */
    public function authorizeDownload(User $user, TicketAttachment $attachment): void
    {
        $attachment->loadMissing('ticket', 'message');

        Gate::forUser($user)->authorize('view', $attachment->ticket);

        if ($attachment->message?->kind !== 'reply') {
            Gate::forUser($user)->authorize('viewInternalNotes', $attachment->ticket);
        }
    }

    /**
     * Always sent as a download with the detected type and nosniff, so even a
     * file that slipped past validation is never rendered by the browser on
     * this origin.
     */
    public function download(TicketAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404, 'File not found.');

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Delete stored files for the given tickets. Called before tickets are
     * removed (directly, or by cascade when a customer or workspace is
     * deleted), because database cascades don't fire model events.
     *
     * @param  iterable<int>|Builder  $ticketIds
     */
    public static function purgeFilesForTickets(iterable|Builder $ticketIds): void
    {
        TicketAttachment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->select(['id', 'disk', 'path'])
            ->chunkById(500, fn ($attachments) => $attachments
                ->groupBy('disk')
                ->each(fn ($group, $disk) => Storage::disk($disk)->delete($group->pluck('path')->all())));
    }

    /** Strip path parts and control characters from a user-supplied file name. */
    private function displayName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? 'attachment';

        return Str::limit($name !== '' ? $name : 'attachment', 200, '');
    }
}
