<?php
/**
 * Block editor integration.
 *
 * @package GTLinkManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GT_Link_Block_Editor {
	private GT_Link_Settings $settings;

	public static function init( GT_Link_Settings $settings ): void {
		$instance = new self( $settings );
		$instance->hooks();
	}

	private function __construct( GT_Link_Settings $settings ) {
		$this->settings = $settings;
	}

	private function hooks(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets(): void {
		$asset_url  = GT_LINK_MANAGER_URL . 'blocks/link-inserter/build/index.js';
		$asset_path = GT_LINK_MANAGER_PATH . 'blocks/link-inserter/build/index.js';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		wp_enqueue_script(
			'gt-link-manager-editor',
			$asset_url,
			array( 'wp-api-fetch', 'wp-rich-text', 'wp-block-editor', 'wp-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-dom-ready' ),
			GT_LINK_MANAGER_VERSION,
			true
		);

		wp_localize_script(
			'gt-link-manager-editor',
			'gtLinkManagerEditor',
			array(
				'restPath' => '/gt-link-manager/v1/links',
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'prefix'   => $this->settings->prefix(),
			)
		);
	}
}
