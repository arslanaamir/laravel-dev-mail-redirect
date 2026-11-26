<?php

namespace ArslanAamir\DevMailRedirect\Support;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class RedirectNotice
{
    /**
     * Add the notice to the top of the HTML and plain text bodies.
     *
     * @param  array<string, Address[]>  $original  Recipients keyed by "To", "CC" and "BCC".
     */
    public function prependTo(Email $message, array $original): void
    {
        $html = $this->contents($message->getHtmlBody());
        $text = $this->contents($message->getTextBody());

        if ($html !== null) {
            $message->html(
                $this->insertIntoHtml($html, $this->html($original)),
                $message->getHtmlCharset() ?: 'utf-8'
            );
        }

        if ($text !== null) {
            $message->text(
                $this->text($original)."\n\n".$text,
                $message->getTextCharset() ?: 'utf-8'
            );
        }

        // An email with attachments only still needs to carry the notice.
        if ($html === null && $text === null) {
            $message->text($this->text($original));
        }
    }

    /**
     * @param  resource|string|null  $body
     */
    private function contents($body): ?string
    {
        if (! is_resource($body)) {
            return $body;
        }

        if (stream_get_meta_data($body)['seekable']) {
            rewind($body);
        }

        return (string) stream_get_contents($body);
    }

    private function insertIntoHtml(string $html, string $notice): string
    {
        // Place the notice just inside <body> so the document stays valid.
        if (preg_match('/<body\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            return substr_replace($html, $notice, $match[0][1] + strlen($match[0][0]), 0);
        }

        return $notice.$html;
    }

    /**
     * @param  array<string, Address[]>  $original
     */
    private function html(array $original): string
    {
        $lines = [];

        foreach ($this->recipientLines($original) as $type => $addresses) {
            $lines[] = '<strong>'.$type.':</strong> '.$this->escape($addresses);
        }

        return '<div style="margin:0 0 16px;padding:12px 16px;border:1px solid #f0b429;background:#fff8e1;'
            .'color:#333333;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;text-align:left;">'
            .'<strong>'.$this->escape($this->line('title')).'</strong> '
            .$this->escape($this->line('intro')).'<br>'
            .implode('<br>', $lines).'<br>'
            .$this->escape($this->line('outro'))
            .'</div>';
    }

    /**
     * @param  array<string, Address[]>  $original
     */
    private function text(array $original): string
    {
        $lines = [trim($this->line('title').' '.$this->line('intro'))];

        foreach ($this->recipientLines($original) as $type => $addresses) {
            $lines[] = $type.': '.$addresses;
        }

        $lines[] = $this->line('outro');
        $lines[] = str_repeat('-', 40);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, Address[]>  $original
     * @return array<string, string>
     */
    private function recipientLines(array $original): array
    {
        return array_map(function (array $addresses) {
            return implode(', ', array_map(function (Address $address) {
                return $address->getName() === ''
                    ? $address->getAddress()
                    : $address->getName().' <'.$address->getAddress().'>';
            }, $addresses));
        }, $original);
    }

    private function line(string $key): string
    {
        return (string) config('dev-mail-redirect.notice.'.$key, '');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
