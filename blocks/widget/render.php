<?php
/**
 * Front-end render for cabintale/widget.
 *
 * @package Cabintale\BookingCalendar
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content (unused).
 * @var WP_Block $block      Block instance.
 */

namespace Cabintale\BookingCalendar;

defined( 'ABSPATH' ) || exit;

// get_block_wrapper_attributes() carries the theme's alignment, spacing and
// custom class settings, so the block honours the editor's own controls without
// this plugin reimplementing any of them.
$cabintale_wrapper = get_block_wrapper_attributes( array( 'class' => 'cabintale-widget' ) );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes every attribute it emits and returns no user-authored HTML; the wrapper attributes come escaped from core.
echo Renderer::render( is_array( $attributes ) ? $attributes : array(), $cabintale_wrapper );
