<?php

namespace ArslanAamir\DevMailRedirect\Listeners;

use ArslanAamir\DevMailRedirect\Support\RedirectNotice;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\ExceptionInterface;

class RedirectOutgoingMail
{
    public function __construct(private RedirectNotice $notice)
    {
    }

    /**
     * Returns false to block the email. Otherwise returns nothing, so that any
     * other MessageSending listeners still run.
     */
    public function handle(MessageSending $event): ?bool
    {
        if (! $this->enabled()) {
            return null;
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            Log::error('Dev mail redirect is enabled but MAIL_REDIRECT_TO has no valid address. The email was not sent.', [
                'subject' => $event->message->getSubject(),
            ]);

            return false;
        }

        $this->redirect($event->message, $recipients);

        return null;
    }

    private function enabled(): bool
    {
        $value = config('dev-mail-redirect.enabled', false);

        // Unrecognised values count as enabled: failing safe is better than
        // emailing real users because of a typo.
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ($value !== null);
    }

    /**
     * @return Address[]
     */
    private function recipients(): array
    {
        $configured = config('dev-mail-redirect.to');

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        $recipients = [];

        foreach ((array) $configured as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            try {
                $recipients[] = Address::create($value);
            } catch (ExceptionInterface $e) {
                Log::warning('Dev mail redirect ignored an invalid address in MAIL_REDIRECT_TO.', [
                    'address' => $value,
                ]);
            }
        }

        return $recipients;
    }

    /**
     * @param  Address[]  $recipients
     */
    private function redirect(Email $message, array $recipients): void
    {
        $original = array_filter([
            'To' => $message->getTo(),
            'CC' => $message->getCc(),
            'BCC' => $message->getBcc(),
        ]);

        $headers = $message->getHeaders();

        // The envelope is built from these headers, so removing them is what
        // keeps the real recipients from receiving the email.
        $headers->remove('To');
        $headers->remove('Cc');
        $headers->remove('Bcc');

        $message->to(...$recipients);

        foreach ($original as $type => $addresses) {
            $headers->addTextHeader('X-Original-'.ucfirst(strtolower($type)), implode(', ', array_map(
                fn (Address $address) => $address->toString(),
                $addresses
            )));
        }

        $this->notice->prependTo($message, $original);
    }
}
