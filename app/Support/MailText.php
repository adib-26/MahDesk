<?php

namespace App\Support;

/**
 * Makes untrusted text (customer names, subjects, message bodies) safe to put
 * into Markdown notification emails.
 *
 * Laravel already HTML-escapes these lines and refuses javascript: links. But
 * the email body is then rendered as Markdown, so a customer could still write
 * "[Reset your password](https://evil.example)" and have it turned into a real
 * link in an email sent to an agent. Escaping Markdown punctuation keeps the
 * text exactly as typed.
 *
 * <, > and & are left alone because Blade escapes them into entities first.
 */
final class MailText
{
    private const MARKDOWN_CHARS = ['\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '#', '+', '-', '.', '!', '|', '~'];

    public static function escape(?string $text): string
    {
        $text = (string) $text;

        return str_replace(
            self::MARKDOWN_CHARS,
            array_map(fn (string $char) => '\\'.$char, self::MARKDOWN_CHARS),
            $text,
        );
    }
}
