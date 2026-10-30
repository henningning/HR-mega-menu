<?php
/**
 * Plugin Name:       Schlichtes Megamenü Block
 * Description:       Megamenü-Erweiterung für native WordPress-Menüs mit Dropdowns, Megamenü-Spalten, automatischen Inhaltslisten und Gutenberg-Ausgabeblock.
 * Version:           5.8.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Henning Rolapp / OpenAI
 * Text Domain:       schlichtes-megamenue
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SMM_Modern_Mega_Menu_Block {
	const VERSION = '5.8.0';
	private static $instance_count = 0;

	public static function init() {
		add_filter( 'register_post_type_args', array( __CLASS__, 'enable_information_in_block_navigation' ), 20, 2 );
		add_action( 'after_setup_theme', array( __CLASS__, 'enable_native_menu_editor' ), 20 );
		add_action( 'init', array( __CLASS__, 'register_menu_post_type' ), 5 ); // Legacy 4.x migration only.
		add_action( 'init', array( __CLASS__, 'ensure_information_post_type_support' ), 100 );
		add_action( 'init', array( __CLASS__, 'register_block' ), 110 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'load-nav-menus.php', array( __CLASS__, 'add_native_menu_metabox' ) );
		add_filter( 'wp_setup_nav_menu_item', array( __CLASS__, 'setup_special_nav_menu_item' ) );
		add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'render_nav_menu_item_fields' ), 10, 5 );
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'save_nav_menu_item_fields' ), 10, 3 );
		add_action( 'wp_ajax_smm_add_special_nav_item', array( __CLASS__, 'ajax_add_special_nav_item' ) );
		add_action( 'wp_ajax_smm_native_preview', array( __CLASS__, 'ajax_native_preview' ) );
		add_action( 'admin_post_smm_save_native_settings', array( __CLASS__, 'handle_save_native_settings' ) );
		add_action( 'admin_post_smm_migrate_legacy_menu', array( __CLASS__, 'handle_migrate_legacy_menu' ) );

		// 4.x endpoints remain for backwards compatibility while legacy menus are migrated.
		add_action( 'admin_post_smm_create_menu', array( __CLASS__, 'handle_create_menu' ) );
		add_action( 'admin_post_smm_save_menu', array( __CLASS__, 'handle_save_menu' ) );
		add_action( 'admin_post_smm_delete_menu', array( __CLASS__, 'handle_delete_menu' ) );
		add_action( 'admin_post_smm_import_navigation', array( __CLASS__, 'handle_import_navigation' ) );
		add_action( 'wp_ajax_smm_search_content', array( __CLASS__, 'ajax_search_content' ) );
		add_action( 'wp_ajax_smm_preview', array( __CLASS__, 'ajax_preview' ) );
	}

	public static function enable_native_menu_editor() {
		register_nav_menus( array( 'smm-native-menu' => __( 'Schlichtes Megamenü', 'schlichtes-megamenue' ) ) );
	}

	/**
	 * Ensure the Information custom post type can be selected by the modern Navigation block.
	 * The filter is intentionally narrow and only touches the project's Information type.
	 */
	public static function enable_information_in_block_navigation( $args, $post_type ) {
		if ( 'leitstelle_info' !== $post_type ) {
			return $args;
		}

		$args['show_in_rest']      = true;
		$args['show_in_nav_menus'] = true;

		return $args;
	}

	/**
	 * Also repair the already-registered post type object. This covers plugin load-order
	 * situations where leitstelle_info was registered before our register_post_type_args filter.
	 */
	public static function ensure_information_post_type_support() {
		$information = get_post_type_object( 'leitstelle_info' );
		if ( ! $information ) {
			return;
		}

		$information->show_in_rest      = true;
		$information->show_in_nav_menus = true;
	}


	public static function register_menu_post_type() {
		register_post_type(
			'smm_menu',
			array(
				'labels' => array(
					'name'          => __( 'Megamenüs', 'schlichtes-megamenue' ),
					'singular_name' => __( 'Megamenü', 'schlichtes-megamenue' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'supports'             => array( 'title' ),
				'capability_type'      => 'page',
				'map_meta_cap'         => true,
				'exclude_from_search'  => true,
			)
		);
	}

	private static function menu_defaults() {
		return array(
			'orientation' => 'horizontal',
			'behavior' => 'hover',
			'clickTarget' => 'arrow',
			'openDelay' => 100,
			'closeDelay' => 240,
			'design' => 'plain',
			'fontFamily' => 'inherit',
			'fontSize' => 16,
			'fontWeight' => 500,
			'lineHeight' => 1.35,
			'letterSpacing' => 0,
			'textTransform' => 'none',
			'barBackground' => '#ffffff',
			'barText' => '#202124',
			'barHoverBackground' => '#f2f4f7',
			'barHoverText' => '#111827',
			'barActiveBackground' => '#eef2ff',
			'barActiveText' => '#1d4ed8',
			'accentColor' => '#1d4ed8',
			'barPaddingX' => 16,
			'barPaddingY' => 12,
			'itemGap' => 4,
			'barRadius' => 4,
			'barBorderStyle' => 'none',
			'barBorderWidth' => 0,
			'barBorderColor' => '#e5e7eb',
			'panelWidthMode' => 'wide',
			'panelCustomWidth' => 1100,
			'panelAlignment' => 'center',
			'edgeGutter' => 16,
			'panelOffset' => 0,
			'panelBackground' => '#ffffff',
			'panelText' => '#30343b',
			'panelHeading' => '#111827',
			'panelLinkHover' => '#1d4ed8',
			'panelBorderColor' => '#e5e7eb',
			'panelBorderWidth' => 1,
			'panelRadius' => 8,
			'panelPadding' => 28,
			'panelFontSize' => 15,
			'headingFontSize' => 16,
			'headingFontWeight' => 700,
			'columns' => 0,
			'columnMinWidth' => 190,
			'columnGap' => 30,
			'rowGap' => 24,
			'showDescriptions' => true,
			'shadowY' => 14,
			'shadowBlur' => 35,
			'shadowOpacity' => 0.14,
			'animation' => 'slide',
			'animationDuration' => 180,
			'mobileBreakpoint' => 900,
			'mobileCollapsed' => true,
			'mobileLabel' => 'Menü',
			'mobileBackground' => '#ffffff',
			'mobileText' => '#202124',
			'mobilePaddingX' => 16,
			'mobilePaddingY' => 13,
			'dropdownFontFamily' => 'inherit',
			'dropdownFontSize' => 15,
			'dropdownFontWeight' => 400,
			'dropdownLineHeight' => 1.4,
			'dropdownLetterSpacing' => 0,
			'dropdownTextTransform' => 'none',
			'dropdownMinWidth' => 220,
			'dropdownPadding' => 8,
			'dropdownItemPaddingX' => 12,
			'dropdownItemPaddingY' => 10,
			'dropdownSubmenuGap' => 4,
			'dropdownBackground' => '#ffffff',
			'dropdownText' => '#30343b',
			'dropdownHoverBackground' => '#f2f4f7',
			'dropdownHoverText' => '#1d4ed8',
			'dropdownBorderColor' => '#e5e7eb',
			'dropdownBorderStyle' => 'solid',
			'dropdownBorderWidth' => 1,
			'dropdownRadius' => 8,
			'dropdownShadowY' => 10,
			'dropdownShadowBlur' => 28,
			'dropdownShadowOpacity' => 0.12,
		);
	}

	private static function empty_menu_config() {
		return array(
			'formatVersion' => 1,
			'settings'      => self::menu_defaults(),
			'items'         => array(),
		);
	}

	private static function get_menu_config( $menu_id ) {
		$menu_id = absint( $menu_id );
		if ( ! $menu_id || 'smm_menu' !== get_post_type( $menu_id ) ) {
			return self::empty_menu_config();
		}
		$config = get_post_meta( $menu_id, '_smm_config', true );
		if ( ! is_array( $config ) ) {
			$config = self::empty_menu_config();
		}
		$config['settings'] = array_merge( self::menu_defaults(), isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array() );
		$config['items'] = isset( $config['items'] ) && is_array( $config['items'] ) ? self::hydrate_editor_labels( $config['items'] ) : array();
		return $config;
	}

	private static function hydrate_editor_labels( $items ) {
		foreach ( $items as &$item ) {
			if ( ! is_array( $item ) ) continue;
			if ( isset( $item['kind'] ) && 'linked' === $item['kind'] && ! empty( $item['objectId'] ) ) {
				if ( isset( $item['objectKind'] ) && 'taxonomy' === $item['objectKind'] ) {
					$term = get_term( absint( $item['objectId'] ), isset( $item['objectType'] ) ? $item['objectType'] : '' );
					if ( $term && ! is_wp_error( $term ) ) $item['editorLabel'] = $term->name;
				} else {
					$post = get_post( absint( $item['objectId'] ) );
					if ( $post ) $item['editorLabel'] = get_the_title( $post );
				}
			}
			if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
				$item['children'] = self::hydrate_editor_labels( $item['children'] );
			}
		}
		unset( $item );
		return $items;
	}

	private static function sanitize_menu_config( $config ) {
		if ( ! is_array( $config ) ) {
			return self::empty_menu_config();
		}
		$defaults = self::menu_defaults();
		$settings = array();
		$incoming = isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array();
		foreach ( $defaults as $key => $default ) {
			$value = array_key_exists( $key, $incoming ) ? $incoming[ $key ] : $default;
			if ( is_bool( $default ) ) {
				$settings[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$settings[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$settings[ $key ] = (float) $value;
			} else {
				$settings[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		$items = isset( $config['items'] ) && is_array( $config['items'] ) ? self::sanitize_menu_items( $config['items'], 0 ) : array();
		return array(
			'formatVersion' => 1,
			'settings'      => $settings,
			'items'         => $items,
		);
	}

	private static function sanitize_menu_items( $items, $depth ) {
		if ( $depth > 5 || ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $items, 0, 80 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$kind = isset( $item['kind'] ) ? sanitize_key( $item['kind'] ) : 'linked';
			if ( ! in_array( $kind, array( 'linked', 'custom', 'column', 'auto' ), true ) ) {
				$kind = 'linked';
			}
			$clean = array(
				'id'            => ! empty( $item['id'] ) ? sanitize_key( $item['id'] ) : wp_generate_uuid4(),
				'kind'          => $kind,
				'labelOverride' => isset( $item['labelOverride'] ) ? sanitize_text_field( $item['labelOverride'] ) : '',
				'heading'       => isset( $item['heading'] ) ? sanitize_text_field( $item['heading'] ) : '',
				'url'           => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
				'objectId'      => isset( $item['objectId'] ) ? absint( $item['objectId'] ) : 0,
				'objectType'    => isset( $item['objectType'] ) ? sanitize_key( $item['objectType'] ) : '',
				'objectKind'    => isset( $item['objectKind'] ) ? sanitize_key( $item['objectKind'] ) : 'post-type',
				'submenuType'   => isset( $item['submenuType'] ) && in_array( $item['submenuType'], array( 'none', 'dropdown', 'mega' ), true ) ? $item['submenuType'] : 'none',
				'openNewTab'    => ! empty( $item['openNewTab'] ),
				'postType'      => isset( $item['postType'] ) ? sanitize_key( $item['postType'] ) : 'post',
				'taxonomy'      => isset( $item['taxonomy'] ) ? sanitize_key( $item['taxonomy'] ) : '',
				'termId'        => isset( $item['termId'] ) ? absint( $item['termId'] ) : 0,
				'parentId'      => isset( $item['parentId'] ) ? max( -1, (int) $item['parentId'] ) : 0,
				'orderBy'       => isset( $item['orderBy'] ) && in_array( $item['orderBy'], array( 'date', 'title', 'menu_order', 'modified' ), true ) ? $item['orderBy'] : 'date',
				'order'         => isset( $item['order'] ) && 'ASC' === strtoupper( (string) $item['order'] ) ? 'ASC' : 'DESC',
				'count'         => self::clamp_int( isset( $item['count'] ) ? $item['count'] : 5, 1, 20 ),
				'showDate'      => ! empty( $item['showDate'] ),
				'moreEnabled'   => ! empty( $item['moreEnabled'] ),
				'moreLabel'     => isset( $item['moreLabel'] ) ? sanitize_text_field( $item['moreLabel'] ) : __( 'Mehr anzeigen', 'schlichtes-megamenue' ),
				'moreTarget'    => isset( $item['moreTarget'] ) && in_array( $item['moreTarget'], array( 'auto', 'taxonomy', 'parent', 'archive', 'linked', 'custom' ), true ) ? $item['moreTarget'] : 'auto',
				'moreLinkId'    => isset( $item['moreLinkId'] ) ? absint( $item['moreLinkId'] ) : 0,
				'moreUrl'       => isset( $item['moreUrl'] ) ? esc_url_raw( $item['moreUrl'] ) : '',
				'children'      => array(),
			);
			if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
				$clean['children'] = self::sanitize_menu_items( $item['children'], $depth + 1 );
			}
			$out[] = $clean;
		}
		return $out;
	}

	public static function register_admin_page() {
		add_menu_page(
			__( 'Megamenü', 'schlichtes-megamenue' ),
			__( 'Megamenü', 'schlichtes-megamenue' ),
			'edit_theme_options',
			'smm-mega-menu',
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-menu-alt3',
			58
		);
		add_submenu_page(
			'smm-mega-menu',
			__( 'Design & Verhalten', 'schlichtes-megamenue' ),
			__( 'Design & Verhalten', 'schlichtes-megamenue' ),
			'edit_theme_options',
			'smm-mega-menu',
			array( __CLASS__, 'render_admin_page' )
		);
		add_submenu_page(
			'smm-mega-menu',
			__( 'Menüstruktur', 'schlichtes-megamenue' ),
			__( 'Menüstruktur', 'schlichtes-megamenue' ),
			'edit_theme_options',
			'smm-native-structure',
			array( __CLASS__, 'render_native_structure_page' )
		);
	}

	public static function enqueue_admin_assets( $hook ) {
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_smm-mega-menu' === $hook || in_array( $current_page, array( 'smm-mega-menu', 'smm-native-structure' ), true ) ) {
			wp_enqueue_style( 'smm-native-admin', plugins_url( 'assets/native-admin.css', __FILE__ ), array(), self::VERSION );
			wp_enqueue_style( 'smm-frontend-style', plugins_url( 'assets/frontend.css', __FILE__ ), array(), self::VERSION );
			wp_enqueue_script( 'smm-frontend-preview', plugins_url( 'assets/frontend.js', __FILE__ ), array(), self::VERSION, true );
			wp_enqueue_script( 'smm-native-settings', plugins_url( 'assets/native-settings.js', __FILE__ ), array( 'jquery', 'smm-frontend-preview' ), self::VERSION, true );
			wp_localize_script( 'smm-native-settings', 'SMMNativeSettings', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'smm_native_preview' ),
			) );
		}

		if ( 'nav-menus.php' === $hook ) {
			wp_enqueue_style( 'smm-native-menu-admin', plugins_url( 'assets/native-menu-admin.css', __FILE__ ), array(), self::VERSION );
			wp_enqueue_script( 'smm-native-menu-admin', plugins_url( 'assets/native-menu-admin.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );
			wp_localize_script( 'smm-native-menu-admin', 'SMMNativeMenu', array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'smm_add_special_nav_item' ),
				'contentTypes'   => self::get_content_type_options(),
				'contentFilters' => self::get_content_filter_options(),
				'contentParents' => self::get_content_parent_options(),
			) );
		}
	}

	private static function get_smm_menus() {
		return get_posts(
			array(
				'post_type'      => 'smm_menu',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung für diese Seite.', 'schlichtes-megamenue' ) );
		}

		$menus = wp_get_nav_menus( array( 'orderby' => 'name' ) );
		$selected_id = isset( $_GET['menu_id'] ) ? absint( $_GET['menu_id'] ) : ( ! empty( $menus ) ? (int) $menus[0]->term_id : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_menu = $selected_id ? wp_get_nav_menu_object( $selected_id ) : false;
		if ( ! $selected_menu ) {
			$selected_id = 0;
		}
		$settings = $selected_id ? self::get_native_menu_settings( $selected_id ) : self::menu_defaults();
		?>
		<div class="wrap smm-native-admin-wrap">
			<div class="smm-native-hero">
				<div>
					<h1><?php esc_html_e( 'Megamenü', 'schlichtes-megamenue' ); ?></h1>
					<p><?php esc_html_e( 'Version 5 nutzt für die Menüstruktur vollständig den nativen WordPress-Menüeditor. Hier werden nur Design und Verhalten eingestellt.', 'schlichtes-megamenue' ); ?></p>
				</div>
				<span class="smm-native-version">v<?php echo esc_html( self::VERSION ); ?> · native WordPress-Menüs</span>
			</div>

			<div class="notice notice-info inline smm-native-notice"><p><strong><?php esc_html_e( 'Wichtig:', 'schlichtes-megamenue' ); ?></strong> <?php esc_html_e( 'Sortierung, Einrückung und Verschieben erfolgen ab jetzt direkt im WordPress-Menüeditor – ohne eigene Drag-&-Drop-Implementierung des Plugins.', 'schlichtes-megamenue' ); ?></p></div>

			<div class="smm-native-toolbar">
				<form method="get" action="">
					<input type="hidden" name="page" value="smm-mega-menu">
					<label for="smm-native-menu-select"><strong><?php esc_html_e( 'Menü', 'schlichtes-megamenue' ); ?></strong></label>
					<select id="smm-native-menu-select" name="menu_id" onchange="this.form.submit()">
						<?php if ( empty( $menus ) ) : ?><option value="0"><?php esc_html_e( 'Noch kein WordPress-Menü vorhanden', 'schlichtes-megamenue' ); ?></option><?php endif; ?>
						<?php foreach ( $menus as $menu ) : ?><option value="<?php echo esc_attr( $menu->term_id ); ?>" <?php selected( $selected_id, $menu->term_id ); ?>><?php echo esc_html( $menu->name ); ?></option><?php endforeach; ?>
					</select>
				</form>
				<div class="smm-native-toolbar-actions">
					<a class="button button-primary" href="<?php echo esc_url( $selected_id ? admin_url( 'nav-menus.php?action=edit&menu=' . $selected_id ) : admin_url( 'nav-menus.php?action=edit&menu=0' ) ); ?>"><?php esc_html_e( 'Menüstruktur bearbeiten', 'schlichtes-megamenue' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=0' ) ); ?>"><?php esc_html_e( 'Neues Menü anlegen', 'schlichtes-megamenue' ); ?></a>
				</div>
			</div>

			<?php if ( $selected_id ) : ?>
			<form id="smm-native-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="smm_save_native_settings">
				<input type="hidden" name="menu_id" value="<?php echo esc_attr( $selected_id ); ?>">
				<?php wp_nonce_field( 'smm_save_native_settings_' . $selected_id ); ?>

				<nav class="smm-native-tabs" aria-label="<?php esc_attr_e( 'Designbereiche', 'schlichtes-megamenue' ); ?>">
					<button type="button" class="is-active" data-tab="bar"><?php esc_html_e( 'Menüzeile', 'schlichtes-megamenue' ); ?></button>
					<button type="button" data-tab="mega"><?php esc_html_e( 'Megamenü', 'schlichtes-megamenue' ); ?></button>
					<button type="button" data-tab="dropdown"><?php esc_html_e( 'Dropdown', 'schlichtes-megamenue' ); ?></button>
					<button type="button" data-tab="mobile"><?php esc_html_e( 'Mobil', 'schlichtes-megamenue' ); ?></button>
					<button type="button" data-tab="behavior"><?php esc_html_e( 'Verhalten', 'schlichtes-megamenue' ); ?></button>
				</nav>

				<?php self::render_native_settings_panels( $settings ); ?>

				<div class="smm-native-savebar">
					<span><?php echo esc_html( sprintf( __( 'Design für „%s“', 'schlichtes-megamenue' ), $selected_menu->name ) ); ?></span>
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Einstellungen speichern', 'schlichtes-megamenue' ); ?></button>
				</div>
			</form>

			<section class="smm-native-preview-card">
				<div class="smm-native-card-heading"><div><h2><?php esc_html_e( 'Vorschau', 'schlichtes-megamenue' ); ?></h2><p><?php esc_html_e( 'Designänderungen werden nach kurzer Verzögerung in der Vorschau aktualisiert.', 'schlichtes-megamenue' ); ?></p></div></div>
				<div id="smm-native-preview" data-menu-id="<?php echo esc_attr( $selected_id ); ?>"><?php echo self::render_native_menu( $selected_id, $settings, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</section>
			<?php else : ?>
				<div class="smm-native-empty"><h2><?php esc_html_e( 'Noch kein Menü vorhanden', 'schlichtes-megamenue' ); ?></h2><p><?php esc_html_e( 'Lege zuerst ein WordPress-Menü an. Danach kannst du hier Megamenü-Design und Verhalten konfigurieren.', 'schlichtes-megamenue' ); ?></p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=0' ) ); ?>"><?php esc_html_e( 'WordPress-Menü anlegen', 'schlichtes-megamenue' ); ?></a></div>
			<?php endif; ?>

			<?php self::render_legacy_migration_box(); ?>
		</div>
		<?php
	}


	public static function render_native_structure_page() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung für diese Seite.', 'schlichtes-megamenue' ) );
		}
		$menus = wp_get_nav_menus( array( 'orderby' => 'name' ) );
		?>
		<div class="wrap smm-native-admin-wrap">
			<h1><?php esc_html_e( 'Menüstruktur', 'schlichtes-megamenue' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Die Struktur wird ab Version 5 nativ von WordPress verwaltet. Dadurch funktionieren Drag & Drop, Einrückung und Hierarchien mit derselben Technik wie bei klassischen WordPress-Menüs.', 'schlichtes-megamenue' ); ?></p>
			<div class="smm-native-menu-list">
			<?php if ( empty( $menus ) ) : ?>
				<div class="smm-native-empty"><p><?php esc_html_e( 'Noch kein Menü vorhanden.', 'schlichtes-megamenue' ); ?></p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=0' ) ); ?>"><?php esc_html_e( 'Menü anlegen', 'schlichtes-megamenue' ); ?></a></div>
			<?php else : foreach ( $menus as $menu ) : ?>
				<div class="smm-native-menu-row"><div><strong><?php echo esc_html( $menu->name ); ?></strong><small><?php echo esc_html( sprintf( __( '%d Menüeinträge', 'schlichtes-megamenue' ), count( wp_get_nav_menu_items( $menu->term_id ) ?: array() ) ) ); ?></small></div><a class="button button-primary" href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=' . $menu->term_id ) ); ?>"><?php esc_html_e( 'Struktur bearbeiten', 'schlichtes-megamenue' ); ?></a></div>
			<?php endforeach; endif; ?>
			</div>
		</div>
		<?php
	}

	private static function get_native_menu_settings( $menu_id ) {
		$saved = get_term_meta( absint( $menu_id ), '_smm_native_settings', true );
		return array_merge( self::menu_defaults(), is_array( $saved ) ? $saved : array() );
	}

	private static function sanitize_native_settings( $incoming ) {
		$defaults = self::menu_defaults();
		$clean = array();
		$incoming = is_array( $incoming ) ? $incoming : array();
		foreach ( $defaults as $key => $default ) {
			$value = array_key_exists( $key, $incoming ) ? $incoming[ $key ] : $default;
			if ( is_bool( $default ) ) {
				$clean[ $key ] = ! empty( $value );
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$clean[ $key ] = (float) $value;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $clean;
	}

	public static function handle_save_native_settings() {
		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		if ( ! $menu_id || ! current_user_can( 'edit_theme_options' ) ) wp_die( esc_html__( 'Nicht erlaubt.', 'schlichtes-megamenue' ) );
		check_admin_referer( 'smm_save_native_settings_' . $menu_id );
		$settings = self::sanitize_native_settings( isset( $_POST['smm_settings'] ) ? wp_unslash( $_POST['smm_settings'] ) : array() );
		update_term_meta( $menu_id, '_smm_native_settings', $settings );
		wp_safe_redirect( add_query_arg( array( 'page' => 'smm-mega-menu', 'menu_id' => $menu_id, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function render_native_settings_panels( $s ) {
		$panels = array(
			'bar' => array(
				'title' => __( 'Menüzeile', 'schlichtes-megamenue' ),
				'fields' => array(
					array('orientation','Ausrichtung','select',array('horizontal'=>'Horizontal','vertical'=>'Vertikal – Untermenüs darunter aufklappen')),
					array('fontFamily','Schriftart','text'), array('fontSize','Schriftgröße','number'), array('fontWeight','Schriftstärke','number'), array('lineHeight','Zeilenhöhe','number-step'), array('letterSpacing','Buchstabenabstand (em)','number-step'),
					array('textTransform','Textform','select',array('none'=>'Normal','uppercase'=>'GROSSBUCHSTABEN','lowercase'=>'kleinbuchstaben','capitalize'=>'Wortanfänge groß')),
					array('design','Design','select',array('plain'=>'Schlicht','underline'=>'Unterstrich','pill'=>'Pill','boxed'=>'Umrandet')),
					array('barBackground','Hintergrund','color'), array('barText','Textfarbe','color'), array('barHoverBackground','Hover-Hintergrund','color'), array('barHoverText','Hover-Text','color'), array('barActiveBackground','Aktiver Hintergrund','color'), array('barActiveText','Aktiver Text','color'), array('accentColor','Akzent','color'),
					array('barPaddingX','Innenabstand horizontal','number'), array('barPaddingY','Innenabstand vertikal','number'), array('itemGap','Abstand Menüpunkte','number'), array('barRadius','Radius Menüzeile','number'),
					array('barBorderStyle','Rahmenstil','select',array('none'=>'Kein Rahmen','solid'=>'Durchgezogen','dashed'=>'Gestrichelt','dotted'=>'Gepunktet')), array('barBorderWidth','Rahmenstärke','number'), array('barBorderColor','Rahmenfarbe','color'),
				),
			),
			'mega' => array(
				'title' => __( 'Megamenü', 'schlichtes-megamenue' ),
				'fields' => array(
					array('panelWidthMode','Breite','select',array('navigation'=>'Menübreite','content'=>'Inhaltsbreite','wide'=>'Weite Breite','viewport'=>'Browserbreite','custom'=>'Eigene Breite')), array('panelCustomWidth','Eigene Breite (px)','number'), array('panelAlignment','Ausrichtung','select',array('left'=>'Links','center'=>'Mitte','right'=>'Rechts')), array('edgeGutter','Randabstand','number'), array('panelOffset','Abstand nach unten','number'),
					array('panelBackground','Hintergrund','color'), array('panelText','Textfarbe','color'), array('panelHeading','Überschriftenfarbe','color'), array('panelLinkHover','Hoverfarbe Links','color'), array('panelBorderColor','Rahmenfarbe','color'), array('panelBorderWidth','Rahmenstärke','number'), array('panelRadius','Radius Megamenü','number'),
					array('panelPadding','Innenabstand','number'), array('panelFontSize','Link-Schriftgröße','number'), array('headingFontSize','Überschriftengröße','number'), array('headingFontWeight','Überschriftenstärke','number'), array('columns','Spalten (0 = automatisch)','number'), array('columnMinWidth','Min. Spaltenbreite','number'), array('columnGap','Spaltenabstand','number'), array('rowGap','Zeilenabstand','number'), array('showDescriptions','Beschreibungen anzeigen','checkbox'), array('shadowY','Schatten Y','number'), array('shadowBlur','Schatten weich','number'), array('shadowOpacity','Schatten Stärke','number-step'),
				),
			),
			'dropdown' => array(
				'title' => __( 'Dropdown', 'schlichtes-megamenue' ),
				'fields' => array(
					array('dropdownFontFamily','Schriftart','text'), array('dropdownFontSize','Schriftgröße','number'), array('dropdownFontWeight','Schriftstärke','number'), array('dropdownLineHeight','Zeilenhöhe','number-step'), array('dropdownLetterSpacing','Buchstabenabstand (em)','number-step'), array('dropdownTextTransform','Textform','select',array('none'=>'Normal','uppercase'=>'GROSSBUCHSTABEN','lowercase'=>'kleinbuchstaben','capitalize'=>'Wortanfänge groß')),
					array('dropdownMinWidth','Mindestbreite','number'), array('dropdownPadding','Panel-Innenabstand','number'), array('dropdownItemPaddingX','Linkabstand horizontal','number'), array('dropdownItemPaddingY','Linkabstand vertikal','number'), array('dropdownSubmenuGap','Abstand Untermenü','number'), array('dropdownBackground','Hintergrund','color'), array('dropdownText','Textfarbe','color'), array('dropdownHoverBackground','Hover-Hintergrund','color'), array('dropdownHoverText','Hover-Text','color'), array('dropdownBorderColor','Rahmenfarbe','color'), array('dropdownBorderStyle','Rahmenstil','select',array('none'=>'Kein Rahmen','solid'=>'Durchgezogen','dashed'=>'Gestrichelt','dotted'=>'Gepunktet')), array('dropdownBorderWidth','Rahmenstärke','number'), array('dropdownRadius','Radius','number'), array('dropdownShadowY','Schatten Y','number'), array('dropdownShadowBlur','Schatten weich','number'), array('dropdownShadowOpacity','Schatten Stärke','number-step'),
				),
			),
			'mobile' => array(
				'title' => __( 'Mobil', 'schlichtes-megamenue' ),
				'fields' => array(
					array('mobileBreakpoint','Breakpoint (px)','number'), array('mobileCollapsed','Fullscreen-Overlay verwenden','checkbox'), array('mobileLabel','Beschriftung','text'), array('mobileBackground','Hintergrund','color'), array('mobileText','Textfarbe','color'), array('mobilePaddingX','Schalter-Abstand horizontal','number'), array('mobilePaddingY','Schalter-Abstand vertikal','number'),
				),
			),
			'behavior' => array(
				'title' => __( 'Verhalten', 'schlichtes-megamenue' ),
				'fields' => array(
					array('behavior','Öffnen mit','select',array('hover'=>'Hover','click'=>'Klick')), array('clickTarget','Klickziel','select',array('arrow'=>'Nur Pfeil','item'=>'Gesamter Menüpunkt')), array('openDelay','Öffnungsverzögerung (ms)','number'), array('closeDelay','Schließverzögerung (ms)','number'), array('animation','Animation','select',array('none'=>'Keine','fade'=>'Fade','slide'=>'Fade + Slide','scale'=>'Scale')), array('animationDuration','Animationsdauer (ms)','number'),
				),
			),
		);

		foreach ( $panels as $key => $panel ) {
			echo '<section class="smm-native-panel' . ( 'bar' === $key ? ' is-active' : '' ) . '" data-panel="' . esc_attr( $key ) . '"><h2>' . esc_html( $panel['title'] ) . '</h2><div class="smm-native-settings-grid">';
			foreach ( $panel['fields'] as $field ) self::render_native_setting_field( $field, $s );
			echo '</div></section>';
		}
	}

	private static function render_native_setting_field( $field, $settings ) {
		$key = $field[0]; $label = $field[1]; $type = $field[2]; $value = array_key_exists( $key, $settings ) ? $settings[ $key ] : '';
		$name = 'smm_settings[' . $key . ']';
		echo '<label class="smm-native-field"><span>' . esc_html( $label ) . '</span>';
		if ( 'select' === $type ) {
			echo '<select name="' . esc_attr( $name ) . '" data-smm-setting="' . esc_attr( $key ) . '">'; foreach ( $field[3] as $v => $l ) echo '<option value="' . esc_attr( $v ) . '" ' . selected( (string) $value, (string) $v, false ) . '>' . esc_html( $l ) . '</option>'; echo '</select>';
		} elseif ( 'checkbox' === $type ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" data-smm-setting="' . esc_attr( $key ) . '" ' . checked( ! empty( $value ), true, false ) . '>';
		} elseif ( 'color' === $type ) {
			echo '<input type="color" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-smm-setting="' . esc_attr( $key ) . '">';
		} else {
			$step = 'number-step' === $type ? ' step="0.01"' : ''; $html_type = in_array( $type, array('number','number-step'), true ) ? 'number' : 'text';
			echo '<input type="' . esc_attr( $html_type ) . '"' . $step . ' name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-smm-setting="' . esc_attr( $key ) . '">';
		}
		echo '</label>';
	}

	private static function render_legacy_migration_box() {
		$legacy = self::get_smm_menus();
		if ( empty( $legacy ) ) return;
		?>
		<section class="smm-native-migration"><h2><?php esc_html_e( 'Vorhandene 4.x-Menüs übernehmen', 'schlichtes-megamenue' ); ?></h2><p><?php esc_html_e( 'Diese alten Plugin-Menüs können einmalig in echte WordPress-Menüs umgewandelt werden. Das Original bleibt bestehen.', 'schlichtes-megamenue' ); ?></p>
		<?php foreach ( $legacy as $menu ) : if ( get_post_meta( $menu->ID, '_smm_migrated_native_id', true ) ) continue; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smm-native-migration-row"><input type="hidden" name="action" value="smm_migrate_legacy_menu"><input type="hidden" name="legacy_id" value="<?php echo esc_attr( $menu->ID ); ?>"><?php wp_nonce_field( 'smm_migrate_legacy_' . $menu->ID ); ?><strong><?php echo esc_html( get_the_title( $menu ) ); ?></strong><button class="button"><?php esc_html_e( 'In natives WordPress-Menü umwandeln', 'schlichtes-megamenue' ); ?></button></form>
		<?php endforeach; ?></section>
		<?php
	}


	public static function add_native_menu_metabox() {
		add_meta_box( 'smm-special-items', __( 'Megamenü-Elemente', 'schlichtes-megamenue' ), array( __CLASS__, 'render_native_menu_metabox' ), 'nav-menus', 'side', 'default' );
	}

	public static function render_native_menu_metabox() {
		global $nav_menu_selected_id;
		$menu_id = absint( $nav_menu_selected_id );
		if ( ! $menu_id ) {
			echo '<p>' . esc_html__( 'Lege zuerst ein Menü an und speichere es. Danach kannst du Megamenü-Spalten und automatische Inhaltslisten hinzufügen.', 'schlichtes-megamenue' ) . '</p>';
			return;
		}
		?>
		<div class="smm-special-metabox" data-menu-id="<?php echo esc_attr( $menu_id ); ?>">
			<p><?php esc_html_e( 'Spezielle Elemente werden als normale WordPress-Menüeinträge angelegt und anschließend mit dem nativen Drag & Drop einsortiert.', 'schlichtes-megamenue' ); ?></p>
			<div class="smm-special-add"><label><strong><?php esc_html_e( 'Megamenü-Spalte', 'schlichtes-megamenue' ); ?></strong><input type="text" class="widefat smm-special-heading" data-kind="column" placeholder="<?php esc_attr_e( 'z. B. Einsatzkräfte', 'schlichtes-megamenue' ); ?>"></label><button type="button" class="button smm-add-special" data-kind="column"><?php esc_html_e( 'Spalte hinzufügen', 'schlichtes-megamenue' ); ?></button></div>
			<hr>
			<div class="smm-special-add"><label><strong><?php esc_html_e( 'Automatische Inhaltsliste', 'schlichtes-megamenue' ); ?></strong><input type="text" class="widefat smm-special-heading" data-kind="auto" placeholder="<?php esc_attr_e( 'z. B. Aktuelle Informationen', 'schlichtes-megamenue' ); ?>"></label><button type="button" class="button smm-add-special" data-kind="auto"><?php esc_html_e( 'Inhaltsliste hinzufügen', 'schlichtes-megamenue' ); ?></button></div>
			<p class="description"><?php esc_html_e( 'Tipp: Hauptmenüpunkt auf „Megamenü“ stellen, danach Spalten oder Inhaltslisten eine Ebene darunter ziehen.', 'schlichtes-megamenue' ); ?></p>
		</div>
		<?php
	}

	public static function ajax_add_special_nav_item() {
		check_ajax_referer( 'smm_add_special_nav_item', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) wp_send_json_error( array( 'message' => __( 'Nicht erlaubt.', 'schlichtes-megamenue' ) ), 403 );
		$menu_id = isset( $_POST['menuId'] ) ? absint( $_POST['menuId'] ) : 0;
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$heading = isset( $_POST['heading'] ) ? sanitize_text_field( wp_unslash( $_POST['heading'] ) ) : '';
		if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) || ! in_array( $kind, array( 'column', 'auto' ), true ) ) wp_send_json_error( array( 'message' => __( 'Ungültige Angaben.', 'schlichtes-megamenue' ) ), 400 );
		$visible_heading = $heading;
		if ( '' === $heading ) $heading = 'column' === $kind ? __( 'Neue Spalte', 'schlichtes-megamenue' ) : __( 'Automatische Inhaltsliste', 'schlichtes-megamenue' );
		$item_id = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'  => $heading,
			'menu-item-url'    => '#',
			'menu-item-status' => 'publish',
			'menu-item-type'   => 'custom',
		) );
		if ( is_wp_error( $item_id ) ) wp_send_json_error( array( 'message' => $item_id->get_error_message() ), 500 );
		update_post_meta( $item_id, '_smm_kind', $kind );
		if ( 'column' === $kind ) {
			update_post_meta( $item_id, '_smm_column_heading', $heading );
		} else {
			update_post_meta( $item_id, '_smm_auto_heading', $visible_heading );
			update_post_meta( $item_id, '_smm_auto_heading_mode', '' !== $visible_heading ? 'manual' : 'none' );
			update_post_meta( $item_id, '_smm_auto_heading_link_id', 0 );
			update_post_meta( $item_id, '_smm_auto_post_type', 'post' );
			update_post_meta( $item_id, '_smm_auto_orderby', 'date' );
			update_post_meta( $item_id, '_smm_auto_order', 'DESC' );
			update_post_meta( $item_id, '_smm_auto_count', 5 );
			update_post_meta( $item_id, '_smm_auto_more_enabled', 1 );
			update_post_meta( $item_id, '_smm_auto_more_label', __( 'Mehr anzeigen', 'schlichtes-megamenue' ) );
			update_post_meta( $item_id, '_smm_auto_more_target', 'auto' );
			update_post_meta( $item_id, '_smm_auto_more_link_id', 0 );
			update_post_meta( $item_id, '_smm_auto_more_url', '' );
		}
		wp_send_json_success( array( 'id' => (int) $item_id ) );
	}

	public static function setup_special_nav_menu_item( $item ) {
		if ( empty( $item->ID ) ) return $item;
		$kind = get_post_meta( $item->ID, '_smm_kind', true );
		if ( 'column' === $kind ) $item->type_label = __( 'Megamenü-Spalte', 'schlichtes-megamenue' );
		if ( 'auto' === $kind ) $item->type_label = __( 'Automatische Inhaltsliste', 'schlichtes-megamenue' );
		return $item;
	}

	public static function render_nav_menu_item_fields( $item_id, $menu_item, $depth, $args, $current_object_id ) {
		$kind = get_post_meta( $item_id, '_smm_kind', true );
		if ( 'column' === $kind ) {
			$heading = get_post_meta( $item_id, '_smm_column_heading', true );
			?>
			<div class="smm-native-item-fields smm-kind-column"><span class="smm-native-badge"><?php esc_html_e( 'Megamenü-Spalte', 'schlichtes-megamenue' ); ?></span><p class="description"><?php esc_html_e( 'Diese Box ist eine reine Spalte ohne Zielseite. Ziehe normale Menüeinträge darunter.', 'schlichtes-megamenue' ); ?></p><p class="field-wide"><label><?php esc_html_e( 'Spaltenüberschrift', 'schlichtes-megamenue' ); ?><br><input type="text" class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][column_heading]" value="<?php echo esc_attr( $heading ); ?>"></label></p><input type="hidden" name="smm_item[<?php echo esc_attr( $item_id ); ?>][kind]" value="column"></div>
			<?php
			return;
		}

		if ( 'auto' === $kind ) {
			$post_type = get_post_meta( $item_id, '_smm_auto_post_type', true ) ?: 'post';
			$taxonomy = get_post_meta( $item_id, '_smm_auto_taxonomy', true );
			$term_id = (int) get_post_meta( $item_id, '_smm_auto_term_id', true );
			$parent_id_raw = get_post_meta( $item_id, '_smm_auto_parent_id', true );
			$parent_id = '' === $parent_id_raw ? 0 : (int) $parent_id_raw;
			$order_by = get_post_meta( $item_id, '_smm_auto_orderby', true ) ?: 'date';
			$order = get_post_meta( $item_id, '_smm_auto_order', true ) ?: 'DESC';
			$count = (int) get_post_meta( $item_id, '_smm_auto_count', true ) ?: 5;
			$heading = get_post_meta( $item_id, '_smm_auto_heading', true );
			$heading_mode = get_post_meta( $item_id, '_smm_auto_heading_mode', true );
			if ( ! in_array( $heading_mode, array( 'none', 'manual', 'linked', 'parent' ), true ) ) $heading_mode = '' !== trim( (string) $heading ) ? 'manual' : 'none';
			$heading_link_id = absint( get_post_meta( $item_id, '_smm_auto_heading_link_id', true ) );
			$show_date = (bool) get_post_meta( $item_id, '_smm_auto_show_date', true );
			$more_enabled = (bool) get_post_meta( $item_id, '_smm_auto_more_enabled', true );
			$more_label = get_post_meta( $item_id, '_smm_auto_more_label', true ) ?: __( 'Mehr anzeigen', 'schlichtes-megamenue' );
			$more_target = get_post_meta( $item_id, '_smm_auto_more_target', true ) ?: 'auto';
			if ( ! in_array( $more_target, array( 'auto', 'taxonomy', 'parent', 'archive', 'linked', 'custom' ), true ) ) $more_target = 'auto';
			$more_link_id = absint( get_post_meta( $item_id, '_smm_auto_more_link_id', true ) );
			$more_url = get_post_meta( $item_id, '_smm_auto_more_url', true );
			$types = self::get_content_type_options(); $filters = self::get_content_filter_options(); $parents = self::get_content_parent_options();
			?>
			<div class="smm-native-item-fields smm-kind-auto" data-smm-auto-item data-item-id="<?php echo esc_attr( $item_id ); ?>"><span class="smm-native-badge"><?php esc_html_e( 'Automatische Inhaltsliste', 'schlichtes-megamenue' ); ?></span><input type="hidden" name="smm_item[<?php echo esc_attr( $item_id ); ?>][kind]" value="auto">
				<p class="field-wide"><label><?php esc_html_e( 'Überschrift der Inhaltsliste', 'schlichtes-megamenue' ); ?><br><select class="widefat smm-auto-heading-mode" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_heading_mode]"><option value="none" <?php selected($heading_mode,'none'); ?>><?php esc_html_e('Keine sichtbare Überschrift','schlichtes-megamenue'); ?></option><option value="manual" <?php selected($heading_mode,'manual'); ?>><?php esc_html_e('Freier Text','schlichtes-megamenue'); ?></option><option value="linked" <?php selected($heading_mode,'linked'); ?>><?php esc_html_e('Verknüpfter Inhalt','schlichtes-megamenue'); ?></option><option value="parent" <?php selected($heading_mode,'parent'); ?>><?php esc_html_e('Übergeordneten Inhalt verwenden','schlichtes-megamenue'); ?></option></select></label></p>
				<p class="field-wide smm-auto-heading-manual-wrap"><label><?php esc_html_e( 'Freie Überschrift', 'schlichtes-megamenue' ); ?><br><input type="text" class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_heading]" value="<?php echo esc_attr( $heading ); ?>"></label></p>
				<p class="field-wide smm-auto-heading-link-wrap"><label><?php esc_html_e( 'Verknüpfter Inhalt', 'schlichtes-megamenue' ); ?><br><select class="widefat smm-auto-heading-link" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_heading_link_id]"><?php self::render_heading_content_options( $heading_link_id ); ?></select></label><span class="description smm-auto-heading-link-description"><?php esc_html_e( 'Bei „Verknüpfter Inhalt“ werden Titel und Link automatisch übernommen. Bei „Freier Text“ ist die Verknüpfung optional.', 'schlichtes-megamenue' ); ?></span></p>
				<p class="field-wide smm-auto-heading-parent-note"><span class="description"><?php esc_html_e( 'Die Überschrift übernimmt automatisch Titel und Link des unter „Übergeordneter Inhalt“ ausgewählten Eintrags. Dafür muss dort ein konkreter Inhalt gewählt sein.', 'schlichtes-megamenue' ); ?></span></p>
				<p class="field-wide"><label><?php esc_html_e( 'Inhaltstyp', 'schlichtes-megamenue' ); ?><br><select class="widefat smm-auto-post-type" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_post_type]"><?php foreach ( $types as $opt ) : ?><option value="<?php echo esc_attr( $opt['value'] ); ?>" <?php selected( $post_type, $opt['value'] ); ?>><?php echo esc_html( $opt['label'] ); ?></option><?php endforeach; ?></select></label></p>
				<p class="field-wide"><label><?php esc_html_e( 'Taxonomie / Kategorie', 'schlichtes-megamenue' ); ?><br><select class="widefat smm-auto-taxonomy" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_taxonomy]" data-current="<?php echo esc_attr( $taxonomy ); ?>"></select></label></p>
				<p class="field-wide smm-auto-term-wrap"><label><?php esc_html_e( 'Kategorie / Begriff', 'schlichtes-megamenue' ); ?><br><select class="widefat smm-auto-term" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_term_id]" data-current="<?php echo esc_attr( $term_id ); ?>"></select></label></p>
				<p class="field-wide smm-auto-parent-wrap"><label><?php esc_html_e( 'Übergeordneter Inhalt', 'schlichtes-megamenue' ); ?><br><select class="widefat smm-auto-parent" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_parent_id]" data-current="<?php echo esc_attr( $parent_id ); ?>"></select></label></p>
				<p class="field-half"><label><?php esc_html_e( 'Sortieren nach', 'schlichtes-megamenue' ); ?><br><select class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_orderby]"><option value="date" <?php selected($order_by,'date'); ?>><?php esc_html_e('Veröffentlichungsdatum','schlichtes-megamenue'); ?></option><option value="title" <?php selected($order_by,'title'); ?>><?php esc_html_e('Titel','schlichtes-megamenue'); ?></option><option value="menu_order" <?php selected($order_by,'menu_order'); ?>><?php esc_html_e('Menü-Reihenfolge','schlichtes-megamenue'); ?></option><option value="modified" <?php selected($order_by,'modified'); ?>><?php esc_html_e('Zuletzt geändert','schlichtes-megamenue'); ?></option></select></label></p>
				<p class="field-half"><label><?php esc_html_e( 'Reihenfolge', 'schlichtes-megamenue' ); ?><br><select class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_order]"><option value="ASC" <?php selected($order,'ASC'); ?>><?php esc_html_e('Aufsteigend','schlichtes-megamenue'); ?></option><option value="DESC" <?php selected($order,'DESC'); ?>><?php esc_html_e('Absteigend','schlichtes-megamenue'); ?></option></select></label></p>
				<p class="field-half"><label><?php esc_html_e( 'Maximal anzeigen', 'schlichtes-megamenue' ); ?><br><input type="number" min="1" max="20" class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_count]" value="<?php echo esc_attr( $count ); ?>"></label></p>
				<p class="field-half"><label><input type="checkbox" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_show_date]" value="1" <?php checked($show_date); ?>> <?php esc_html_e( 'Datum anzeigen', 'schlichtes-megamenue' ); ?></label></p>
				<hr><p class="field-wide"><label><input type="checkbox" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_more_enabled]" value="1" <?php checked($more_enabled); ?>> <strong><?php esc_html_e( '„Mehr anzeigen“-Link', 'schlichtes-megamenue' ); ?></strong></label></p>
				<p class="field-wide"><label><?php esc_html_e( 'Linktext', 'schlichtes-megamenue' ); ?><br><input type="text" class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_more_label]" value="<?php echo esc_attr( $more_label ); ?>"></label></p>
				<p class="field-wide"><label><?php esc_html_e( 'Ziel von „Mehr anzeigen“', 'schlichtes-megamenue' ); ?><br><select class="widefat smm-auto-more-target" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_more_target]"><option value="auto" <?php selected($more_target,'auto'); ?>><?php esc_html_e('Automatisch passend zur Liste','schlichtes-megamenue'); ?></option><option value="taxonomy" <?php selected($more_target,'taxonomy'); ?>><?php esc_html_e('Gewählte Kategorie / Taxonomie','schlichtes-megamenue'); ?></option><option value="parent" <?php selected($more_target,'parent'); ?>><?php esc_html_e('Gewählter übergeordneter Inhalt','schlichtes-megamenue'); ?></option><option value="archive" <?php selected($more_target,'archive'); ?>><?php esc_html_e('Archiv des Inhaltstyps','schlichtes-megamenue'); ?></option><option value="linked" <?php selected($more_target,'linked'); ?>><?php esc_html_e('Bestimmte Seite / Beitrag / Information','schlichtes-megamenue'); ?></option><option value="custom" <?php selected($more_target,'custom'); ?>><?php esc_html_e('Eigene URL','schlichtes-megamenue'); ?></option></select></label></p>
				<p class="field-wide smm-auto-more-linked-wrap"><label><?php esc_html_e( 'Ziel-Inhalt', 'schlichtes-megamenue' ); ?><br><select class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_more_link_id]"><?php self::render_heading_content_options( $more_link_id ); ?></select></label><span class="description"><?php esc_html_e( 'Titeländerungen und Permalinks dieses Inhalts werden automatisch berücksichtigt.', 'schlichtes-megamenue' ); ?></span></p>
				<p class="field-wide smm-auto-more-custom-wrap"><label><?php esc_html_e( 'Eigene URL', 'schlichtes-megamenue' ); ?><br><input type="url" class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][auto_more_url]" value="<?php echo esc_attr( $more_url ); ?>"></label></p>
				<p class="field-wide smm-auto-more-source-note"><span class="description"></span></p>
			</div>
			<?php
			return;
		}

		$submenu_type = get_post_meta( $item_id, '_smm_submenu_type', true );
		if ( ! in_array( $submenu_type, array( 'none', 'dropdown', 'mega' ), true ) ) $submenu_type = 'none';
		?>
		<div class="smm-native-item-fields smm-kind-normal"><p class="field-wide"><label><strong><?php esc_html_e( 'Schlichtes Megamenü', 'schlichtes-megamenue' ); ?></strong><br><select class="widefat" name="smm_item[<?php echo esc_attr( $item_id ); ?>][submenu_type]"><option value="none" <?php selected($submenu_type,'none'); ?>><?php esc_html_e('Kein Untermenü','schlichtes-megamenue'); ?></option><option value="dropdown" <?php selected($submenu_type,'dropdown'); ?>><?php esc_html_e('Normales Dropdown','schlichtes-megamenue'); ?></option><option value="mega" <?php selected($submenu_type,'mega'); ?>><?php esc_html_e('Megamenü','schlichtes-megamenue'); ?></option></select></label><span class="description"><?php esc_html_e( 'Diese Einstellung wirkt, wenn der Eintrag Hauptmenüpunkt ist.', 'schlichtes-megamenue' ); ?></span></p></div>
		<?php
	}

	public static function save_nav_menu_item_fields( $menu_id, $menu_item_db_id, $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) || empty( $_POST['smm_item'][ $menu_item_db_id ] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data = wp_unslash( $_POST['smm_item'][ $menu_item_db_id ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$kind = isset( $data['kind'] ) ? sanitize_key( $data['kind'] ) : get_post_meta( $menu_item_db_id, '_smm_kind', true );
		if ( 'column' === $kind ) {
			update_post_meta( $menu_item_db_id, '_smm_kind', 'column' );
			update_post_meta( $menu_item_db_id, '_smm_column_heading', isset($data['column_heading']) ? sanitize_text_field($data['column_heading']) : '' );
			return;
		}
		if ( 'auto' === $kind ) {
			update_post_meta( $menu_item_db_id, '_smm_kind', 'auto' );
			update_post_meta( $menu_item_db_id, '_smm_auto_heading', isset($data['auto_heading']) ? sanitize_text_field($data['auto_heading']) : '' );
			$heading_mode = isset($data['auto_heading_mode']) && in_array($data['auto_heading_mode'],array('none','manual','linked','parent'),true) ? $data['auto_heading_mode'] : 'none';
			update_post_meta( $menu_item_db_id, '_smm_auto_heading_mode', $heading_mode );
			$heading_link_id = isset($data['auto_heading_link_id']) ? absint($data['auto_heading_link_id']) : 0;
			if ( $heading_link_id && ! self::get_valid_heading_link_post( $heading_link_id ) ) $heading_link_id = 0;
			update_post_meta( $menu_item_db_id, '_smm_auto_heading_link_id', $heading_link_id );
			update_post_meta( $menu_item_db_id, '_smm_auto_post_type', isset($data['auto_post_type']) ? sanitize_key($data['auto_post_type']) : 'post' );
			update_post_meta( $menu_item_db_id, '_smm_auto_taxonomy', isset($data['auto_taxonomy']) ? sanitize_key($data['auto_taxonomy']) : '' );
			update_post_meta( $menu_item_db_id, '_smm_auto_term_id', isset($data['auto_term_id']) ? absint($data['auto_term_id']) : 0 );
			update_post_meta( $menu_item_db_id, '_smm_auto_parent_id', isset($data['auto_parent_id']) ? max(-1,(int)$data['auto_parent_id']) : 0 );
			$order_by = isset($data['auto_orderby']) && in_array($data['auto_orderby'],array('date','title','menu_order','modified'),true) ? $data['auto_orderby'] : 'date';
			update_post_meta( $menu_item_db_id, '_smm_auto_orderby', $order_by );
			update_post_meta( $menu_item_db_id, '_smm_auto_order', isset($data['auto_order']) && 'ASC' === strtoupper($data['auto_order']) ? 'ASC' : 'DESC' );
			update_post_meta( $menu_item_db_id, '_smm_auto_count', self::clamp_int(isset($data['auto_count'])?$data['auto_count']:5,1,20) );
			update_post_meta( $menu_item_db_id, '_smm_auto_show_date', !empty($data['auto_show_date']) ? 1 : 0 );
			update_post_meta( $menu_item_db_id, '_smm_auto_more_enabled', !empty($data['auto_more_enabled']) ? 1 : 0 );
			update_post_meta( $menu_item_db_id, '_smm_auto_more_label', isset($data['auto_more_label']) ? sanitize_text_field($data['auto_more_label']) : __( 'Mehr anzeigen', 'schlichtes-megamenue' ) );
			$more_target = isset($data['auto_more_target']) && in_array($data['auto_more_target'],array('auto','taxonomy','parent','archive','linked','custom'),true) ? $data['auto_more_target'] : 'auto';
			update_post_meta( $menu_item_db_id, '_smm_auto_more_target', $more_target );
			$more_link_id = isset($data['auto_more_link_id']) ? absint($data['auto_more_link_id']) : 0;
			if ( $more_link_id && ! self::get_valid_heading_link_post( $more_link_id ) ) $more_link_id = 0;
			update_post_meta( $menu_item_db_id, '_smm_auto_more_link_id', $more_link_id );
			update_post_meta( $menu_item_db_id, '_smm_auto_more_url', isset($data['auto_more_url']) ? esc_url_raw($data['auto_more_url']) : '' );
			return;
		}

		delete_post_meta( $menu_item_db_id, '_smm_kind' );
		$type = isset( $data['submenu_type'] ) && in_array( $data['submenu_type'], array( 'none','dropdown','mega' ), true ) ? $data['submenu_type'] : 'none';
		update_post_meta( $menu_item_db_id, '_smm_submenu_type', $type );
	}


	public static function handle_migrate_legacy_menu() {
		$legacy_id = isset( $_POST['legacy_id'] ) ? absint( $_POST['legacy_id'] ) : 0;
		if ( ! $legacy_id || 'smm_menu' !== get_post_type( $legacy_id ) || ! current_user_can( 'edit_theme_options' ) ) wp_die( esc_html__( 'Nicht erlaubt.', 'schlichtes-megamenue' ) );
		check_admin_referer( 'smm_migrate_legacy_' . $legacy_id );
		$existing = absint( get_post_meta( $legacy_id, '_smm_migrated_native_id', true ) );
		if ( $existing && wp_get_nav_menu_object( $existing ) ) {
			wp_safe_redirect( admin_url( 'nav-menus.php?action=edit&menu=' . $existing ) ); exit;
		}
		$name = get_the_title( $legacy_id ); if ( ! $name ) $name = __( 'Importiertes Megamenü', 'schlichtes-megamenue' );
		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) wp_die( esc_html( $menu_id->get_error_message() ) );
		$config = self::get_menu_config( $legacy_id );
		update_term_meta( $menu_id, '_smm_native_settings', isset($config['settings']) ? self::sanitize_native_settings($config['settings']) : self::menu_defaults() );
		foreach ( $config['items'] as $item ) self::migrate_legacy_item_to_native( $menu_id, $item, 0 );
		update_post_meta( $legacy_id, '_smm_migrated_native_id', $menu_id );
		wp_safe_redirect( admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ) ); exit;
	}

	private static function migrate_legacy_item_to_native( $menu_id, $item, $parent_id ) {
		if ( ! is_array($item) ) return 0;
		$kind = isset($item['kind']) ? $item['kind'] : 'linked';
		$title = '';
		$args = array( 'menu-item-parent-id' => absint($parent_id), 'menu-item-status' => 'publish' );
		if ( 'linked' === $kind && !empty($item['objectId']) ) {
			if ( isset($item['objectKind']) && 'taxonomy' === $item['objectKind'] ) {
				$args += array('menu-item-object-id'=>absint($item['objectId']),'menu-item-object'=>sanitize_key($item['objectType']),'menu-item-type'=>'taxonomy');
			} else {
				$post = get_post(absint($item['objectId']));
				if ( $post ) $args += array('menu-item-object-id'=>$post->ID,'menu-item-object'=>$post->post_type,'menu-item-type'=>'post_type');
				else $args += array('menu-item-title'=>isset($item['editorLabel'])?$item['editorLabel']:'','menu-item-url'=>'#','menu-item-type'=>'custom');
			}
			if ( !empty($item['labelOverride']) ) $args['menu-item-title'] = sanitize_text_field($item['labelOverride']);
		} elseif ( 'custom' === $kind ) {
			$args += array('menu-item-title'=>isset($item['labelOverride'])?$item['labelOverride']:'Eigener Link','menu-item-url'=>isset($item['url'])?$item['url']:'#','menu-item-type'=>'custom');
		} elseif ( 'column' === $kind ) {
			$title = !empty($item['heading']) ? $item['heading'] : __( 'Spalte', 'schlichtes-megamenue' );
			$args += array('menu-item-title'=>$title,'menu-item-url'=>'#','menu-item-type'=>'custom');
		} elseif ( 'auto' === $kind ) {
			$title = !empty($item['heading']) ? $item['heading'] : __( 'Neueste Inhalte', 'schlichtes-megamenue' );
			$args += array('menu-item-title'=>$title,'menu-item-url'=>'#','menu-item-type'=>'custom');
		} else return 0;
		$new_id = wp_update_nav_menu_item( $menu_id, 0, $args );
		if ( is_wp_error($new_id) ) return 0;
		if ( 'column' === $kind ) { update_post_meta($new_id,'_smm_kind','column'); update_post_meta($new_id,'_smm_column_heading',$title); }
		if ( 'auto' === $kind ) {
			update_post_meta($new_id,'_smm_kind','auto'); update_post_meta($new_id,'_smm_auto_heading',$title); update_post_meta($new_id,'_smm_auto_heading_mode','' !== trim((string)$title) ? 'manual' : 'none'); update_post_meta($new_id,'_smm_auto_heading_link_id',0);
			update_post_meta($new_id,'_smm_auto_post_type',isset($item['postType'])?$item['postType']:'post'); update_post_meta($new_id,'_smm_auto_taxonomy',isset($item['taxonomy'])?$item['taxonomy']:''); update_post_meta($new_id,'_smm_auto_term_id',isset($item['termId'])?$item['termId']:0); update_post_meta($new_id,'_smm_auto_parent_id',isset($item['parentId'])?$item['parentId']:0); update_post_meta($new_id,'_smm_auto_orderby',isset($item['orderBy'])?$item['orderBy']:'date'); update_post_meta($new_id,'_smm_auto_order',isset($item['order'])?$item['order']:'DESC'); update_post_meta($new_id,'_smm_auto_count',isset($item['count'])?$item['count']:5); update_post_meta($new_id,'_smm_auto_show_date',!empty($item['showDate'])?1:0); update_post_meta($new_id,'_smm_auto_more_enabled',!empty($item['moreEnabled'])?1:0); update_post_meta($new_id,'_smm_auto_more_label',isset($item['moreLabel'])?$item['moreLabel']:'Mehr anzeigen'); update_post_meta($new_id,'_smm_auto_more_target',isset($item['moreTarget'])?$item['moreTarget']:'auto'); update_post_meta($new_id,'_smm_auto_more_link_id',isset($item['moreLinkId'])?absint($item['moreLinkId']):0); update_post_meta($new_id,'_smm_auto_more_url',isset($item['moreUrl'])?$item['moreUrl']:'');
		}
		if ( in_array($kind,array('linked','custom'),true) ) update_post_meta($new_id,'_smm_submenu_type',isset($item['submenuType'])?$item['submenuType']:'none');
		foreach ( isset($item['children']) && is_array($item['children']) ? $item['children'] : array() as $child ) self::migrate_legacy_item_to_native( $menu_id, $child, $new_id );
		return $new_id;
	}

	public static function ajax_native_preview() {
		check_ajax_referer( 'smm_native_preview', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) wp_send_json_error(array('message'=>'Forbidden'),403);
		$menu_id = isset($_POST['menuId']) ? absint($_POST['menuId']) : 0;
		$settings_json = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '{}';
		$settings = json_decode($settings_json,true); $settings = self::sanitize_native_settings(is_array($settings)?$settings:array());
		wp_send_json_success(array('html'=>self::render_native_menu($menu_id,$settings,true)));
	}

	private static function render_native_menu( $menu_id, $settings = null, $preview = false ) {
		$menu = wp_get_nav_menu_object( absint($menu_id) );
		if ( ! $menu ) return self::placeholder( __( 'Das ausgewählte WordPress-Menü wurde nicht gefunden.', 'schlichtes-megamenue' ) );
		if ( null === $settings ) $settings = self::get_native_menu_settings( $menu->term_id );
		list($tree,$attributes) = self::native_menu_to_tree_and_attributes($menu->term_id,$settings);
		if ( empty($tree) ) return self::placeholder( __( 'Dieses Menü enthält noch keine Einträge.', 'schlichtes-megamenue' ) );
		return self::render_tree_markup($tree,$attributes,$menu->name,$preview);
	}

	/**
	 * Read native menu items in the exact order persisted by WordPress.
	 *
	 * We intentionally query the nav_menu_item posts directly instead of using
	 * wp_get_nav_menu_items(), because that function renumbers the output_key and
	 * exposes the result to filters before our renderer receives it. The database
	 * menu_order value is the source of truth used by the native menu editor.
	 */
	private static function get_native_menu_items_in_saved_order( $menu_id ) {
		global $wpdb;

		$menu = wp_get_nav_menu_object( absint( $menu_id ) );
		if ( ! $menu || empty( $menu->term_taxonomy_id ) ) {
			return array();
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				 WHERE tr.term_taxonomy_id = %d
				   AND p.post_type = 'nav_menu_item'
				   AND p.post_status = 'publish'
				 ORDER BY p.menu_order ASC, p.ID ASC",
				(int) $menu->term_taxonomy_id
			)
		);

		$items = array();
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			$item = wp_setup_nav_menu_item( $post );
			if ( $item && _is_valid_nav_menu_item( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	private static function native_menu_to_tree_and_attributes( $menu_id, $settings ) {
		$items = self::get_native_menu_items_in_saved_order( $menu_id );

		/*
		 * Keep the exact order stored by the native WordPress menu editor.
		 * wp_get_nav_menu_items() normalizes/renumbers its output and can also be
		 * changed by filters. For the mega-menu renderer we deliberately use the
		 * persisted nav_menu_item positions and only group that already ordered
		 * sequence by parent.
		 */
		$by_parent = array();
		foreach ( $items as $item ) {
			$parent = (int) $item->menu_item_parent;
			if ( ! isset( $by_parent[ $parent ] ) ) {
				$by_parent[ $parent ] = array();
			}
			$by_parent[ $parent ][] = $item;
		}
		$attributes = array_merge(self::menu_defaults(),array('itemModes'=>array(),'dynamicPostColumns'=>array(),'megaColumnHeadings'=>array()),is_array($settings)?$settings:array());
		$tree=array();
		foreach(isset($by_parent[0])?$by_parent[0]:array() as $root_item){
			$kind=get_post_meta($root_item->ID,'_smm_kind',true); if(in_array($kind,array('column','auto'),true))continue;
			$node=self::native_item_to_link($root_item,$by_parent,false);
			$children=isset($by_parent[$root_item->ID])?$by_parent[$root_item->ID]:array();
			$mode=get_post_meta($root_item->ID,'_smm_submenu_type',true); if(!in_array($mode,array('none','dropdown','mega'),true))$mode='none'; if('none'===$mode && !empty($children))$mode='dropdown';
			$attributes['itemModes'][$node['key']]=$mode;
			$node['children']=array();
			if('dropdown'===$mode){ foreach($children as $child){ if(in_array(get_post_meta($child->ID,'_smm_kind',true),array('column','auto'),true))continue; $node['children'][]=self::native_item_to_link($child,$by_parent,true); } }
			elseif('mega'===$mode){
				foreach($children as $child){
					$child_kind=get_post_meta($child->ID,'_smm_kind',true);
					if('auto'===$child_kind){
						$node['children'][]=array(
							'key'=>'native-auto:'.$child->ID,
							'__smm_kind'=>'auto',
							'__smm_auto_config'=>self::native_auto_config($child->ID),
							'label'=>'', 'url'=>'', 'title'=>'', 'description'=>'', 'target'=>'', 'rel'=>'', 'children'=>array(),
						);
						continue;
					}
					if('column'===$child_kind){
						$col=array('key'=>'column:'.$child->ID,'label'=>get_post_meta($child->ID,'_smm_column_heading',true)?:$child->title,'url'=>'','title'=>'','description'=>'','target'=>'','rel'=>'','children'=>array());
						foreach(isset($by_parent[$child->ID])?$by_parent[$child->ID]:array() as $grand){ if(in_array(get_post_meta($grand->ID,'_smm_kind',true),array('column','auto'),true))continue; $col['children'][]=self::native_item_to_link($grand,$by_parent,true); }
						$node['children'][]=$col; continue;
					}
					$node['children'][]=self::native_item_to_link($child,$by_parent,true);
				}
			}
			$tree[]=$node;
		}
		return array($tree,$attributes);
	}

	private static function native_item_to_link( $item, $by_parent, $recursive = true ) {
		$children=array();
		if($recursive){ foreach(isset($by_parent[$item->ID])?$by_parent[$item->ID]:array() as $child){ if(in_array(get_post_meta($child->ID,'_smm_kind',true),array('column','auto'),true))continue; $children[]=self::native_item_to_link($child,$by_parent,true); } }
		return array('key'=>'navitem:'.$item->ID,'label'=>wp_strip_all_tags($item->title),'url'=>$item->url,'title'=>isset($item->attr_title)?$item->attr_title:'','description'=>isset($item->description)?$item->description:'','target'=>isset($item->target)?$item->target:'','rel'=>isset($item->xfn)?$item->xfn:'','children'=>$children);
	}

	private static function native_auto_config( $item_id ) {
		return array(
			'id'=>'native-auto-'.absint($item_id),
			'post_type'=>get_post_meta($item_id,'_smm_auto_post_type',true)?:'post',
			'taxonomy'=>get_post_meta($item_id,'_smm_auto_taxonomy',true)?:'',
			'taxonomy_term_id'=>(int)get_post_meta($item_id,'_smm_auto_term_id',true),
			'parent_id'=>(int)get_post_meta($item_id,'_smm_auto_parent_id',true),
			'order_by'=>get_post_meta($item_id,'_smm_auto_orderby',true)?:'date',
			'order'=>get_post_meta($item_id,'_smm_auto_order',true)?:'DESC',
			'count'=>self::clamp_int(get_post_meta($item_id,'_smm_auto_count',true)?:5,1,20),
			'show_date'=>(bool)get_post_meta($item_id,'_smm_auto_show_date',true),
			'heading'=>get_post_meta($item_id,'_smm_auto_heading',true)?:'',
			'heading_mode'=>in_array(get_post_meta($item_id,'_smm_auto_heading_mode',true),array('none','manual','linked','parent'),true) ? get_post_meta($item_id,'_smm_auto_heading_mode',true) : ( get_post_meta($item_id,'_smm_auto_heading',true) ? 'manual' : 'none' ),
			'heading_link_id'=>absint(get_post_meta($item_id,'_smm_auto_heading_link_id',true)),
			'more_enabled'=>(bool)get_post_meta($item_id,'_smm_auto_more_enabled',true),
			'more_label'=>get_post_meta($item_id,'_smm_auto_more_label',true)?:__( 'Mehr anzeigen', 'schlichtes-megamenue' ),
			'more_target'=>get_post_meta($item_id,'_smm_auto_more_target',true)?:'auto',
			'more_link_id'=>absint(get_post_meta($item_id,'_smm_auto_more_link_id',true)),
			'more_url'=>get_post_meta($item_id,'_smm_auto_more_url',true)?:'',
		);
	}

	public static function handle_create_menu() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'schlichtes-megamenue' ) );
		}
		check_admin_referer( 'smm_create_menu' );
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : __( 'Neues Megamenü', 'schlichtes-megamenue' );
		$id = wp_insert_post( array( 'post_type' => 'smm_menu', 'post_status' => 'publish', 'post_title' => $title ), true );
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		update_post_meta( $id, '_smm_config', self::empty_menu_config() );
		wp_safe_redirect( admin_url( 'admin.php?page=smm-mega-menu&menu_id=' . absint( $id ) ) );
		exit;
	}

	public static function handle_save_menu() {
		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		if ( ! $menu_id || 'smm_menu' !== get_post_type( $menu_id ) || ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Ungültiges Menü oder keine Berechtigung.', 'schlichtes-megamenue' ) );
		}
		check_admin_referer( 'smm_save_menu_' . $menu_id );
		$title = isset( $_POST['menu_title'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_title'] ) ) : get_the_title( $menu_id );
		$json = isset( $_POST['config_json'] ) ? wp_unslash( $_POST['config_json'] ) : '';
		$config = json_decode( $json, true );
		$config = self::sanitize_menu_config( $config );
		update_post_meta( $menu_id, '_smm_config', $config );
		wp_update_post( array( 'ID' => $menu_id, 'post_title' => $title ) );
		wp_safe_redirect( admin_url( 'admin.php?page=smm-mega-menu&menu_id=' . $menu_id . '&saved=1' ) );
		exit;
	}

	public static function handle_delete_menu() {
		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		if ( ! $menu_id || 'smm_menu' !== get_post_type( $menu_id ) || ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Ungültiges Menü oder keine Berechtigung.', 'schlichtes-megamenue' ) );
		}
		check_admin_referer( 'smm_delete_menu_' . $menu_id );
		wp_delete_post( $menu_id, true );
		wp_safe_redirect( admin_url( 'admin.php?page=smm-mega-menu' ) );
		exit;
	}


	public static function handle_import_navigation() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'schlichtes-megamenue' ) );
		}
		check_admin_referer( 'smm_import_navigation' );
		$navigation_id = isset( $_POST['navigation_id'] ) ? absint( $_POST['navigation_id'] ) : 0;
		$navigation = get_post( $navigation_id );
		if ( ! $navigation || 'wp_navigation' !== $navigation->post_type ) {
			wp_die( esc_html__( 'Navigation nicht gefunden.', 'schlichtes-megamenue' ) );
		}
		$legacy_attrs = self::find_legacy_block_attributes( $navigation_id );
		$config = self::import_navigation_config( $navigation, $legacy_attrs );
		$title = trim( get_the_title( $navigation ) );
		if ( '' === $title ) {
			$title = __( 'Importiertes Megamenü', 'schlichtes-megamenue' );
		}
		$new_id = wp_insert_post(
			array(
				'post_type'   => 'smm_menu',
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}
		update_post_meta( $new_id, '_smm_config', self::sanitize_menu_config( $config ) );
		update_post_meta( $new_id, '_smm_source_navigation_id', $navigation_id );
		wp_safe_redirect( admin_url( 'admin.php?page=smm-mega-menu&menu_id=' . absint( $new_id ) . '&imported=1' ) );
		exit;
	}

	private static function find_legacy_block_attributes( $navigation_id ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '<!-- wp:smm/mega-menu' ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft') AND post_content LIKE %s ORDER BY post_modified_gmt DESC LIMIT 150",
				$like
			)
		);
		foreach ( $rows as $row ) {
			$found = self::find_smm_block_in_blocks( parse_blocks( $row->post_content ), $navigation_id );
			if ( is_array( $found ) ) {
				return $found;
			}
		}
		return array();
	}

	private static function find_smm_block_in_blocks( $blocks, $navigation_id ) {
		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && 'smm/mega-menu' === $block['blockName'] ) {
				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				if ( absint( isset( $attrs['navigationId'] ) ? $attrs['navigationId'] : 0 ) === absint( $navigation_id ) ) {
					return $attrs;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::find_smm_block_in_blocks( $block['innerBlocks'], $navigation_id );
				if ( is_array( $found ) ) {
					return $found;
				}
			}
		}
		return null;
	}

	private static function import_navigation_config( $navigation, $legacy_attrs ) {
		$tree = self::normalize_navigation_blocks( parse_blocks( $navigation->post_content ), '', true );
		$config = self::empty_menu_config();
		$defaults = self::menu_defaults();
		foreach ( $defaults as $key => $default ) {
			if ( array_key_exists( $key, $legacy_attrs ) ) {
				$config['settings'][ $key ] = $legacy_attrs[ $key ];
			}
		}
		foreach ( $tree as $top_index => $item ) {
			$top = self::config_item_from_normalized( $item );
			$mode = self::get_item_mode( $legacy_attrs, isset( $item['key'] ) ? $item['key'] : '', $top_index );
			$top['submenuType'] = empty( $item['children'] ) && empty( self::get_dynamic_post_columns( $legacy_attrs, isset( $item['key'] ) ? $item['key'] : '', $top_index ) ) ? 'none' : $mode;
			$top['children'] = array();
			if ( 'dropdown' === $top['submenuType'] ) {
				foreach ( $item['children'] as $child ) {
					$top['children'][] = self::config_item_from_normalized_recursive( $child );
				}
			} elseif ( 'mega' === $top['submenuType'] ) {
				foreach ( $item['children'] as $column_index => $column ) {
					$custom_heading = self::get_custom_column_heading(
						$legacy_attrs,
						isset( $item['key'] ) ? $item['key'] : '',
						$top_index,
						isset( $column['key'] ) ? $column['key'] : '',
						$column_index
					);
					$col = array(
						'id'            => wp_generate_uuid4(),
						'kind'          => 'column',
						'heading'       => '' !== $custom_heading ? $custom_heading : $column['label'],
						'labelOverride' => '',
						'url'           => '',
						'objectId'      => 0,
						'objectType'    => '',
						'objectKind'    => 'post-type',
						'submenuType'   => 'none',
						'openNewTab'    => false,
						'postType'      => 'post',
						'taxonomy'      => '',
						'termId'        => 0,
						'count'         => 5,
						'showDate'      => false,
						'moreEnabled'   => false,
						'moreLabel'     => __( 'Mehr anzeigen', 'schlichtes-megamenue' ),
						'moreTarget'    => 'auto',
						'moreLinkId'    => 0,
						'moreUrl'       => '',
						'children'      => array(),
					);
					if ( '' !== $custom_heading && ! empty( $column['url'] ) && '#' !== $column['url'] ) {
						$col['children'][] = self::config_item_from_normalized( $column );
					}
					foreach ( $column['children'] as $child ) {
						$col['children'][] = self::config_item_from_normalized_recursive( $child );
					}
					$top['children'][] = $col;
				}
				$dynamic = self::get_dynamic_post_columns( $legacy_attrs, isset( $item['key'] ) ? $item['key'] : '', $top_index );
				foreach ( $dynamic as $dyn ) {
					$top['children'][] = array(
						'id'            => wp_generate_uuid4(),
						'kind'          => 'auto',
						'heading'       => $dyn['heading'],
						'headingMode'   => isset( $dyn['heading_mode'] ) ? $dyn['heading_mode'] : ( ! empty( $dyn['heading'] ) ? 'manual' : 'none' ),
						'headingLinkId' => isset( $dyn['heading_link_id'] ) ? absint( $dyn['heading_link_id'] ) : 0,
						'labelOverride' => '',
						'url'           => '',
						'objectId'      => 0,
						'objectType'    => '',
						'objectKind'    => 'post-type',
						'submenuType'   => 'none',
						'openNewTab'    => false,
						'postType'      => $dyn['post_type'],
						'taxonomy'      => $dyn['taxonomy'],
						'termId'        => $dyn['taxonomy_term_id'],
						'parentId'      => isset( $dyn['parent_id'] ) ? $dyn['parent_id'] : 0,
						'orderBy'       => isset( $dyn['order_by'] ) ? $dyn['order_by'] : 'date',
						'order'         => isset( $dyn['order'] ) ? $dyn['order'] : 'DESC',
						'count'         => $dyn['count'],
						'showDate'      => $dyn['show_date'],
						'moreEnabled'   => ! empty( $dyn['more_enabled'] ),
						'moreLabel'     => ! empty( $dyn['more_label'] ) ? $dyn['more_label'] : __( 'Mehr anzeigen', 'schlichtes-megamenue' ),
						'moreTarget'    => ! empty( $dyn['more_target'] ) ? $dyn['more_target'] : 'auto',
						'moreLinkId'    => ! empty( $dyn['more_link_id'] ) ? absint( $dyn['more_link_id'] ) : 0,
						'moreUrl'       => ! empty( $dyn['more_url'] ) ? $dyn['more_url'] : '',
						'children'      => array(),
					);
				}
			}
			$config['items'][] = $top;
		}
		return $config;
	}

	private static function config_item_from_normalized_recursive( $item ) {
		$out = self::config_item_from_normalized( $item );
		$out['children'] = array();
		foreach ( isset( $item['children'] ) ? $item['children'] : array() as $child ) {
			$out['children'][] = self::config_item_from_normalized_recursive( $child );
		}
		return $out;
	}

	private static function config_item_from_normalized( $item ) {
		$key = isset( $item['key'] ) ? (string) $item['key'] : '';
		$object_id = 0;
		$object_type = '';
		$object_kind = 'post-type';
		$kind = 'custom';
		if ( preg_match( '/^post-type:([^:]+):(\d+)$/', $key, $m ) ) {
			$kind = 'linked';
			$object_type = sanitize_key( $m[1] );
			$object_id = absint( $m[2] );
		} elseif ( preg_match( '/^taxonomy:([^:]+):(\d+)$/', $key, $m ) ) {
			$kind = 'linked';
			$object_kind = 'taxonomy';
			$object_type = sanitize_key( $m[1] );
			$object_id = absint( $m[2] );
		}
		return array(
			'id'            => wp_generate_uuid4(),
			'kind'          => $kind,
			'labelOverride' => $kind === 'custom' ? ( isset( $item['label'] ) ? $item['label'] : '' ) : '',
			'heading'       => '',
			'headingMode'   => 'none',
			'headingLinkId' => 0,
			'url'           => $kind === 'custom' ? ( isset( $item['url'] ) ? $item['url'] : '' ) : '',
			'objectId'      => $object_id,
			'objectType'    => $object_type,
			'objectKind'    => $object_kind,
			'submenuType'   => 'none',
			'openNewTab'    => isset( $item['target'] ) && '_blank' === $item['target'],
			'postType'      => 'post',
			'taxonomy'      => '',
			'termId'        => 0,
			'parentId'      => 0,
			'orderBy'       => 'date',
			'order'         => 'DESC',
			'count'         => 5,
			'showDate'      => false,
			'moreEnabled'   => false,
			'moreLabel'     => __( 'Mehr anzeigen', 'schlichtes-megamenue' ),
			'moreTarget'    => 'auto',
			'moreLinkId'    => 0,
			'moreUrl'       => '',
			'children'      => array(),
		);
	}

	public static function ajax_search_content() {
		check_ajax_referer( 'smm_admin_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'schlichtes-megamenue' ) ), 403 );
		}
		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}
		$types = array_column( self::get_content_type_options(), 'value' );
		$query = new WP_Query(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				's'              => $search,
				'orderby'        => 'relevance',
				'order'          => 'DESC',
			)
		);
		$results = array();
		foreach ( $query->posts as $post ) {
			$obj = get_post_type_object( $post->post_type );
			$results[] = array(
				'id'       => (int) $post->ID,
				'postType' => $post->post_type,
				'title'    => get_the_title( $post ),
				'typeLabel'=> $obj && ! empty( $obj->labels->singular_name ) ? $obj->labels->singular_name : $post->post_type,
				'url'      => get_permalink( $post ),
			);
		}
		wp_send_json_success( $results );
	}

	public static function ajax_preview() {
		check_ajax_referer( 'smm_admin_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'schlichtes-megamenue' ) ), 403 );
		}
		$json = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '';
		$config = self::sanitize_menu_config( json_decode( $json, true ) );
		wp_send_json_success( array( 'html' => self::render_config_menu( $config, 0, true ) ) );
	}

	private static function link_from_config_item( $item ) {
		$label = ! empty( $item['labelOverride'] ) ? $item['labelOverride'] : '';
		$url = ! empty( $item['url'] ) ? $item['url'] : '';
		$key = 'cfg:' . ( ! empty( $item['id'] ) ? $item['id'] : wp_generate_uuid4() );
		if ( 'linked' === $item['kind'] && ! empty( $item['objectId'] ) ) {
			if ( 'taxonomy' === $item['objectKind'] ) {
				$term = get_term( absint( $item['objectId'] ), $item['objectType'] );
				if ( $term && ! is_wp_error( $term ) ) {
					if ( '' === $label ) $label = $term->name;
					$term_url = get_term_link( $term );
					if ( ! is_wp_error( $term_url ) ) $url = $term_url;
					$key = 'taxonomy:' . $item['objectType'] . ':' . absint( $item['objectId'] );
				}
			} else {
				$post = get_post( absint( $item['objectId'] ) );
				if ( $post && 'publish' === $post->post_status ) {
					if ( '' === $label ) $label = get_the_title( $post );
					$url = get_permalink( $post );
					$key = 'post-type:' . $post->post_type . ':' . $post->ID;
				}
			}
		}
		if ( '' === $label ) $label = __( 'Menüpunkt', 'schlichtes-megamenue' );
		$children = array();
		foreach ( isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array() as $child ) {
			if ( in_array( $child['kind'], array( 'linked', 'custom' ), true ) ) {
				$children[] = self::link_from_config_item( $child );
			}
		}
		return array(
			'key' => $key, 'label' => wp_strip_all_tags( $label ), 'url' => $url, 'title' => '', 'description' => '',
			'target' => ! empty( $item['openNewTab'] ) ? '_blank' : '', 'rel' => '', 'children' => $children,
		);
	}

	private static function config_to_tree_and_attributes( $config ) {
		$attributes = array_merge( self::menu_defaults(), array( 'itemModes' => array(), 'megaColumnHeadings' => array(), 'dynamicPostColumns' => array() ) );
		$attributes = array_merge( $attributes, isset( $config['settings'] ) ? $config['settings'] : array() );
		$tree = array();
		foreach ( $config['items'] as $top_index => $item ) {
			if ( ! in_array( $item['kind'], array( 'linked', 'custom' ), true ) ) continue;
			$node = self::link_from_config_item( $item );
			$mode = isset( $item['submenuType'] ) ? $item['submenuType'] : 'none';
			$attributes['itemModes'][ $node['key'] ] = 'dropdown' === $mode ? 'dropdown' : 'mega';
			$node['children'] = array();
			if ( 'dropdown' === $mode ) {
				foreach ( $item['children'] as $child ) {
					if ( in_array( $child['kind'], array( 'linked', 'custom' ), true ) ) $node['children'][] = self::link_from_config_item( $child );
				}
			} elseif ( 'mega' === $mode ) {
				$dynamic = array();
				foreach ( $item['children'] as $child ) {
					if ( 'column' === $child['kind'] ) {
						$col_children = array();
						foreach ( $child['children'] as $grandchild ) {
							if ( in_array( $grandchild['kind'], array( 'linked', 'custom' ), true ) ) $col_children[] = self::link_from_config_item( $grandchild );
						}
						$node['children'][] = array(
							'key' => 'column:' . $child['id'], 'label' => $child['heading'] ? $child['heading'] : __( 'Spalte', 'schlichtes-megamenue' ),
							'url' => '', 'title' => '', 'description' => '', 'target' => '', 'rel' => '', 'children' => $col_children,
						);
					} elseif ( 'auto' === $child['kind'] ) {
						$dynamic[] = array(
							'id' => $child['id'], 'heading' => $child['heading'], 'postType' => $child['postType'], 'taxonomy' => $child['taxonomy'],
							'termId' => $child['termId'], 'parentId' => isset( $child['parentId'] ) ? $child['parentId'] : 0,
							'orderBy' => isset( $child['orderBy'] ) ? $child['orderBy'] : 'date', 'order' => isset( $child['order'] ) ? $child['order'] : 'DESC',
							'count' => $child['count'], 'showDate' => $child['showDate'],
							'moreEnabled' => $child['moreEnabled'], 'moreLabel' => $child['moreLabel'], 'moreTarget' => $child['moreTarget'], 'moreLinkId' => isset($child['moreLinkId']) ? $child['moreLinkId'] : 0, 'moreUrl' => $child['moreUrl'],
						);
					}
				}
				if ( ! empty( $dynamic ) ) $attributes['dynamicPostColumns'][ $node['key'] ] = $dynamic;
			}
			$tree[] = $node;
		}
		return array( $tree, $attributes );
	}

	private static function render_config_menu( $config, $menu_id = 0, $preview = false ) {
		list( $tree, $attributes ) = self::config_to_tree_and_attributes( $config );
		if ( empty( $tree ) ) return self::placeholder( __( 'Dieses Megamenü enthält noch keine Hauptmenüpunkte.', 'schlichtes-megamenue' ) );
		return self::render_tree_markup( $tree, $attributes, $menu_id ? get_the_title( $menu_id ) : __( 'Megamenü Vorschau', 'schlichtes-megamenue' ), $preview );
	}

	private static function render_tree_markup( $tree, $attributes, $aria_label, $preview = false ) {
		self::$instance_count++;
		$instance_id = 'smm-' . self::$instance_count . '-' . wp_rand( 1000, 9999 );
		$row_id = $instance_id . '-row';
		$behavior = self::choice( self::attr( $attributes, 'behavior', 'hover' ), array( 'hover', 'click' ), 'hover' );
		$click_target = self::choice( self::attr( $attributes, 'clickTarget', 'arrow' ), array( 'arrow', 'item' ), 'arrow' );
		$width_mode = self::choice( self::attr( $attributes, 'panelWidthMode', 'wide' ), array( 'navigation', 'content', 'wide', 'viewport', 'custom' ), 'wide' );
		$alignment = self::choice( self::attr( $attributes, 'panelAlignment', 'center' ), array( 'left', 'center', 'right' ), 'center' );
		$animation = self::choice( self::attr( $attributes, 'animation', 'slide' ), array( 'none', 'fade', 'slide', 'scale' ), 'slide' );
		$design = self::choice( self::attr( $attributes, 'design', 'plain' ), array( 'plain', 'underline', 'pill', 'boxed' ), 'plain' );
		$text_transform = self::choice( self::attr( $attributes, 'textTransform', 'none' ), array( 'none', 'uppercase', 'lowercase', 'capitalize' ), 'none' );
		$bar_border_style = self::choice( self::attr( $attributes, 'barBorderStyle', 'none' ), array( 'none', 'solid', 'dashed', 'dotted' ), 'none' );
		$columns = self::clamp_int( self::attr( $attributes, 'columns', 0 ), 0, 6 );
		$wrapper_attributes = $preview ? 'class="wp-block-smm-mega-menu smm-block-wrapper smm-preview-wrapper"' : get_block_wrapper_attributes( array( 'class' => 'smm-block-wrapper' ) );
		$orientation = self::choice( self::attr( $attributes, 'orientation', 'horizontal' ), array( 'horizontal', 'vertical' ), 'horizontal' );
		$nav_classes = array( 'smm-mega-menu', 'smm-orientation-' . $orientation, 'smm-animation-' . $animation, 'smm-design-' . $design, $columns > 0 ? 'smm-columns-fixed' : 'smm-columns-auto' );
		$style_vars = self::build_css_variables( $attributes, $columns, $text_transform, $bar_border_style );
		$data_attrs = sprintf(
			'data-smm-behavior="%1$s" data-smm-click-target="%2$s" data-smm-open-delay="%3$d" data-smm-close-delay="%4$d" data-smm-breakpoint="%5$d" data-smm-width-mode="%6$s" data-smm-custom-width="%7$d" data-smm-alignment="%8$s" data-smm-edge-gutter="%9$d" data-smm-mobile-collapsed="%10$s"',
			esc_attr( $behavior ), esc_attr( $click_target ), self::clamp_int( self::attr( $attributes, 'openDelay', 100 ), 0, 2000 ),
			self::clamp_int( self::attr( $attributes, 'closeDelay', 240 ), 0, 3000 ), self::clamp_int( self::attr( $attributes, 'mobileBreakpoint', 900 ), 480, 1600 ),
			esc_attr( $width_mode ), self::clamp_int( self::attr( $attributes, 'panelCustomWidth', 1100 ), 320, 2400 ), esc_attr( $alignment ),
			self::clamp_int( self::attr( $attributes, 'edgeGutter', 16 ), 0, 100 ), self::bool_attr( $attributes, 'mobileCollapsed', true ) ? '1' : '0'
		);
		$mobile_label = trim( (string) self::attr( $attributes, 'mobileLabel', __( 'Menü', 'schlichtes-megamenue' ) ) );
		if ( '' === $mobile_label ) $mobile_label = __( 'Menü', 'schlichtes-megamenue' );
		ob_start(); ?>
		<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<nav id="<?php echo esc_attr( $instance_id ); ?>" class="<?php echo esc_attr( implode( ' ', $nav_classes ) ); ?>" aria-label="<?php echo esc_attr( $aria_label ); ?>" style="<?php echo esc_attr( $style_vars ); ?>" <?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<button class="smm-mobile-main-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $instance_id . '-mobile-overlay' ); ?>">
					<span class="smm-mobile-main-label"><?php echo esc_html( $mobile_label ); ?></span><span class="smm-mobile-main-icon" aria-hidden="true"><span></span><span></span><span></span></span>
				</button>
				<div id="<?php echo esc_attr( $instance_id . '-mobile-overlay' ); ?>" class="smm-mobile-overlay" aria-hidden="false">
					<div class="smm-mobile-overlay-bar"><span class="smm-mobile-overlay-title"><?php echo esc_html( $mobile_label ); ?></span><button class="smm-mobile-overlay-close" type="button" aria-label="<?php esc_attr_e( 'Menü schließen', 'schlichtes-megamenue' ); ?>"><span aria-hidden="true"></span><span aria-hidden="true"></span></button></div>
					<div class="smm-mobile-overlay-scroll"><ul id="<?php echo esc_attr( $row_id ); ?>" class="smm-menu-row">
						<?php foreach ( $tree as $index => $item ) echo self::render_top_item( $item, $instance_id . '-' . $index, $attributes, (int) $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</ul></div>
					<p class="smm-mobile-close-hint"><?php esc_html_e( 'Zum Schließen oben rechts auf das X klicken oder Esc drücken.', 'schlichtes-megamenue' ); ?></p>
				</div>
			</nav>
		</div>
		<?php return ob_get_clean();
	}

	public static function register_block() {
		wp_register_script(
			'smm-editor-script',
			plugins_url( 'assets/editor.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
			self::VERSION,
			true
		);

		wp_register_style( 'smm-frontend-style', plugins_url( 'assets/frontend.css', __FILE__ ), array(), self::VERSION );
		wp_register_style( 'smm-editor-style', plugins_url( 'assets/editor.css', __FILE__ ), array( 'smm-frontend-style' ), self::VERSION );
		wp_register_script( 'smm-frontend-script', plugins_url( 'assets/frontend.js', __FILE__ ), array(), self::VERSION, true );

		$native_menus = array( array( 'label' => __( 'WordPress-Menü auswählen …', 'schlichtes-megamenue' ), 'value' => 0 ) );
		foreach ( wp_get_nav_menus( array( 'orderby' => 'name' ) ) as $menu ) {
			$native_menus[] = array( 'label' => $menu->name, 'value' => (int) $menu->term_id );
		}
		$legacy_native_map = array();
		$navigation_native_map = array();
		foreach ( self::get_smm_menus() as $legacy_menu ) {
			$native_id = absint( get_post_meta( $legacy_menu->ID, '_smm_migrated_native_id', true ) );
			if ( $native_id && wp_get_nav_menu_object( $native_id ) ) {
				$legacy_native_map[ (string) $legacy_menu->ID ] = $native_id;
				$source_navigation = absint( get_post_meta( $legacy_menu->ID, '_smm_source_navigation_id', true ) );
				if ( $source_navigation ) $navigation_native_map[ (string) $source_navigation ] = $native_id;
			}
		}
		wp_localize_script(
			'smm-editor-script',
			'SMMBlockData',
			array(
				'nativeMenus'          => $native_menus,
				'legacyNativeMap'      => $legacy_native_map,
				'navigationNativeMap'  => $navigation_native_map,
				'adminUrl'             => admin_url( 'admin.php?page=smm-mega-menu' ),
				'structureUrl'         => admin_url( 'nav-menus.php' ),
				'version'              => self::VERSION,
			)
		);

		register_block_type(
			__DIR__ . '/block',
			array(
				'editor_script'   => 'smm-editor-script',
				'style'           => 'smm-frontend-style',
				'editor_style'    => 'smm-editor-style',
				'script'          => 'smm-frontend-script',
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	public static function register_rest_routes() {
		register_rest_route(
			'smm/v1',
			'/navigation/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_navigation_items' ),
				'permission_callback' => static function() {
					return current_user_can( 'edit_theme_options' );
				},
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => static function( $value ) {
							return absint( $value ) > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			'smm/v1',
			'/content-config',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_content_config' ),
				'permission_callback' => static function() {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);
	}

	public static function rest_content_config() {
		self::ensure_information_post_type_support();

		return rest_ensure_response(
			array(
				'contentTypes'         => self::get_content_type_options(),
				'contentFilters'       => self::get_content_filter_options(),
				'informationAvailable' => (bool) get_post_type_object( 'leitstelle_info' ),
				'version'              => self::VERSION,
			)
		);
	}

	public static function rest_navigation_items( $request ) {
		$navigation_id = absint( $request['id'] );
		$navigation    = get_post( $navigation_id );
		if ( ! $navigation || 'wp_navigation' !== $navigation->post_type || 'publish' !== $navigation->post_status ) {
			return new WP_Error( 'smm_navigation_not_found', __( 'Navigation nicht gefunden.', 'schlichtes-megamenue' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'id'    => $navigation_id,
				'items' => self::navigation_editor_items_for_post( $navigation ),
			)
		);
	}

	private static function get_category_options() {
		$options = array(
			array(
				'label' => __( 'Alle Kategorien', 'schlichtes-megamenue' ),
				'value' => 0,
			),
		);

		$categories = get_categories(
			array(
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		foreach ( $categories as $category ) {
			$options[] = array(
				'label' => $category->name,
				'value' => (int) $category->term_id,
			);
		}
		return $options;
	}


	private static function get_content_type_options() {
		$options = array();
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'objects' );

		// The Information type is also included if another plugin registered it before this block is rendered.
		$information = get_post_type_object( 'leitstelle_info' );
		if ( $information && ! isset( $post_types['leitstelle_info'] ) ) {
			$post_types['leitstelle_info'] = $information;
		}

		foreach ( $post_types as $post_type => $object ) {
			if ( in_array( $post_type, array( 'attachment', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_block' ), true ) ) {
				continue;
			}
			if ( empty( $object->public ) && empty( $object->publicly_queryable ) ) {
				continue;
			}
			$label = ! empty( $object->labels->name ) ? $object->labels->name : $object->label;
			$options[] = array(
				'label' => wp_strip_all_tags( $label ),
				'value' => sanitize_key( $post_type ),
			);
		}

		usort(
			$options,
			static function( $a, $b ) {
				if ( 'post' === $a['value'] ) {
					return -1;
				}
				if ( 'post' === $b['value'] ) {
					return 1;
				}
				if ( 'leitstelle_info' === $a['value'] ) {
					return -1;
				}
				if ( 'leitstelle_info' === $b['value'] ) {
					return 1;
				}
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		return $options;
	}

	private static function get_valid_heading_link_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || 'publish' !== $post->post_status ) return false;
		$object = get_post_type_object( $post->post_type );
		if ( ! $object || ( empty( $object->public ) && empty( $object->publicly_queryable ) ) ) return false;
		return $post;
	}

	private static function get_heading_content_options() {
		static $cache = null;
		if ( null !== $cache ) return $cache;
		$result = array();
		foreach ( self::get_content_type_options() as $type_option ) {
			$post_type = $type_option['value'];
			$posts = get_posts( array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => 750,
				'orderby' => 'title',
				'order' => 'ASC',
				'no_found_rows' => true,
				'suppress_filters' => false,
			) );
			if ( empty( $posts ) ) continue;
			$result[] = array( 'label' => $type_option['label'], 'posts' => $posts );
		}
		$cache = $result;
		return $cache;
	}

	private static function render_heading_content_options( $selected_id = 0 ) {
		echo '<option value="0">' . esc_html__( '– keinen Inhalt auswählen –', 'schlichtes-megamenue' ) . '</option>';
		foreach ( self::get_heading_content_options() as $group ) {
			echo '<optgroup label="' . esc_attr( $group['label'] ) . '">';
			foreach ( $group['posts'] as $post ) {
				echo '<option value="' . esc_attr( $post->ID ) . '"' . selected( (int) $selected_id, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
			}
			echo '</optgroup>';
		}
	}

	private static function get_content_filter_options() {
		$result = array();
		foreach ( self::get_content_type_options() as $type_option ) {
			$post_type = $type_option['value'];
			$filters   = array();
			$taxonomies = get_object_taxonomies( $post_type, 'objects' );
			foreach ( $taxonomies as $taxonomy => $object ) {
				if ( empty( $object->public ) && empty( $object->show_ui ) ) {
					continue;
				}
				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'number'     => 250,
						'orderby'    => 'name',
						'order'      => 'ASC',
					)
				);
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					continue;
				}
				$term_options = array(
					array(
						'label' => sprintf( __( 'Alle %s', 'schlichtes-megamenue' ), ! empty( $object->labels->name ) ? $object->labels->name : $object->label ),
						'value' => 0,
					),
				);
				foreach ( $terms as $term ) {
					$term_options[] = array(
						'label' => $term->name,
						'value' => (int) $term->term_id,
					);
				}
				$filters[] = array(
					'taxonomy' => sanitize_key( $taxonomy ),
					'label'    => ! empty( $object->labels->singular_name ) ? $object->labels->singular_name : $object->label,
					'terms'    => $term_options,
				);
			}
			$result[ $post_type ] = $filters;
		}
		return $result;
	}

	private static function get_content_parent_options() {
		$result = array();
		foreach ( self::get_content_type_options() as $type_option ) {
			$post_type = $type_option['value'];
			$object = get_post_type_object( $post_type );
			if ( ! $object || empty( $object->hierarchical ) ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 500,
					'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
					'no_found_rows'  => true,
				)
			);

			$options = array(
				array( 'label' => __( 'Alle Ebenen', 'schlichtes-megamenue' ), 'value' => 0 ),
				array( 'label' => __( 'Nur ohne übergeordneten Inhalt', 'schlichtes-megamenue' ), 'value' => -1 ),
			);
			$by_parent = array();
			$known_ids = array();
			foreach ( $posts as $post ) {
				$known_ids[ (int) $post->ID ] = true;
				$parent = (int) $post->post_parent;
				if ( ! isset( $by_parent[ $parent ] ) ) {
					$by_parent[ $parent ] = array();
				}
				$by_parent[ $parent ][] = $post;
			}

			$visited = array();
			$append = function( $parent_id, $depth ) use ( &$append, &$options, &$visited, $by_parent ) {
				if ( empty( $by_parent[ $parent_id ] ) || $depth > 12 ) {
					return;
				}
				foreach ( $by_parent[ $parent_id ] as $post ) {
					if ( isset( $visited[ $post->ID ] ) ) {
						continue;
					}
					$visited[ $post->ID ] = true;
					$options[] = array(
						'label' => str_repeat( '— ', $depth ) . wp_strip_all_tags( get_the_title( $post ) ),
						'value' => (int) $post->ID,
					);
					$append( (int) $post->ID, $depth + 1 );
				}
			};
			$append( 0, 0 );

			// Include orphaned entries whose parent is not part of the published result set.
			foreach ( $posts as $post ) {
				if ( ! isset( $visited[ $post->ID ] ) ) {
					$options[] = array(
						'label' => wp_strip_all_tags( get_the_title( $post ) ),
						'value' => (int) $post->ID,
					);
				}
			}
			$result[ $post_type ] = $options;
		}
		return $result;
	}

	private static function get_navigation_options() {
		$posts = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array(
			array(
				'label' => __( 'Navigation auswählen …', 'schlichtes-megamenue' ),
				'value' => 0,
			),
		);

		foreach ( $posts as $post ) {
			$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
			if ( '' === $title ) {
				/* translators: %d is the navigation post ID. */
				$title = sprintf( __( 'Navigation #%d', 'schlichtes-megamenue' ), (int) $post->ID );
			}
			$options[] = array(
				'label' => $title,
				'value' => (int) $post->ID,
			);
		}

		return $options;
	}

	private static function get_navigation_editor_items() {
		$posts = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$data = array();
		foreach ( $posts as $post ) {
			$data[ (string) $post->ID ] = self::navigation_editor_items_for_post( $post );
		}

		return $data;
	}

	private static function navigation_editor_items_for_post( $post ) {
		$tree = self::normalize_navigation_blocks( parse_blocks( $post->post_content ), '', true );
		$top  = array();
		foreach ( $tree as $index => $item ) {
			$children = array();
			foreach ( $item['children'] as $child_index => $child ) {
				$children[] = array(
					'index' => (int) $child_index,
					'key'   => isset( $child['key'] ) ? (string) $child['key'] : 'path:' . $index . '.' . $child_index,
					'label' => (string) $child['label'],
				);
			}
			$top[] = array(
				'index'       => (int) $index,
				'key'         => isset( $item['key'] ) ? (string) $item['key'] : 'path:' . $index,
				'label'       => (string) $item['label'],
				'hasChildren' => ! empty( $item['children'] ),
				'children'    => $children,
			);
		}
		return $top;
	}

	public static function render_block( $attributes, $content, $block ) {
		$native_menu_id = isset( $attributes['navMenuId'] ) ? absint( $attributes['navMenuId'] ) : 0;
		if ( $native_menu_id && wp_get_nav_menu_object( $native_menu_id ) ) {
			return self::render_native_menu( $native_menu_id, null, false );
		}

		// Legacy 4.x block: continue rendering until the menu is migrated.
		$legacy_menu_id = isset( $attributes['menuId'] ) ? absint( $attributes['menuId'] ) : 0;
		if ( $legacy_menu_id && 'smm_menu' === get_post_type( $legacy_menu_id ) ) {
			$migrated = absint( get_post_meta( $legacy_menu_id, '_smm_migrated_native_id', true ) );
			if ( $migrated && wp_get_nav_menu_object( $migrated ) ) return self::render_native_menu( $migrated, null, false );
			return self::render_config_menu( self::get_menu_config( $legacy_menu_id ), $legacy_menu_id, false );
		}

		// Legacy 2.x/3.x block navigation remains readable.
		$navigation_id = isset( $attributes['navigationId'] ) ? absint( $attributes['navigationId'] ) : 0;
		if ( $navigation_id ) {
			$imported = get_posts( array( 'post_type'=>'smm_menu','post_status'=>'publish','posts_per_page'=>1,'meta_key'=>'_smm_source_navigation_id','meta_value'=>$navigation_id,'fields'=>'ids' ) );
			if ( ! empty( $imported ) ) {
				$migrated = absint( get_post_meta( $imported[0], '_smm_migrated_native_id', true ) );
				if ( $migrated && wp_get_nav_menu_object( $migrated ) ) return self::render_native_menu( $migrated, null, false );
				return self::render_config_menu( self::get_menu_config( $imported[0] ), $imported[0], false );
			}
		}

		return self::placeholder( __( 'Bitte in den Block-Einstellungen ein natives WordPress-Menü auswählen.', 'schlichtes-megamenue' ) );
	}

	private static function placeholder( $message ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return '';
		}
		return '<div class="smm-admin-placeholder">' . esc_html( $message ) . '</div>';
	}

	private static function normalize_navigation_blocks( $blocks, $path = '', $sync_linked_titles = true ) {
		$items = array();
		foreach ( $blocks as $block_index => $block ) {
			$name      = isset( $block['blockName'] ) ? $block['blockName'] : '';
			$item_path = '' === $path ? (string) $block_index : $path . '.' . $block_index;
			if ( 'core/navigation-link' === $name || 'core/navigation-submenu' === $name ) {
				$items[] = self::normalize_navigation_item( $block, $item_path, $sync_linked_titles );
			} elseif ( 'core/home-link' === $name ) {
				$attrs   = isset( $block['attrs'] ) ? $block['attrs'] : array();
				$items[] = array(
					'key'         => 'home',
					'label'       => ! empty( $attrs['label'] ) ? wp_strip_all_tags( $attrs['label'] ) : __( 'Startseite', 'schlichtes-megamenue' ),
					'url'         => home_url( '/' ),
					'title'       => '',
					'description' => '',
					'target'      => '',
					'rel'         => '',
					'children'    => array(),
				);
			} elseif ( 'core/page-list' === $name ) {
				$items = array_merge( $items, self::page_list_items() );
			} elseif ( ! empty( $block['innerBlocks'] ) ) {
				$items = array_merge( $items, self::normalize_navigation_blocks( $block['innerBlocks'], $item_path, $sync_linked_titles ) );
			}
		}
		return array_values( array_filter( $items ) );
	}

	private static function normalize_navigation_item( $block, $path = '', $sync_linked_titles = true ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$link  = self::resolve_link_attributes( $attrs, $path );

		return array(
			'key'         => $link['key'],
			'label'       => ( $sync_linked_titles && ! empty( $link['is_linked'] ) && '' !== trim( (string) $link['label'] ) ) ? $link['label'] : ( ! empty( $attrs['label'] ) ? wp_strip_all_tags( $attrs['label'] ) : $link['label'] ),
			'url'         => $link['url'],
			'title'       => isset( $attrs['title'] ) ? wp_strip_all_tags( $attrs['title'] ) : '',
			'description' => isset( $attrs['description'] ) ? wp_strip_all_tags( $attrs['description'] ) : '',
			'target'      => ! empty( $attrs['opensInNewTab'] ) ? '_blank' : '',
			'rel'         => isset( $attrs['rel'] ) ? sanitize_text_field( $attrs['rel'] ) : '',
			'children'    => ! empty( $block['innerBlocks'] ) ? self::normalize_navigation_blocks( $block['innerBlocks'], $path, $sync_linked_titles ) : array(),
		);
	}

	private static function resolve_link_attributes( $attrs, $path = '' ) {
		$saved_url = isset( $attrs['url'] ) ? (string) $attrs['url'] : '';
		$url       = $saved_url;
		$label     = '';
		$id        = isset( $attrs['id'] ) ? absint( $attrs['id'] ) : 0;
		$type      = isset( $attrs['type'] ) ? sanitize_key( $attrs['type'] ) : '';
		$kind      = isset( $attrs['kind'] ) ? sanitize_key( $attrs['kind'] ) : '';
		$key       = '';
		$is_linked = false;

		if ( $id && 'post-type' === $kind ) {
			$post = get_post( $id );
			if ( $post ) {
				$is_linked = true;
				$permalink = get_permalink( $post );
				if ( $permalink ) {
					// Always resolve the current permalink. This keeps navigation links valid after slug/permalink changes.
					$url = $permalink;
				}
				$label = get_the_title( $post );
			}
			$key = 'post-type:' . ( $type ? $type : 'post' ) . ':' . $id;
		} elseif ( $id && 'taxonomy' === $kind && $type ) {
			$term = get_term( $id, $type );
			if ( $term && ! is_wp_error( $term ) ) {
				$is_linked = true;
				$term_link = get_term_link( $term );
				if ( ! is_wp_error( $term_link ) ) {
					// Always resolve the current term link for the same reason as post permalinks above.
					$url = $term_link;
				}
				$label = $term->name;
			}
			$key = 'taxonomy:' . $type . ':' . $id;
		}

		if ( '' === $key ) {
			if ( '' !== trim( $saved_url ) ) {
				$key = 'url:' . $saved_url;
			} else {
				$key = 'path:' . ( '' !== $path ? $path : '0' );
			}
		}

		return array(
			'key'       => $key,
			'url'       => $url,
			'label'     => wp_strip_all_tags( $label ),
			'is_linked' => $is_linked,
		);
	}

	private static function page_list_items() {
		$pages = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish',
			)
		);
		if ( empty( $pages ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $pages as $page ) {
			$parent = (int) $page->post_parent;
			if ( ! isset( $by_parent[ $parent ] ) ) {
				$by_parent[ $parent ] = array();
			}
			$by_parent[ $parent ][] = $page;
		}

		$build = function( $parent ) use ( &$build, $by_parent ) {
			$out = array();
			if ( empty( $by_parent[ $parent ] ) ) {
				return $out;
			}
			foreach ( $by_parent[ $parent ] as $page ) {
				$out[] = array(
					'key'         => 'post-type:page:' . (int) $page->ID,
					'label'       => get_the_title( $page ),
					'url'         => get_permalink( $page ),
					'title'       => '',
					'description' => '',
					'target'      => '',
					'rel'         => '',
					'children'    => $build( (int) $page->ID ),
				);
			}
			return $out;
		};

		return $build( 0 );
	}

	private static function get_dynamic_post_columns( $attributes, $item_key, $top_index ) {
		$all = isset( $attributes['dynamicPostColumns'] ) && is_array( $attributes['dynamicPostColumns'] ) ? $attributes['dynamicPostColumns'] : array();
		$raw = array();
		if ( '' !== $item_key && isset( $all[ $item_key ] ) && is_array( $all[ $item_key ] ) ) {
			$raw = $all[ $item_key ];
		} elseif ( isset( $all[ (string) (int) $top_index ] ) && is_array( $all[ (string) (int) $top_index ] ) ) {
			$raw = $all[ (string) (int) $top_index ];
		}

		$columns = array();
		foreach ( array_slice( $raw, 0, 8 ) as $column ) {
			if ( ! is_array( $column ) ) {
				continue;
			}

			// Native 5.x menu items use snake_case meta-derived keys, while legacy
			// 2.x–4.x configurations use camelCase. Accept both shapes here so the
			// renderer never silently falls back to newest posts.
			$post_type_raw = isset( $column['postType'] ) ? $column['postType'] : ( isset( $column['post_type'] ) ? $column['post_type'] : 'post' );
			$post_type = sanitize_key( (string) $post_type_raw );
			if ( ! post_type_exists( $post_type ) || ! is_post_type_viewable( get_post_type_object( $post_type ) ) ) {
				$post_type = 'post';
			}

			$taxonomy = isset( $column['taxonomy'] ) ? sanitize_key( (string) $column['taxonomy'] ) : '';
			$term_id_raw = isset( $column['termId'] ) ? $column['termId'] : ( isset( $column['taxonomy_term_id'] ) ? $column['taxonomy_term_id'] : 0 );
			$term_id  = absint( $term_id_raw );

			// Backward compatibility with 2.7–2.9 automatic post columns.
			if ( 'post' === $post_type && ! $term_id && ! empty( $column['categoryId'] ) ) {
				$taxonomy = 'category';
				$term_id  = absint( $column['categoryId'] );
			}
			if ( $taxonomy && ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				$taxonomy = '';
				$term_id  = 0;
			}

			$parent_raw = isset( $column['parentId'] ) ? $column['parentId'] : ( isset( $column['parent_id'] ) ? $column['parent_id'] : 0 );
			$order_by_raw = isset( $column['orderBy'] ) ? $column['orderBy'] : ( isset( $column['order_by'] ) ? $column['order_by'] : 'date' );
			$show_date_raw = isset( $column['showDate'] ) ? $column['showDate'] : ( isset( $column['show_date'] ) ? $column['show_date'] : false );
			$more_enabled_raw = isset( $column['moreEnabled'] ) ? $column['moreEnabled'] : ( isset( $column['more_enabled'] ) ? $column['more_enabled'] : false );
			$more_label_raw = isset( $column['moreLabel'] ) ? $column['moreLabel'] : ( isset( $column['more_label'] ) ? $column['more_label'] : __( 'Mehr anzeigen', 'schlichtes-megamenue' ) );
			$more_target_raw = isset( $column['moreTarget'] ) ? $column['moreTarget'] : ( isset( $column['more_target'] ) ? $column['more_target'] : 'auto' );
			$more_link_id_raw = isset( $column['moreLinkId'] ) ? $column['moreLinkId'] : ( isset( $column['more_link_id'] ) ? $column['more_link_id'] : 0 );
			$more_url_raw = isset( $column['moreUrl'] ) ? $column['moreUrl'] : ( isset( $column['more_url'] ) ? $column['more_url'] : '' );
			$heading_mode_raw = isset( $column['headingMode'] ) ? $column['headingMode'] : ( isset( $column['heading_mode'] ) ? $column['heading_mode'] : ( ! empty( $column['heading'] ) ? 'manual' : 'none' ) );
			$heading_link_id_raw = isset( $column['headingLinkId'] ) ? $column['headingLinkId'] : ( isset( $column['heading_link_id'] ) ? $column['heading_link_id'] : 0 );

			$columns[] = array(
				'id'        => isset( $column['id'] ) ? sanitize_key( (string) $column['id'] ) : '',
				'heading'   => isset( $column['heading'] ) ? sanitize_text_field( (string) $column['heading'] ) : '',
				'heading_mode' => in_array( $heading_mode_raw, array( 'none', 'manual', 'linked', 'parent' ), true ) ? $heading_mode_raw : 'none',
				'heading_link_id' => absint( $heading_link_id_raw ),
				'post_type' => $post_type,
				'taxonomy'  => $taxonomy,
				'taxonomy_term_id' => $term_id,
				'parent_id' => max( -1, (int) $parent_raw ),
				'order_by'  => in_array( $order_by_raw, array( 'date', 'title', 'menu_order', 'modified' ), true ) ? $order_by_raw : 'date',
				'order'     => isset( $column['order'] ) && 'ASC' === strtoupper( (string) $column['order'] ) ? 'ASC' : 'DESC',
				'count'     => self::clamp_int( isset( $column['count'] ) ? $column['count'] : 5, 1, 20 ),
				'show_date' => ! empty( $show_date_raw ),
				'more_enabled' => ! empty( $more_enabled_raw ),
				'more_label'   => sanitize_text_field( (string) $more_label_raw ),
				'more_target'  => in_array( $more_target_raw, array( 'auto', 'taxonomy', 'parent', 'archive', 'linked', 'custom' ), true ) ? $more_target_raw : 'auto',
				'more_link_id' => absint( $more_link_id_raw ),
				'more_url'     => esc_url_raw( (string) $more_url_raw ),
			);
		}
		return $columns;
	}

	private static function render_dynamic_post_column( $config ) {
		$order_by = isset( $config['order_by'] ) && in_array( $config['order_by'], array( 'date', 'title', 'menu_order', 'modified' ), true ) ? $config['order_by'] : 'date';
		$order = isset( $config['order'] ) && 'ASC' === strtoupper( (string) $config['order'] ) ? 'ASC' : 'DESC';
		$args = array(
			'post_type'           => $config['post_type'],
			'post_status'         => 'publish',
			'posts_per_page'      => $config['count'] + 1,
			'orderby'             => 'menu_order' === $order_by ? array( 'menu_order' => $order, 'title' => $order ) : $order_by,
			'order'               => $order,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'suppress_filters'    => false,
		);
		$parent_id = isset( $config['parent_id'] ) ? (int) $config['parent_id'] : 0;
		if ( -1 === $parent_id ) {
			$args['post_parent'] = 0;
		} elseif ( $parent_id > 0 ) {
			$parent_post = get_post( $parent_id );
			if ( $parent_post && $config['post_type'] === $parent_post->post_type ) {
				$args['post_parent'] = $parent_id;
			}
		}
		if ( $config['taxonomy'] && $config['taxonomy_term_id'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => $config['taxonomy'],
					'field'    => 'term_id',
					'terms'    => array( $config['taxonomy_term_id'] ),
				),
			);
		}
		$posts = get_posts( $args );
		$has_more = count( $posts ) > $config['count'];
		if ( $has_more ) {
			$posts = array_slice( $posts, 0, $config['count'] );
		}
		$more_url = '';
		if ( ! empty( $config['more_enabled'] ) && $has_more ) {
			$more_target = isset( $config['more_target'] ) && in_array( $config['more_target'], array( 'auto', 'taxonomy', 'parent', 'archive', 'linked', 'custom' ), true ) ? $config['more_target'] : 'auto';
			$resolve_taxonomy = function() use ( $config ) {
				if ( empty( $config['taxonomy'] ) || empty( $config['taxonomy_term_id'] ) ) return '';
				$term = get_term( $config['taxonomy_term_id'], $config['taxonomy'] );
				if ( ! $term || is_wp_error( $term ) ) return '';
				$link = get_term_link( $term );
				return is_wp_error( $link ) ? '' : $link;
			};
			$resolve_parent = function() use ( $config ) {
				if ( empty( $config['parent_id'] ) || (int) $config['parent_id'] <= 0 ) return '';
				$parent_post = get_post( (int) $config['parent_id'] );
				if ( ! $parent_post || $config['post_type'] !== $parent_post->post_type || 'publish' !== $parent_post->post_status ) return '';
				return get_permalink( $parent_post );
			};
			$resolve_archive = function() use ( $config ) {
				$archive = get_post_type_archive_link( $config['post_type'] );
				return $archive ? $archive : '';
			};

			if ( 'custom' === $more_target ) {
				$more_url = ! empty( $config['more_url'] ) ? $config['more_url'] : '';
			} elseif ( 'linked' === $more_target ) {
				$link_post = self::get_valid_heading_link_post( isset( $config['more_link_id'] ) ? $config['more_link_id'] : 0 );
				if ( $link_post ) $more_url = get_permalink( $link_post );
			} elseif ( 'taxonomy' === $more_target ) {
				$more_url = $resolve_taxonomy();
			} elseif ( 'parent' === $more_target ) {
				$more_url = $resolve_parent();
			} elseif ( 'archive' === $more_target ) {
				$more_url = $resolve_archive();
			} else {
				$more_url = $resolve_taxonomy();
				if ( ! $more_url ) $more_url = $resolve_parent();
				if ( ! $more_url ) $more_url = $resolve_archive();
			}
		}

		$post_type_object = get_post_type_object( $config['post_type'] );
		$type_plural      = $post_type_object && ! empty( $post_type_object->labels->name ) ? $post_type_object->labels->name : __( 'Inhalte', 'schlichtes-megamenue' );
		$heading_mode     = isset( $config['heading_mode'] ) && in_array( $config['heading_mode'], array( 'none', 'manual', 'linked', 'parent' ), true ) ? $config['heading_mode'] : ( ! empty( $config['heading'] ) ? 'manual' : 'none' );
		$heading          = '';
		$heading_url      = '';
		if ( 'manual' === $heading_mode ) {
			$heading = trim( (string) $config['heading'] );
			$link_post = self::get_valid_heading_link_post( isset( $config['heading_link_id'] ) ? $config['heading_link_id'] : 0 );
			if ( $link_post ) $heading_url = get_permalink( $link_post );
		} elseif ( 'linked' === $heading_mode ) {
			$link_post = self::get_valid_heading_link_post( isset( $config['heading_link_id'] ) ? $config['heading_link_id'] : 0 );
			if ( $link_post ) { $heading = get_the_title( $link_post ); $heading_url = get_permalink( $link_post ); }
		} elseif ( 'parent' === $heading_mode ) {
			$parent_post = self::get_valid_heading_link_post( isset( $config['parent_id'] ) ? $config['parent_id'] : 0 );
			if ( $parent_post && $config['post_type'] === $parent_post->post_type ) { $heading = get_the_title( $parent_post ); $heading_url = get_permalink( $parent_post ); }
		}

		ob_start();
		?>
		<div class="smm-column smm-dynamic-post-column">
			<?php if ( '' !== $heading ) : ?>
				<?php if ( $heading_url ) : ?><a class="smm-column-heading smm-column-heading-custom smm-column-heading-link" href="<?php echo esc_url( $heading_url ); ?>"><?php echo esc_html( $heading ); ?></a><?php else : ?><span class="smm-column-heading smm-column-heading-custom"><?php echo esc_html( $heading ); ?></span><?php endif; ?>
			<?php endif; ?>
			<?php if ( ! empty( $posts ) ) : ?>
				<ul class="smm-link-list smm-dynamic-post-list">
					<?php foreach ( $posts as $post ) : ?>
						<li class="smm-link-item smm-dynamic-post-item">
							<a class="smm-panel-link smm-dynamic-post-link" href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
							<?php if ( $config['show_date'] ) : ?>
								<time class="smm-post-date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<span class="smm-description"><?php echo esc_html( sprintf( __( 'Keine %s gefunden.', 'schlichtes-megamenue' ), $type_plural ) ); ?></span>
			<?php endif; ?>
			<?php if ( $more_url ) : ?>
				<a class="smm-more-link" href="<?php echo esc_url( $more_url ); ?>"><?php echo esc_html( $config['more_label'] ? $config['more_label'] : __( 'Mehr anzeigen', 'schlichtes-megamenue' ) ); ?><span aria-hidden="true"> →</span></a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_top_item( $item, $item_id, $attributes, $top_index ) {
		$has_children    = ! empty( $item['children'] );
		$current         = self::is_current_branch( $item );
		$item_key        = isset( $item['key'] ) ? (string) $item['key'] : '';
		$mode            = self::get_item_mode( $attributes, $item_key, $top_index );
		$dynamic_columns = self::get_dynamic_post_columns( $attributes, $item_key, $top_index );
		$has_submenu     = $has_children || ( 'mega' === $mode && ! empty( $dynamic_columns ) );
		$classes      = array( 'smm-menu-item' );
		if ( $has_submenu ) {
			$classes[] = 'smm-has-children';
			$classes[] = 'dropdown' === $mode ? 'smm-submenu-dropdown' : 'smm-submenu-mega';
		}
		if ( $current ) {
			$classes[] = 'is-current';
		}

		$panel_id = $item_id . '-panel';
		ob_start();
		?>
		<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-smm-item data-smm-submenu-type="<?php echo esc_attr( $mode ); ?>">
			<div class="smm-trigger-wrap">
				<?php echo self::render_label_link( $item, 'smm-top-link', $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $has_submenu ) : ?>
					<button class="smm-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Untermenü %s öffnen', 'schlichtes-megamenue' ), $item['label'] ) ); ?>">
						<span class="smm-caret" aria-hidden="true"></span>
					</button>
				<?php endif; ?>
			</div>
			<?php if ( $has_submenu ) : ?>
				<?php if ( 'dropdown' === $mode ) : ?>
					<div id="<?php echo esc_attr( $panel_id ); ?>" class="smm-panel smm-dropdown-panel" hidden>
						<?php echo self::render_dropdown_list( $item['children'], $attributes, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php else : ?>
					<div id="<?php echo esc_attr( $panel_id ); ?>" class="smm-panel smm-mega-panel" hidden>
						<div class="smm-panel-inner">
							<?php foreach ( $item['children'] as $column_index => $column ) : ?>
								<?php if ( isset( $column['__smm_kind'] ) && 'auto' === $column['__smm_kind'] && ! empty( $column['__smm_auto_config'] ) ) : ?>
									<?php echo self::render_dynamic_post_column( $column['__smm_auto_config'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php else : ?>
									<?php echo self::render_column( $column, $attributes, $item_key, $top_index, (int) $column_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							<?php endforeach; ?>
							<?php foreach ( $dynamic_columns as $dynamic_column ) : ?>
								<?php echo self::render_dynamic_post_column( $dynamic_column ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</li>
		<?php
		return ob_get_clean();
	}

	private static function get_item_mode( $attributes, $item_key, $top_index ) {
		$modes = isset( $attributes['itemModes'] ) && is_array( $attributes['itemModes'] ) ? $attributes['itemModes'] : array();
		if ( '' !== $item_key && isset( $modes[ $item_key ] ) ) {
			return 'dropdown' === $modes[ $item_key ] ? 'dropdown' : 'mega';
		}
		// Backward compatibility with 2.2/2.3 where settings were stored by array index.
		$legacy_key = (string) (int) $top_index;
		return isset( $modes[ $legacy_key ] ) && 'dropdown' === $modes[ $legacy_key ] ? 'dropdown' : 'mega';
	}

	private static function get_custom_column_heading( $attributes, $item_key, $top_index, $column_key, $column_index ) {
		$all = isset( $attributes['megaColumnHeadings'] ) && is_array( $attributes['megaColumnHeadings'] ) ? $attributes['megaColumnHeadings'] : array();

		if ( '' !== $item_key && ! empty( $all[ $item_key ] ) && is_array( $all[ $item_key ] ) ) {
			if ( '' !== $column_key && array_key_exists( $column_key, $all[ $item_key ] ) ) {
				return sanitize_text_field( (string) $all[ $item_key ][ $column_key ] );
			}
		}

		// Backward compatibility with 2.2/2.3 index-based column headings.
		$top_legacy = (string) (int) $top_index;
		$col_legacy = (string) (int) $column_index;
		if ( ! empty( $all[ $top_legacy ] ) && is_array( $all[ $top_legacy ] ) && array_key_exists( $col_legacy, $all[ $top_legacy ] ) ) {
			return sanitize_text_field( (string) $all[ $top_legacy ][ $col_legacy ] );
		}
		return '';
	}

	private static function render_column( $item, $attributes, $item_key, $top_index, $column_index ) {
		$show_descriptions = self::bool_attr( $attributes, 'showDescriptions', true );
		$column_key        = isset( $item['key'] ) ? (string) $item['key'] : '';
		$custom_heading    = self::get_custom_column_heading( $attributes, $item_key, $top_index, $column_key, $column_index );
		ob_start();
		?>
		<div class="smm-column">
			<?php if ( '' !== $custom_heading ) : ?>
				<span class="smm-column-heading smm-column-heading-custom"><?php echo esc_html( $custom_heading ); ?></span>
			<?php else : ?>
				<?php echo self::render_label_link( $item, 'smm-column-heading', self::is_current_branch( $item ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php if ( $show_descriptions && ! empty( $item['description'] ) ) : ?>
				<span class="smm-description"><?php echo esc_html( $item['description'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $custom_heading && ! empty( $item['url'] ) && '#' !== trim( (string) $item['url'] ) ) : ?>
				<ul class="smm-link-list smm-column-root-list">
					<li class="smm-link-item smm-column-root-item<?php echo self::is_current_item( $item ) ? ' is-current' : ''; ?>">
						<?php echo self::render_label_link( $item, 'smm-panel-link smm-column-root-link', self::is_current_item( $item ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</li>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $item['children'] ) ) : ?>
				<ul class="smm-link-list">
					<?php foreach ( $item['children'] as $child ) : ?>
						<?php echo self::render_nested_item( $child, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_dropdown_list( $items, $attributes, $root = false ) {
		if ( empty( $items ) ) {
			return '';
		}
		ob_start();
		?>
		<ul class="<?php echo esc_attr( $root ? 'smm-dropdown-list' : 'smm-dropdown-sublist' ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<li class="smm-dropdown-item<?php echo self::is_current_branch( $item ) ? ' is-current' : ''; ?>">
					<?php echo self::render_label_link( $item, 'smm-dropdown-link', self::is_current_item( $item ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( ! empty( $item['children'] ) ) : ?>
						<span class="smm-dropdown-caret" aria-hidden="true"></span>
						<?php echo self::render_dropdown_list( $item['children'], $attributes, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return ob_get_clean();
	}

	private static function render_nested_item( $item, $attributes ) {
		$show_descriptions = self::bool_attr( $attributes, 'showDescriptions', true );
		ob_start();
		?>
		<li class="smm-link-item<?php echo self::is_current_item( $item ) ? ' is-current' : ''; ?>">
			<?php echo self::render_label_link( $item, 'smm-panel-link', self::is_current_item( $item ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $show_descriptions && ! empty( $item['description'] ) ) : ?>
				<span class="smm-description"><?php echo esc_html( $item['description'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $item['children'] ) ) : ?>
				<ul class="smm-link-list smm-link-list-nested">
					<?php foreach ( $item['children'] as $child ) : ?>
						<?php echo self::render_nested_item( $child, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</li>
		<?php
		return ob_get_clean();
	}

	private static function render_label_link( $item, $class, $current ) {
		$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
		if ( '' === $label ) {
			$label = __( 'Menüpunkt', 'schlichtes-megamenue' );
		}
		$url = isset( $item['url'] ) ? trim( (string) $item['url'] ) : '';
		if ( '' === $url || '#' === $url ) {
			return '<span class="' . esc_attr( $class . ' smm-label-only' ) . '">' . esc_html( $label ) . '</span>';
		}

		$attrs = ' class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"';
		if ( $current ) {
			$attrs .= ' aria-current="page"';
		}
		if ( ! empty( $item['title'] ) ) {
			$attrs .= ' title="' . esc_attr( $item['title'] ) . '"';
		}
		if ( '_blank' === $item['target'] ) {
			$attrs .= ' target="_blank"';
			$rel = trim( $item['rel'] . ' noopener noreferrer' );
			$attrs .= ' rel="' . esc_attr( $rel ) . '"';
		} elseif ( ! empty( $item['rel'] ) ) {
			$attrs .= ' rel="' . esc_attr( $item['rel'] ) . '"';
		}

		return '<a' . $attrs . '>' . esc_html( $label ) . '</a>';
	}

	private static function is_current_branch( $item ) {
		if ( self::is_current_item( $item ) ) {
			return true;
		}
		if ( ! empty( $item['children'] ) ) {
			foreach ( $item['children'] as $child ) {
				if ( self::is_current_branch( $child ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function is_current_item( $item ) {
		if ( empty( $item['url'] ) ) {
			return false;
		}
		$request_path = isset( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->request ) ? (string) $GLOBALS['wp']->request : '';
		$current_url  = home_url( $request_path );
		$item_host    = wp_parse_url( $item['url'], PHP_URL_HOST );
		$current_host = wp_parse_url( $current_url, PHP_URL_HOST );
		$item_path    = wp_parse_url( $item['url'], PHP_URL_PATH );
		$current_path = wp_parse_url( $current_url, PHP_URL_PATH );
		if ( null === $item_path || null === $current_path ) {
			return false;
		}
		if ( $item_host && $current_host && strtolower( $item_host ) !== strtolower( $current_host ) ) {
			return false;
		}
		return untrailingslashit( $item_path ) === untrailingslashit( $current_path );
	}

	private static function build_css_variables( $a, $columns, $text_transform, $bar_border_style ) {
		$font_family = self::font_family( self::attr( $a, 'fontFamily', 'inherit' ) );
		$dropdown_font_family = self::font_family( self::attr( $a, 'dropdownFontFamily', 'inherit' ) );
		$dropdown_text_transform = self::choice( self::attr( $a, 'dropdownTextTransform', 'none' ), array( 'none', 'uppercase', 'lowercase', 'capitalize' ), 'none' );
		$dropdown_border_style = self::choice( self::attr( $a, 'dropdownBorderStyle', 'solid' ), array( 'none', 'solid', 'dashed', 'dotted' ), 'solid' );
		$vars = array(
			'--smm-font-family'          => $font_family,
			'--smm-font-size'            => self::px( self::attr( $a, 'fontSize', 16 ), 10, 40 ),
			'--smm-font-weight'          => (string) self::clamp_int( self::attr( $a, 'fontWeight', 500 ), 100, 900 ),
			'--smm-line-height'           => (string) self::clamp_float( self::attr( $a, 'lineHeight', 1.35 ), 1, 2.5 ),
			'--smm-letter-spacing'        => self::em( self::attr( $a, 'letterSpacing', 0 ), -0.1, 0.5 ),
			'--smm-text-transform'        => $text_transform,
			'--smm-bar-bg'                => self::color( self::attr( $a, 'barBackground', '#ffffff' ), '#ffffff' ),
			'--smm-bar-text'              => self::color( self::attr( $a, 'barText', '#202124' ), '#202124' ),
			'--smm-bar-hover-bg'          => self::color( self::attr( $a, 'barHoverBackground', '#f2f4f7' ), '#f2f4f7' ),
			'--smm-bar-hover-text'        => self::color( self::attr( $a, 'barHoverText', '#111827' ), '#111827' ),
			'--smm-bar-active-bg'         => self::color( self::attr( $a, 'barActiveBackground', '#eef2ff' ), '#eef2ff' ),
			'--smm-bar-active-text'       => self::color( self::attr( $a, 'barActiveText', '#1d4ed8' ), '#1d4ed8' ),
			'--smm-accent'                => self::color( self::attr( $a, 'accentColor', '#1d4ed8' ), '#1d4ed8' ),
			'--smm-bar-px'                => self::px( self::attr( $a, 'barPaddingX', 16 ), 0, 80 ),
			'--smm-bar-py'                => self::px( self::attr( $a, 'barPaddingY', 12 ), 0, 50 ),
			'--smm-item-gap'              => self::px( self::attr( $a, 'itemGap', 4 ), 0, 60 ),
			'--smm-bar-radius'            => self::px( self::attr( $a, 'barRadius', 4 ), 0, 40 ),
			'--smm-bar-border-style'      => $bar_border_style,
			'--smm-bar-border-width'      => self::px( self::attr( $a, 'barBorderWidth', 0 ), 0, 10 ),
			'--smm-bar-border-color'      => self::color( self::attr( $a, 'barBorderColor', '#e5e7eb' ), '#e5e7eb' ),
			'--smm-dropdown-font-family'  => $dropdown_font_family,
			'--smm-dropdown-font-size'    => self::px( self::attr( $a, 'dropdownFontSize', 15 ), 10, 40 ),
			'--smm-dropdown-font-weight'  => (string) self::clamp_int( self::attr( $a, 'dropdownFontWeight', 400 ), 100, 900 ),
			'--smm-dropdown-line-height'   => (string) self::clamp_float( self::attr( $a, 'dropdownLineHeight', 1.4 ), 1, 2.5 ),
			'--smm-dropdown-letter-spacing' => self::em( self::attr( $a, 'dropdownLetterSpacing', 0 ), -0.1, 0.5 ),
			'--smm-dropdown-text-transform' => $dropdown_text_transform,
			'--smm-dropdown-min-width'     => self::px( self::attr( $a, 'dropdownMinWidth', 220 ), 140, 600 ),
			'--smm-dropdown-padding'       => self::px( self::attr( $a, 'dropdownPadding', 8 ), 0, 40 ),
			'--smm-dropdown-item-px'       => self::px( self::attr( $a, 'dropdownItemPaddingX', 12 ), 0, 60 ),
			'--smm-dropdown-item-py'       => self::px( self::attr( $a, 'dropdownItemPaddingY', 10 ), 0, 40 ),
			'--smm-dropdown-submenu-gap'   => self::px( self::attr( $a, 'dropdownSubmenuGap', 4 ), 0, 30 ),
			'--smm-dropdown-bg'            => self::color( self::attr( $a, 'dropdownBackground', '#ffffff' ), '#ffffff' ),
			'--smm-dropdown-text'          => self::color( self::attr( $a, 'dropdownText', '#30343b' ), '#30343b' ),
			'--smm-dropdown-hover-bg'      => self::color( self::attr( $a, 'dropdownHoverBackground', '#f2f4f7' ), '#f2f4f7' ),
			'--smm-dropdown-hover-text'    => self::color( self::attr( $a, 'dropdownHoverText', '#1d4ed8' ), '#1d4ed8' ),
			'--smm-dropdown-border-color'  => self::color( self::attr( $a, 'dropdownBorderColor', '#e5e7eb' ), '#e5e7eb' ),
			'--smm-dropdown-border-style'  => $dropdown_border_style,
			'--smm-dropdown-border-width'  => self::px( self::attr( $a, 'dropdownBorderWidth', 1 ), 0, 10 ),
			'--smm-dropdown-radius'        => self::px( self::attr( $a, 'dropdownRadius', 8 ), 0, 60 ),
			'--smm-dropdown-shadow-y'      => self::px( self::attr( $a, 'dropdownShadowY', 10 ), 0, 60 ),
			'--smm-dropdown-shadow-blur'   => self::px( self::attr( $a, 'dropdownShadowBlur', 28 ), 0, 100 ),
			'--smm-dropdown-shadow-alpha'  => (string) self::clamp_float( self::attr( $a, 'dropdownShadowOpacity', 0.12 ), 0, 0.6 ),
			'--smm-panel-bg'              => self::color( self::attr( $a, 'panelBackground', '#ffffff' ), '#ffffff' ),
			'--smm-panel-text'            => self::color( self::attr( $a, 'panelText', '#30343b' ), '#30343b' ),
			'--smm-panel-heading'         => self::color( self::attr( $a, 'panelHeading', '#111827' ), '#111827' ),
			'--smm-panel-hover'           => self::color( self::attr( $a, 'panelLinkHover', '#1d4ed8' ), '#1d4ed8' ),
			'--smm-panel-border'          => self::color( self::attr( $a, 'panelBorderColor', '#e5e7eb' ), '#e5e7eb' ),
			'--smm-panel-border-width'    => self::px( self::attr( $a, 'panelBorderWidth', 1 ), 0, 10 ),
			'--smm-panel-font-size'       => self::px( self::attr( $a, 'panelFontSize', 15 ), 10, 32 ),
			'--smm-heading-font-size'     => self::px( self::attr( $a, 'headingFontSize', 16 ), 10, 34 ),
			'--smm-heading-font-weight'   => (string) self::clamp_int( self::attr( $a, 'headingFontWeight', 700 ), 100, 900 ),
			'--smm-panel-padding'         => self::px( self::attr( $a, 'panelPadding', 28 ), 0, 80 ),
			'--smm-column-gap'            => self::px( self::attr( $a, 'columnGap', 30 ), 0, 80 ),
			'--smm-row-gap'               => self::px( self::attr( $a, 'rowGap', 24 ), 0, 80 ),
			'--smm-column-min'            => self::px( self::attr( $a, 'columnMinWidth', 190 ), 120, 500 ),
			'--smm-columns'               => (string) max( 1, $columns ),
			'--smm-panel-radius'          => self::px( self::attr( $a, 'panelRadius', 8 ), 0, 60 ),
			'--smm-panel-offset'          => self::px( self::attr( $a, 'panelOffset', 0 ), 0, 40 ),
			'--smm-edge-gutter'          => self::px( self::attr( $a, 'edgeGutter', 16 ), 0, 100 ),
			'--smm-shadow-y'              => self::px( self::attr( $a, 'shadowY', 14 ), 0, 60 ),
			'--smm-shadow-blur'           => self::px( self::attr( $a, 'shadowBlur', 35 ), 0, 100 ),
			'--smm-shadow-alpha'          => (string) self::clamp_float( self::attr( $a, 'shadowOpacity', 0.14 ), 0, 0.6 ),
			'--smm-duration'              => self::ms( self::attr( $a, 'animationDuration', 180 ), 0, 1500 ),
			'--smm-mobile-bg'             => self::color( self::attr( $a, 'mobileBackground', '#ffffff' ), '#ffffff' ),
			'--smm-mobile-text'           => self::color( self::attr( $a, 'mobileText', '#202124' ), '#202124' ),
			'--smm-mobile-toggle-px'      => self::px( self::attr( $a, 'mobilePaddingX', 16 ), 0, 60 ),
			'--smm-mobile-toggle-py'      => self::px( self::attr( $a, 'mobilePaddingY', 13 ), 0, 40 ),
		);

		return implode(
			';',
			array_map(
				static function( $key, $value ) {
					return $key . ':' . $value;
				},
				array_keys( $vars ),
				$vars
			)
		) . ';';
	}

	private static function attr( $attributes, $key, $default ) {
		return array_key_exists( $key, $attributes ) ? $attributes[ $key ] : $default;
	}

	private static function bool_attr( $attributes, $key, $default ) {
		return array_key_exists( $key, $attributes ) ? (bool) $attributes[ $key ] : (bool) $default;
	}

	private static function choice( $value, $choices, $default ) {
		return in_array( $value, $choices, true ) ? $value : $default;
	}

	private static function clamp_int( $value, $min, $max ) {
		return max( $min, min( $max, (int) $value ) );
	}

	private static function clamp_float( $value, $min, $max ) {
		return max( $min, min( $max, (float) $value ) );
	}

	private static function px( $value, $min, $max ) {
		return self::clamp_float( $value, $min, $max ) . 'px';
	}

	private static function em( $value, $min, $max ) {
		return self::clamp_float( $value, $min, $max ) . 'em';
	}

	private static function ms( $value, $min, $max ) {
		return self::clamp_int( $value, $min, $max ) . 'ms';
	}

	private static function color( $value, $fallback ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|var\(--[A-Za-z0-9_-]+\)|transparent|currentColor)$/', $value ) ? $value : $fallback;
	}

	private static function font_family( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value || 'inherit' === $value ) {
			return 'inherit';
		}
		$value = preg_replace( '/[^A-Za-z0-9\s,"\'\-_.()]+/', '', $value );
		return $value ? $value : 'inherit';
	}
}

SMM_Modern_Mega_Menu_Block::init();
