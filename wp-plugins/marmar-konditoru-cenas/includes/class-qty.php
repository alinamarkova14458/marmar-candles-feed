<?php
/**
 * Konditoriem: sveču skaita pogas (10 / 50 / 100 / 200 sveces) ar kopsummu
 * un cena 0,45 €/svece, ja grozā ir 200+ šādu sveču (Alīna, 07.11.2026).
 * Mazumtirdzniecības pircēji to neredz.
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_KC_Qty {

	const TIER_FROM   = 200;   // sveces grozā
	const TIER_OFF    = 0.05;  // €/svece (0,50 → 0,45)
	const TIER_MAXPPC = 0.575; // tikai sveces ar konditora cenu ~0,50 €/svece (ar svečturiem 0,57)

	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_quantity', array( __CLASS__, 'buttons' ), 5 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'tier' ), 50 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'cart_label' ), 20, 3 );
	}

	/** Cik sveču ir vienā iepakojumā («Daudzums: 10 gab» vai «| 10 gab» nosaukumā). */
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
		if ( preg_match( '/\|\s*(\d+)\s*gab\b/i', $p->get_name(), $m ) && (int) $m[1] > 1 ) {
			return (int) $m[1];
		}
		return 0;
	}

	public static function buttons() {
		global $product;
		if ( ! MarMar_Konditoru_Cenas::is_konditor() || ! MarMar_Konditoru_Cenas::has_kc_prices( $product ) ) {
			return;
		}
		$n = self::pack_size( $product );
		if ( $n < 2 ) {
			return;
		}
		$simple_price = $product->is_type( 'variable' ) ? 0 : (float) MarMar_Konditoru_Cenas::kc_price( $product );
		?>
		<div class="mmq" data-pack="<?php echo (int) $n; ?>" data-price="<?php echo esc_attr( $simple_price ); ?>" data-tier="<?php echo esc_attr( self::TIER_FROM ); ?>" data-off="<?php echo esc_attr( self::TIER_OFF ); ?>" data-maxppc="<?php echo esc_attr( self::TIER_MAXPPC ); ?>">
			<div class="mmq-t">Cik sveču vajag?</div>
			<div class="mmq-btns">
				<?php foreach ( array( 1, 5, 10, 20 ) as $k ) : ?>
					<button type="button" class="mmq-b<?php echo 1 === $k ? ' is-on' : ''; ?>" data-packs="<?php echo (int) $k; ?>"><?php echo (int) ( $k * $n ); ?> <small>sveces</small></button>
				<?php endforeach; ?>
			</div>
			<label class="mmq-other">Cits daudzums: <input type="number" min="<?php echo (int) $n; ?>" step="<?php echo (int) $n; ?>" value="<?php echo (int) $n; ?>" inputmode="numeric"> sveces</label>
			<div class="mmq-sum" aria-live="polite"></div>
		</div>
		<style>
		.mmq{margin:4px 0 14px;width:100%}
		.mmq-t{font-weight:600;margin-bottom:8px}
		.mmq-btns{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
		.mmq-b{padding:10px 4px!important;border:1.5px solid #E5C9C6!important;background:#fff!important;color:#5a4a4a!important;border-radius:12px!important;font-weight:600!important;font-size:15px!important;line-height:1.1!important;min-height:0!important;letter-spacing:0!important;text-transform:none!important}
		.mmq-b small{display:block;font-weight:400;font-size:11px;opacity:.8}
		.mmq-b.is-on{border-color:#B85F5F!important;background:#FBF3F2!important;color:#B85F5F!important}
		.mmq-other{display:block;margin-top:10px;font-size:13px;color:#7a6a6a}
		.mmq-other input{width:84px!important;padding:4px 8px!important;margin:0 4px;display:inline-block!important;min-height:0!important;height:32px!important}
		.mmq-sum{margin-top:10px;font-size:15px}
		.mmq-sum b{font-size:18px}
		form.cart .mmq ~ .quantity,form.cart .woocommerce-variation-add-to-cart .quantity{display:none!important}
		</style>
		<script>
		(function(){var box=document.currentScript.previousElementSibling.previousElementSibling;var form=box.closest('form.cart');if(!form)return;
		var N=+box.dataset.pack,price=+box.dataset.price||0,TIER=+box.dataset.tier,OFF=+box.dataset.off,MAX=+box.dataset.maxppc;
		var qty=form.querySelector('input.qty'),other=box.querySelector('.mmq-other input'),sum=box.querySelector('.mmq-sum');
		function fmt(v){return v.toFixed(2).replace('.',',')+' €';}
		function setPacks(p){p=Math.max(1,Math.round(p));if(qty){qty.value=p;qty.dispatchEvent(new Event('change',{bubbles:true}));}
		 box.querySelectorAll('.mmq-b').forEach(function(b){b.classList.toggle('is-on',+b.dataset.packs===p);});other.value=p*N;draw();}
		function draw(){var p=qty?Math.max(1,+qty.value||1):1,c=p*N;if(!price){sum.innerHTML='Izvēlies dizainu, lai redzētu summu.';return;}
		 var ppc=price/N,unit=price,tier=false;if(c>=TIER&&ppc<=MAX){unit=price-OFF*N;tier=true;}
		 sum.innerHTML=c+' sveces × '+fmt(unit/N).replace(' €','')+' €/svece = <b>'+fmt(unit*p)+'</b>'+(tier?' <small>(cena no '+TIER+' svecēm)</small>':(ppc<=MAX?' <small>No '+TIER+' svecēm — '+fmt(ppc-OFF)+'/svece</small>':''));}
		box.querySelectorAll('.mmq-b').forEach(function(b){b.addEventListener('click',function(){setPacks(+b.dataset.packs);});});
		other.addEventListener('change',function(){setPacks(Math.ceil((+other.value||N)/N));});
		if(window.jQuery){jQuery(form).on('found_variation',function(e,v){price=+v.display_price||0;draw();}).on('reset_data',function(){if(form.classList.contains('variations_form'))price=0;draw();});}
		setPacks(1);})();
		</script>
		<?php
	}

	/** 200+ sveces grozā (konditoram) → 0,45 €/svece tām precēm, kuru konditora cena ~0,50 €/svece. */
	public static function tier( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! MarMar_Konditoru_Cenas::is_konditor() ) {
			return;
		}
		$elig  = array();
		$total = 0;
		foreach ( $cart->get_cart() as $key => $ci ) {
			$p = $ci['data'];
			$p->delete_meta_data( '_mm_tier_price' ); // tikai atmiņā, netiek saglabāts
			$n  = self::pack_size( $p );
			$kc = MarMar_Konditoru_Cenas::kc_price( $p );
			if ( $n > 1 && '' !== $kc && ( $kc / $n ) <= self::TIER_MAXPPC ) {
				$elig[ $key ] = array( $n, (float) $kc );
				$total       += $n * (int) $ci['quantity'];
			}
		}
		if ( $total < self::TIER_FROM ) {
			return;
		}
		foreach ( $elig as $key => $x ) {
			$cart->cart_contents[ $key ]['data']->update_meta_data( '_mm_tier_price', round( $x[1] - self::TIER_OFF * $x[0], 2 ) );
		}
	}

	/** Grozā konditoram: «× 5» → redzams arī sveču skaits. */
	public static function cart_label( $name, $ci, $key ) {
		if ( ! MarMar_Konditoru_Cenas::is_konditor() ) {
			return $name;
		}
		$n = self::pack_size( $ci['data'] );
		if ( $n > 1 && $ci['quantity'] > 1 ) {
			$name .= ' <small class="mmq-cnt">(' . (int) ( $n * $ci['quantity'] ) . ' sveces)</small>';
		}
		return $name;
	}
}
