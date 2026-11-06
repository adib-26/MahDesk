import { Button } from '@/components/ui/button';
import { Download, FileText, ImageIcon, Paperclip, X } from 'lucide-react';
import { useRef, useState } from 'react';

export interface AttachmentLimits {
    max_file_kb: number;
    max_total_kb: number;
    max_files: number;
    extensions: string[];
}

export interface AttachmentItem {
    id: string;
    name: string;
    size: number;
    mime_type: string;
    url: string;
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, '')} MB`;
}

/**
 * File picker with the same limits the server enforces, so people get
 * immediate feedback. The server still validates every file (type is checked
 * from the file contents there, which the browser can't do reliably).
 */
export function AttachmentPicker({
    files,
    onChange,
    limits,
    error,
}: {
    files: File[];
    onChange: (files: File[]) => void;
    limits: AttachmentLimits;
    error?: string;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [problem, setProblem] = useState<string | null>(null);

    const add = (picked: FileList | null) => {
        if (!picked) return;

        const next = [...files];
        const rejected: string[] = [];

        for (const file of Array.from(picked)) {
            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

            if (!limits.extensions.includes(extension)) {
                rejected.push(`${file.name}: file type not allowed`);
            } else if (file.size === 0) {
                rejected.push(`${file.name}: file is empty`);
            } else if (file.size > limits.max_file_kb * 1024) {
                rejected.push(`${file.name}: larger than ${formatBytes(limits.max_file_kb * 1024)}`);
            } else if (next.length >= limits.max_files) {
                rejected.push(`${file.name}: only ${limits.max_files} files per message`);
            } else if (next.reduce((sum, f) => sum + f.size, 0) + file.size > limits.max_total_kb * 1024) {
                rejected.push(`${file.name}: total would exceed ${formatBytes(limits.max_total_kb * 1024)}`);
            } else {
                next.push(file);
            }
        }

        setProblem(rejected.length ? rejected.join('. ') : null);
        onChange(next);

        if (input.current) input.current.value = '';
    };

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <input
                    ref={input}
                    type="file"
                    multiple
                    className="hidden"
                    accept={limits.extensions.map((e) => `.${e}`).join(',')}
                    onChange={(event) => add(event.target.files)}
                />
                <Button type="button" variant="outline" size="sm" onClick={() => input.current?.click()} disabled={files.length >= limits.max_files}>
                    <Paperclip data-icon="inline-start" />
                    Attach files
                </Button>
                <span className="text-muted-foreground text-xs">
                    Up to {limits.max_files} files, {formatBytes(limits.max_file_kb * 1024)} each, {formatBytes(limits.max_total_kb * 1024)} total ·{' '}
                    {limits.extensions.join(', ')}
                </span>
            </div>

            {files.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {files.map((file, index) => (
                        <li key={`${file.name}-${index}`} className="bg-muted/50 flex items-center gap-2 rounded-sm border px-2 py-1 text-xs">
                            <span className="max-w-48 truncate">{file.name}</span>
                            <span className="text-muted-foreground">{formatBytes(file.size)}</span>
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-foreground"
                                onClick={() => onChange(files.filter((_, i) => i !== index))}
                                aria-label={`Remove ${file.name}`}
                            >
                                <X className="size-3" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {(problem || error) && <p className="text-sm text-red-600 dark:text-red-400">{problem ?? error}</p>}
        </div>
    );
}

export function AttachmentList({ attachments }: { attachments?: AttachmentItem[] }) {
    if (!attachments?.length) return null;

    return (
        <ul className="mt-3 flex flex-wrap gap-2">
            {attachments.map((file) => {
                const Icon = file.mime_type.startsWith('image/') ? ImageIcon : FileText;

                return (
                    <li key={file.id}>
                        {/* A plain link (not Inertia's <Link>) so the browser performs a normal file download. */}
                        <a href={file.url} className="hover:bg-muted flex items-center gap-2 rounded-sm border px-2 py-1 text-xs" download>
                            <Icon className="size-3.5" />
                            <span className="max-w-56 truncate">{file.name}</span>
                            <span className="text-muted-foreground">{formatBytes(file.size)}</span>
                            <Download className="text-muted-foreground size-3" />
                        </a>
                    </li>
                );
            })}
        </ul>
    );
}

/** First attachment error from an Inertia errors bag ("attachments", "attachments.0", ...). */
export function attachmentError(errors: Record<string, string | undefined>): string | undefined {
    return Object.entries(errors).find(([key]) => key === 'attachments' || key.startsWith('attachments.'))?.[1];
}
