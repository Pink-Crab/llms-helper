<?php
/**
 * Converter that echoes its input.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use PinkCrab\LLMs_Helper\Interfaces\Markdown_Converter;

class Echo_Converter implements Markdown_Converter {

	public function convert( string $html, string $base_url ): string {
		return 'converted(' . $html . ' @ ' . $base_url . ")\n";
	}
}
