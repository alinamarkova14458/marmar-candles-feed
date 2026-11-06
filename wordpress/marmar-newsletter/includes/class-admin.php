<?php
/**
 * Админка: поля рассылки, кнопки «тест / отправить», подписчики, настройки.
 */

defined( 'ABSPATH' ) || exit;

class Marmar_NL_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		add_action( 'add_meta_boxes_' . Marmar_NL_Campaigns::CPT, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . Marmar_NL_Campaigns::CPT, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'manage_' . Marmar_NL_Campaigns::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Marmar_NL_Campaigns::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );

		foreach ( array( 'test', 'start', 'pause', 'retry', 'import', 'import_csv', 'add_sub', 'unsub', 'delete_sub', 'smtp_test' ) as $a ) {
			add_action( 'admin_post_marmar_nl_' . $a, array( __CLASS__, 'action_' . $a ) );
		}
	}

	/* ---------- Меню и настройки ---------- */

	public static function menu() {
		$parent = 'edit.php?post_type=' . Marmar_NL_Campaigns::CPT;
		add_submenu_page( $parent, 'Подписчики', 'Подписчики', self::CAP, 'marmar-nl-subscribers', array( __CLASS__, 'page_subscribers' ) );
		add_submenu_page( $parent, 'Настройки рассылок', 'Настройки', self::CAP, 'marmar-nl-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function register_settings() {
		register_setting(
			'marmar_nl',
			Marmar_NL_Mailer::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	public static function sanitize_settings( $in ) {
		$old = (array) get_option( Marmar_NL_Mailer::OPTION, array() );
		$in  = (array) $in;
		$out = array(
			'from_email'   => sanitize_email( $in['from_email'] ?? '' ),
			'from_name'    => sanitize_text_field( $in['from_name'] ?? '' ),
			'smtp_enabled' => empty( $in['smtp_enabled'] ) ? 0 : 1,
			'smtp_host'    => sanitize_text_field( $in['smtp_host'] ?? 'smtp.gmail.com' ),
			'smtp_port'    => absint( $in['smtp_port'] ?? 587 ),
			'smtp_secure'  => ( $in['smtp_secure'] ?? 'tls' ) === 'ssl' ? 'ssl' : 'tls',
			'smtp_user'    => sanitize_text_field( $in['smtp_user'] ?? '' ),
			'smtp_all'     => empty( $in['smtp_all'] ) ? 0 : 1,
			'batch_size'   => max( 1, min( 100, absint( $in['batch_size'] ?? 20 ) ) ),
			'test_emails'  => sanitize_text_field( $in['test_emails'] ?? '' ),
			'address'      => sanitize_text_field( $in['address'] ?? '' ),
		);
		// Пустое поле пароля = оставить прежний (пароль не показываем в форме).
		$pass              = isset( $in['smtp_pass'] ) ? trim( (string) $in['smtp_pass'] ) : '';
		$out['smtp_pass']  = '' !== $pass ? preg_replace( '/\s+/', '', $pass ) : ( $old['smtp_pass'] ?? '' );
		return $out;
	}

	/* ---------- Экран рассылки ---------- */

	public static function meta_boxes() {
		add_meta_box( 'marmar_nl_fields', 'Письмо', array( __CLASS__, 'box_fields' ), null, 'normal', 'high' );
		add_meta_box( 'marmar_nl_send', 'Отправка', array( __CLASS__, 'box_send' ), null, 'side', 'high' );
	}

	public static function box_fields( $post ) {
		wp_nonce_field( 'marmar_nl_save', 'marmar_nl_nonce' );
		$subject   = get_post_meta( $post->ID, '_mm_subject', true );
		$preheader = get_post_meta( $post->ID, '_mm_preheader', true );
		$audience  = get_post_meta( $post->ID, '_mm_audience', true );
		$audience  = 'all' === $audience ? 'all' : 'consent';
		$counts    = Marmar_NL_Subscribers::counts();
		?>
		<p><label><strong>Тема письма</strong><br>
			<input type="text" name="mm_subject" value="<?php echo esc_attr( $subject ); ?>" class="widefat" maxlength="150"></label></p>
		<p><label><strong>Прехедер</strong> (серый текст после темы во входящих)<br>
			<input type="text" name="mm_preheader" value="<?php echo esc_attr( $preheader ); ?>" class="widefat" maxlength="200"></label></p>
		<p><strong>Кому</strong><br>
			<label><input type="radio" name="mm_audience" value="consent" <?php checked( $audience, 'consent' ); ?>>
				Только давшим согласие (<?php echo (int) $counts['consent']; ?>)</label><br>
			<label><input type="radio" name="mm_audience" value="all" <?php checked( $audience, 'all' ); ?>>
				Согласие + покупатели из импорта (<?php echo (int) $counts['subscribed']; ?>)</label>
		</p>
		<p class="description">В тексте можно писать <code>{vards}</code> — имя клиента, <code>{vards_komats}</code> — «, Имя» (или пусто, если имени нет).
			Ссылка «Atrakstīties» добавляется в подвал автоматически. Ко всем ссылкам на сайт добавляются UTM-метки для GA4.</p>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['marmar_nl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['marmar_nl_nonce'] ) ), 'marmar_nl_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		update_post_meta( $post_id, '_mm_subject', sanitize_text_field( wp_unslash( $_POST['mm_subject'] ?? '' ) ) );
		update_post_meta( $post_id, '_mm_preheader', sanitize_text_field( wp_unslash( $_POST['mm_preheader'] ?? '' ) ) );
		update_post_meta( $post_id, '_mm_audience', ( $_POST['mm_audience'] ?? '' ) === 'all' ? 'all' : 'consent' );
	}

	private static function action_url( $action, $args = array() ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'marmar_nl_' . $action ), $args ), admin_url( 'admin-post.php' ) ), 'marmar_nl_' . $action );
	}

	public static function box_send( $post ) {
		$state    = Marmar_NL_Campaigns::state( $post->ID );
		$stats    = Marmar_NL_Campaigns::stats( $post->ID );
		$settings = Marmar_NL_Mailer::settings();
		$audience = get_post_meta( $post->ID, '_mm_audience', true );
		$n        = Marmar_NL_Subscribers::count_audience( 'all' === $audience ? 'all' : 'consent' );
		$labels   = array(
			'draft'   => 'Черновик',
			'sending' => 'Отправляется…',
			'paused'  => 'На паузе',
			'sent'    => 'Отправлено',
		);
		echo '<p><strong>Статус:</strong> ' . esc_html( $labels[ $state ] ?? $state ) . '</p>';
		if ( array_sum( $stats ) ) {
			printf(
				'<p>Отправлено: <strong>%d</strong><br>В очереди: %d<br>Ошибки: %d<br>Пропущено (отписались): %d</p>',
				(int) $stats['sent'],
				(int) $stats['queued'],
				(int) $stats['failed'],
				(int) $stats['skipped']
			);
			foreach ( Marmar_NL_Campaigns::last_errors( $post->ID ) as $err ) {
				echo '<p style="color:#b32d2e;font-size:12px;">' . esc_html( $err ) . '</p>';
			}
		}
		if ( ! Marmar_NL_Mailer::smtp_ready() ) {
			echo '<p style="color:#b32d2e;">SMTP не настроен — письма уйдут через сервер хостинга и могут попасть в спам. <a href="' . esc_url( admin_url( 'edit.php?post_type=' . Marmar_NL_Campaigns::CPT . '&page=marmar-nl-settings' ) ) . '">Настроить</a></p>';
		}
		echo '<p class="description">Сначала нажмите «Обновить/Сохранить», потом кнопки ниже.</p>';
		echo '<p><a class="button" href="' . esc_url( self::action_url( 'test', array( 'campaign' => $post->ID ) ) ) . '">Отправить тест</a><br><span class="description">на: ' . esc_html( $settings['test_emails'] ) . '</span></p>';

		if ( in_array( $state, array( 'draft', 'paused' ), true ) && 'auto-draft' !== $post->post_status ) {
			$confirm = sprintf( 'Отправить письмо %d адресатам? Отменить отправленное будет нельзя.', $n );
			echo '<p><a class="button button-primary" onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');" href="' . esc_url( self::action_url( 'start', array( 'campaign' => $post->ID ) ) ) . '">' . ( 'paused' === $state ? 'Продолжить отправку' : 'Отправить всем (' . (int) $n . ')' ) . '</a></p>';
		}
		if ( 'sending' === $state ) {
			echo '<p><a class="button" href="' . esc_url( self::action_url( 'pause', array( 'campaign' => $post->ID ) ) ) . '">Пауза</a></p>';
		}
		if ( $stats['failed'] > 0 ) {
			echo '<p><a class="button" href="' . esc_url( self::action_url( 'retry', array( 'campaign' => $post->ID ) ) ) . '">Повторить неудачные</a></p>';
		}
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['mm_state'] = 'Статус';
				$new['mm_sent']  = 'Отправлено';
			}
		}
		return $new;
	}

	public static function column( $col, $post_id ) {
		if ( 'mm_state' === $col ) {
			echo esc_html( Marmar_NL_Campaigns::state( $post_id ) );
		} elseif ( 'mm_sent' === $col ) {
			$s = Marmar_NL_Campaigns::stats( $post_id );
			echo (int) $s['sent'] . ( $s['failed'] ? ' / ошибок: ' . (int) $s['failed'] : '' );
		}
	}

	/* ---------- Действия ---------- */

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'marmar_nl_' . $action );
	}

	private static function back( $msg, $type = 'success', $url = '' ) {
		set_transient( 'marmar_nl_notice_' . get_current_user_id(), array( $msg, $type ), 60 );
		wp_safe_redirect( $url ? $url : wp_get_referer() );
		exit;
	}

	private static function campaign_arg() {
		return isset( $_GET['campaign'] ) ? absint( $_GET['campaign'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function action_test() {
		self::guard( 'test' );
		$emails = preg_split( '/[\s,;]+/', (string) Marmar_NL_Mailer::settings()['test_emails'], -1, PREG_SPLIT_NO_EMPTY );
		$res    = Marmar_NL_Campaigns::send_test( self::campaign_arg(), $emails );
		$fail   = array();
		foreach ( $res as $to => $r ) {
			if ( true !== $r ) {
				$fail[] = $to . ': ' . $r;
			}
		}
		if ( ! $res ) {
			self::back( 'Не указан адрес для теста (Настройки).', 'error' );
		}
		self::back( $fail ? 'Тест не отправлен — ' . implode( '; ', $fail ) : 'Тест отправлен на: ' . implode( ', ', array_keys( $res ) ), $fail ? 'error' : 'success' );
	}

	public static function action_start() {
		self::guard( 'start' );
		$r = Marmar_NL_Campaigns::start( self::campaign_arg() );
		self::back( is_int( $r ) ? 'Рассылка запущена: уходит порциями раз в минуту.' : $r, is_int( $r ) ? 'success' : 'error' );
	}

	public static function action_pause() {
		self::guard( 'pause' );
		Marmar_NL_Campaigns::pause( self::campaign_arg() );
		self::back( 'Рассылка на паузе.' );
	}

	public static function action_retry() {
		self::guard( 'retry' );
		$n = Marmar_NL_Campaigns::retry_failed( self::campaign_arg() );
		self::back( sprintf( 'Повторно в очереди: %d.', $n ) );
	}

	public static function action_import() {
		self::guard( 'import' );
		$r = Marmar_NL_Subscribers::import_customers();
		self::back( sprintf( 'Импорт: новых %d, уже были %d, пропущено (отписались/ошибка) %d.', $r['created'], $r['exists'], $r['skipped'] ) );
	}

	public static function action_import_csv() {
		self::guard( 'import_csv' );
		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			self::back( 'Файл не выбран.', 'error' );
		}
		$r = Marmar_NL_Subscribers::import_csv( $_FILES['csv']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_string( $r ) ) {
			self::back( $r, 'error' );
		}
		self::back( sprintf( 'Импорт базы: новых %d, уже были %d, отписавшихся %d, пропущено %d, неверных адресов %d.', $r['created'], $r['exists'], $r['unsubscribed'], $r['skipped'], $r['invalid'] ) );
	}

	public static function action_add_sub() {
		self::guard( 'add_sub' );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$r     = Marmar_NL_Subscribers::add( $email, $name, 'manual' );
		$map   = array(
			'created'      => 'Подписчик добавлен.',
			'resubscribed' => 'Подписка восстановлена.',
			'exists'       => 'Такой адрес уже есть.',
			'invalid'      => 'Неверный email.',
		);
		self::back( $map[ $r ] ?? $r, 'invalid' === $r ? 'error' : 'success' );
	}

	public static function action_unsub() {
		self::guard( 'unsub' );
		Marmar_NL_Subscribers::unsubscribe( absint( $_GET['id'] ?? 0 ) );
		self::back( 'Отписан.' );
	}

	public static function action_delete_sub() {
		self::guard( 'delete_sub' );
		Marmar_NL_Subscribers::delete( absint( $_GET['id'] ?? 0 ) );
		self::back( 'Удалён (данные стёрты).' );
	}

	public static function action_smtp_test() {
		self::guard( 'smtp_test' );
		$s  = Marmar_NL_Mailer::settings();
		$to = preg_split( '/[\s,;]+/', (string) $s['test_emails'], -1, PREG_SPLIT_NO_EMPTY );
		$to = $to ? $to[0] : get_option( 'admin_email' );
		$r  = Marmar_NL_Mailer::send( $to, 'MarMar: SMTP pārbaude', '<p>Ja tu lasi šo — SMTP darbojas. ✨</p>', 'SMTP pārbaude', null, 'smtp-test' );
		self::back( true === $r ? 'Проверочное письмо отправлено на ' . $to : 'Ошибка: ' . $r, true === $r ? 'success' : 'error' );
	}

	public static function notices() {
		$key = 'marmar_nl_notice_' . get_current_user_id();
		$n   = get_transient( $key );
		if ( ! $n ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( 'error' === $n[1] ? 'error' : 'success' ), esc_html( $n[0] ) );
	}

	/* ---------- Страницы ---------- */

	public static function page_subscribers() {
		$counts = Marmar_NL_Subscribers::counts();
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows   = Marmar_NL_Subscribers::query( array( 'search' => $search, 'limit' => 50, 'offset' => ( $paged - 1 ) * 50 ) );
		$src    = array(
			'checkout' => 'галочка в заказе',
			'manual'   => 'вручную',
			'list'     => 'база подписчиков (CSV)',
			'customer' => 'покупатель (импорт)',
		);
		?>
		<div class="wrap">
			<h1>Подписчики</h1>
			<p>Подписаны: <strong><?php echo (int) $counts['subscribed']; ?></strong>
				(с согласием: <?php echo (int) $counts['consent']; ?>, покупатели из импорта: <?php echo (int) $counts['customers']; ?>) ·
				Отписались: <?php echo (int) $counts['unsubscribed']; ?></p>

			<div style="display:flex;gap:24px;flex-wrap:wrap;margin:16px 0;">
				<div class="card" style="max-width:420px;">
					<h2>Импорт покупателей</h2>
					<p>Добавляет покупателей из выполненных заказов. Им уходят только рассылки с аудиторией «Согласие + покупатели».
						Отписавшиеся не возвращаются.</p>
					<p><a class="button" href="<?php echo esc_url( self::action_url( 'import' ) ); ?>">Импортировать из заказов</a></p>
				</div>
				<div class="card" style="max-width:420px;">
					<h2>Загрузить базу подписчиков</h2>
					<p>CSV с колонкой email (и, если есть, имя). Подходит экспорт из Mailchimp, Excel или Google Sheets («Сохранить как CSV»).
						Эти люди считаются давшими согласие. Отписавшиеся в файле останутся отписанными.</p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="marmar_nl_import_csv">
						<?php wp_nonce_field( 'marmar_nl_import_csv' ); ?>
						<input type="file" name="csv" accept=".csv,text/csv" required>
						<button class="button">Загрузить</button>
					</form>
				</div>
				<div class="card" style="max-width:420px;">
					<h2>Добавить вручную</h2>
					<p>Только если человек сам попросил присылать новости.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="marmar_nl_add_sub">
						<?php wp_nonce_field( 'marmar_nl_add_sub' ); ?>
						<input type="email" name="email" placeholder="email" required>
						<input type="text" name="first_name" placeholder="Имя">
						<button class="button">Добавить</button>
					</form>
				</div>
			</div>

			<form method="get">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Marmar_NL_Campaigns::CPT ); ?>">
				<input type="hidden" name="page" value="marmar-nl-subscribers">
				<p class="search-box"><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Поиск по email"><button class="button">Найти</button></p>
			</form>

			<table class="widefat striped">
				<thead><tr><th>Email</th><th>Имя</th><th>Статус</th><th>Источник</th><th>Добавлен</th><th></th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6">Пока никого.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r->email ); ?></td>
						<td><?php echo esc_html( $r->first_name ); ?></td>
						<td><?php echo 'subscribed' === $r->status ? 'подписан' : 'отписался'; ?></td>
						<td><?php echo esc_html( $src[ $r->source ] ?? $r->source ); ?><?php echo $r->order_id ? ' · #' . (int) $r->order_id : ''; ?></td>
						<td><?php echo esc_html( get_date_from_gmt( $r->created_at, 'd.m.Y' ) ); ?></td>
						<td>
							<?php if ( 'subscribed' === $r->status ) : ?>
								<a href="<?php echo esc_url( self::action_url( 'unsub', array( 'id' => $r->id ) ) ); ?>">отписать</a> ·
							<?php endif; ?>
							<a href="<?php echo esc_url( self::action_url( 'delete_sub', array( 'id' => $r->id ) ) ); ?>" onclick="return confirm('Удалить данные этого человека полностью?');" style="color:#b32d2e;">удалить</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $rows ) === 50 ) : ?>
				<p><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>">Дальше →</a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function page_settings() {
		$s   = Marmar_NL_Mailer::settings();
		$opt = Marmar_NL_Mailer::OPTION;
		?>
		<div class="wrap">
			<h1>Настройки рассылок</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'marmar_nl' ); ?>
				<table class="form-table" role="presentation">
					<tr><th>Отправитель (имя)</th><td><input class="regular-text" name="<?php echo esc_attr( $opt ); ?>[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>"></td></tr>
					<tr><th>Отправитель (email)</th><td><input class="regular-text" type="email" name="<?php echo esc_attr( $opt ); ?>[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>">
						<p class="description">Для Google — тот же адрес, что «Логин SMTP», или его подтверждённый псевдоним.</p></td></tr>
					<tr><th>Адреса для теста</th><td><input class="regular-text" name="<?php echo esc_attr( $opt ); ?>[test_emails]" value="<?php echo esc_attr( $s['test_emails'] ); ?>">
						<p class="description">Через запятую.</p></td></tr>
					<tr><th>Адрес в подвале письма</th><td><input class="regular-text" name="<?php echo esc_attr( $opt ); ?>[address]" value="<?php echo esc_attr( $s['address'] ); ?>"></td></tr>
					<tr><th>Писем за минуту</th><td><input type="number" min="1" max="100" name="<?php echo esc_attr( $opt ); ?>[batch_size]" value="<?php echo (int) $s['batch_size']; ?>"></td></tr>
				</table>
				<h2>SMTP (Google)</h2>
				<p>Логин — ваш Google-адрес, пароль — <strong>пароль приложения</strong> (Google-аккаунт → Безопасность → Пароли приложений), не обычный пароль.</p>
				<table class="form-table" role="presentation">
					<tr><th>Включить SMTP</th><td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[smtp_enabled]" value="1" <?php checked( $s['smtp_enabled'] ); ?>> Отправлять рассылки через SMTP</label></td></tr>
					<tr><th>Сервер</th><td><input class="regular-text" name="<?php echo esc_attr( $opt ); ?>[smtp_host]" value="<?php echo esc_attr( $s['smtp_host'] ); ?>">
						порт <input type="number" style="width:80px" name="<?php echo esc_attr( $opt ); ?>[smtp_port]" value="<?php echo (int) $s['smtp_port']; ?>">
						<select name="<?php echo esc_attr( $opt ); ?>[smtp_secure]"><option value="tls" <?php selected( $s['smtp_secure'], 'tls' ); ?>>TLS (587)</option><option value="ssl" <?php selected( $s['smtp_secure'], 'ssl' ); ?>>SSL (465)</option></select></td></tr>
					<tr><th>Логин SMTP</th><td><input class="regular-text" name="<?php echo esc_attr( $opt ); ?>[smtp_user]" value="<?php echo esc_attr( $s['smtp_user'] ); ?>" autocomplete="off"></td></tr>
					<tr><th>Пароль приложения</th><td><input class="regular-text" type="password" name="<?php echo esc_attr( $opt ); ?>[smtp_pass]" value="" autocomplete="new-password" placeholder="<?php echo $s['smtp_pass'] ? '•••••••• (сохранён — оставьте пустым)' : ''; ?>"></td></tr>
					<tr><th>Вся почта сайта</th><td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[smtp_all]" value="1" <?php checked( $s['smtp_all'] ); ?>> Отправлять через SMTP и письма о заказах WooCommerce</label>
						<p class="description">Включайте после успешной проверки ниже — тогда и письма о заказах перестанут попадать в спам.</p></td></tr>
				</table>
				<?php submit_button( 'Сохранить' ); ?>
			</form>
			<p><a class="button" href="<?php echo esc_url( self::action_url( 'smtp_test' ) ); ?>">Отправить проверочное письмо</a></p>
		</div>
		<?php
	}
}
