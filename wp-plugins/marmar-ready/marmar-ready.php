<?php
/**
 * Plugin Name:       MarMar Pieejams uzreiz
 * Description:       Gatavās sveces bez atsevišķām kartītēm: administratore atzīmē variantu un daudzumu → prece automātiski nonāk sadaļā «Pieejams uzreiz» ar plāksnīti; kad izpārdots — pazūd no sadaļas, bet paliek pārdošanā pēc pasūtījuma. Plāksnītes: «Pieejams uzreiz», «Populārs», «Dažādas krāsas».
 * Version:           1.3.5
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Ready {

	const CAT_SLUG  = 'sveces-pieejamas-uzreiz';
	const FLAG      = '_mm_ready_auto';   // produktam: kategoriju pārvalda šis spraudnis
	const OPT_POP   = 'marmar_popular_ids';
	const OPT_MULTI = 'marmar_merged_ids';
	const PAGE      = 'marmar-ready';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mm_ready_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_mm_ready_variations', array( __CLASS__, 'ajax_variations' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_stock' ) );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_stock' ) );
		add_filter( 'woocommerce_get_availability_text', array( __CLASS__, 'availability' ), 20, 2 );
		add_filter( 'woocommerce_backordered_item_meta_name', array( __CLASS__, 'backorder_label' ) );
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'badges' ), 9 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'dots' ), 5 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'eta_line' ), 15 );
		add_action( 'wp_head', array( __CLASS__, 'css' ), 99 );
		add_action( 'woocommerce_before_variations_form', array( __CLASS__, 'ready_box' ), 5 );
		add_action( 'mm_bestsellers_daily', array( __CLASS__, 'compute_bestsellers' ) );
		if ( ! wp_next_scheduled( 'mm_bestsellers_daily' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:17' ), 'daily', 'mm_bestsellers_daily' );
		}
	}

	const OPT_BEST = 'marmar_bestsellers';
	const BEST_N   = 6;
	const EXCLUDE  = array( 11212 );                 // svečturi — aksesuārs
	/** Vecās kartītes → apvienotā kartīte (pārdošanas vēsture). */
	const MERGE_MAP = array( 14657 => 25282, 14536 => 25282, 12016 => 25282, 11956 => 25282, 11926 => 25282, 11925 => 25282, 11867 => 25282, 11866 => 25282, 11837 => 25282, 11836 => 25282, 11806 => 25282, 11777 => 25282, 11776 => 25282, 11746 => 25282, 11717 => 25282, 11511 => 25282, 11390 => 25282, 11213 => 25282, 11690 => 25282, 22821 => 25282, 22059 => 25282, 22061 => 25282, 22062 => 25282, 11063 => 25282, 22820 => 25282, 22854 => 25282, 18852 => 25256, 19123 => 25256, 18850 => 25256, 19121 => 25256, 19122 => 25256, 18853 => 25256, 18851 => 25256, 11092 => 25256, 15406 => 25256, 24867 => 25674, 22717 => 25674, 16639 => 25674, 16698 => 25674, 16638 => 25674, 16637 => 25674, 22718 => 25674, 16728 => 25674, 15196 => 25674, 14656 => 25674, 22857 => 25674, 16336 => 25674, 22894 => 25674, 22891 => 25674, 22822 => 25674, 22170 => 25674, 16337 => 26097, 21967 => 26097, 18767 => 26097, 18738 => 26097, 18737 => 26097, 18707 => 26097, 17567 => 26097, 16730 => 26097, 16729 => 26097, 22889 => 26097, 22750 => 26097, 22749 => 26097, 17027 => 26388, 22083 => 26388, 22069 => 26953, 22071 => 26953, 22073 => 26953, 22075 => 26953, 22078 => 26953, 22080 => 26953, 22883 => 27234, 22858 => 27234, 14956 => 27515 );

	/** Top N pēc pārdotā daudzuma pēdējos 12 mēnešos (apvienotajām — ar veco kartīšu pārdošanu). */
	public static function compute_bestsellers() {
		$orders = wc_get_orders( array( 'status' => array( 'wc-processing', 'wc-completed' ), 'date_created' => '>' . strtotime( '-365 days' ), 'limit' => -1 ) );
		$sum    = array();
		foreach ( $orders as $o ) {
			foreach ( $o->get_items() as $it ) {
				$pid = (int) $it->get_product_id();
				$pid = self::MERGE_MAP[ $pid ] ?? $pid;
				if ( in_array( $pid, self::EXCLUDE, true ) || 'publish' !== get_post_status( $pid ) ) {
					continue;
				}
				$sum[ $pid ] = ( $sum[ $pid ] ?? 0 ) + (int) $it->get_quantity();
			}
		}
		arsort( $sum );
		$top = array_slice( array_keys( array_filter( $sum, function ( $q ) { return $q >= 3; } ) ), 0, self::BEST_N );
		$qty = array();
		foreach ( $top as $id ) {
			$qty[ (string) $id ] = (int) $sum[ $id ];
		}
		update_option( self::OPT_BEST, array( 'ids' => $top, 'qty' => $qty, 'time' => time() ), false );
		return $top;
	}

	public static function bestsellers() {
		$o = get_option( self::OPT_BEST );
		if ( ! $o || empty( $o['qty'] ) || ( time() - (int) ( $o['time'] ?? 0 ) ) > 2 * DAY_IN_SECONDS ) {
			self::compute_bestsellers();
			$o = get_option( self::OPT_BEST );
		}
		return array_map( 'intval', (array) ( $o['ids'] ?? array() ) );
	}

	/** Produkta lapā: kuri tieši varianti ir gatavi; klikšķis izvēlas variantu. */
	public static function ready_box() {
		global $product;
		if ( ! $product instanceof WC_Product || 'yes' !== $product->get_meta( self::FLAG, true ) ) {
			return;
		}
		$list = (array) $product->get_meta( '_mm_ready_list', true );
		if ( ! $list ) {
			return;
		}
		echo '<div class="mmr-box"><div class="mmr-t">⚡ Pieejams uzreiz — izsūtīsim 1–2 darba dienās</div><div class="mmr-l">';
		foreach ( $list as $r ) {
			echo '<button type="button" class="mmr-i" data-attrs="' . esc_attr( wp_json_encode( $r['attrs'] ) ) . '">' . esc_html( ucfirst( mb_strtolower( (string) $r['label'] ) ) ) . ' <b>' . (int) $r['q'] . ' gab</b></button>';
		}
		echo '</div><div class="mmr-n">Pārējās krāsas izgatavojam pēc pasūtījuma 5–7 dienās.</div></div>';
		echo '<script>document.addEventListener("click",function(e){var b=e.target.closest(".mmr-i");if(!b)return;var a=JSON.parse(b.dataset.attrs||"{}"),f=document.querySelector("form.variations_form");if(!f)return;Object.keys(a).forEach(function(k){var s=f.querySelector("select[name=\""+k+"\"]");if(s){s.value=a[k];if(window.jQuery)jQuery(s).trigger("change");else s.dispatchEvent(new Event("change",{bubbles:true}));}var li=f.querySelector("ul[data-attribute_name=\""+k+"\"] li[data-value=\""+a[k]+"\"]");if(li&&!li.classList.contains("selected"))li.click();});f.scrollIntoView({behavior:"smooth",block:"center"});});</script>';
	}

	/* ---------------- Gatavība ---------------- */

	/** Cik gatavu gab. ir variantam / vienkāršai precei (tikai, ja krājums tiek uzskaitīts). */
	public static function ready_qty( $p ) {
		if ( ! $p instanceof WC_Product || ! $p->managing_stock() ) {
			return 0;
		}
		return max( 0, (int) $p->get_stock_quantity() );
	}

	public static function product_ready( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return 0;
		}
		if ( $product->is_type( 'variable' ) ) {
			$sum = 0;
			foreach ( $product->get_children() as $vid ) {
				$sum += self::ready_qty( wc_get_product( $vid ) );
			}
			return $sum;
		}
		return self::ready_qty( $product );
	}

	/** Pievieno/noņem kategoriju «Pieejams uzreiz» — tikai precēm, ko pārvalda šis spraudnis. */
	public static function sync( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'yes' !== $product->get_meta( self::FLAG, true ) ) {
			return;
		}
		$term = get_term_by( 'slug', self::CAT_SLUG, 'product_cat' );
		if ( ! $term ) {
			return;
		}
		$qty  = self::product_ready( $product );
		$list = array();
		$its  = $product->is_type( 'variable' ) ? array_map( 'wc_get_product', $product->get_children() ) : array( $product );
		foreach ( $its as $it ) {
			$q = self::ready_qty( $it );
			if ( $it && $q > 0 ) {
				$list[] = array( 'id' => $it->get_id(), 'q' => $q, 'label' => $it->is_type( 'variation' ) ? self::var_label( $it ) : '', 'attrs' => $it->is_type( 'variation' ) ? $it->get_variation_attributes() : array() );
			}
		}
		$product->update_meta_data( '_mm_ready_list', $list );
		$product->update_meta_data( '_mm_ready_qty', $qty );
		$product->save_meta_data();
		$ids = $product->get_category_ids();
		$has = in_array( (int) $term->term_id, array_map( 'intval', $ids ), true );
		$rdy = $qty > 0;
		if ( $rdy && ! $has ) {
			$ids[] = (int) $term->term_id;
		} elseif ( ! $rdy && $has ) {
			$ids = array_diff( $ids, array( (int) $term->term_id ) );
		} else {
			return;
		}
		$product->set_category_ids( array_values( $ids ) );
		$product->save();
	}

	public static function on_stock( $p ) {
		$pid = $p->is_type( 'variation' ) ? $p->get_parent_id() : $p->get_id();
		self::sync( $pid );
	}

	/** Teksts zem varianta: gatavs vai izgatavošana. */
	public static function availability( $text, $p ) {
		if ( ! $p instanceof WC_Product || ! $p->managing_stock() ) {
			return $text;
		}
		$q = self::ready_qty( $p );
		if ( $q > 0 ) {
			return sprintf( '⚡ Pieejams uzreiz — %d gab. Izsūtīsim 1–2 darba dienās.', $q );
		}
		if ( $p->backorders_allowed() ) {
			return 'Izgatavosim pēc pasūtījuma 5–7 dienās.';
		}
		return $text;
	}

	public static function backorder_label() {
		return 'Izgatavot pēc pasūtījuma';
	}

	/* ---------------- Plāksnītes katalogā ---------------- */

	public static function merged_ids() {
		$ids = array_map( 'intval', (array) get_option( self::OPT_MULTI, array() ) );
		return $ids ? $ids : array( 25256, 25282 );
	}

	/** Vai prece ir gatava (mūsu atzīme vai manuāli kategorijā «Pieejams uzreiz»). */
	private static function is_ready_now( $product ) {
		if ( 'yes' === $product->get_meta( self::FLAG, true ) ) {
			return (int) $product->get_meta( '_mm_ready_qty', true ) > 0;
		}
		$term = get_term_by( 'slug', self::CAT_SLUG, 'product_cat' );
		return $term && in_array( (int) $term->term_id, array_map( 'intval', $product->get_category_ids() ), true );
	}

	/** Plāksnītes (variants D): maks. 2; secība Uzreiz → Pārdotākais → Jaunums. */
	public static function badges() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$out = array();
		if ( 'yes' === $product->get_meta( self::FLAG, true ) && (int) $product->get_meta( '_mm_ready_qty', true ) > 0 ) {
			$list = (array) $product->get_meta( '_mm_ready_list', true );
			if ( ! $product->is_type( 'variable' ) || ! $list ) {
				$t = 'Pieejams uzreiz';
			} elseif ( 1 === count( $list ) ) {
				$first = explode( ' · ', (string) $list[0]['label'] );
				$t     = 'Uzreiz: ' . $first[0];
			} else {
				$t = 'Uzreiz: ' . count( $list ) . ' varianti';
			}
			$out[] = '<span class="mmb mmb-ready"><i class="mmb-dot"></i>' . esc_html( $t ) . '</span>';
		}
		if ( in_array( (int) $product->get_id(), self::bestsellers(), true ) ) {
			$out[] = '<span class="mmb mmb-best"><i class="mmb-ico">🔥</i>Pārdotākais</span>';
		} elseif ( in_array( (int) $product->get_id(), array_map( 'intval', (array) get_option( self::OPT_POP, array() ) ), true ) ) {
			$out[] = '<span class="mmb mmb-new"><i class="mmb-dot"></i>Jaunums</span>';
		}
		$cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
		if ( in_array( 'kazu-sveces', (array) $cats, true ) || false !== mb_stripos( $product->get_name(), 'personaliz' ) ) {
			$pers = '<span class="mmb mmb-pers"><i class="mmb-dot"></i>Personalizējams</span>';
			// Personalizējams pirms «Jaunums», pēc «Uzreiz»/«Pārdotākais».
			$jn = array_values( array_filter( $out, function ( $x ) { return false !== strpos( $x, 'mmb-new' ); } ) );
			$out = array_values( array_filter( $out, function ( $x ) { return false === strpos( $x, 'mmb-new' ); } ) );
			$out[] = $pers;
			$out = array_merge( $out, $jn );
		}
		$out = array_slice( $out, 0, 2 );
		if ( ! $out ) {
			return;
		}
		echo '<span class="mmb-stack">' . implode( '', $out ) . '</span>'; // phpcs:ignore
		static $js = false;
		if ( ! $js ) {
			$js = true;
			add_action( 'wp_footer', function () {
				echo '<script>document.querySelectorAll(".mmb-stack").forEach(function(b){var li=b.closest("li");var f=li&&li.querySelector("figure");if(f){f.style.position="relative";f.appendChild(b);}});</script>';
			}, 99 );
		}
	}

	/** Folijas krāsu nosaukumi (lokālais atribūts melnajām svecēm) → krāsa. */
	const FOIL = array( 'zelta' => '#D4AF37', 'sudraba' => '#C9CBD0', 'rozā' => '#E59AB4', 'sarkana' => '#C0392B', 'zila' => '#2E5EAA', 'lillā' => '#9B59B6', 'zaļa' => '#3C9A5F', 'bez folijas' => '#222' );

	/** Krāsu punkti zem foto katalogā: līdz 5 + «+N». */
	public static function dots() {
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return;
		}
		$cols = array();
		foreach ( $product->get_attributes() as $a ) {
			if ( ! $a->get_variation() ) {
				continue;
			}
			if ( $a->is_taxonomy() && 'pa_izvelieties-krasu' === $a->get_name() ) {
				foreach ( $a->get_terms() as $t ) {
					if ( false !== strpos( $t->slug, 'mikss' ) ) {
						continue;
					}
					$c = get_term_meta( $t->term_id, 'product_attribute_color', true );
					if ( $c ) {
						$cols[] = array( $c, $t->name );
					}
				}
			} elseif ( ! $a->is_taxonomy() && false !== mb_stripos( $a->get_name(), 'krās' ) ) {
				foreach ( $a->get_options() as $o ) {
					$k = mb_strtolower( $o );
					if ( isset( self::FOIL[ $k ] ) ) {
						$f      = self::FOIL[ $k ];
						$bg     = 'bez folijas' === $k ? '#1f1f1f' : 'linear-gradient(135deg,#1f1f1f 0 38%,' . $f . ' 38% 66%,#1f1f1f 66%)';
						$cols[] = array( $bg, $o );
					}
				}
			}
		}
		if ( count( $cols ) < 3 ) {
			return;
		}
		$show = array_slice( $cols, 0, 5 );
		echo '<div class="mmd-dots" aria-label="' . count( $cols ) . ' krāsas">';
		foreach ( $show as $c ) {
			echo '<i style="background:' . esc_attr( $c[0] ) . '" title="' . esc_attr( $c[1] ) . '"></i>';
		}
		if ( count( $cols ) > 5 ) {
			echo '<span>+' . ( count( $cols ) - 5 ) . '</span>';
		}
		echo '</div>';
	}

	/** Termiņa rinda zem cenas katalogā. */
	public static function eta_line() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		echo self::is_ready_now( $product )
			? '<div class="mm-eta-line is-ready">Gatavs — izsūtīsim 1–2 d.</div>'
			: '<div class="mm-eta-line">Izgatavojam 5–7 d.</div>';
	}

	public static function css() {
		echo '<style id="mm-ready-css">ul.products li.product{position:relative}.mmb-stack{position:absolute;top:12px;right:12px;z-index:5;display:flex;flex-direction:column;align-items:flex-end;gap:6px;pointer-events:none}'
			. '.mmb{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.93);color:#5a4a4a;font-size:10.5px;font-weight:600;letter-spacing:.13em;text-transform:uppercase;line-height:1;padding:7px 11px;border-radius:999px;box-shadow:0 2px 8px rgba(0,0,0,.06);white-space:nowrap}'
			. '.mmb-dot{width:7px;height:7px;border-radius:50%;background:#B85F5F;display:inline-block}.mmb-ready .mmb-dot{background:#3C9A5F}.mmb-pers .mmb-dot{background:#C8A46A}.mmb-ico{font-style:normal;font-size:12px;letter-spacing:0}'
			. '.mmd-dots{display:flex;align-items:center;gap:5px;margin:6px 0 2px}.mmd-dots i{width:13px;height:13px;border-radius:50%;border:1px solid rgba(0,0,0,.12);box-shadow:inset 0 0 0 1.5px #fff}.mmd-dots span{font-size:11.5px;color:#8a7a7a;margin-left:2px}'
			. '.mm-eta-line{font-size:12px;color:#8a7a7a;margin:2px 0 8px}.mm-eta-line.is-ready{color:#2f7d5b;font-weight:600}'
			. '.mmr-box{margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#EEF6F1;border:1px solid #CFE6D8}.mmr-t{font-weight:600;color:#2f7d5b;font-size:14px;margin-bottom:8px}.mmr-l{display:flex;flex-wrap:wrap;gap:6px}.mmr-i{background:#fff!important;border:1px solid #CFE6D8!important;color:#3a4a42!important;border-radius:999px!important;padding:6px 12px!important;font-size:13px!important;min-height:0!important;line-height:1.2!important;text-transform:none!important;letter-spacing:0!important}.mmr-i b{color:#2f7d5b}.mmr-n{font-size:12px;color:#6b7a72;margin-top:8px}'
			. '.single-product .stock.available-on-backorder,.single-product .stock.in-stock{font-size:14px}</style>';
	}

	/* ---------------- Administrācija ---------------- */

	public static function menu() {
		add_submenu_page( 'woocommerce', 'Pieejams uzreiz', 'Pieejams uzreiz', 'manage_woocommerce', self::PAGE, array( __CLASS__, 'page' ) );
	}

	private static function var_label( $v ) {
		$parts = array();
		foreach ( $v->get_variation_attributes() as $k => $val ) {
			$tax = str_replace( 'attribute_', '', $k );
			if ( taxonomy_exists( $tax ) ) {
				$t   = get_term_by( 'slug', $val, $tax );
				$val = $t ? $t->name : $val;
			}
			$parts[] = $val;
		}
		return implode( ' · ', $parts );
	}

	public static function ajax_variations() {
		check_ajax_referer( 'mm_ready', 'n' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$p   = wc_get_product( absint( $_GET['p'] ?? 0 ) );
		$out = array();
		if ( $p && $p->is_type( 'variable' ) ) {
			foreach ( $p->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( $v && 'publish' === $v->get_status() ) {
					$out[] = array( 'id' => $vid, 'label' => self::var_label( $v ) );
				}
			}
			usort( $out, function ( $a, $b ) { return strcmp( $a['label'], $b['label'] ); } );
		} elseif ( $p ) {
			$out[] = array( 'id' => $p->get_id(), 'label' => '(vienkārša prece)' );
		}
		wp_send_json_success( $out );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Nav tiesību' );
		}
		check_admin_referer( 'mm_ready' );
		$do  = sanitize_key( $_POST['do'] ?? '' );
		$msg = '';
		if ( 'set' === $do ) {
			$id  = absint( $_POST['item'] ?? 0 );
			$qty = max( 0, (int) ( $_POST['qty'] ?? 0 ) );
			$p   = wc_get_product( $id );
			if ( $p ) {
				$p->set_manage_stock( $qty > 0 );
				if ( $qty > 0 ) {
					$p->set_stock_quantity( $qty );
					$p->set_backorders( 'yes' );
				}
				$p->set_stock_status( 'instock' );
				$p->save();
				$parent = $p->is_type( 'variation' ) ? wc_get_product( $p->get_parent_id() ) : $p;
				if ( $parent ) {
					$parent->update_meta_data( self::FLAG, 'yes' );
					$parent->save_meta_data();
					self::sync( $parent->get_id() );
				}
				$msg = $qty > 0 ? 'saved' : 'removed';
			}
		} elseif ( 'popular' === $do ) {
			$ids = array_map( 'absint', (array) ( $_POST['popular'] ?? array() ) );
			update_option( self::OPT_POP, array_values( array_filter( $ids ) ), false );
			$msg = 'popular';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&m=' . $msg ) );
		exit;
	}

	public static function page() {
		self::compute_bestsellers(); // svaigs saraksts, atverot lapu
		$products = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'return' => 'objects' ) );
		$ready    = array();
		$flagged  = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'meta_key' => self::FLAG, 'meta_value' => 'yes', 'return' => 'objects' ) );
		foreach ( $flagged as $p ) {
			$items = $p->is_type( 'variable' ) ? array_map( 'wc_get_product', $p->get_children() ) : array( $p );
			foreach ( $items as $it ) {
				if ( $it && 'yes' === $p->get_meta( self::FLAG, true ) && self::ready_qty( $it ) > 0 ) {
					$ready[] = array( $p, $it );
				}
			}
		}
		$pop   = array_map( 'intval', (array) get_option( self::OPT_POP, array() ) );
		$nonce = wp_create_nonce( 'mm_ready' );
		$m     = sanitize_key( $_GET['m'] ?? '' );
		?>
		<div class="wrap">
			<h1>Pieejams uzreiz</h1>
			<?php if ( $m ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( array( 'saved' => 'Saglabāts.', 'removed' => 'Noņemts no «Pieejams uzreiz».', 'popular' => 'Jaunumi saglabāti.' )[ $m ] ?? 'Saglabāts.' ); ?></p></div><?php endif; ?>
			<p>Atzīmē gatavās sveces: prece un variants (krāsa, dizains) un cik gatavu iepakojumu ir. Prece parādās sadaļā «Pieejams uzreiz» ar plāksnīti; kad izpārdota — pazūd no sadaļas, bet paliek pārdošanā pēc pasūtījuma (5–7 dienas).</p>

			<h2>Pievienot / mainīt</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="mmr-form">
				<input type="hidden" name="action" value="mm_ready_save"><input type="hidden" name="do" value="set">
				<?php wp_nonce_field( 'mm_ready' ); ?>
				<p><label>Prece<br><select id="mmr-p" style="min-width:420px"><option value="">— izvēlies —</option>
					<?php foreach ( $products as $p ) : ?><option value="<?php echo (int) $p->get_id(); ?>"><?php echo esc_html( $p->get_name() ); ?></option><?php endforeach; ?>
				</select></label></p>
				<p><label>Variants<br><select name="item" id="mmr-v" style="min-width:420px" required><option value="">—</option></select></label></p>
				<p><label>Gatavi iepakojumi (gab.)<br><input type="number" name="qty" min="0" value="1" style="width:100px"></label> <small>0 — noņemt no «Pieejams uzreiz»</small></p>
				<p><button class="button button-primary">Saglabāt</button></p>
			</form>

			<h2>Šobrīd pieejams uzreiz (<?php echo count( $ready ); ?>)</h2>
			<table class="widefat striped"><thead><tr><th>Prece</th><th>Variants</th><th>Gab.</th><th></th></tr></thead><tbody>
			<?php if ( ! $ready ) : ?><tr><td colspan="4">Vēl nekas nav atzīmēts.</td></tr><?php endif; ?>
			<?php foreach ( $ready as $r ) : list( $p, $it ) = $r; ?>
				<tr><td><?php echo esc_html( $p->get_name() ); ?></td><td><?php echo esc_html( $it->is_type( 'variation' ) ? self::var_label( $it ) : '—' ); ?></td>
				<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px">
					<input type="hidden" name="action" value="mm_ready_save"><input type="hidden" name="do" value="set"><input type="hidden" name="item" value="<?php echo (int) $it->get_id(); ?>">
					<?php wp_nonce_field( 'mm_ready' ); ?>
					<input type="number" name="qty" min="0" value="<?php echo (int) self::ready_qty( $it ); ?>" style="width:80px"><button class="button">Saglabāt</button></form></td>
				<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mm_ready_save"><input type="hidden" name="do" value="set"><input type="hidden" name="item" value="<?php echo (int) $it->get_id(); ?>"><input type="hidden" name="qty" value="0">
					<?php wp_nonce_field( 'mm_ready' ); ?><button class="button-link-delete">Noņemt</button></form></td></tr>
			<?php endforeach; ?>
			</tbody></table>

			<h2 style="margin-top:2em">🔥 Pārdotākais (automātiski)</h2>
			<p>Katru nakti — <?php echo (int) self::BEST_N; ?> pārdotākās preces pēdējos 12 mēnešos (vismaz 3 gab.). Pašlaik:
			<?php $bo = get_option( self::OPT_BEST ); foreach ( self::bestsellers() as $bid ) { echo '<br>• ' . esc_html( get_the_title( $bid ) ) . ' — ' . (int) ( $bo['qty'][ (string) $bid ] ?? 0 ) . ' gab.'; } ?></p>
			<h2 style="margin-top:2em">Jaunums (atzīmē pati — jaunumiem)</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mm_ready_save"><input type="hidden" name="do" value="popular">
				<?php wp_nonce_field( 'mm_ready' ); ?>
				<div style="columns:2;max-width:900px">
				<?php foreach ( $products as $p ) : ?>
					<label style="display:block;break-inside:avoid;margin:2px 0"><input type="checkbox" name="popular[]" value="<?php echo (int) $p->get_id(); ?>" <?php checked( in_array( (int) $p->get_id(), $pop, true ) ); ?>> <?php echo esc_html( $p->get_name() ); ?></label>
				<?php endforeach; ?>
				</div>
				<p><button class="button button-primary">Saglabāt jaunumus</button></p>
			</form>
		</div>
		<script>
		document.getElementById('mmr-p').addEventListener('change',function(){var v=document.getElementById('mmr-v');v.innerHTML='<option value="">Ielādē…</option>';
		fetch(ajaxurl+'?action=mm_ready_variations&n=<?php echo esc_js( $nonce ); ?>&p='+this.value).then(function(r){return r.json();}).then(function(d){v.innerHTML='';(d.data||[]).forEach(function(o){var e=document.createElement('option');e.value=o.id;e.textContent=o.label;v.appendChild(e);});});});
		</script>
		<?php
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Ready', 'init' ) );
