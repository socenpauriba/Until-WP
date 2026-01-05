<?php
/**
 * Script de desinstal·lació per Until WP
 *
 * Aquest fitxer s'executa quan el plugin es desinstal·la des de WordPress.
 * Neteja totes les dades del plugin: taules de base de dades, opcions, etc.
 *
 * @package Until_WP
 */

// Si uninstall.php no és cridat per WordPress, sortir
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Verificar que el plugin s'està desinstal·lant de debò
if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

/**
 * Eliminar les taules de base de dades
 */
function until_wp_delete_database_tables() {
	global $wpdb;
	
	$scheduled_table = $wpdb->prefix . 'until_wp_scheduled';
	$history_table = $wpdb->prefix . 'until_wp_history';
	
	$wpdb->query( "DROP TABLE IF EXISTS {$scheduled_table}" );
	$wpdb->query( "DROP TABLE IF EXISTS {$history_table}" );
}

/**
 * Eliminar totes les opcions del plugin
 */
function until_wp_delete_options() {
	// Eliminar opcions individuals
	delete_option( 'until_wp_version' );
	delete_option( 'until_wp_notifications' );
	
	// Eliminar opcions amb prefix (per si n'hi ha més)
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'until_wp_%'" );
	
	// Netejar cache
	wp_cache_flush();
}

/**
 * Eliminar events programats de WP-Cron
 */
function until_wp_clear_cron_jobs() {
	// Eliminar l'event principal de comprovació
	$timestamp = wp_next_scheduled( 'until_wp_check_scheduled_changes' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'until_wp_check_scheduled_changes' );
	}
	wp_clear_scheduled_hook( 'until_wp_check_scheduled_changes' );
	
	// Eliminar l'event de neteja d'historial
	$timestamp = wp_next_scheduled( 'until_wp_cleanup_history' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'until_wp_cleanup_history' );
	}
	wp_clear_scheduled_hook( 'until_wp_cleanup_history' );
}

/**
 * Eliminar metadades de posts relacionades (si n'hi ha)
 */
function until_wp_delete_post_meta() {
	global $wpdb;
	
	// Eliminar qualsevol metadada relacionada amb el plugin
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_until_wp_%'" );
}

/**
 * Eliminar user meta relacionades (si n'hi ha)
 */
function until_wp_delete_user_meta() {
	global $wpdb;
	
	// Eliminar qualsevol user meta relacionada amb el plugin
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'until_wp_%'" );
}

/**
 * Eliminar transients relacionats
 */
function until_wp_delete_transients() {
	global $wpdb;
	
	// Eliminar transients normals
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_until_wp_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_until_wp_%'" );
	
	// Eliminar transients de site (per multisite)
	if ( is_multisite() ) {
		$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_until_wp_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_until_wp_%'" );
	}
}

/**
 * Neteja completa per multisite
 */
function until_wp_multisite_cleanup() {
	global $wpdb;
	
	if ( ! is_multisite() ) {
		return;
	}
	
	// Obtenir tots els blogs/sites
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
	
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		
		// Executar totes les funcions de neteja per cada site
		until_wp_delete_database_tables();
		until_wp_delete_options();
		until_wp_clear_cron_jobs();
		until_wp_delete_post_meta();
		until_wp_delete_user_meta();
		until_wp_delete_transients();
		
		restore_current_blog();
	}
}

/**
 * Executar la desinstal·lació
 */
if ( is_multisite() ) {
	// Neteja per multisite
	until_wp_multisite_cleanup();
} else {
	// Neteja per site individual
	until_wp_delete_database_tables();
	until_wp_delete_options();
	until_wp_clear_cron_jobs();
	until_wp_delete_post_meta();
	until_wp_delete_user_meta();
	until_wp_delete_transients();
}

// Neteja final del cache
wp_cache_flush();

// Log (opcional) - només en mode debug
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	error_log( 'Until WP: Plugin desinstal·lat correctament. Totes les dades han estat eliminades.' );
}

