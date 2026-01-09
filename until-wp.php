<?php
/**
 * Plugin Name: Until WP
 * Plugin URI: https://github.com/socenpauriba/until-wp
 * Description: Programa canvis automàtics en posts de WordPress (estat, entrades fixades, funcions personalitzades, etc.)
 * Version: 1.1.1
 * Author: Nuvol.cat
 * Author URI: https://nuvol.cat
 * Text Domain: until-wp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Si aquest fitxer s'accedeix directament, avortar
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Definir constants del plugin
define( 'UNTIL_WP_VERSION', '1.1.1' );
define( 'UNTIL_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UNTIL_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UNTIL_WP_PLUGIN_FILE', __FILE__ );

/**
 * Classe principal del plugin Until WP
 */
class Until_WP {
	
	/**
	 * Instància singleton
	 */
	private static $instance = null;
	
	/**
	 * Instàncies de les classes del plugin
	 */
	public $database;
	public $scheduler;
	public $metabox;
	public $admin;
	public $notifications;
	public $history;
	
	/**
	 * Constructor privat per implementar singleton
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}
	
	/**
	 * Obtenir la instància singleton
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Carregar les dependències del plugin
	 */
	private function load_dependencies() {
		require_once UNTIL_WP_PLUGIN_DIR . 'includes/class-until-wp-database.php';
		require_once UNTIL_WP_PLUGIN_DIR . 'includes/class-until-wp-scheduler.php';
		require_once UNTIL_WP_PLUGIN_DIR . 'includes/class-until-wp-metabox.php';
		require_once UNTIL_WP_PLUGIN_DIR . 'includes/class-until-wp-admin.php';
		require_once UNTIL_WP_PLUGIN_DIR . 'includes/class-until-wp-notifications.php';
		require_once UNTIL_WP_PLUGIN_DIR . 'includes/class-until-wp-history.php';
	}
	
	/**
	 * Inicialitzar els hooks del plugin
	 */
	private function init_hooks() {
		// Hooks d'activació i desactivació
		register_activation_hook( UNTIL_WP_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( UNTIL_WP_PLUGIN_FILE, array( $this, 'deactivate' ) );
		
		// Hook per inicialitzar el plugin després que WordPress estigui carregat
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		
		// Hook per carregar els fitxers de traducció
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}
	
	/**
	 * Inicialitzar les classes del plugin
	 */
	public function init() {
		$this->database = new Until_WP_Database();
		
		// Verificar si les taules existeixen, si no, crear-les
		$this->check_and_create_tables();
		
		$this->scheduler = new Until_WP_Scheduler( $this->database );
		$this->metabox = new Until_WP_Metabox( $this->database );
		$this->admin = new Until_WP_Admin( $this->database );
		$this->notifications = new Until_WP_Notifications( $this->database );
		$this->history = new Until_WP_History( $this->database );
	}
	
	/**
	 * Verificar i crear les taules si no existeixen
	 */
	private function check_and_create_tables() {
		global $wpdb;
		
		$scheduled_table = $wpdb->prefix . 'until_wp_scheduled';
		
		// Verificar si la taula existeix
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$scheduled_table}'" );
		
		if ( ! $table_exists ) {
			// Si no existeix, crear les taules
			$this->database->create_tables();
			
			// Registrar el cron event si no està programat
			if ( ! wp_next_scheduled( 'until_wp_check_scheduled_changes' ) ) {
				wp_schedule_event( time(), 'every_minute', 'until_wp_check_scheduled_changes' );
			}
		}
	}
	
	/**
	 * Activar el plugin
	 */
	public function activate() {
		// Crear les taules de base de dades
		require_once UNTIL_WP_PLUGIN_DIR . 'includes/class-until-wp-database.php';
		$database = new Until_WP_Database();
		$database->create_tables();
		
		// Programar el cron event
		if ( ! wp_next_scheduled( 'until_wp_check_scheduled_changes' ) ) {
			wp_schedule_event( time(), 'every_minute', 'until_wp_check_scheduled_changes' );
		}
		
		// Guardar la versió del plugin
		update_option( 'until_wp_version', UNTIL_WP_VERSION );
	}
	
	/**
	 * Desactivar el plugin
	 */
	public function deactivate() {
		// Eliminar el cron event
		$timestamp = wp_next_scheduled( 'until_wp_check_scheduled_changes' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'until_wp_check_scheduled_changes' );
		}
		wp_clear_scheduled_hook( 'until_wp_check_scheduled_changes' );
	}
	
	/**
	 * Carregar els fitxers de traducció
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'until-wp',
			false,
			dirname( plugin_basename( UNTIL_WP_PLUGIN_FILE ) ) . '/languages'
		);
	}
}

/**
 * Afegir l'interval personalitzat per al cron (cada minut)
 */
add_filter( 'cron_schedules', 'until_wp_add_cron_interval' );
function until_wp_add_cron_interval( $schedules ) {
	$schedules['every_minute'] = array(
		'interval' => 60,
		'display'  => __( 'Cada minut', 'until-wp' )
	);
	return $schedules;
}


require 'update/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/socenpauriba/Until-WP',
	__FILE__,
	'until-wp'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

$myUpdateChecker->addResultFilter( function( $info, $response = null ) {
    $info->icons = array(
        '1x' => plugin_dir_url(__FILE__) . 'img/icon-128x128.png',
        '2x' => plugin_dir_url(__FILE__) . 'img/icon-256x256.png',
    );
	$description_path = plugin_dir_path(__FILE__) . 'description.html';
    if (file_exists($description_path)) {
        $info->sections = array(
            'description' => file_get_contents($description_path),
        );
    } else {
        $info->sections = array(
            'description' => 'Descripció no disponible.',
        );
    }
    return $info;
});

/**
 * Inicialitzar el plugin
 */
function until_wp() {
	return Until_WP::get_instance();
}

// Iniciar el plugin
until_wp();
