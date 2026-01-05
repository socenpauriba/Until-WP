<?php
/**
 * Classe per gestionar la base de dades del plugin Until WP
 *
 * @package Until_WP
 */

// Si aquest fitxer s'accedeix directament, avortar
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Until_WP_Database {
	
	/**
	 * Nom de la taula de canvis programats
	 */
	private $scheduled_table;
	
	/**
	 * Nom de la taula d'historial
	 */
	private $history_table;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->scheduled_table = $wpdb->prefix . 'until_wp_scheduled';
		$this->history_table = $wpdb->prefix . 'until_wp_history';
	}
	
	/**
	 * Crear les taules de base de dades
	 */
	public function create_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Taula de canvis programats
		$sql_scheduled = "CREATE TABLE IF NOT EXISTS {$this->scheduled_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id bigint(20) UNSIGNED NOT NULL,
			change_type varchar(50) NOT NULL,
			new_value varchar(255) NOT NULL,
			scheduled_time datetime NOT NULL,
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY scheduled_time (scheduled_time),
			KEY status (status)
		) $charset_collate;";
		
		// Taula d'historial
		$sql_history = "CREATE TABLE IF NOT EXISTS {$this->history_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id bigint(20) UNSIGNED NOT NULL,
			change_type varchar(50) NOT NULL,
			old_value varchar(255) NOT NULL,
			new_value varchar(255) NOT NULL,
			scheduled_time datetime NOT NULL,
			executed_time datetime NOT NULL,
			executed_by bigint(20) UNSIGNED NOT NULL,
			created_by bigint(20) UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY executed_time (executed_time)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_scheduled );
		dbDelta( $sql_history );
	}
	
	/**
	 * Afegir un canvi programat
	 *
	 * @param int $post_id ID del post
	 * @param string $change_type Tipus de canvi (post_status, sticky)
	 * @param string $new_value Nou valor
	 * @param string $scheduled_time Data i hora programada (format MySQL)
	 * @param int $created_by ID de l'usuari que crea el canvi
	 * @return int|false ID del registre inserit o false si error
	 */
	public function add_scheduled_change( $post_id, $change_type, $new_value, $scheduled_time, $created_by ) {
		global $wpdb;
		
		$result = $wpdb->insert(
			$this->scheduled_table,
			array(
				'post_id' => $post_id,
				'change_type' => $change_type,
				'new_value' => $new_value,
				'scheduled_time' => $scheduled_time,
				'created_by' => $created_by,
				'created_at' => current_time( 'mysql' ),
				'status' => 'pending'
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		
		if ( $result ) {
			return $wpdb->insert_id;
		}
		
		return false;
	}
	
	/**
	 * Obtenir canvis programats pendents
	 *
	 * @param int|null $limit Límit de resultats
	 * @return array Array de canvis programats
	 */
	public function get_pending_changes( $limit = null ) {
		global $wpdb;
		
		$current_time = current_time( 'mysql' );
		
		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->scheduled_table} 
			WHERE status = 'pending' 
			AND scheduled_time <= %s 
			ORDER BY scheduled_time ASC",
			$current_time
		);
		
		if ( $limit ) {
			$sql .= $wpdb->prepare( " LIMIT %d", $limit );
		}
		
		return $wpdb->get_results( $sql );
	}
	
	/**
	 * Obtenir tots els canvis programats amb filtre opcional
	 *
	 * @param array $args Arguments de filtre
	 * @return array Array de canvis programats
	 */
	public function get_scheduled_changes( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'post_id' => null,
			'status' => 'pending',
			'change_type' => null,
			'limit' => 100,
			'offset' => 0,
			'orderby' => 'scheduled_time',
			'order' => 'ASC'
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( $args['post_id'] ) {
			$where[] = 'post_id = %d';
			$where_values[] = $args['post_id'];
		}
		
		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}
		
		if ( $args['change_type'] ) {
			$where[] = 'change_type = %s';
			$where_values[] = $args['change_type'];
		}
		
		$where_clause = implode( ' AND ', $where );
		
		$sql = "SELECT * FROM {$this->scheduled_table} WHERE {$where_clause}";
		
		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}
		
		$sql .= $wpdb->prepare(
			" ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d",
			$args['limit'],
			$args['offset']
		);
		
		return $wpdb->get_results( $sql );
	}
	
	/**
	 * Comptar canvis programats amb filtre opcional
	 *
	 * @param array $args Arguments de filtre
	 * @return int Nombre de canvis
	 */
	public function count_scheduled_changes( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'post_id' => null,
			'status' => 'pending',
			'change_type' => null
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( $args['post_id'] ) {
			$where[] = 'post_id = %d';
			$where_values[] = $args['post_id'];
		}
		
		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}
		
		if ( $args['change_type'] ) {
			$where[] = 'change_type = %s';
			$where_values[] = $args['change_type'];
		}
		
		$where_clause = implode( ' AND ', $where );
		
		$sql = "SELECT COUNT(*) FROM {$this->scheduled_table} WHERE {$where_clause}";
		
		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}
		
		return (int) $wpdb->get_var( $sql );
	}
	
	/**
	 * Obtenir un canvi programat per ID
	 *
	 * @param int $id ID del canvi programat
	 * @return object|null Objecte del canvi o null
	 */
	public function get_scheduled_change( $id ) {
		global $wpdb;
		
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->scheduled_table} WHERE id = %d",
				$id
			)
		);
	}
	
	/**
	 * Cancel·lar un canvi programat
	 *
	 * @param int $id ID del canvi programat
	 * @return bool True si s'ha cancel·lat, false si no
	 */
	public function cancel_scheduled_change( $id ) {
		global $wpdb;
		
		$result = $wpdb->update(
			$this->scheduled_table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		
		return $result !== false;
	}
	
	/**
	 * Eliminar un canvi programat
	 *
	 * @param int $id ID del canvi programat
	 * @return bool True si s'ha eliminat, false si no
	 */
	public function delete_scheduled_change( $id ) {
		global $wpdb;
		
		$result = $wpdb->delete(
			$this->scheduled_table,
			array( 'id' => $id ),
			array( '%d' )
		);
		
		return $result !== false;
	}
	
	/**
	 * Marcar un canvi com a executat i moure'l a l'historial
	 *
	 * @param int $id ID del canvi programat
	 * @param string $old_value Valor antic
	 * @param int $executed_by ID de l'usuari que executa (0 per cron)
	 * @return bool True si s'ha marcat, false si no
	 */
	public function mark_as_executed( $id, $old_value, $executed_by = 0 ) {
		global $wpdb;
		
		// Obtenir el canvi programat
		$change = $this->get_scheduled_change( $id );
		
		if ( ! $change ) {
			return false;
		}
		
		// Afegir a l'historial
		$result = $wpdb->insert(
			$this->history_table,
			array(
				'post_id' => $change->post_id,
				'change_type' => $change->change_type,
				'old_value' => $old_value,
				'new_value' => $change->new_value,
				'scheduled_time' => $change->scheduled_time,
				'executed_time' => current_time( 'mysql' ),
				'executed_by' => $executed_by,
				'created_by' => $change->created_by
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		
		if ( ! $result ) {
			return false;
		}
		
		// Eliminar de canvis programats
		return $this->delete_scheduled_change( $id );
	}
	
	/**
	 * Obtenir l'historial de canvis
	 *
	 * @param array $args Arguments de filtre
	 * @return array Array de canvis executats
	 */
	public function get_history( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'post_id' => null,
			'change_type' => null,
			'limit' => 100,
			'offset' => 0,
			'orderby' => 'executed_time',
			'order' => 'DESC'
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( $args['post_id'] ) {
			$where[] = 'post_id = %d';
			$where_values[] = $args['post_id'];
		}
		
		if ( $args['change_type'] ) {
			$where[] = 'change_type = %s';
			$where_values[] = $args['change_type'];
		}
		
		$where_clause = implode( ' AND ', $where );
		
		$sql = "SELECT * FROM {$this->history_table} WHERE {$where_clause}";
		
		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}
		
		$sql .= $wpdb->prepare(
			" ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d",
			$args['limit'],
			$args['offset']
		);
		
		return $wpdb->get_results( $sql );
	}
	
	/**
	 * Comptar l'historial de canvis
	 *
	 * @param array $args Arguments de filtre
	 * @return int Nombre de canvis
	 */
	public function count_history( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'post_id' => null,
			'change_type' => null
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( $args['post_id'] ) {
			$where[] = 'post_id = %d';
			$where_values[] = $args['post_id'];
		}
		
		if ( $args['change_type'] ) {
			$where[] = 'change_type = %s';
			$where_values[] = $args['change_type'];
		}
		
		$where_clause = implode( ' AND ', $where );
		
		$sql = "SELECT COUNT(*) FROM {$this->history_table} WHERE {$where_clause}";
		
		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}
		
		return (int) $wpdb->get_var( $sql );
	}
	
	/**
	 * Netejar les taules (per desinstal·lació)
	 */
	public function drop_tables() {
		global $wpdb;
		
		$wpdb->query( "DROP TABLE IF EXISTS {$this->scheduled_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$this->history_table}" );
	}
}

