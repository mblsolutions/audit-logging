<?php

namespace MBLSolutions\AuditLogging\Tests\Unit\Support;

use MBLSolutions\AuditLogging\Support\Concerns\ShouldMaskSensitiveData;
use MBLSolutions\AuditLogging\Tests\LaravelTestCase;
use PHPUnit\Framework\Attributes\Test;

class MaskSensitiveDataTest extends LaravelTestCase
{
    protected object $masker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->masker = new class {
            use ShouldMaskSensitiveData;
        };
    }

    #[Test]
    public function it_masks_json_that_contains_escaped_quotes(): void
    {
        $json = json_encode(['name' => 'Dan "The Man" Smith', 'password' => 'secret']);

        $masked = json_decode($this->masker->maskSensitiveData($json, ['password']), true);

        $this->assertSame('******', $masked['password']);
        $this->assertSame('Dan "The Man" Smith', $masked['name']);
    }

    #[Test]
    public function it_masks_keys_in_any_case(): void
    {
        $masked = $this->masker->maskSensitiveData(['PIN' => '1234', 'Pan' => '6331101999990016', 'items' => [['pin' => '9876']]], ['pin', 'pan']);

        $this->assertSame('****', $masked['PIN']);
        $this->assertSame('****************', $masked['Pan']);
        $this->assertSame('****', $masked['items'][0]['pin']);
    }

    #[Test]
    public function it_masks_protected_keys_in_a_query_string(): void
    {
        $query = 'pan=6331101999990016&PIN=1234&card%5Bpin%5D=9876&amount=10&page=';

        $this->assertSame(
            'pan=****************&PIN=****&card%5Bpin%5D=****&amount=10&page=',
            $this->masker->maskQueryString($query, ['pan', 'pin'])
        );
    }

    #[Test]
    public function it_masks_path_segments_that_hold_a_protected_value(): void
    {
        $this->assertSame(
            '/api/reset-password/************/confirm',
            $this->masker->maskPathSegments('/api/reset-password/secret-token/confirm', ['secret-token'])
        );
    }

    #[Test]
    public function it_leaves_non_json_strings_alone(): void
    {
        $this->assertSame('plain text', $this->masker->maskSensitiveData('plain text', ['password']));
    }
}
