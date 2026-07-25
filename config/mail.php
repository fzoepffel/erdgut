<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            // Never unlimited, but generous: smtp.ionos.de tarpits (delays
            // its greeting ~10s+), so a successful delivery needs well over
            // 10s. Nobody user-facing waits on this anymore (mails are
            // queued); the bound only protects the cron-driven worker from
            // a truly dead connection.
            'timeout' => (int) env('MAIL_TIMEOUT', 20),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Form Recipient
    |--------------------------------------------------------------------------
    |
    | Incoming public contact messages are sent to this inbox. If empty, the
    | application falls back to the configured global "from" address.
    |
    */
    'contact_to' => env('CONTACT_FORM_TO'),

    /*
    |--------------------------------------------------------------------------
    | Unsubscribe Mailbox (RFC 2369 List-Unsubscribe)
    |--------------------------------------------------------------------------
    |
    | Every outgoing mail receives a List-Unsubscribe header pointing to this
    | inbox. Required by Gmail/Yahoo bulk-sender guidelines and improves
    | deliverability even for low-volume transactional senders. If empty, the
    | header is omitted.
    |
    */
    'unsubscribe_mailbox' => env('MAIL_UNSUBSCRIBE_MAILBOX'),

    /*
    |--------------------------------------------------------------------------
    | Sent-Copy Archive (IMAP APPEND)
    |--------------------------------------------------------------------------
    |
    | SMTP submission only relays a message for delivery; it never touches the
    | IMAP "Sent" folder. When enabled, every outgoing mail is copied into the
    | Sent folder of the mailbox it was actually sent FROM, so site mail shows
    | up in IONOS webmail / any mail client like a hand-sent message.
    |
    | 'accounts' is keyed by the lowercased From address. A message whose From
    | has no matching account is skipped (never an error). Passwords are the
    | IMAP mailbox passwords — keep them in .env only, never commit them.
    |
    */
    'archive' => [
        'enabled' => (bool) env('MAIL_ARCHIVE_ENABLED', false),
        'host' => env('MAIL_ARCHIVE_IMAP_HOST', 'imap.ionos.de'),
        'port' => (int) env('MAIL_ARCHIVE_IMAP_PORT', 993),
        'folder' => env('MAIL_ARCHIVE_SENT_FOLDER', 'Sent'),
        'timeout' => (int) env('MAIL_ARCHIVE_IMAP_TIMEOUT', 15),

        'accounts' => array_filter([
            strtolower((string) env('MAIL_FROM_ADDRESS', 'noreply@erd-gut.de')) => [
                'username' => env('MAIL_ARCHIVE_NOREPLY_USERNAME', env('MAIL_FROM_ADDRESS')),
                'password' => env('MAIL_ARCHIVE_NOREPLY_PASSWORD'),
            ],
            strtolower((string) env('SHOP_COMPANY_EMAIL', 'kontakt@erd-gut.de')) => [
                'username' => env('MAIL_ARCHIVE_KONTAKT_USERNAME', env('SHOP_COMPANY_EMAIL')),
                'password' => env('MAIL_ARCHIVE_KONTAKT_PASSWORD'),
            ],
        ], fn (array $account): bool => ! empty($account['password'])),
    ],

];
