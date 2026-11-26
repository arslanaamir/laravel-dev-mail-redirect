<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable redirection
    |--------------------------------------------------------------------------
    |
    | When enabled, every email sent through Laravel's mail system goes to the
    | addresses below instead of the real recipients. A value that is not a
    | recognised boolean (for example a typo such as "ture") counts as enabled,
    | so a mistake never delivers mail to real users.
    |
    */

    'enabled' => env('MAIL_REDIRECT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Redirect addresses
    |--------------------------------------------------------------------------
    |
    | One address, or several separated by commas. If redirection is enabled and
    | there is no valid address here, outgoing mail is blocked.
    |
    */

    'to' => env('MAIL_REDIRECT_TO'),

    /*
    |--------------------------------------------------------------------------
    | Notice added to each redirected email
    |--------------------------------------------------------------------------
    |
    | Plain text only. It is escaped before being placed in HTML emails. The
    | original To, CC and BCC lines are listed between the intro and the outro.
    |
    */

    'notice' => [
        'title' => 'Development notice:',
        'intro' => 'This email was originally intended for:',
        'outro' => 'You have received it instead because the system is currently under development.',
    ],

];
