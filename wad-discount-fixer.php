<?php

/**
 * Plugin Name: WAD Discount Fixer
 * Description: This plugin fixes a bug in the WooCommerce Advanced Discounts plugin where the discount flip-flops on and off when checking against the cart total, if the cart total switches between being greater than and less than the cutoff value.
 * Version: 1.0.0
 * Author: The team at PIE
 * Author URI: https://pie.co.de
 */

namespace PIE\WAD_Discount_Fixer;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_filter( 'wad_is_rule_valid', __NAMESPACE__ . '\modify_is_valid', 10, 2 );

/**
 * Checks if we are validating one of the bugged conditions and if so, performs our own checks to determine if a rule
 * should be applied or not. This is necessary because it would appear that the WooCommerce Advanced Discounts plugin 
 * does not have robust enough checks.
 * 
 * For the order subtotal conditions, we need to check the cart total by iterating over the cart contents directly since 
 * the plugin already filters the cart_subtotal amount, which it then checks again after filtering to see if the discount 
 * should be applied. This results in a discount being removed if the discount would take the cart subtotal below the
 * threshold amount, then being re-applied because the subtotal is now above the threshold amount, then being removed again,
 * and so on...
 * 
 * @todo investigate if the get_subtotal_tax needs to be accounted for differently in different cases
 *
 * @param boolean $is_valid
 * @param array $rule ['condition', 'operator', 'value']
 * @return boolean
 */
function modify_is_valid( bool $is_valid, array $rule ) : bool
{

    // Early return if rule condition and operator do not match. We are being pretty specific here, but
    // the rule could be tweaked if other discount rules are being applied incorrectly
    if ( ! in_array( $rule['condition'], [ 'order-subtotal-inc-taxes', 'order-subtotal'] ) || $rule['operator'] !== '>' ) {
        return $is_valid;
    }

    $cart_total = 0;

    // Initialize cart total with tax amount, if required
    if ( 'order-subtotal-inc-taxes' === $rule['condition'] ) {
        $cart_total += (float) WC()->cart->get_subtotal_tax();
    }

    // Manually calculate the cart total by looping through the line items since cart->get_subtotal() can return the discounted
    // subtotal, which is why the discount application flip-flops
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        // Avoid get_price() since that sends us into an infinite loop between the discount plugin and WooCommerce. Clearly, we are 
        // already hooked in to the get_price function somewhere
        $product_price = (float) $cart_item['data']->get_data()['price']; 
        $cart_total += $cart_item['quantity'] * $product_price;
    }

    // we are ok to check for the '>' operator specifically, but if introducing other conditions, we may need to change this comparison
    // to be more flexible
    return $cart_total > (float) $rule['value'];

}