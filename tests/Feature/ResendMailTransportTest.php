<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Resend\Contracts\Client;
use Resend\Email as ResendEmail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ResendMailTransportTest extends TestCase
{
    public function test_resend_mailer_can_be_resolved_with_configured_api_key(): void
    {
        config(['services.resend.key' => 're_test_key']);

        $this->assertInstanceOf(ResendTransport::class, Mail::mailer('resend')->getSymfonyTransport());
    }

    public function test_resend_transport_passes_the_real_sender_and_recipient_to_the_api(): void
    {
        $emails = new class
        {
            public array $parameters = [];

            public function send(array $parameters): ResendEmail
            {
                $this->parameters = $parameters;

                return ResendEmail::from(['id' => 'test-message-id']);
            }
        };

        $client = new class($emails) implements Client
        {
            public function __construct(public object $emails) {}
        };

        $transport = new ResendTransport($client);
        $transport->send((new Email)
            ->from('onboarding@resend.dev')
            ->to('morador@example.com')
            ->subject('Comunicado')
            ->text('Mensagem de teste'));

        $this->assertStringContainsString('onboarding@resend.dev', $emails->parameters['from']);
        $this->assertStringContainsString('morador@example.com', $emails->parameters['to'][0]);
        $this->assertSame('Comunicado', $emails->parameters['subject']);
        $this->assertSame('Mensagem de teste', $emails->parameters['text']);
    }
}
