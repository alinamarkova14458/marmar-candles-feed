<?php
/**
 * Plugin Name:       MarMar Pieejams uzreiz
 * Description:       Gatavās sveces bez atsevišķām kartītēm: administratore atzīmē variantu un daudzumu → prece automātiski nonāk sadaļā «Pieejams uzreiz» ar plāksnīti; kad izpārdots — pazūd no sadaļas, bet paliek pārdošanā pēc pasūtījuma. Plāksnītes: «Pieejams uzreiz», «Populārs», «Dažādas krāsas».
 * Version:           1.0.0
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
		add_action( 'wp_head', array( __CLASS__, 'css' ), 99 );
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
		$qty = self::product_ready( $product );
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

	public static function badges() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$out = array();
		if ( 'yes' === $product->get_meta( self::FLAG, true ) && (int) $product->get_meta( '_mm_ready_qty', true ) > 0 ) {
			$out[] = '<span class="mmb mmb-ready">⚡ Pieejams uzreiz</span>';
		}
		if ( in_array( (int) $product->get_id(), array_map( 'intval', (array) get_option( self::OPT_POP, array() ) ), true ) ) {
			$out[] = '<span class="mmb mmb-pop">★ Populārs</span>';
		}
		if ( in_array( (int) $product->get_id(), self::merged_ids(), true ) ) {
			$out[] = '<span class="mmb mmb-colors">🎨 Dažādas krāsas</span>';
		}
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

	public static function css() {
		echo '<style id="mm-ready-css">ul.products li.product{position:relative}.mmb-stack{position:absolute;top:12px;right:12px;z-index:5;display:flex;flex-direction:column;align-items:flex-end;gap:6px;pointer-events:none}'
			. '.mmb{font-size:12px;font-weight:600;line-height:1;padding:7px 10px;border-radius:999px;box-shadow:0 2px 8px rgba(0,0,0,.08);white-space:nowrap}'
			. '.mmb-colors{background:rgba(255,255,255,.92);color:#5a4a4a}.mmb-ready{background:#2f7d5b;color:#fff}.mmb-pop{background:#B85F5F;color:#fff}'
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
			<?php if ( $m ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( array( 'saved' => 'Saglabāts.', 'removed' => 'Noņemts no «Pieejams uzreiz».', 'popular' => 'Populārie saglabāti.' )[ $m ] ?? 'Saglabāts.' ); ?></p></div><?php endif; ?>
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

			<h2 style="margin-top:2em">★ Populārs</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mm_ready_save"><input type="hidden" name="do" value="popular">
				<?php wp_nonce_field( 'mm_ready' ); ?>
				<div style="columns:2;max-width:900px">
				<?php foreach ( $products as $p ) : ?>
					<label style="display:block;break-inside:avoid;margin:2px 0"><input type="checkbox" name="popular[]" value="<?php echo (int) $p->get_id(); ?>" <?php checked( in_array( (int) $p->get_id(), $pop, true ) ); ?>> <?php echo esc_html( $p->get_name() ); ?></label>
				<?php endforeach; ?>
				</div>
				<p><button class="button button-primary">Saglabāt populāros</button></p>
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
