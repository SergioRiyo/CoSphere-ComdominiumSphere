<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailDeliveryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_contains_a_working_link_for_the_recipient(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        $this->assertMatchesRegularExpression('/href="([^"]*email\/verify\/[^"]*)"/', $message->getHtmlBody());
        preg_match('/href="([^"]*email\/verify\/[^"]*)"/', $message->getHtmlBody(), $matches);
        $this->get(html_entity_decode($matches[1]))->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_password_reset_email_contains_a_working_link_for_the_recipient(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        $this->assertMatchesRegularExpression('/href="([^"]*reset-password\/[^"]*)"/', $message->getHtmlBody());
        preg_match('/href="([^"]*reset-password\/[^"]*)"/', $message->getHtmlBody(), $matches);
        $url = html_entity_decode($matches[1]);
        $this->get($url)->assertOk();
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
