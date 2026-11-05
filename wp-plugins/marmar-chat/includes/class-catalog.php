<?php
defined( 'ABSPATH' ) || exit;

/**
 * Компактный каталог для модели: одна строка на товар. Хранится в transient,
 * пересобирается при изменении товаров (не чаще раза в 10 минут — ради кэша промпта).
 */
class MarMar_Chat_Catalog {

	const TRANSIENT = 'marmar_chat_catalog_v1';

	public static function init() {
		$flush = array( __CLASS__, 'mark_stale' );
		add_action( 'woocommerce_update_product', $flush );
		add_action( 'woocommerce_new_product', $flush );
		add_action( 'woocommerce_delete_product', $flush );
		add_action( 'woocommerce_product_set_stock_status', $flush );
	}

	public static function mark_stale() {
		update_option( 'marmar_chat_catalog_stale', 1, false );
	}

	public static function text() {
		$cached = get_transient( self::TRANSIENT );
		$stale  = get_option( 'marmar_chat_catalog_stale' );
		if ( $cached && ( ! $stale || get_transient( self::TRANSIENT . '_fresh' ) ) ) {
			return $cached;
		}
		$text = self::build();
		set_transient( self::TRANSIENT, $text, DAY_IN_SECONDS );
		set_transient( self::TRANSIENT . '_fresh', 1, 10 * MINUTE_IN_SECONDS );
		delete_option( 'marmar_chat_catalog_stale' );
		return $text;
	}

	public static function build() {
		$ids   = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => -1,
				'return'     => 'ids',
				'orderby'    => 'menu_order',
				'order'      => 'ASC',
			)
		);
		$lines = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$cats = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
			$line = array(
				'id=' . $id,
				self::clean( $p->get_name() ),
				self::price( $p ),
				'cat: ' . implode( ', ', is_array( $cats ) ? $cats : array() ),
			);
			if ( $p->is_type( 'variable' ) ) {
				$opts = array();
				foreach ( $p->get_variation_attributes() as $attr => $values ) {
					$values = array_filter( array_map( 'strval', (array) $values ) );
					if ( $values ) {
						$opts[] = wc_attribute_label( $attr, $p ) . ': ' . implode( '/', array_slice( $values, 0, 30 ) );
					}
				}
				if ( $opts ) {
					$line[] = 'options: ' . implode( '; ', $opts );
				}
			}
			$desc = self::clean( $p->get_short_description() ? $p->get_short_description() : $p->get_description() );
			if ( $desc ) {
				$line[] = 'about: ' . mb_substr( $desc, 0, 160 );
			}
			if ( ! $p->is_in_stock() ) {
				$line[] = 'OUT OF STOCK';
			}
			$lines[] = implode( ' | ', $line );
		}
		return implode( "\n", $lines );
	}

	private static function clean( $s ) {
		return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) ) );
	}

	private static function price( WC_Product $p ) {
		if ( $p->is_type( 'variable' ) ) {
			$min = (float) $p->get_variation_price( 'min', true );
			$max = (float) $p->get_variation_price( 'max', true );
			return $min === $max ? self::money( $min ) : self::money( $min ) . '–' . self::money( $max );
		}
		return self::money( (float) wc_get_price_to_display( $p ) );
	}

	private static function money( $v ) {
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' ) . ' €';
	}

	/**
	 * Данные карточки товара для виджета.
	 */
	public static function card( $id ) {
		$p = wc_get_product( (int) $id );
		if ( ! $p || 'publish' !== $p->get_status() ) {
			return null;
		}
		return array(
			'id'    => $p->get_id(),
			'name'  => self::clean( $p->get_name() ),
			'price' => self::price( $p ),
			'url'   => $p->get_permalink(),
			'image' => wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src( 'woocommerce_thumbnail' ),
		);
	}
}
