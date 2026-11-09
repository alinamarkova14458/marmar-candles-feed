<?php
/**
 * Plugin Name:       MarMar Mikss
 * Description:       «Savs mikss (līdz 5 krāsām)»: ja krāsā izvēlēts šis variants, pircējs atzīmē 2–5 krāsas; izvēle redzama grozā, pasūtījumā un e-pastos.
 * Version:           1.1.1
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Mix {

	const TAX   = 'pa_izvelieties-krasu';
	const SLUG  = 'savs-mikss';
	const MIN   = 2;
	const MAX   = 5;
	const FIELD = 'mm_mix';

	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'field' ), 5 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 20, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_data' ), 20, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_meta' ), 20, 4 );
		add_action( 'init', array( __CLASS__, 'mix_colors' ), 30 );
		// «Vēlmes» lauks (pirmā gada ciparu komplekti).
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'wish_field' ), 6 );
	}

	const WISH        = 'mm_wish';
	const WISH_PRODS  = array( 24811, 20440, 20651 );

	public static function wish_field() {
		global $product;
		if ( ! $product instanceof WC_Product || ! in_array( (int) $product->get_id(), apply_filters( 'marmar_wish_products', self::WISH_PRODS ), true ) ) {
			return;
		}
		echo '<div class="mmw"><input type="text" id="mmw-t" name="' . esc_attr( self::WISH ) . '" maxlength="200" placeholder="Vēlmes (nav obligāti), piem.: 1 un 12 ar zelta foliju" aria-label="Vēlmes (nav obligāti)"></div>'
			. '<style>.mmw{width:100%;margin:2px 0 12px}.mmw input{width:100%;height:40px!important;min-height:0!important;border-radius:10px;padding:6px 12px!important;font-size:13px!important}</style>';
	}

	/** Miksu flīžu krāsas (Variation Swatches «color» tips), ja vēl nav iestatītas. */
	public static function mix_colors() {
		if ( get_option( 'marmar_mix_colors_v1' ) ) {
			return;
		}
		$map = array( 'pastelt' => '#F3D3DE', 'mikss-2' => '#C9A7D8', 'mikss-4' => '#A8D8CB', 'mikss-6' => '#F4C7A1', 'savs-mikss' => '#E9DCCB' );
		$done = 0;
		foreach ( get_terms( array( 'taxonomy' => self::TAX, 'hide_empty' => false ) ) as $t ) {
			foreach ( $map as $needle => $hex ) {
				if ( false !== strpos( $t->slug, $needle ) && false !== strpos( $t->slug, 'mikss' ) && ! get_term_meta( $t->term_id, 'product_attribute_color', true ) ) {
					update_term_meta( $t->term_id, 'product_attribute_color', $hex );
					$done++;
				}
			}
		}
		if ( $done >= 5 ) {
			update_option( 'marmar_mix_colors_v1', 1, false );
		}
	}

	private static function has_mix( $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return false;
		}
		$attrs = $product->get_attributes();
		if ( empty( $attrs[ self::TAX ] ) ) {
			return false;
		}
		$slugs = wc_get_product_terms( $product->get_id(), self::TAX, array( 'fields' => 'slugs' ) );
		return in_array( self::SLUG, $slugs, true );
	}

	/** Krāsas izvēlei: visas atribūta krāsas, izņemot miksus. */
	private static function colors() {
		$out   = array();
		$terms = get_terms( array( 'taxonomy' => self::TAX, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $t ) {
			if ( false !== strpos( $t->slug, 'mikss' ) ) {
				continue;
			}
			$c     = get_term_meta( $t->term_id, 'product_attribute_color', true );
			$out[] = array( 'slug' => $t->slug, 'name' => $t->name, 'color' => $c ? $c : '#ddd' );
		}
		return $out;
	}

	public static function field() {
		global $product;
		if ( ! self::has_mix( $product ) ) {
			return;
		}
		?>
		<div class="mmx" hidden>
			<div class="mmx-t">Izvēlies <?php echo (int) self::MIN; ?>–<?php echo (int) self::MAX; ?> krāsas savam miksam <span class="mmx-n"></span></div>
			<div class="mmx-grid">
				<?php foreach ( self::colors() as $c ) : ?>
					<label class="mmx-c" title="<?php echo esc_attr( $c['name'] ); ?>"><input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>[]" value="<?php echo esc_attr( $c['slug'] ); ?>"><span class="mmx-dot" style="background:<?php echo esc_attr( $c['color'] ); ?>"></span><span class="mmx-l"><?php echo esc_html( $c['name'] ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</div>
		<style>
		.mmx{margin:6px 0 16px;padding:12px;border:1px solid #F0D9D6;border-radius:14px;background:#fff;width:100%}
		.mmx-t{font-weight:600;font-size:14px;margin-bottom:10px}.mmx-n{font-weight:400;color:#B85F5F}
		.mmx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:8px}
		.mmx-c{display:flex;align-items:center;gap:6px;font-size:12.5px;line-height:1.2;cursor:pointer;margin:0!important}
		.mmx-c input{position:absolute;opacity:0;pointer-events:none}
		.mmx-dot{flex:0 0 22px;width:22px;height:22px;border-radius:50%;border:1px solid rgba(0,0,0,.15);box-shadow:inset 0 0 0 2px #fff}
		.mmx-c input:checked+.mmx-dot{outline:2px solid #B85F5F;outline-offset:2px}
		.mmx-c.is-off{opacity:.35;pointer-events:none}
		</style>
		<script>
		(function(){var box=document.currentScript.previousElementSibling.previousElementSibling;var form=box.closest('form.cart');if(!form)return;var MAX=<?php echo (int) self::MAX; ?>,MIN=<?php echo (int) self::MIN; ?>,SL='<?php echo esc_js( self::SLUG ); ?>';
		var cbs=[].slice.call(box.querySelectorAll('input[type=checkbox]')),n=box.querySelector('.mmx-n');
		function sel(){var s=form.querySelector('select[name="attribute_<?php echo esc_js( self::TAX ); ?>"]');return s?s.value:'';}
		function upd(){var on=sel()===SL;box.hidden=!on;cbs.forEach(function(c){c.disabled=!on;});var k=cbs.filter(function(c){return c.checked;}).length;n.textContent=on?'('+k+'/'+MAX+')':'';cbs.forEach(function(c){c.parentNode.classList.toggle('is-off',on&&!c.checked&&k>=MAX);});}
		cbs.forEach(function(c){c.addEventListener('change',upd);});
		form.addEventListener('change',upd);if(window.jQuery){jQuery(form).on('woocommerce_variation_has_changed found_variation reset_data',upd);}
		form.addEventListener('submit',function(e){if(sel()!==SL)return;var k=cbs.filter(function(c){return c.checked;}).length;if(k<MIN||k>MAX){e.preventDefault();e.stopImmediatePropagation();box.scrollIntoView({block:'center'});n.textContent='— izvēlies vismaz '+MIN+' krāsas';}},true);
		upd();})();
		</script>
		<?php
	}

	private static function posted() {
		$v = isset( $_POST[ self::FIELD ] ) ? (array) wp_unslash( $_POST[ self::FIELD ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$v = array_values( array_unique( array_filter( array_map( 'sanitize_title', $v ) ) ) );
		return array_slice( $v, 0, self::MAX );
	}

	private static function is_mix_variation( $variation_id, $variations ) {
		$val = $variations[ 'attribute_' . self::TAX ] ?? '';
		if ( ! $val && $variation_id ) {
			$v   = wc_get_product( $variation_id );
			$val = $v ? $v->get_attribute( self::TAX ) : '';
			$val = sanitize_title( $val );
		}
		return self::SLUG === $val;
	}

	public static function validate( $ok, $product_id, $qty, $variation_id = 0, $variations = array() ) {
		if ( $ok && self::is_mix_variation( $variation_id, (array) $variations ) ) {
			$n = count( self::posted() );
			if ( $n < self::MIN ) {
				wc_add_notice( sprintf( 'Lūdzu, izvēlies %d–%d krāsas savam miksam.', self::MIN, self::MAX ), 'error' );
				return false;
			}
		}
		return $ok;
	}

	public static function cart_data( $data, $product_id, $variation_id ) {
		$wish = isset( $_POST[ self::WISH ] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST[ self::WISH ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $wish ) {
			$data[ self::WISH ] = mb_substr( $wish, 0, 200 );
		}
		$vars = array();
		foreach ( $_POST as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( 0 === strpos( $k, 'attribute_' ) ) {
				$vars[ $k ] = sanitize_title( wp_unslash( $v ) );
			}
		}
		if ( self::is_mix_variation( $variation_id, $vars ) ) {
			$names = array();
			foreach ( self::posted() as $slug ) {
				$t = get_term_by( 'slug', $slug, self::TAX );
				if ( $t ) {
					$names[] = $t->name;
				}
			}
			if ( $names ) {
				$data[ self::FIELD ] = implode( ', ', $names );
			}
		}
		return $data;
	}

	public static function item_data( $data, $ci ) {
		if ( ! empty( $ci[ self::FIELD ] ) ) {
			$data[] = array( 'key' => 'Mikss', 'value' => $ci[ self::FIELD ] );
		}
		if ( ! empty( $ci[ self::WISH ] ) ) {
			$data[] = array( 'key' => 'Vēlmes', 'value' => $ci[ self::WISH ] );
		}
		return $data;
	}

	public static function order_meta( $item, $key, $values, $order ) {
		if ( ! empty( $values[ self::FIELD ] ) ) {
			$item->add_meta_data( 'Krāsu mikss', $values[ self::FIELD ], true );
		}
		if ( ! empty( $values[ self::WISH ] ) ) {
			$item->add_meta_data( 'Vēlmes', $values[ self::WISH ], true );
		}
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Mix', 'init' ) );
