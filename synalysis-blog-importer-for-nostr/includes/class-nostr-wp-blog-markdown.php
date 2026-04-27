<?php
/**
 * Markdown to HTML (safe subset).
 *
 * @package SynalysisBlogImporterForNostr
 */

declare(strict_types=1);

use League\CommonMark\GithubFlavoredMarkdownConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Synalysis_Blog_Importer_Markdown {

	public static function to_html( string $markdown ): string {
		$converter = new GithubFlavoredMarkdownConverter(
			array(
				'html_input'         => 'strip',
				'allow_unsafe_links' => false,
				'max_nesting_level'  => 10,
			)
		);

		return $converter->convert( $markdown )->getContent();
	}
}
