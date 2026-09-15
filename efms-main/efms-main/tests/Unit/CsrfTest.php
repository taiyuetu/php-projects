<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    public function testTokenGenerationAndRetrieval(): void
    {
        $token1 = Csrf::token();
        $this->assertNotEmpty($token1);
        $this->assertEquals(64, strlen($token1)); // 32 bytes in hex

        // Subsequent call should return the same token
        $token2 = Csrf::token();
        $this->assertEquals($token1, $token2);
    }

    public function testValidationSucceedsWithMatchingToken(): void
    {
        $token = Csrf::token();
        $this->assertTrue(Csrf::validate($token));
    }

    public function testValidationFailsWithInvalidToken(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::validate('invalid-token-string'));
        $this->assertFalse(Csrf::validate(null));
        $this->assertFalse(Csrf::validate(''));
    }

    public function testFieldRendersHiddenInput(): void
    {
        $field = Csrf::field();
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_token"', $field);
        $this->assertStringContainsString(Csrf::token(), $field);
    }
}
