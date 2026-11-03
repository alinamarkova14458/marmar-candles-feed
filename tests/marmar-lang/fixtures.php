<?php
/**
 * Test fixtures for marmar-lang (run with: wp eval-file fixtures.php).
 * Writes translation meta directly so fixtures do not depend on the plugin.
 */

function mmfx_cat( $name, $slug, $meta = array() ) {
	$t = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug, 'description' => 'Latviešu kategorijas apraksts.' ) );
	$id = is_wp_error( $t ) ? (int) $t->get_error_data() : (int) $t['term_id'];
	foreach ( $meta as $k => $v ) { update_term_meta( $id, $k, $v ); }
	return $id;
}

function mmfx_meta( $id, $meta ) {
	foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); }
}

$cat = mmfx_cat( 'Ciparu sveces', 'ciparu-sveces', array(
	'_mm_tr_en_name'        => 'Number candles',
	'_mm_tr_en_description' => 'English category description.',
) );

// Simple product with full English translation.
$p = new WC_Product_Simple();
$p->set_name( 'Ciparu svece “5”' );
$p->set_slug( 'ciparu-svece-5' );
$p->set_regular_price( '1.40' );
$p->set_description( 'Latviešu apraksts ciparu svecei.' );
$p->set_short_description( 'Īss latviešu apraksts.' );
$p->set_category_ids( array( $cat ) );
$simple = $p->save();
mmfx_meta( $simple, array(
	'_mm_tr_en_title'    => 'Number candle “5”',
	'_mm_tr_en_content'  => 'English description of the number candle.',
	'_mm_tr_en_excerpt'  => 'Short English description.',
	'_mm_tr_en_seo_title' => 'Number candle 5 for cakes | MarMar Candles',
	'_mm_tr_en_seo_desc'  => 'Handmade number candle 5 for birthday cakes.',
) );

// Variable product with colour attribute (Bordo / Pienbalts) and English translation.
$tax = 'pa_krasa';
if ( ! taxonomy_exists( $tax ) ) {
	wc_create_attribute( array( 'name' => 'Krāsa', 'slug' => 'krasa' ) );
	register_taxonomy( $tax, 'product' );
}
$bordo = wp_insert_term( 'Bordo', $tax, array( 'slug' => 'bordo' ) );
$white = wp_insert_term( 'Pienbalts', $tax, array( 'slug' => 'pienbalts' ) );
update_term_meta( $bordo['term_id'], '_mm_tr_en_name', 'Burgundy' );
$attr = new WC_Product_Attribute();
$attr->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
$attr->set_name( $tax );
$attr->set_options( array( $bordo['term_id'], $white['term_id'] ) );
$attr->set_visible( true );
$attr->set_variation( true );
$v = new WC_Product_Variable();
$v->set_name( 'Ziemassvētku bumba' );
$v->set_slug( 'ziemassvetku-bumba' );
$v->set_attributes( array( $attr ) );
$v->set_category_ids( array( $cat ) );
$v->set_description( 'Latviešu apraksts bumbai.' );
$var_id = $v->save();
foreach ( array( 'bordo', 'pienbalts' ) as $slug ) {
	$var = new WC_Product_Variation();
	$var->set_parent_id( $var_id );
	$var->set_attributes( array( $tax => $slug ) );
	$var->set_regular_price( '9.00' );
	$var->save();
}
mmfx_meta( $var_id, array(
	'_mm_tr_en_title'   => 'Christmas ball candle',
	'_mm_tr_en_content' => 'English description of the ball.',
) );

// Product without translation.
$n = new WC_Product_Simple();
$n->set_name( 'Galda svece' );
$n->set_slug( 'galda-svece' );
$n->set_regular_price( '3.00' );
$n->set_description( 'Tikai latviski.' );
$n->set_category_ids( array( $cat ) );
$untranslated = $n->save();

// Product with title translation only (partial).
$q = new WC_Product_Simple();
$q->set_name( 'Sirds svece' );
$q->set_slug( 'sirds-svece' );
$q->set_regular_price( '2.00' );
$q->set_description( 'Sirds apraksts.' );
$q->set_short_description( 'Sirds īsais apraksts.' );
$q->set_category_ids( array( $cat ) );
$partial = $q->save();
mmfx_meta( $partial, array( '_mm_tr_en_title' => 'Heart candle' ) );

// Page with English translation.
$page = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Piegāde',
	'post_name'    => 'piegade',
	'post_content' => 'Piegāde visā Latvijā.',
) );
mmfx_meta( $page, array( '_mm_tr_en_title' => 'Delivery', '_mm_tr_en_content' => 'Delivery across Latvia.' ) );

// Header menu: home, category, page.
$menu = wp_create_nav_menu( 'header' );
$home_item = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Sākums', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
$cat_item  = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $cat, 'menu-item-object' => 'product_cat', 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish' ) );
$page_item = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $page, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
update_post_meta( $page_item, '_mm_tr_en_title', 'Delivery' );
$locations = get_theme_mod( 'nav_menu_locations', array() );
foreach ( array( 'menu_1', 'menu_2', 'menu_mobile' ) as $loc ) { $locations[ $loc ] = $menu; }
set_theme_mod( 'nav_menu_locations', $locations );

// Shipping zone Latvia, flat rate 2 € (checkout needs a shipping method).
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Latvija' );
$zone->add_location( 'LV', 'country' );
$zone->save();
$iid = $zone->add_shipping_method( 'flat_rate' );
update_option( 'woocommerce_flat_rate_' . $iid . '_settings', array( 'title' => 'Pakomāts', 'cost' => '2', 'tax_status' => 'none' ) );

update_option( 'mm_lang_public', true );
update_option( 'mm_lang_enabled', array( 'en' ) );

file_put_contents( getenv( 'MMWP_DIR' ) . '/fixtures.json', wp_json_encode( array(
	'simple' => $simple, 'variable' => $var_id, 'untranslated' => $untranslated, 'partial' => $partial,
	'page' => $page, 'cat' => $cat, 'bordo' => $bordo['term_id'], 'menu' => $menu, 'page_item' => $page_item,
) ) );
echo "fixtures ok\n";
