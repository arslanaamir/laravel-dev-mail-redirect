<?php

namespace ArslanAamir\DevMailRedirect\Tests;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Message;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class RedirectOutgoingMailTest extends TestCase
{
    public function test_it_leaves_mail_untouched_when_disabled(): void
    {
        config(['dev-mail-redirect.enabled' => false]);

        $this->send();

        $this->assertSame(['user@example.com', 'manager@example.com', 'audit@example.com'], $this->envelope());
        $this->assertSame('<html><body><p>Hello</p></body></html>', $this->email()->getHtmlBody());
        $this->assertFalse($this->email()->getHeaders()->has('X-Original-To'));
    }

    public function test_only_the_redirect_address_receives_the_mail(): void
    {
        $this->send();

        $this->assertSame(['dev@example.test'], $this->envelope());
        $this->assertSame(['dev@example.test'], $this->addresses($this->email()->getTo()));
        $this->assertSame([], $this->email()->getCc());
        $this->assertSame([], $this->email()->getBcc());
        $this->assertSame('Subject line', $this->email()->getSubject());
    }

    public function test_it_supports_several_addresses_and_skips_invalid_ones(): void
    {
        config(['dev-mail-redirect.to' => ' dev@example.test, not-an-email ,QA Team <qa@example.test>,']);

        $this->send();

        $this->assertSame(['dev@example.test', 'qa@example.test'], $this->envelope());
    }

    public function test_the_notice_lists_original_recipients_in_html_and_text(): void
    {
        $this->send();

        $html = $this->email()->getHtmlBody();
        $text = $this->email()->getTextBody();

        $this->assertStringStartsWith('<html><body><div', $html);
        $this->assertStringEndsWith('<p>Hello</p></body></html>', $html);
        $this->assertStringContainsString('John Doe &lt;user@example.com&gt;', $html);
        $this->assertStringContainsString('<strong>CC:</strong> manager@example.com', $html);
        $this->assertStringContainsString('<strong>BCC:</strong> audit@example.com', $html);
        $this->assertStringContainsString('under development', $html);

        $this->assertStringStartsWith('Development notice: This email was originally intended for:', $text);
        $this->assertStringContainsString("To: John Doe <user@example.com>\nCC: manager@example.com\nBCC: audit@example.com", $text);
        $this->assertStringEndsWith('Plain hello', $text);
    }

    public function test_original_recipients_are_kept_in_headers(): void
    {
        $this->send();

        $headers = $this->email()->getHeaders();

        $this->assertStringContainsString('user@example.com', $headers->get('X-Original-To')->getBodyAsString());
        $this->assertStringContainsString('manager@example.com', $headers->get('X-Original-Cc')->getBodyAsString());
        $this->assertStringContainsString('audit@example.com', $headers->get('X-Original-Bcc')->getBodyAsString());
    }

    public function test_empty_cc_and_bcc_are_not_listed(): void
    {
        Mail::raw('Plain hello', fn (Message $message) => $message->to('user@example.com')->subject('Raw'));

        $this->assertStringNotContainsString('CC:', $this->email()->getTextBody());
        $this->assertNull($this->email()->getHtmlBody());
    }

    public function test_recipient_names_are_escaped_in_html(): void
    {
        Mail::html('<p>Hi</p>', fn (Message $message) => $message
            ->to('user@example.com', '<script>x</script>')
            ->subject('Escaping'));

        $this->assertStringNotContainsString('<script>', $this->email()->getHtmlBody());
        $this->assertStringContainsString('&lt;script&gt;', $this->email()->getHtmlBody());
        $this->assertStringEndsWith('<p>Hi</p>', $this->email()->getHtmlBody());
    }

    public function test_attachments_are_preserved(): void
    {
        Mail::raw('See file', fn (Message $message) => $message
            ->to('user@example.com')
            ->subject('File')
            ->attachData('content', 'report.txt'));

        $this->assertCount(1, $this->email()->getAttachments());
    }

    /**
     * @dataProvider unusableAddresses
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unusableAddresses')]
    public function test_it_blocks_mail_without_a_valid_redirect_address($to): void
    {
        config(['dev-mail-redirect.to' => $to]);

        $this->send();

        $this->assertCount(0, $this->sent());
    }

    public static function unusableAddresses(): array
    {
        return [[null], [''], [' , '], ['not-an-email']];
    }

    /**
     * @dataProvider enabledValues
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('enabledValues')]
    public function test_it_reads_the_enabled_value_safely($value, string $expected): void
    {
        config(['dev-mail-redirect.enabled' => $value]);

        $this->send();

        $this->assertSame($expected, $this->envelope()[0]);
    }

    public static function enabledValues(): array
    {
        return [
            'true' => [true, 'dev@example.test'],
            '"1"' => ['1', 'dev@example.test'],
            '"on"' => ['on', 'dev@example.test'],
            'typo' => ['ture', 'dev@example.test'],
            'false' => [false, 'user@example.com'],
            '"false"' => ['false', 'user@example.com'],
            '"0"' => ['0', 'user@example.com'],
            'empty' => ['', 'user@example.com'],
            'null' => [null, 'user@example.com'],
        ];
    }

    public function test_it_redirects_mailables(): void
    {
        Mail::to('user@example.com')->send(new SampleMailable());

        $this->assertSame(['dev@example.test'], $this->envelope());
        $this->assertStringContainsString('user@example.com', $this->email()->getHtmlBody());
    }

    public function test_it_redirects_queued_mailables(): void
    {
        Mail::to('user@example.com')->queue(new SampleMailable());

        $this->assertSame(['dev@example.test'], $this->envelope());
    }

    public function test_it_redirects_notifications(): void
    {
        NotificationFacade::route('mail', 'user@example.com')->notify(new SampleNotification());

        $this->assertSame(['dev@example.test'], $this->envelope());
        $this->assertStringContainsString('user@example.com', $this->email()->getHtmlBody());
        $this->assertStringContainsString('user@example.com', $this->email()->getTextBody());
    }

    public function test_it_runs_before_application_listeners_that_return_a_value(): void
    {
        Event::listen(MessageSending::class, fn () => true);

        $this->send();

        $this->assertSame(['dev@example.test'], $this->envelope());
    }

    private function send(): void
    {
        Mail::send([], [], function (Message $message) {
            $message->to('user@example.com', 'John Doe')
                ->cc('manager@example.com')
                ->bcc('audit@example.com')
                ->subject('Subject line');

            $message->getSymfonyMessage()
                ->html('<html><body><p>Hello</p></body></html>')
                ->text('Plain hello');
        });
    }

    private function sent()
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }

    private function email(): Email
    {
        return $this->sent()->last()->getOriginalMessage();
    }

    private function envelope(): array
    {
        return $this->addresses($this->sent()->last()->getEnvelope()->getRecipients());
    }

    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $address->getAddress(), $addresses);
    }
}

class SampleMailable extends Mailable
{
    public function build()
    {
        return $this->subject('Mailable')->html('<p>Mailable body</p>');
    }
}

class SampleNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())->subject('Notification')->line('Notification body');
    }
}
