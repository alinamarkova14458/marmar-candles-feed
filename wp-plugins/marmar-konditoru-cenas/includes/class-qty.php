<?php
/**
 * Konditoriem (Alīna, 08.11.2026):
 * - tievās sveces 8 / 10 / 13 cm: plāksnītes 10 / 50 / 100 gab, cena par gabalu
 *   10 gab — 0,50 €, 50 gab — 0,48 €, 100 gab — 0,45 € (+0,07 €/gab ar svečturiem);
 *   cena mainās lapā un grozā automātiski pēc sveču skaita rindā;
 * - Zigzag un Viļņotās (mazumtirdzniecībā 7 gab) konditoram ir 10 gab iepakojums.
 * Mazumtirdzniecības pircēji to neredz.
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_KC_Qty {

	/** Sveču skaits rindā → cena par gabalu (bez svečturiem). */
	const TIERS = array( 100 => 0.45, 50 => 0.48, 10 => 0.50 );
	const HOLDER = 0.07;     // svečturis, €/gab
	const BASE_PPC = 0.50;   // tievās sveces konditora cena par gabalu
	const BTNS = array( 10, 50, 100 );
	const FLAT_13 = 0.60;    // 13 cm sveces: 0,60 €/gab jebkuram daudzumam

	public static function is_13( $product ) {
		$p = ( $product instanceof WC_Product && $product->is_type( 'variation' ) ) ? wc_get_product( $product->get_parent_id() ) : $product;
		return $p instanceof WC_Product && (bool) preg_match( '/\b13\s*cm\b/i', $p->get_name() );
	}

	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_quantity', array( __CLASS__, 'buttons' ), 5 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'tier' ), 50 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'cart_label' ), 20, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'pack_item_data' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'pack_order_meta' ), 20, 4 );
		add_filter( 'the_title', array( __CLASS__, 'title_10' ), 20, 2 );
		add_filter( 'woocommerce_product_title', array( __CLASS__, 'product_title_10' ), 20, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
	}

	/**
	 * Administratoram: konditora cenu iestatīšana vienā pieprasījumā (lai hostings nebremzē).
	 * POST /wp-json/marmar-kc/v1/set  {"items":[{"product":123,"price":6.0,"holder_price":6.7}]}
	 * Variācijām ar «AR SVEČTURIEM» liek holder_price (ja dots), pārējām price; vecās vērtības atgriež.
	 */
	public static function rest() {
		register_rest_route( 'marmar-kc/v1', '/set', array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
			'callback'            => function ( WP_REST_Request $r ) {
				$out = array();
				foreach ( (array) $r->get_param( 'items' ) as $it ) {
					$p = wc_get_product( (int) ( $it['product'] ?? 0 ) );
					if ( ! $p ) {
						$out[] = array( 'product' => $it['product'] ?? 0, 'error' => 'not found' );
						continue;
					}
					$price  = number_format( (float) $it['price'], 2, '.', '' );
					$hprice = isset( $it['holder_price'] ) ? number_format( (float) $it['holder_price'], 2, '.', '' ) : $price;
					$old    = array();
					if ( $p->is_type( 'variable' ) ) {
						foreach ( $p->get_children() as $vid ) {
							$v = wc_get_product( $vid );
							if ( ! $v ) {
								continue;
							}
							$ar = false;
							foreach ( $v->get_attributes() as $val ) {
								if ( preg_match( '/^ar[\s-]+sve/iu', (string) $val ) ) {
									$ar = true;
								}
							}
							$old[ $vid ] = $v->get_meta( MarMar_Konditoru_Cenas::META, true );
							$v->update_meta_data( MarMar_Konditoru_Cenas::META, $ar ? $hprice : $price );
							$v->save_meta_data();
						}
						$p->update_meta_data( MarMar_Konditoru_Cenas::META_HAS, 'yes' );
						$p->save_meta_data();
						wc_delete_product_transients( $p->get_id() );
					} else {
						$old[ $p->get_id() ] = $p->get_meta( MarMar_Konditoru_Cenas::META, true );
						$p->update_meta_data( MarMar_Konditoru_Cenas::META, $price );
						$p->save_meta_data();
					}
					$out[] = array( 'product' => $p->get_id(), 'type' => $p->get_type(), 'set' => count( $old ), 'old' => $old );
				}
				return $out;
			},
		) );
	}

	/** Cik sveču ir vienā mazumtirdzniecības iepakojumā («Daudzums: 10 gab» vai «10 gab» nosaukumā). */
	public static function pack_size( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return 0;
		}
		$p = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( ! $p ) {
			return 0;
		}
		foreach ( $p->get_attributes() as $a ) {
			$name = $a->is_taxonomy() ? wc_attribute_label( $a->get_name() ) : $a->get_name();
			if ( false !== stripos( $name, 'daudzums' ) ) {
				$opts = $a->is_taxonomy() ? wc_get_product_terms( $p->get_id(), $a->get_name(), array( 'fields' => 'names' ) ) : $a->get_options();
				if ( 1 === count( $opts ) && preg_match( '/(\d+)\s*gab/i', (string) reset( $opts ), $m ) ) {
					return (int) $m[1];
				}
				return 0;
			}
		}
		if ( preg_match( '/(?:\||,)\s*(\d+)\s*gab\b/i', $p->get_name(), $m ) && (int) $m[1] > 1 ) {
			return (int) $m[1];
		}
		return 0;
	}

	/** Zigzag / Viļņotās 7 gab → konditoram 10 gab. */
	public static function is_pack7_to_10( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		$p = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		return $p && 7 === self::pack_size( $product ) && preg_match( '/zig\s*zag|viļņ/iu', $p->get_name() );
	}

	/** Konditora sveču skaits iepakojumā. */
	public static function kc_pack( $product ) {
		return self::is_pack7_to_10( $product ) ? 10 : self::pack_size( $product );
	}

	/** Tievās sveces: konditora cena ≈ 0,50 €/gab (ar svečturiem 0,57). */
	public static function is_thin( $product ) {
		$n  = self::pack_size( $product );
		$kc = MarMar_Konditoru_Cenas::kc_price( $product );
		if ( $n < 2 || '' === $kc || self::is_pack7_to_10( $product ) ) {
			return false;
		}
		$ppc = $kc / $n;
		return abs( $ppc - self::BASE_PPC ) < 0.005 || abs( $ppc - self::BASE_PPC - self::HOLDER ) < 0.005;
	}

	public static function ppc_for( $candles, $holder ) {
		$ppc = self::BASE_PPC;
		foreach ( self::TIERS as $from => $p ) {
			if ( $candles >= $from ) {
				$ppc = $p;
				break;
			}
		}
		return $ppc + ( $holder ? self::HOLDER : 0 );
	}

	public static function buttons() {
		global $product;
		if ( ! MarMar_Konditoru_Cenas::is_konditor() || ! MarMar_Konditoru_Cenas::has_kc_prices( $product ) ) {
			return;
		}
		$n = self::pack_size( $product );
		if ( $n < 2 || self::is_pack7_to_10( $product ) ) {
			return;
		}
		// Tikai tievajām svecēm (0,50 €/gab); pārbaudām pēc pirmās variācijas / paša produkta.
		$any_thin = false;
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $vid ) {
				if ( self::is_thin( wc_get_product( $vid ) ) ) {
					$any_thin = true;
					break;
				}
			}
		} else {
			$any_thin = self::is_thin( $product );
		}
		$flat = self::is_13( $product );
		if ( ! $any_thin && ! $flat ) {
			return;
		}
		$tiers = array();
		if ( $flat ) {
			$tiers[] = array( 1, self::FLAT_13 );
		} else {
			foreach ( self::TIERS as $from => $p ) {
				$tiers[] = array( (int) $from, (float) $p );
			}
		}
		$base = $flat ? self::FLAT_13 : self::BASE_PPC;
		?>
		<div class="mmq" data-pack="<?php echo (int) $n; ?>" data-tiers='<?php echo esc_attr( wp_json_encode( $tiers ) ); ?>' data-holder="<?php echo esc_attr( self::HOLDER ); ?>" data-base="<?php echo esc_attr( $base ); ?>">
			<div class="mmq-t">Daudzums</div>
			<div class="mmq-btns">
				<?php foreach ( self::BTNS as $i => $c ) : ?>
					<button type="button" class="mmq-b<?php echo 0 === $i ? ' is-on' : ''; ?>" data-candles="<?php echo (int) $c; ?>"><?php echo (int) $c; ?> gab<small><?php echo esc_html( number_format( $flat ? self::FLAT_13 : self::ppc_for( $c, false ), 2, ',', '' ) ); ?> €/gab</small></button>
				<?php endforeach; ?>
			</div>
			<div class="mmq-sum" aria-live="polite"></div>
		</div>
		<style>
		.mmq{margin:4px 0 14px;width:100%}
		.mmq-t{font-size:14px;letter-spacing:.04em;text-transform:uppercase;margin-bottom:8px}
		.mmq-btns{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
		.mmq-b{padding:9px 4px!important;border:1.5px solid #E5C9C6!important;background:#fff!important;color:#5a4a4a!important;border-radius:12px!important;font-weight:600!important;font-size:15px!important;line-height:1.15!important;min-height:0!important;letter-spacing:0!important;text-transform:none!important}
		.mmq-b small{display:block;font-weight:400;font-size:11.5px;opacity:.85}
		.mmq-b.is-on{border-color:#B85F5F!important;background:#FBF3F2!important;color:#B85F5F!important}
		.mmq-sum{margin-top:10px;font-size:15px}
		.mmq-sum b{font-size:18px}
		form.cart .mmq ~ .quantity,form.cart .woocommerce-variation-add-to-cart .quantity{display:none!important}
		</style>
		<script>
		(function(){var box=document.currentScript.previousElementSibling.previousElementSibling;var form=box.closest('form.cart');if(!form)return;
		var N=+box.dataset.pack,T=JSON.parse(box.dataset.tiers),H=+box.dataset.holder,B=+box.dataset.base,holder=null,cur=10;
		var sum=box.querySelector('.mmq-sum');function Q(){return form.querySelector('input[name="quantity"]');}
		function fmt(v){return v.toFixed(2).replace('.',',')+' €';}
		function ppc(c){var p=B;for(var i=0;i<T.length;i++){if(c>=T[i][0]){p=T[i][1];break;}}return p+(holder?H:0);}
		function set(c){cur=c;var qty=Q();if(qty){qty.value=Math.max(1,Math.round(c/N));qty.dispatchEvent(new Event('change',{bubbles:true}));}
		 box.querySelectorAll('.mmq-b').forEach(function(b){b.classList.toggle('is-on',+b.dataset.candles===c);});draw();}
		function draw(){var p=ppc(cur);box.querySelectorAll('.mmq-b').forEach(function(b){b.querySelector('small').textContent=fmt(ppc(+b.dataset.candles)).replace(' €','')+' €/gab';});
		 sum.innerHTML=cur+' gab × '+fmt(p).replace(' €','')+' €/gab = <b>'+fmt(p*cur)+'</b>'+(holder?' <small>(ar svečturiem)</small>':'');}
		box.querySelectorAll('.mmq-b').forEach(function(b){b.addEventListener('click',function(){set(+b.dataset.candles);});});
		function sync(){var qty=Q();if(qty)qty.value=Math.max(1,Math.round(cur/N));}form.addEventListener('submit',sync,true);var btn=form.querySelector('.single_add_to_cart_button');if(btn){btn.addEventListener('click',sync,true);btn.addEventListener('pointerdown',sync,true);}
		if(window.jQuery){jQuery(form).on('found_variation',function(e,v){var a=v.attributes||{},h=false;for(var k in a){if(/ar-svec|ar svečtur/i.test(a[k]))h=true;}holder=h;draw();}).on('reset_data',function(){holder=null;draw();});}
		set(10);})();
		</script>
		<?php
	}

	/** Cena rindā pēc sveču skaita (tievās sveces) un 10 gab cena Zigzag/Viļņotajām. */
	public static function tier( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! MarMar_Konditoru_Cenas::is_konditor() ) {
			return;
		}
		foreach ( $cart->get_cart() as $key => $ci ) {
			$p = $ci['data'];
			$p->delete_meta_data( '_mm_tier_price' ); // tikai atmiņā
			if ( ! self::is_thin( $p ) ) {
				continue;
			}
			$n       = self::pack_size( $p );
			$kc      = (float) MarMar_Konditoru_Cenas::kc_price( $p );
			$holder  = ( $kc / $n ) > self::BASE_PPC + 0.03;
			$candles = $n * (int) $ci['quantity'];
			$price   = round( self::ppc_for( $candles, $holder ) * $n, 2 );
			if ( abs( $price - $kc ) > 0.001 ) {
				$cart->cart_contents[ $key ]['data']->update_meta_data( '_mm_tier_price', $price );
			}
		}
	}

	/** Grozā: sveču skaits un cena par gabalu. */
	public static function cart_label( $name, $ci, $key ) {
		if ( ! MarMar_Konditoru_Cenas::is_konditor() ) {
			return $name;
		}
		$p = $ci['data'];
		if ( self::is_thin( $p ) ) {
			$n = self::pack_size( $p );
			$c = $n * (int) $ci['quantity'];
			$name .= ' <small class="mmq-cnt">(' . (int) $c . ' gab × ' . esc_html( number_format( (float) $p->get_price() / $n, 2, ',', '' ) ) . ' €/gab)</small>';
		}
		return $name;
	}

	/** Zigzag/Viļņotās: konditoram nosaukumā «10 gab» «7 gab» vietā. */
	public static function swap_7( $title ) {
		return preg_replace( '/\b7\s*gab\b/iu', '10 gab', $title );
	}

	public static function title_10( $title, $id = 0 ) {
		if ( $id && ! is_admin() && MarMar_Konditoru_Cenas::is_konditor() && 'product' === get_post_type( $id ) ) {
			$p = wc_get_product( $id );
			if ( $p && self::is_pack7_to_10( $p ) ) {
				return self::swap_7( $title );
			}
		}
		return $title;
	}

	public static function product_title_10( $title, $product ) {
		return ( MarMar_Konditoru_Cenas::is_konditor() && ! is_admin() && self::is_pack7_to_10( $product ) ) ? self::swap_7( $title ) : $title;
	}

	public static function pack_item_data( $data, $ci ) {
		if ( MarMar_Konditoru_Cenas::is_konditor() && self::is_pack7_to_10( $ci['data'] ) ) {
			foreach ( $data as $i => $d ) {
				if ( isset( $d['value'] ) ) {
					$data[ $i ]['value'] = self::swap_7( (string) $d['value'] );
				}
				if ( isset( $d['display'] ) ) {
					$data[ $i ]['display'] = self::swap_7( (string) $d['display'] );
				}
			}
			$data[] = array( 'key' => 'Konditora iepakojums', 'value' => '10 gab' );
		}
		return $data;
	}

	public static function pack_order_meta( $item, $key, $values, $order ) {
		if ( MarMar_Konditoru_Cenas::is_konditor( $order->get_customer_id() ) && self::is_pack7_to_10( $values['data'] ) ) {
			$item->add_meta_data( 'Konditora iepakojums', '10 gab', true );
			$item->set_name( self::swap_7( $item->get_name() ) );
		}
	}
}
