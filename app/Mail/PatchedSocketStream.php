<?php
namespace App\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class PatchedSocketStream extends SocketStream
{
    public function startTLS(): bool
    {
        stream_context_set_option(
            $this->stream,
            'ssl',
            'cafile',
            ini_get('openssl.cafile') ?: '/etc/pki/tls/cert.pem'
        );
        stream_context_set_option($this->stream, 'ssl', 'verify_peer', true);
        stream_context_set_option($this->stream, 'ssl', 'verify_peer_name', true);

        set_error_handler(function ($type, $msg) {
            throw new TransportException('Unable to connect with STARTTLS: ' . $msg);
        });
        try {
            return stream_socket_enable_crypto($this->stream, true);
        } finally {
            restore_error_handler();
        }
    }
}
