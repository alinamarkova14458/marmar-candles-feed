<?php
/**
 * Шаблон письма рассылки.
 *
 * Переменные: $content_html, $preheader, $unsub_url, $logo_url, $site_url, $site_name, $address.
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( $site_name ); ?></title>
<style>
	.mm-content img { max-width: 100%; height: auto; border-radius: 10px; }
	.mm-content a { color: #8a4b3c; }
	@media (max-width: 620px) {
		.mm-card { padding: 24px 18px !important; }
		.mm-col { display: block !important; width: 100% !important; }
	}
</style>
</head>
<body style="margin:0;padding:0;background:#f6efe9;">
<span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;"><?php echo esc_html( $preheader ); ?>&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f6efe9;">
	<tr>
		<td align="center" style="padding:24px 12px;">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
				<tr>
					<td align="center" style="padding:8px 0 20px;">
						<a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;color:#3d2b26;font-family:Georgia,'Times New Roman',serif;font-size:24px;letter-spacing:1px;">
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="160" style="display:block;width:160px;max-width:160px;height:auto;border:0;">
							<?php else : ?>
								<?php echo esc_html( $site_name ); ?>
							<?php endif; ?>
						</a>
					</td>
				</tr>
				<tr>
					<td class="mm-card mm-content" style="background:#ffffff;border-radius:16px;padding:36px 40px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#3d2b26;">
						<?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML кампании пишет администратор. ?>
					</td>
				</tr>
				<tr>
					<td align="center" style="padding:20px 16px 8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#8c7b74;">
						<?php echo esc_html( $address ); ?><br>
						Tu saņem šo vēstuli, jo esi iepirkusies(-ies) vai pierakstījies(-usies) jaunumiem <a href="<?php echo esc_url( $site_url ); ?>" style="color:#8c7b74;"><?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ); ?></a>.<br>
						<a href="<?php echo esc_url( $unsub_url ); ?>" style="color:#8c7b74;text-decoration:underline;">Atrakstīties no jaunumiem</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
