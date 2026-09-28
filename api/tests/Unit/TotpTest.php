<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Auth\Totp;
use Tests\TestCase;

class TotpTest extends TestCase
{
    public function test_a_fresh_code_verifies_and_a_wrong_code_does_not(): void
    {
        $secret = Totp::secret();
        $code = Totp::code($secret);

        $this->assertTrue(Totp::verify($secret, $code));
        $this->assertFalse(Totp::verify($secret, '000000'));
    }
}
