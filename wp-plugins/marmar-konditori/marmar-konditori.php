<?php
/**
 * Plugin Name:       MarMar Konditori
 * Description:       Bezmaksas konditoru katalogs: pieteikuma forma ([marmar_konditori_forma]), moderācija administrācijā un katalogs ar filtriem ([marmar_konditori]).
 * Version:           1.0.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 * Text Domain:       marmar-konditori
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Konditori {

	const CPT    = 'mm_konditors';
	const OPTION = 'marmar_konditori';
	const VER    = '1.0.0';
	const MAX_MB = 5;

	public static function cities() {
		return array( 'Rīga', 'Jūrmala', 'Jelgava', 'Liepāja', 'Daugavpils', 'Ventspils', 'Rēzekne', 'Valmiera', 'Ogre', 'Jēkabpils', 'Cēsis', 'Tukums', 'Salaspils', 'Sigulda', 'Mārupe', 'Ķekava', 'Ādaži', 'Olaine', 'Bauska', 'Dobele', 'Talsi', 'Kuldīga', 'Saldus', 'Limbaži', 'Smiltene', 'Madona', 'Gulbene', 'Alūksne', 'Balvi', 'Krāslava', 'Ludza', 'Preiļi', 'Aizkraukle', 'Līvāni', 'Cita' );
	}

	public static function types() {
		return array( 'Bērnu tortes', 'Dzimšanas dienas tortes', 'Kāzu tortes', 'Bento tortes', 'Kēksiņi un deserti', 'Bez glutēna', 'Vegāniskas' );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_shortcode( 'marmar_konditori', array( __CLASS__, 'catalog' ) );
		add_shortcode( 'marmar_konditori_forma', array( __CLASS__, 'form' ) );
		add_action( 'init', array( __CLASS__, 'handle_submit' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'settings_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings_register' ) );
		add_filter( 'add_menu_classes', array( __CLASS__, 'pending_bubble' ) );
	}

	public static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'notify'     => get_option( 'admin_email' ),
				'candles'    => home_url( '/shop-2/' ),
				'form_url'   => '',
				'instagram'  => 'marmar.candles',
			)
		);
	}

	/* ---------------- CPT ---------------- */

	public static function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => 'Konditori',
					'singular_name' => 'Konditors',
					'add_new_item'  => 'Pievienot konditoru',
					'edit_item'     => 'Rediģēt konditoru',
					'menu_name'     => 'Konditori',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-store',
				'menu_position'       => 58,
				'supports'            => array( 'title', 'editor', 'thumbnail' ),
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			)
		);
	}

	private static function meta( $id ) {
		$types = get_post_meta( $id, '_mmk_types', true );
		return array(
			'city'      => (string) get_post_meta( $id, '_mmk_city', true ),
			'types'     => is_array( $types ) ? $types : array(),
			'instagram' => (string) get_post_meta( $id, '_mmk_instagram', true ),
			'phone'     => (string) get_post_meta( $id, '_mmk_phone', true ),
			'website'   => (string) get_post_meta( $id, '_mmk_website', true ),
			'email'     => (string) get_post_meta( $id, '_mmk_email', true ),
			'consent'   => (string) get_post_meta( $id, '_mmk_consent_at', true ),
		);
	}

	/** Instagram: «@vards», «vards» vai saite → tikai lietotājvārds. */
	public static function ig_handle( $raw ) {
		$raw = trim( (string) $raw );
		if ( preg_match( '~instagram\.com/([A-Za-z0-9._]+)~i', $raw, $m ) ) {
			$raw = $m[1];
		}
		$raw = ltrim( $raw, '@' );
		return preg_match( '/^[A-Za-z0-9._]{1,30}$/', $raw ) ? $raw : '';
	}

	/* ---------------- Assets ---------------- */

	public static function assets() {
		global $post;
		if ( ! $post || ( ! has_shortcode( $post->post_content, 'marmar_konditori' ) && ! has_shortcode( $post->post_content, 'marmar_konditori_forma' ) ) ) {
			return;
		}
		wp_enqueue_style( 'marmar-konditori', plugins_url( 'assets/konditori.css', __FILE__ ), array(), self::VER );
		wp_enqueue_script( 'marmar-konditori', plugins_url( 'assets/konditori.js', __FILE__ ), array(), self::VER, true );
	}

	/* ---------------- Catalog ---------------- */

	public static function catalog( $atts ) {
		$s     = self::settings();
		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			)
		);
		$cities = array();
		$types  = array();
		$cards  = '';
		foreach ( $posts as $p ) {
			$m = self::meta( $p->ID );
			if ( $m['city'] ) {
				$cities[ $m['city'] ] = true;
			}
			foreach ( $m['types'] as $t ) {
				$types[ $t ] = true;
			}
			$cards .= self::card( $p, $m, $s );
		}
		$form_url = $s['form_url'] ? $s['form_url'] : '#pieteikties';

		ob_start();
		echo '<div class="mmk" data-mmk>';
		if ( $posts ) {
			echo '<div class="mmk-tools">';
			echo '<label class="mmk-search"><span class="screen-reader-text">Meklēt</span><input type="search" placeholder="Meklēt pēc vārda vai pilsētas" data-mmk-q></label>';
			echo '<div class="mmk-chips" role="group" aria-label="Pilsēta">';
			echo '<button type="button" class="mmk-chip is-on" data-mmk-city="">Visa Latvija</button>';
			foreach ( array_intersect( self::cities(), array_keys( $cities ) ) as $c ) {
				echo '<button type="button" class="mmk-chip" data-mmk-city="' . esc_attr( $c ) . '">' . esc_html( $c ) . '</button>';
			}
			echo '</div>';
			if ( $types ) {
				echo '<div class="mmk-chips" role="group" aria-label="Tortes veids">';
				foreach ( array_intersect( self::types(), array_keys( $types ) ) as $t ) {
					echo '<button type="button" class="mmk-chip mmk-chip--type" data-mmk-type="' . esc_attr( $t ) . '" aria-pressed="false">' . esc_html( $t ) . '</button>';
				}
				echo '</div>';
			}
			echo '</div>';
			echo '<div class="mmk-grid">' . $cards . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in card()
			echo '<p class="mmk-empty" hidden data-mmk-empty>Nekas netika atrasts. Pamēģiniet citu pilsētu.</p>';
		} else {
			echo '<p class="mmk-empty">Katalogs tikko sācies — pirmie konditori drīz būs šeit.</p>';
		}
		echo '<div class="mmk-join"><span class="mmk-star" aria-hidden="true">✦</span><h2>Esat konditors? Pievienojieties bez maksas</h2>';
		echo '<p>Jūsu tortes redzēs cilvēki, kas meklē sveces svētkiem. Mēs atzīmēsim jūs Instagram un uztaisīsim kopīgu ierakstu.</p>';
		echo '<a class="mmk-btn" href="' . esc_url( $form_url ) . '">Pieteikties katalogā</a></div>';
		echo '</div>';
		return ob_get_clean();
	}

	private static function card( $p, $m, $s ) {
		$img  = get_the_post_thumbnail( $p->ID, 'medium_large', array( 'class' => 'mmk-img', 'loading' => 'lazy', 'alt' => esc_attr( $p->post_title . ' — torte' ) ) );
		$ig   = self::ig_handle( $m['instagram'] );
		$hay  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $p->post_title . ' ' . $m['city'] ) : strtolower( $p->post_title . ' ' . $m['city'] );
		$html = '<article class="mmk-card" data-city="' . esc_attr( $m['city'] ) . '" data-types="' . esc_attr( implode( '|', $m['types'] ) ) . '" data-hay="' . esc_attr( $hay ) . '">';
		$html .= $img ? $img : '<div class="mmk-img mmk-img--empty" aria-hidden="true">✦</div>';
		$html .= '<div class="mmk-body"><div class="mmk-head"><h3>' . esc_html( $p->post_title ) . '</h3>';
		if ( $m['city'] ) {
			$html .= '<span class="mmk-city">' . esc_html( $m['city'] ) . '</span>';
		}
		$html .= '</div>';
		if ( $m['types'] ) {
			$html .= '<div class="mmk-tags">';
			foreach ( $m['types'] as $t ) {
				$html .= '<span>' . esc_html( $t ) . '</span>';
			}
			$html .= '</div>';
		}
		$about = trim( wp_strip_all_tags( $p->post_content ) );
		if ( $about ) {
			$html .= '<p class="mmk-about">' . esc_html( wp_trim_words( $about, 30 ) ) . '</p>';
		}
		$html .= '<div class="mmk-links">';
		if ( $ig ) {
			$html .= '<a href="' . esc_url( 'https://www.instagram.com/' . $ig . '/' ) . '" target="_blank" rel="noopener nofollow">Instagram</a>';
		}
		if ( $m['phone'] ) {
			$html .= '<a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $m['phone'] ) ) . '">Zvanīt</a>';
		}
		if ( $m['website'] ) {
			$html .= '<a href="' . esc_url( $m['website'] ) . '" target="_blank" rel="noopener">Mājaslapa</a>';
		}
		$html .= '</div>';
		$html .= '<a class="mmk-btn mmk-btn--candles" href="' . esc_url( $s['candles'] ) . '">✦ Sveces šai tortei</a>';
		$html .= '</div></article>';
		return $html;
	}

	/* ---------------- Form ---------------- */

	public static function form() {
		// Formā ir nonce — lapu nedrīkst kešot (LiteSpeed Cache).
		do_action( 'litespeed_control_set_nocache', 'marmar-konditori form' );
		$done = isset( $_GET['mmk'] ) && 'ok' === $_GET['mmk']; // phpcs:ignore WordPress.Security.NonceVerification
		$err  = isset( $_GET['mmk_err'] ) ? sanitize_key( wp_unslash( $_GET['mmk_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$msgs = array(
			'nonce'   => 'Lapa bija atvērta pārāk ilgi. Lūdzu, mēģiniet vēlreiz.',
			'fields'  => 'Lūdzu, aizpildiet visus obligātos laukus (*).',
			'consent' => 'Lai publicētu, nepieciešama jūsu piekrišana.',
			'contact' => 'Norādiet vismaz Instagram vai tālruni, lai klienti varētu sazināties.',
			'photo'   => 'Pievienojiet tortes foto (JPG, PNG vai WebP, līdz ' . self::MAX_MB . ' MB).',
			'email'   => 'Lūdzu, pārbaudiet e-pasta adresi.',
			'slow'    => 'Pārāk daudz pieteikumu. Lūdzu, mēģiniet vēlāk.',
		);
		ob_start();
		echo '<div class="mmk-form-wrap" id="pieteikties">';
		if ( $done ) {
			echo '<div class="mmk-notice mmk-notice--ok" role="status"><strong>Paldies! Pieteikums saņemts.</strong> Mēs to apskatīsim un, tiklīdz jūsu kartīte būs publicēta, nosūtīsim e-pastu.</div></div>';
			return ob_get_clean();
		}
		if ( $err && isset( $msgs[ $err ] ) ) {
			echo '<div class="mmk-notice mmk-notice--err" role="alert">' . esc_html( $msgs[ $err ] ) . '</div>';
		}
		$s = self::settings();
		?>
		<form class="mmk-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( remove_query_arg( array( 'mmk', 'mmk_err' ) ) ); ?>#pieteikties">
			<?php wp_nonce_field( 'mmk_submit', 'mmk_nonce' ); ?>
			<input type="hidden" name="mmk_action" value="submit">
			<input type="hidden" name="mmk_t" value="<?php echo esc_attr( time() ); ?>">
			<div class="mmk-hp" aria-hidden="true"><label>Neaizpildīt <input type="text" name="mmk_website2" tabindex="-1" autocomplete="off"></label></div>

			<label class="mmk-field"><span>Vārds vai konditorejas nosaukums *</span><input type="text" name="mmk_name" required maxlength="80" autocomplete="organization"></label>
			<label class="mmk-field"><span>Pilsēta *</span><select name="mmk_city" required><option value="">— izvēlieties —</option>
				<?php foreach ( self::cities() as $c ) : ?><option><?php echo esc_html( $c ); ?></option><?php endforeach; ?>
			</select></label>
			<fieldset class="mmk-field"><legend>Ko gatavojat?</legend><div class="mmk-checks">
				<?php foreach ( self::types() as $t ) : ?><label><input type="checkbox" name="mmk_types[]" value="<?php echo esc_attr( $t ); ?>"> <?php echo esc_html( $t ); ?></label><?php endforeach; ?>
			</div></fieldset>
			<label class="mmk-field"><span>Instagram</span><input type="text" name="mmk_instagram" placeholder="@jusu.konditoreja" maxlength="100"></label>
			<label class="mmk-field"><span>Tālrunis (būs redzams klientiem)</span><input type="tel" name="mmk_phone" placeholder="+371 …" maxlength="30" autocomplete="tel"></label>
			<label class="mmk-field"><span>Mājaslapa</span><input type="url" name="mmk_website" placeholder="https://…" maxlength="200"></label>
			<label class="mmk-field"><span>E-pasts saziņai ar mums * <small>(netiks publicēts)</small></span><input type="email" name="mmk_email" required maxlength="120" autocomplete="email"></label>
			<label class="mmk-field"><span>Īsi par sevi</span><textarea name="mmk_about" rows="3" maxlength="400" placeholder="Piemēram: cepu bērnu tortes ar dabīgiem krēmiem, piegāde Rīgā."></textarea></label>
			<label class="mmk-field"><span>Jūsu tortes foto * <small>(JPG, PNG vai WebP, līdz <?php echo (int) self::MAX_MB; ?> MB)</small></span><input type="file" name="mmk_photo" accept="image/jpeg,image/png,image/webp" required></label>

			<label class="mmk-consent"><input type="checkbox" name="mmk_consent" value="1" required> <span>Piekrītu, ka MarMar Candles publicē manu vārdu, pilsētu, foto un norādīto Instagram/tālruni/mājaslapu konditoru katalogā. Datus varu mainīt vai dzēst jebkurā brīdī, rakstot uz <?php echo esc_html( $s['notify'] ); ?>. *</span></label>
			<label class="mmk-consent"><input type="checkbox" name="mmk_collab" value="1"> <span>Esmu gatavs(-a) atzīmēt @<?php echo esc_html( $s['instagram'] ); ?> Instagram un uztaisīt kopīgu ierakstu.</span></label>

			<button type="submit" class="mmk-btn">Nosūtīt pieteikumu</button>
			<p class="mmk-small">Pēc pārbaudes publicēsim jūsu kartīti un paziņosim e-pastā. Bez maksas.</p>
		</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function back( $code ) {
		// Forma sūta uz to pašu lapu, tāpēc wp_get_referer() šeit atgriež false — ņemam neapstrādāto un pārbaudām.
		$ref = wp_validate_redirect( wp_get_raw_referer(), '' );
		$url = $ref ? $ref : home_url( '/' );
		$url = remove_query_arg( array( 'mmk', 'mmk_err' ), $url );
		$url = 'ok' === $code ? add_query_arg( 'mmk', 'ok', $url ) : add_query_arg( 'mmk_err', $code, $url );
		wp_safe_redirect( $url . '#pieteikties' );
		exit;
	}

	public static function handle_submit() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['mmk_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! isset( $_POST['mmk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mmk_nonce'] ) ), 'mmk_submit' ) ) {
			self::back( 'nonce' );
		}
		// Botu filtri: slēptais lauks un pārāk ātra iesniegšana — izliekamies, ka viss kārtībā.
		$t = (int) ( $_POST['mmk_t'] ?? 0 );
		if ( ! empty( $_POST['mmk_website2'] ) || ( $t && time() - $t < 4 ) ) {
			self::back( 'ok' );
		}
		$ip_key = 'mmk_rate_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$count  = (int) get_transient( $ip_key );
		if ( $count >= 5 ) {
			self::back( 'slow' );
		}

		$name    = sanitize_text_field( wp_unslash( $_POST['mmk_name'] ?? '' ) );
		$city    = sanitize_text_field( wp_unslash( $_POST['mmk_city'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['mmk_email'] ?? '' ) );
		$ig      = self::ig_handle( wp_unslash( $_POST['mmk_instagram'] ?? '' ) );
		$phone   = preg_replace( '/[^0-9+ ()-]/', '', (string) wp_unslash( $_POST['mmk_phone'] ?? '' ) );
		$website = esc_url_raw( wp_unslash( $_POST['mmk_website'] ?? '' ) );
		$about   = sanitize_textarea_field( wp_unslash( $_POST['mmk_about'] ?? '' ) );
		$types   = array_values( array_intersect( self::types(), array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['mmk_types'] ?? array() ) ) ) );

		if ( '' === $name || ! in_array( $city, self::cities(), true ) ) {
			self::back( 'fields' );
		}
		if ( ! is_email( $email ) ) {
			self::back( 'email' );
		}
		if ( empty( $_POST['mmk_consent'] ) ) {
			self::back( 'consent' );
		}
		if ( '' === $ig && '' === trim( $phone ) ) {
			self::back( 'contact' );
		}
		$f = $_FILES['mmk_photo'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $f || UPLOAD_ERR_OK !== (int) $f['error'] || (int) $f['size'] > self::MAX_MB * MB_IN_BYTES ) {
			self::back( 'photo' );
		}
		$check = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
		if ( empty( $check['type'] ) || ! @getimagesize( $f['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			self::back( 'photo' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::CPT,
				'post_status'  => 'pending',
				'post_title'   => $name,
				'post_content' => $about,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			self::back( 'fields' );
		}
		update_post_meta( $post_id, '_mmk_city', $city );
		update_post_meta( $post_id, '_mmk_types', $types );
		update_post_meta( $post_id, '_mmk_instagram', $ig );
		update_post_meta( $post_id, '_mmk_phone', trim( $phone ) );
		update_post_meta( $post_id, '_mmk_website', $website );
		update_post_meta( $post_id, '_mmk_email', $email );
		update_post_meta( $post_id, '_mmk_collab', empty( $_POST['mmk_collab'] ) ? 'no' : 'yes' );
		update_post_meta( $post_id, '_mmk_consent_at', current_time( 'mysql' ) );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$_FILES['mmk_photo']['name'] = sanitize_file_name( 'konditors-' . sanitize_title( $name ) . '-torte.' . $check['ext'] );
		$att = media_handle_upload( 'mmk_photo', $post_id, array( 'post_title' => $name . ' — torte' ) );
		if ( ! is_wp_error( $att ) ) {
			set_post_thumbnail( $post_id, $att );
			update_post_meta( $att, '_wp_attachment_image_alt', $name . ' — torte' );
		}

		set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

		$s = self::settings();
		if ( $s['notify'] ) {
			wp_mail(
				$s['notify'],
				'Jauns konditors katalogam: ' . $name,
				"Jauns pieteikums konditoru katalogam.\n\nVārds: {$name}\nPilsēta: {$city}\nInstagram: " . ( $ig ? '@' . $ig : '—' ) . "\nTālrunis: " . ( $phone ? $phone : '—' ) . "\nE-pasts: {$email}\n\nApstiprināt: " . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				array( 'Reply-To: ' . $email )
			);
		}
		self::back( 'ok' );
	}

	/** Kad pieteikums publicēts — paziņojam konditoram (vienreiz). */
	public static function on_publish( $new, $old, $post ) {
		if ( self::CPT !== $post->post_type || 'publish' !== $new || 'publish' === $old ) {
			return;
		}
		if ( get_post_meta( $post->ID, '_mmk_notified', true ) ) {
			return;
		}
		$email = get_post_meta( $post->ID, '_mmk_email', true );
		if ( ! is_email( $email ) ) {
			return;
		}
		$s    = self::settings();
		$page = self::catalog_page_url();
		wp_mail(
			$email,
			'Jūsu kartīte ir MarMar Candles konditoru katalogā ✦',
			"Sveiki!\n\nPaldies, ka pievienojāties! Jūsu kartīte \"" . $post->post_title . "\" ir publicēta: " . ( $page ? $page : home_url( '/' ) ) . "\n\nMēs būsim priecīgi, ja atzīmēsiet @" . $s['instagram'] . " Instagram — tad uztaisīsim arī kopīgu ierakstu.\n\nJa vēlaties ko mainīt vai dzēst, vienkārši atbildiet uz šo e-pastu.\n\nAr siltiem sveicieniem,\nMarMar Candles",
			array( 'Reply-To: ' . $s['notify'] )
		);
		update_post_meta( $post->ID, '_mmk_notified', current_time( 'mysql' ) );
	}

	private static function catalog_page_url() {
		global $wpdb;
		$id = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish' AND post_content LIKE '%[marmar_konditori]%' LIMIT 1" ); // phpcs:ignore
		return $id ? get_permalink( (int) $id ) : '';
	}

	/* ---------------- Admin ---------------- */

	public static function meta_box() {
		add_meta_box( 'mmk_meta', 'Kontakti un dati', array( __CLASS__, 'meta_box_html' ), self::CPT, 'normal', 'high' );
	}

	public static function meta_box_html( $post ) {
		$m = self::meta( $post->ID );
		wp_nonce_field( 'mmk_meta', 'mmk_meta_nonce' );
		echo '<table class="form-table">';
		echo '<tr><th>Pilsēta</th><td><select name="mmk_city"><option value=""></option>';
		foreach ( self::cities() as $c ) {
			echo '<option ' . selected( $m['city'], $c, false ) . '>' . esc_html( $c ) . '</option>';
		}
		echo '</select></td></tr><tr><th>Ko gatavo</th><td>';
		foreach ( self::types() as $t ) {
			echo '<label style="display:inline-block;margin-right:12px"><input type="checkbox" name="mmk_types[]" value="' . esc_attr( $t ) . '" ' . checked( in_array( $t, $m['types'], true ), true, false ) . '> ' . esc_html( $t ) . '</label>';
		}
		echo '</td></tr>';
		foreach ( array( 'instagram' => 'Instagram', 'phone' => 'Tālrunis (publisks)', 'website' => 'Mājaslapa', 'email' => 'E-pasts (nav publisks)' ) as $k => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td><input class="regular-text" name="mmk_' . esc_attr( $k ) . '" value="' . esc_attr( $m[ $k ] ) . '"></td></tr>';
		}
		$collab = get_post_meta( $post->ID, '_mmk_collab', true );
		echo '<tr><th>Kopīgs ieraksts</th><td>' . ( 'yes' === $collab ? '✅ piekrīt' : '—' ) . '</td></tr>';
		echo '<tr><th>Piekrišana publicēšanai</th><td>' . ( $m['consent'] ? esc_html( $m['consent'] ) : 'pievienots manuāli' ) . '</td></tr>';
		echo '</table><p class="description">Foto — “Featured image” labajā pusē. Lai parādītu katalogā, nospiediet “Publish”.</p>';
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['mmk_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mmk_meta_nonce'] ) ), 'mmk_meta' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$city = sanitize_text_field( wp_unslash( $_POST['mmk_city'] ?? '' ) );
		update_post_meta( $post_id, '_mmk_city', in_array( $city, self::cities(), true ) ? $city : '' );
		update_post_meta( $post_id, '_mmk_types', array_values( array_intersect( self::types(), array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['mmk_types'] ?? array() ) ) ) ) );
		update_post_meta( $post_id, '_mmk_instagram', self::ig_handle( wp_unslash( $_POST['mmk_instagram'] ?? '' ) ) );
		update_post_meta( $post_id, '_mmk_phone', preg_replace( '/[^0-9+ ()-]/', '', (string) wp_unslash( $_POST['mmk_phone'] ?? '' ) ) );
		update_post_meta( $post_id, '_mmk_website', esc_url_raw( wp_unslash( $_POST['mmk_website'] ?? '' ) ) );
		update_post_meta( $post_id, '_mmk_email', sanitize_email( wp_unslash( $_POST['mmk_email'] ?? '' ) ) );
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['mmk_photo'] = 'Foto';
				$new['mmk_city']  = 'Pilsēta';
				$new['mmk_ig']    = 'Instagram';
			}
		}
		return $new;
	}

	public static function column( $col, $id ) {
		if ( 'mmk_photo' === $col ) {
			echo get_the_post_thumbnail( $id, array( 60, 60 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'mmk_city' === $col ) {
			echo esc_html( get_post_meta( $id, '_mmk_city', true ) );
		} elseif ( 'mmk_ig' === $col ) {
			$ig = get_post_meta( $id, '_mmk_instagram', true );
			echo $ig ? '<a href="' . esc_url( 'https://www.instagram.com/' . $ig . '/' ) . '" target="_blank" rel="noopener">@' . esc_html( $ig ) . '</a>' : '—';
		}
	}

	public static function pending_bubble( $menu ) {
		$n = (int) wp_count_posts( self::CPT )->pending;
		if ( $n ) {
			foreach ( $menu as $i => $item ) {
				if ( 'edit.php?post_type=' . self::CPT === $item[2] ) {
					$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $n . '</span></span>';
				}
			}
		}
		return $menu;
	}

	public static function settings_menu() {
		add_submenu_page( 'edit.php?post_type=' . self::CPT, 'Iestatījumi', 'Iestatījumi', 'manage_options', 'mmk-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function settings_register() {
		register_setting(
			'mmk_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $in ) {
					$in = is_array( $in ) ? $in : array();
					return array(
						'notify'    => sanitize_email( $in['notify'] ?? '' ),
						'candles'   => esc_url_raw( $in['candles'] ?? '' ),
						'form_url'  => esc_url_raw( $in['form_url'] ?? '' ),
						'instagram' => self::ig_handle( $in['instagram'] ?? '' ),
					);
				},
			)
		);
	}

	public static function settings_page() {
		$s = self::settings();
		$o = self::OPTION;
		echo '<div class="wrap"><h1>Konditori — iestatījumi</h1><form method="post" action="options.php">';
		settings_fields( 'mmk_settings' );
		echo '<table class="form-table">';
		echo '<tr><th>E-pasts paziņojumiem</th><td><input class="regular-text" name="' . esc_attr( $o ) . '[notify]" value="' . esc_attr( $s['notify'] ) . '"></td></tr>';
		echo '<tr><th>Saite “Sveces šai tortei”</th><td><input class="regular-text" name="' . esc_attr( $o ) . '[candles]" value="' . esc_attr( $s['candles'] ) . '"></td></tr>';
		echo '<tr><th>Pieteikuma formas lapa</th><td><input class="regular-text" name="' . esc_attr( $o ) . '[form_url]" value="' . esc_attr( $s['form_url'] ) . '"><p class="description">Tukšs — forma tajā pašā lapā zem kataloga (#pieteikties).</p></td></tr>';
		echo '<tr><th>Mūsu Instagram</th><td>@<input name="' . esc_attr( $o ) . '[instagram]" value="' . esc_attr( $s['instagram'] ) . '"></td></tr>';
		echo '</table>';
		submit_button( 'Saglabāt' );
		echo '</form><p>Shortcodes: <code>[marmar_konditori]</code> — katalogs, <code>[marmar_konditori_forma]</code> — pieteikuma forma.</p></div>';
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Konditori', 'init' ) );
