<?php

namespace App\Mail;

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class PatchedEsmtpTransport extends EsmtpTransport
{
    public function __construct(
        string $host = 'localhost',
        int $port = 0,
        bool $tls = false,
        \Psr\EventDispatcher\EventDispatcherInterface $dispatcher = null,
        \Psr\Log\LoggerInterface $logger = null
    ) {
        parent::__construct($host, $port, $tls, $dispatcher, $logger);

        // Replace the stream with our patched version
        $this->stream = new PatchedSocketStream();
    }
}