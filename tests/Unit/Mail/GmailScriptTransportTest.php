<?php

namespace Tests\Unit\Mail;

use App\Mail\Transport\GmailScriptTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class GmailScriptTransportTest extends TestCase
{
    private string $scriptUrl = 'https://script.google.com/macros/s/AKfycbz_example/exec';

    private string $secret = 'my-super-confidential-secret-key-12345';

    private function createSampleEmail(): Email
    {
        return (new Email)
            ->from('hello@example.com')
            ->to('customer@example.com')
            ->subject('Reset Your Password')
            ->text('Please click the link to reset your password.')
            ->html('<p>Please click the link to reset your password.</p>');
    }

    public function test_timeout_is_set_to_20_seconds_and_payload_is_posted(): void
    {
        Http::fake([
            $this->scriptUrl => Http::response(['ok' => true], 200),
        ]);

        $transport = new GmailScriptTransport($this->scriptUrl, $this->secret);
        $email = $this->createSampleEmail();

        $transport->send($email);

        Http::assertSent(function (Request $request) {
            return $request->url() === $this->scriptUrl
                && $request['secret'] === $this->secret
                && $request['to'] === 'customer@example.com'
                && $request['subject'] === 'Reset Your Password'
                && $request['text'] === 'Please click the link to reset your password.'
                && str_contains($request['html'], 'Please click the link to reset your password.')
                && $request['fromName'] === config('mail.from.name');
        });

        // Verify the code explicitly specifies a 20-second timeout
        $transportCode = file_get_contents(app_path('Mail/Transport/GmailScriptTransport.php'));
        $this->assertStringContainsString('Http::timeout(20)', $transportCode);
    }

    public function test_secret_is_never_leaked_in_exception_when_relay_returns_error(): void
    {
        Http::fake([
            $this->scriptUrl => Http::response(['ok' => false, 'error' => 'Quota exceeded on Google Apps Script'], 400),
        ]);

        $transport = new GmailScriptTransport($this->scriptUrl, $this->secret);
        $email = $this->createSampleEmail();

        try {
            $transport->send($email);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Gmail relay failed: Quota exceeded on Google Apps Script', $e->getMessage());
            $this->assertStringNotContainsString($this->secret, $e->getMessage());
        }
    }

    public function test_secret_is_never_leaked_in_exception_when_server_returns_500(): void
    {
        Http::fake([
            $this->scriptUrl => Http::response('Internal Server Error', 500),
        ]);

        $transport = new GmailScriptTransport($this->scriptUrl, $this->secret);
        $email = $this->createSampleEmail();

        try {
            $transport->send($email);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Gmail relay failed: 500', $e->getMessage());
            $this->assertStringNotContainsString($this->secret, $e->getMessage());
        }
    }

    public function test_secret_is_never_leaked_in_to_string(): void
    {
        $transport = new GmailScriptTransport($this->scriptUrl, $this->secret);

        $this->assertSame('gmailscript', (string) $transport);
        $this->assertStringNotContainsString($this->secret, (string) $transport);
    }

    public function test_secret_is_never_logged_when_error_is_reported(): void
    {
        Http::fake([
            $this->scriptUrl => Http::response(['ok' => false, 'error' => 'Relay execution crashed'], 502),
        ]);

        $transport = new GmailScriptTransport($this->scriptUrl, $this->secret);
        $email = $this->createSampleEmail();

        Log::spy();

        try {
            $transport->send($email);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            report($e);

            Log::shouldNotHaveReceived('error', function ($message, $context = []) {
                return str_contains((string) $message, $this->secret)
                    || str_contains(json_encode($context), $this->secret);
            });
        }
    }
}
