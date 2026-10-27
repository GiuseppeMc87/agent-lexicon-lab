<?php

namespace Tests\Unit\Services;

use App\Services\TokenDecoder;
use PHPUnit\Framework\TestCase;

class TokenDecoderTest extends TestCase
{
    public function test_decodes_only_explicit_delimited_symbols(): void
    {
        $decoder = new TokenDecoder;

        $decoded = $decoder->decode(
            'Use [[sun]] while sunshine and sunflower stay unchanged.',
            ['sun' => 'day marker'],
        );

        $this->assertSame(
            'Use day marker while sunshine and sunflower stay unchanged.',
            $decoded,
        );
    }

    public function test_does_not_cascade_meanings_through_other_symbols(): void
    {
        $decoder = new TokenDecoder;

        $decoded = $decoder->decode(
            'Compare [[alpha]] with [[beta]].',
            [
                'alpha' => '[[beta]]',
                'beta' => 'second meaning',
            ],
        );

        $this->assertSame('Compare [[beta]] with second meaning.', $decoded);
    }

    public function test_extracts_unique_valid_symbols_in_message_order(): void
    {
        $decoder = new TokenDecoder;

        $symbols = $decoder->extractSymbols(
            '[[first]] [[second-2]] [[first]] [[INVALID]]',
        );

        $this->assertSame(['first', 'second-2'], $symbols);
    }
}
