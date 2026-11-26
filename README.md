# Laravel Dev Mail Redirect

Send every outgoing email in a Laravel application to your own addresses while the system is under development. Real users receive nothing, and no mailable, notification or controller needs to change.

## Requirements

- Laravel 9 or newer
- PHP 8.0.2 or newer

## Installation

Add the GitHub repository to your project, then require the package:

```bash
composer config repositories.dev-mail-redirect vcs https://github.com/arslanaamir/laravel-dev-mail-redirect
composer require arslanaamir/laravel-dev-mail-redirect:dev-main
```

To install from a local folder instead:

```bash
composer config repositories.dev-mail-redirect path /path/to/laravel-dev-mail-redirect
composer require arslanaamir/laravel-dev-mail-redirect:@dev
```

The service provider is discovered automatically.

Install it as a normal dependency (not with `--dev`) if you want to use it on staging servers that run `composer install --no-dev`.

## Configuration

Add to your `.env` file:

```env
MAIL_REDIRECT_ENABLED=true
MAIL_REDIRECT_TO="dev@example.com,qa@example.com"
```

| Variable | Meaning | Default |
|---|---|---|
| `MAIL_REDIRECT_ENABLED` | Turns redirection on or off | `false` |
| `MAIL_REDIRECT_TO` | One address, or several separated by commas | empty |

After changing these values, refresh anything that holds the old ones:

```bash
php artisan config:clear   # or config:cache if you cache configuration
php artisan queue:restart  # if you run queue workers
```

Restart long-running processes such as Octane as well.

## Behaviour

| Situation | Result |
|---|---|
| Redirection off | Email is sent unchanged to the real recipients |
| On, with at least one valid address | Email goes only to the redirect addresses |
| On, with no valid address | Email is **not sent** and an error is logged |
| On, with some invalid addresses | Valid ones are used and a warning is logged for the others |
| Unrecognised `MAIL_REDIRECT_ENABLED` value, such as `ture` | Treated as on |

For redirected emails:

- To, CC and BCC are replaced by the redirect addresses.
- A notice is added to the top of the HTML and plain text bodies.
- The original recipients are also stored in the `X-Original-To`, `X-Original-Cc` and `X-Original-Bcc` headers.
- Subject and attachments are unchanged.

The notice looks like this:

> **Development notice:** This email was originally intended for:
> **To:** John Doe <john@example.com>
> **CC:** manager@example.com
> You have received it instead because the system is currently under development.

To change its wording, publish the config file and edit the `notice` section:

```bash
php artisan vendor:publish --tag=dev-mail-redirect-config
```

## What is covered

The package listens to Laravel's `MessageSending` event, so it applies to everything sent through Laravel's mail system: `Mail` facade, mailables, mail notifications, queued mail, and any mailer driver (SMTP, SES, Mailgun, Postmark, and Mailjet or Gmail API drivers that plug into Laravel Mail).

## Limitations

- **Direct API calls are not redirected.** Code that calls the Mailjet SDK, the Google Gmail client, PHPMailer or cURL directly bypasses Laravel Mail. The same applies to third-party packages with their own send method.
- **Provider templates.** If the body is rendered by the provider (for example a Mailjet template), check that the notice appears. Recipients set through provider-specific headers or API options are not rewritten.
- **Custom MIME bodies.** If your code sets a raw MIME body with `setBody()`, recipients are redirected but the notice is not added.
- **Other listeners.** Laravel stops calling `MessageSending` listeners after one returns a non-null value. This package registers early, but another package's listener that is registered earlier and returns a value would prevent the redirect.
- **Production.** There is no environment check. If `MAIL_REDIRECT_ENABLED=true` on production, production email is redirected too.

Before relying on the package in a project, send one test email with that project's real mailer and confirm it arrives only at the redirect address.

## Testing

```bash
composer install
composer test
```
