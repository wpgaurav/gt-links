<?php
/**
 * Links list table.
 *
 * @package GTLinkManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GT_Link_List_Table extends WP_List_Table {
	private GT_Link_DB $db;

	private string $prefix;

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private array $categories;

	/**
	 * @param array<int, array<string, mixed>> $categories Categories.
	 */
	public function __construct( GT_Link_DB $db, array $categories, string $prefix ) {
		$this->db         = $db;
		$this->categories = $categories;
		$this->prefix     = sanitize_title_with_dashes( $prefix );

		parent::__construct(
			array(
				'singular' => 'gt_link',
				'plural'   => 'gt_links',
				'ajax'     => false,
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		$columns = array(
			'cb'            => '<input type="checkbox" />',
			'id'            => esc_html__( 'ID', 'gt-link-manager' ),
			'name'          => esc_html__( 'Name', 'gt-link-manager' ),
			'branded_url'   => esc_html__( 'Branded URL', 'gt-link-manager' ),
			'url'           => esc_html__( 'Destination', 'gt-link-manager' ),
			'redirect_type' => esc_html__( 'Type', 'gt-link-manager' ),
			'rel'           => esc_html__( 'Rel', 'gt-link-manager' ),
			'category'      => esc_html__( 'Category', 'gt-link-manager' ),
			'created_at'    => esc_html__( 'Created', 'gt-link-manager' ),
		);

		return (array) apply_filters( 'gt_link_manager_link_columns', $columns );
	}

	/**
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'id'            => array( 'id', true ),
			'name'          => array( 'name', false ),
			'branded_url'   => array( 'slug', false ),
			'url'           => array( 'url', false ),
			'redirect_type' => array( 'redirect_type', false ),
			'rel'           => array( 'rel', false ),
			'category'      => array( 'category_id', false ),
			'created_at'    => array( 'created_at', false ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'bulk_delete'        => esc_html__( 'Delete', 'gt-link-manager' ),
			'bulk_301'           => esc_html__( 'Set 301', 'gt-link-manager' ),
			'bulk_302'           => esc_html__( 'Set 302', 'gt-link-manager' ),
			'bulk_307'           => esc_html__( 'Set 307', 'gt-link-manager' ),
			'bulk_rel_none'      => esc_html__( 'Clear rel', 'gt-link-manager' ),
			'bulk_rel_nofollow'  => esc_html__( 'Set rel: nofollow', 'gt-link-manager' ),
			'bulk_rel_sponsored' => esc_html__( 'Set rel: sponsored', 'gt-link-manager' ),
			'bulk_rel_ugc'       => esc_html__( 'Set rel: ugc', 'gt-link-manager' ),
			'bulk_set_category'  => esc_html__( 'Set category', 'gt-link-manager' ),
		);
	}

	public function no_items(): void {
		echo esc_html__( 'No links found.', 'gt-link-manager' );
	}

	/**
	 * @param array<string, mixed> $item Item.
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="link_ids[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * @param array<string, mixed> $item Item.
	 */
	protected function column_name( $item ): string {
		$edit_url = add_query_arg(
			array(
				'page'    => 'gt-links-edit',
				'link_id' => (int) $item['id'],
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'gt-links',
					'action' => 'delete',
					'link'   => (int) $item['id'],
				),
				admin_url( 'admin.php' )
			),
			'gt_link_delete_' . (int) $item['id']
		);

		$branded_url = home_url( '/' . trim( $this->prefix, '/' ) . '/' . (string) $item['slug'] );

		$actions = array(
			'edit'       => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'gt-link-manager' ) . '</a>',
			'quick_edit' => '<a href="#" class="gt-link-quick-edit" data-link-id="' . (int) $item['id'] . '" data-url="' . esc_attr( (string) $item['url'] ) . '" data-redirect-type="' . (int) $item['redirect_type'] . '">' . esc_html__( 'Quick Edit', 'gt-link-manager' ) . '</a>',
			'copy_url'   => '<a href="#" class="gt-link-copy-url" data-copy-url="' . esc_attr( $branded_url ) . '">' . esc_html__( 'Copy URL', 'gt-link-manager' ) . '</a>',
			'delete'     => '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'gt-link-manager' ) . '</a>',
			'view'       => '<a href="' . esc_url( $branded_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'gt-link-manager' ) . '</a>',
		);

		return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( (string) $item['name'] ) . '</a></strong>' . $this->row_actions( $actions );
	}

	/**
	 * @param array<string, mixed> $item Item.
	 */
	protected function column_branded_url( $item ): string {
		$url = home_url( '/' . trim( $this->prefix, '/' ) . '/' . (string) $item['slug'] );
		return '<code>' . esc_html( $url ) . '</code>';
	}

	/**
	 * @param array<string, mixed> $item Item.
	 */
	protected function column_url( $item ): string {
		$url = (string) $item['url'];
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
	}

	/**
	 * @param array<string, mixed> $item Item.
	 */
	protected function column_category( $item ): string {
		$category_id = (int) ( $item['category_id'] ?? 0 );
		if ( $category_id <= 0 ) {
			return '&mdash;';
		}

		foreach ( $this->categories as $category ) {
			if ( (int) $category['id'] === $category_id ) {
				return esc_html( (string) $category['name'] );
			}
		}

		return '&mdash;';
	}

	/**
	 * @param array<string, mixed> $item Item.
	 */
	protected function column_default( $item, $column_name ): string {
		if ( isset( $item[ $column_name ] ) ) {
			return esc_html( (string) $item[ $column_name ] );
		}

		return '';
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$category      = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect_type = isset( $_GET['redirect_type'] ) ? absint( $_GET['redirect_type'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rel           = isset( $_GET['rel'] ) ? sanitize_key( (string) wp_unslash( $_GET['rel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bulk_category = isset( $_REQUEST['bulk_category_id'] ) ? absint( $_REQUEST['bulk_category_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="alignleft actions">';
		echo '<select name="category_id"><option value="0">' . esc_html__( 'All categories', 'gt-link-manager' ) . '</option>';
		foreach ( $this->categories as $cat ) {
			echo '<option value="' . (int) $cat['id'] . '" ' . selected( $category, (int) $cat['id'], false ) . '>' . esc_html( (string) $cat['name'] ) . '</option>';
		}
		echo '</select>';

		echo '<select name="redirect_type">';
		echo '<option value="0">' . esc_html__( 'All types', 'gt-link-manager' ) . '</option>';
		echo '<option value="301" ' . selected( $redirect_type, 301, false ) . '>301</option>';
		echo '<option value="302" ' . selected( $redirect_type, 302, false ) . '>302</option>';
		echo '<option value="307" ' . selected( $redirect_type, 307, false ) . '>307</option>';
		echo '</select>';

		echo '<select name="rel">';
		echo '<option value="">' . esc_html__( 'All rel values', 'gt-link-manager' ) . '</option>';
		echo '<option value="nofollow" ' . selected( $rel, 'nofollow', false ) . '>nofollow</option>';
		echo '<option value="sponsored" ' . selected( $rel, 'sponsored', false ) . '>sponsored</option>';
		echo '<option value="ugc" ' . selected( $rel, 'ugc', false ) . '>ugc</option>';
		echo '</select>';

		submit_button( esc_html__( 'Filter', 'gt-link-manager' ), 'secondary', 'filter_action', false );
		echo '</div>';

		echo '<div class="alignleft actions">';
		echo '<select name="bulk_category_id">';
		echo '<option value="0">' . esc_html__( 'Category for bulk action', 'gt-link-manager' ) . '</option>';
		foreach ( $this->categories as $cat ) {
			echo '<option value="' . (int) $cat['id'] . '" ' . selected( $bulk_category, (int) $cat['id'], false ) . '>' . esc_html( (string) $cat['name'] ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
	}

	public function prepare_items(): void {
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'gt_links_per_page', 20 );
		$current_page = $this->get_pagenum();
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( (string) wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby      = isset( $_REQUEST['orderby'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order        = isset( $_REQUEST['order'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$sortable = $this->get_sortable_columns();
		if ( isset( $sortable[ $orderby ] ) ) {
			$orderby = (string) $sortable[ $orderby ][0];
		}

		$filters = array(
			'search'        => $search,
			'category_id'   => isset( $_REQUEST['category_id'] ) ? absint( $_REQUEST['category_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'redirect_type' => isset( $_REQUEST['redirect_type'] ) ? absint( $_REQUEST['redirect_type'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'rel'           => isset( $_REQUEST['rel'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['rel'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$total_items = $this->db->count_links( $filters );
		$this->items = $this->db->list_links( $filters, $current_page, $per_page, $orderby, strtoupper( $order ) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
	}

	private function process_bulk_action(): void {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( (string) wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		$link_ids = isset( $_REQUEST['link_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['link_ids'] ) ) : array();
		$link_ids = array_filter( $link_ids );
		if ( empty( $link_ids ) ) {
			return;
		}

		foreach ( $link_ids as $link_id ) {
			if ( 'bulk_delete' === $action ) {
				$this->db->delete_link( $link_id );
				continue;
			}

			$link = $this->db->get_link_by_id( $link_id );
			if ( null === $link ) {
				continue;
			}

			if ( in_array( $action, array( 'bulk_301', 'bulk_302', 'bulk_307' ), true ) ) {
				$this->db->update_link(
					$link_id,
					array_merge( $link, array( 'redirect_type' => (int) str_replace( 'bulk_', '', $action ) ) )
				);
				continue;
			}

			if ( in_array( $action, array( 'bulk_rel_none', 'bulk_rel_nofollow', 'bulk_rel_sponsored', 'bulk_rel_ugc' ), true ) ) {
				$map = array(
					'bulk_rel_none'      => '',
					'bulk_rel_nofollow'  => 'nofollow',
					'bulk_rel_sponsored' => 'sponsored',
					'bulk_rel_ugc'       => 'ugc',
				);
				$this->db->update_link(
					$link_id,
					array_merge( $link, array( 'rel' => $map[ $action ] ?? '' ) )
				);
				continue;
			}

			if ( 'bulk_set_category' === $action ) {
				$category_id = isset( $_REQUEST['bulk_category_id'] ) ? absint( $_REQUEST['bulk_category_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->db->update_link(
					$link_id,
					array_merge( $link, array( 'category_id' => $category_id ) )
				);
			}
		}
	}
}
