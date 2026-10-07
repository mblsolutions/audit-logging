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
    public function it_leaves_non_json_strings_alone(): void
    {
        $this->assertSame('plain text', $this->masker->maskSensitiveData('plain text', ['password']));
    }
}
