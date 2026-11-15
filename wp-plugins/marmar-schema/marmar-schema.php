<?php
/**
 * Plugin Name:       MarMar Schema
 * Description:       Strukturētie dati Google un AI asistentiem (ChatGPT, Perplexity u.c.): veikala kontakti, adrese, sociālie tīkli; precēm zīmols, SKU un piegāde; sākumlapai bez «Article/Person». Darbojas virs Rank Math.
 * Version:           1.0.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Schema {

	const ORG_ID = 'https://marmarcandles.com/#organization';

	public static function init() {
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'filter' ), 99, 2 );
	}

	private static function org_extra() {
		return array(
			'description'  => 'Roku darba sveces tortēm, ciparu sveces, kūku toperi, Ziemassvētku sveces un personalizētas kāzu sveces no dabīgā vaska. Izgatavotas Rīgā, piegāde pakomātos visā Latvijā.',
			'email'        => 'info@marmarcandles.lv',
			'telephone'    => '+37129710414',
			'address'      => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => 'Jaunsaules iela 7a',
				'addressLocality' => 'Rīga',
				'addressCountry'  => 'LV',
			),
			'areaServed'   => array( 'LV', 'LT', 'EE' ),
			'sameAs'       => array(
				'https://www.instagram.com/marmar.candles/',
				'https://www.facebook.com/marmar.candles',
				'https://g.page/r/CXL0frKfTFEFEBM',
			),
			'contactPoint' => array(
				'@type'             => 'ContactPoint',
				'contactType'       => 'customer service',
				'email'             => 'info@marmarcandles.lv',
				'telephone'         => '+37129710414',
				'availableLanguage' => array( 'lv', 'ru', 'en' ),
			),
		);
	}

	private static function shipping() {
		return array(
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => array( '@type' => 'MonetaryAmount', 'value' => '2.50', 'currency' => 'EUR' ),
			'shippingDestination' => array( '@type' => 'DefinedRegion', 'addressCountry' => 'LV' ),
			'deliveryTime'        => array(
				'@type'        => 'ShippingDeliveryTime',
				'handlingTime' => array( '@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 7, 'unitCode' => 'DAY' ),
				'transitTime'  => array( '@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 5, 'unitCode' => 'DAY' ),
			),
		);
	}

	public static function filter( $data, $jsonld = null ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$front = is_front_page();
		foreach ( $data as $key => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$types = (array) ( isset( $node['@type'] ) ? $node['@type'] : array() );

			// Veikals: kontakti, adrese, sociālie tīkli.
			if ( in_array( 'Organization', $types, true ) || in_array( 'OnlineStore', $types, true ) ) {
				$data[ $key ] = array_merge( $node, self::org_extra() );
				continue;
			}

			// Sākumlapa nav raksts un nav personas lapa.
			if ( $front && ( in_array( 'Article', $types, true ) || in_array( 'BlogPosting', $types, true ) || in_array( 'Person', $types, true ) ) ) {
				unset( $data[ $key ] );
				continue;
			}

			// Preces: zīmols, SKU, pārdevējs = veikals, piegāde.
			if ( in_array( 'Product', $types, true ) ) {
				$node['brand'] = array( '@type' => 'Brand', 'name' => 'MarMar CANDLES' );
				if ( empty( $node['sku'] ) && function_exists( 'wc_get_product' ) ) {
					$p = wc_get_product( get_queried_object_id() );
					if ( $p ) {
						$node['sku'] = $p->get_sku() ? $p->get_sku() : (string) $p->get_id();
					}
				}
				if ( isset( $node['offers'] ) && is_array( $node['offers'] ) ) {
					$offers = isset( $node['offers']['@type'] ) ? array( $node['offers'] ) : $node['offers'];
					foreach ( $offers as $i => $o ) {
						if ( ! is_array( $o ) ) {
							continue;
						}
						$o['seller'] = array( '@id' => self::ORG_ID );
						if ( ! empty( $o['url'] ) && false === strpos( $o['url'], '/lv/' ) ) {
							$o['url'] = str_replace( 'https://marmarcandles.com/', 'https://marmarcandles.com/lv/', $o['url'] );
						}
						$o['shippingDetails'] = self::shipping();
						$offers[ $i ]         = $o;
					}
					$node['offers'] = isset( $node['offers']['@type'] ) ? $offers[0] : $offers;
				}
				$data[ $key ] = $node;
			}
		}
		return $data;
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Schema', 'init' ) );
