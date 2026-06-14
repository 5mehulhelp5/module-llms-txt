<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Text;

use Angeo\LlmsTxt\Model\Text\Truncator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Text\Truncator
 */
class TruncatorTest extends TestCase
{
    private Truncator $truncator;

    protected function setUp(): void
    {
        $this->truncator = new Truncator();
    }

    public function testNoTruncationWithinLimit(): void
    {
        self::assertSame('short', $this->truncator->truncate('short', 100));
    }

    public function testZeroOrNegativeMaxReturnsInput(): void
    {
        self::assertSame('text', $this->truncator->truncate('text', 0));
        self::assertSame('text', $this->truncator->truncate('text', -5));
    }

    public function testTruncatesOnWordBoundaryWithEllipsis(): void
    {
        $out = $this->truncator->truncate('one two three four five six seven eight nine ten', 20);
        self::assertLessThanOrEqual(21, mb_strlen($out));
        self::assertStringEndsWith('…', $out);
        self::assertMatchesRegularExpression('/[a-z]…$/u', $out);
    }

    /**
     * Single-pass invariant: truncating an already-truncated-at-a-larger-max
     * string yields the same result as truncating the original — this is what
     * lets entity providers sanitize ONCE at the largest length and renderers
     * truncate down per format with byte-identical legacy output.
     */
    public function testIdempotentDownTruncation(): void
    {
        $text = str_repeat('lorem ipsum dolor sit amet ', 400); // ~10800 chars

        $direct  = $this->truncator->truncate($text, 2000);
        $staged  = $this->truncator->truncate($this->truncator->truncate($text, 5000), 2000);

        self::assertSame($direct, $staged);
    }

    public function testHardTruncateWhenNoSuitableWhitespace(): void
    {
        $out = $this->truncator->truncate(str_repeat('x', 50), 10);
        self::assertSame(str_repeat('x', 10) . '…', $out);
    }
}
