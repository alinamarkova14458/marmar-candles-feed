<?php
defined( 'ABSPATH' ) || exit;

/**
 * Конструктор на странице товара.
 */
class MarMar_Wedding_Product {

	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render' ), 5 );
		add_filter( 'woocommerce_quantity_input_args', array( __CLASS__, 'quantity_args' ), 20, 2 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 20, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'loop_button_text' ), 20, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'loop_button_url' ), 20, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( __CLASS__, 'loop_button_args' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	private static function current() {
		if ( ! is_product() ) {
			return null;
		}
		$p = wc_get_product( get_queried_object_id() );
		return ( $p && MarMar_Wedding_Settings::is_wedding( $p ) ) ? $p : null;
	}

	public static function assets() {
		if ( ! self::current() ) {
			return;
		}
		$url = plugin_dir_url( MARMAR_WEDDING_FILE );
		wp_enqueue_style( 'marmar-wedding', $url . 'assets/wedding.css', array(), MARMAR_WEDDING_VERSION );
		wp_enqueue_script( 'marmar-wedding', $url . 'assets/wedding.js', array(), MARMAR_WEDDING_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'marmar-wedding',
			'MarMarWedding',
			array(
				'tiers'     => MarMar_Wedding_Settings::tiers(),
				'minQty'    => MarMar_Wedding_Settings::min_qty(),
				'upload'    => WC_AJAX::get_endpoint( 'marmar_wedding_upload' ),
				'maxFiles'  => MarMar_Wedding_Uploads::MAX_FILES,
				'maxBytes'  => MarMar_Wedding_Uploads::MAX_BYTES,
				'decimal'   => wc_get_price_decimal_separator(),
				'i18n'      => array(
					'each'      => '€ / gab.',
					'total'     => 'Kopā',
					'uploading' => 'Augšupielādē…',
					'remove'    => 'Noņemt',
					'tooBig'    => 'Fails ir lielāks par 10 MB.',
					'tooMany'   => 'Var pievienot ne vairāk kā 3 failus.',
					'failed'    => 'Neizdevās augšupielādēt failu.',
				),
			)
		);
	}

	public static function body_class( $classes ) {
		if ( self::current() ) {
			$classes[] = 'mmw-product';
		}
		return $classes;
	}

	public static function quantity_args( $args, $product ) {
		if ( MarMar_Wedding_Settings::is_wedding( $product ) ) {
			$min                 = MarMar_Wedding_Settings::min_qty();
			$args['min_value']   = $min;
			$args['input_value'] = max( $min, (int) ( $args['input_value'] ?? $min ) );
			$args['step']        = 1;
		}
		return $args;
	}

	private static function money( $v ) {
		return wc_price( $v, array( 'decimals' => 2 ) );
	}

	public static function price_html( $html, $product ) {
		if ( ! MarMar_Wedding_Settings::is_wedding( $product ) ) {
			return $html;
		}
		$tiers = MarMar_Wedding_Settings::tiers();
		if ( ! $tiers ) {
			return $html;
		}
		$min = min( $tiers );
		return 'no ' . self::money( $min ) . ' <span class="mmw-unit">/ gab.</span>';
	}

	/* В каталоге кнопка ведёт на страницу конструктора, а не кладёт товар в корзину. */
	public static function loop_button_text( $text, $product ) {
		return MarMar_Wedding_Settings::is_wedding( $product ) ? 'Izveidot' : $text;
	}

	public static function loop_button_url( $url, $product ) {
		return MarMar_Wedding_Settings::is_wedding( $product ) ? $product->get_permalink() : $url;
	}

	public static function loop_button_args( $args, $product ) {
		if ( MarMar_Wedding_Settings::is_wedding( $product ) && isset( $args['class'] ) ) {
			$args['class'] = trim( str_replace( array( 'ajax_add_to_cart', 'add_to_cart_button' ), '', $args['class'] ) );
		}
		return $args;
	}

	public static function render() {
		global $product;
		if ( ! $product || ! MarMar_Wedding_Settings::is_wedding( $product ) ) {
			return;
		}
		$s      = MarMar_Wedding_Settings::all();
		$types  = MarMar_Wedding_Settings::types();
		$tiers  = MarMar_Wedding_Settings::tiers();
		$posted = function ( $k, $d = '' ) {
			return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : $d; // phpcs:ignore WordPress.Security.NonceVerification
		};
		?>
		<div class="mmw" data-mmw>
			<?php if ( $s['intro'] ) : ?>
				<p class="mmw-intro"><?php echo esc_html( $s['intro'] ); ?></p>
			<?php endif; ?>

			<fieldset class="mmw-step">
				<legend><span class="mmw-n">1</span> Svece</legend>
				<div class="mmw-cards">
					<?php foreach ( $types as $i => $t ) : ?>
						<label class="mmw-card">
							<input type="radio" name="mmw_type" value="<?php echo esc_attr( $t ); ?>" <?php checked( $posted( 'mmw_type', $types[0] ?? '' ), $t ); ?> required>
							<span><?php echo esc_html( $t ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<fieldset class="mmw-step">
				<legend><span class="mmw-n">2</span> Daudzums</legend>
				<table class="mmw-tiers">
					<?php
					$froms = array_keys( $tiers );
					foreach ( $froms as $i => $from ) :
						$to = isset( $froms[ $i + 1 ] ) ? $froms[ $i + 1 ] - 1 : null;
						?>
						<tr data-from="<?php echo (int) $from; ?>">
							<td><?php echo esc_html( null === $to ? $from . '+ gab.' : $from . '–' . $to . ' gab.' ); ?></td>
							<td><?php echo wp_kses_post( self::money( $tiers[ $from ] ) ); ?> / gab.</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="mmw-help">Minimālais daudzums — <?php echo (int) MarMar_Wedding_Settings::min_qty(); ?> gab. Daudzumu ievadiet zemāk pie pogas.</p>
			</fieldset>

			<fieldset class="mmw-step">
				<legend><span class="mmw-n">3</span> Lentes krāsa</legend>
				<div class="mmw-swatches">
					<?php foreach ( MarMar_Wedding_Settings::ribbons() as $name => $hex ) : ?>
						<label class="mmw-swatch" title="<?php echo esc_attr( $name ); ?>">
							<input type="radio" name="mmw_ribbon" value="<?php echo esc_attr( $name ); ?>" <?php checked( $posted( 'mmw_ribbon' ), $name ); ?> required>
							<span class="mmw-dot" style="background:<?php echo esc_attr( $hex ); ?>"></span>
							<span class="mmw-swatch-name"><?php echo esc_html( $name ); ?></span>
						</label>
					<?php endforeach; ?>
					<label class="mmw-swatch">
						<input type="radio" name="mmw_ribbon" value="Cita" <?php checked( $posted( 'mmw_ribbon' ), 'Cita' ); ?>>
						<span class="mmw-dot mmw-dot--other">?</span>
						<span class="mmw-swatch-name">Cita — aprakstīšu komentārā</span>
					</label>
				</div>
			</fieldset>

			<fieldset class="mmw-step">
				<legend><span class="mmw-n">4</span> Personalizācija</legend>
				<div class="mmw-options">
					<?php foreach ( MarMar_Wedding_Settings::personalisations() as $key => $label ) : ?>
						<label class="mmw-option">
							<input type="radio" name="mmw_pers" value="<?php echo esc_attr( $key ); ?>" <?php checked( $posted( 'mmw_pers', 'tag' ), $key ); ?>>
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<div class="mmw-pers-fields" data-for="tag sticker">
					<p class="mmw-field">
						<label for="mmw_names">Teksts (piem., vārdi)</label>
						<input type="text" id="mmw_names" name="mmw_names" maxlength="80" placeholder="Anna &amp; Jānis" value="<?php echo esc_attr( $posted( 'mmw_names' ) ); ?>">
					</p>
					<p class="mmw-field">
						<label for="mmw_text_date">Datums uz birkas/uzlīmes</label>
						<input type="text" id="mmw_text_date" name="mmw_text_date" maxlength="40" placeholder="12.06.2027" value="<?php echo esc_attr( $posted( 'mmw_text_date' ) ); ?>">
					</p>
				</div>
				<div class="mmw-pers-fields" data-for="sticker">
					<p class="mmw-field">
						<span class="mmw-label">Ielūgums, gatavs dizains vai iedvesmas attēls</span>
						<span class="mmw-upload">
							<input type="file" id="mmw_file" accept=".jpg,.jpeg,.png,.webp,.heic,.pdf" multiple>
							<label for="mmw_file" class="mmw-upload-btn">＋ Pievienot failu</label>
							<small>JPG, PNG, PDF · līdz 10 MB · līdz 3 failiem</small>
						</span>
						<span class="mmw-files" aria-live="polite"></span>
						<input type="hidden" name="mmw_files" value="<?php echo esc_attr( $posted( 'mmw_files' ) ); ?>">
					</p>
				</div>
			</fieldset>

			<fieldset class="mmw-step">
				<legend><span class="mmw-n">5</span> Kāzu datums un vēlmes</legend>
				<p class="mmw-field">
					<label for="mmw_wedding_date">Kāzu datums</label>
					<input type="date" id="mmw_wedding_date" name="mmw_wedding_date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $posted( 'mmw_wedding_date' ) ); ?>" required>
				</p>
				<p class="mmw-field">
					<label for="mmw_comment">Komentārs (stils, krāsas, vēlmes)</label>
					<textarea id="mmw_comment" name="mmw_comment" rows="3" maxlength="1000"><?php echo isset( $_POST['mmw_comment'] ) ? esc_textarea( sanitize_textarea_field( wp_unslash( $_POST['mmw_comment'] ) ) ) : ''; // phpcs:ignore ?></textarea>
				</p>
			</fieldset>

			<div class="mmw-summary" aria-live="polite">
				<span class="mmw-summary-unit"></span>
				<strong class="mmw-summary-total"></strong>
			</div>
			<?php if ( $s['after'] ) : ?>
				<p class="mmw-after"><?php echo esc_html( $s['after'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
