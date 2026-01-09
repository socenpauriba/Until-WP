<?php
/**
 * Classe per gestionar l'historial de canvis
 *
 * @package Until_WP
 */

// Si aquest fitxer s'accedeix directament, avortar
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Until_WP_History {
	
	/**
	 * Instància de la classe de base de dades
	 */
	private $database;
	
	/**
	 * Constructor
	 *
	 * @param Until_WP_Database $database Instància de la base de dades
	 */
	public function __construct( $database ) {
		$this->database = $database;
		$this->init_hooks();
	}
	
	/**
	 * Inicialitzar els hooks
	 */
	private function init_hooks() {
		// Afegir una columna a la llista de posts amb l'estat de canvis programats
		add_filter( 'manage_posts_columns', array( $this, 'add_posts_column' ) );
		add_filter( 'manage_pages_columns', array( $this, 'add_posts_column' ) );
		
		add_action( 'manage_posts_custom_column', array( $this, 'render_posts_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'render_posts_column' ), 10, 2 );
		
		// Afegir widget al dashboard
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		
		// Programar neteja periòdica de l'historial
		add_action( 'until_wp_cleanup_history', array( $this, 'cleanup_old_history' ) );
		
		// Programar l'event si no existeix
		if ( ! wp_next_scheduled( 'until_wp_cleanup_history' ) ) {
			wp_schedule_event( time(), 'daily', 'until_wp_cleanup_history' );
		}
	}
	
	/**
	 * Afegir una columna a la llista de posts
	 *
	 * @param array $columns Columnes existents
	 * @return array Columnes modificades
	 */
	public function add_posts_column( $columns ) {
		// Afegir la columna abans de la columna de data
		$new_columns = array();
		
		foreach ( $columns as $key => $value ) {
			if ( $key === 'date' ) {
				$new_columns['until_wp_scheduled'] = __( 'Canvis Programats', 'until-wp' );
			}
			$new_columns[ $key ] = $value;
		}
		
		return $new_columns;
	}
	
	/**
	 * Renderitzar el contingut de la columna
	 *
	 * @param string $column_name Nom de la columna
	 * @param int $post_id ID del post
	 */
	public function render_posts_column( $column_name, $post_id ) {
		if ( $column_name !== 'until_wp_scheduled' ) {
			return;
		}
		
		// Obtenir els canvis programats per aquest post
		$changes = $this->database->get_scheduled_changes( array(
			'post_id' => $post_id,
			'status' => 'pending',
			'limit' => 5
		) );
		
		if ( empty( $changes ) ) {
			echo '<span style="color: #999;">—</span>';
			return;
		}
		
		echo '<div class="until-wp-column-changes">';
		foreach ( $changes as $change ) {
			$time_diff = human_time_diff( time(), strtotime( $change->scheduled_time ) );
			$label = $this->get_change_short_label( $change );
			
			echo '<div class="until-wp-change-item" style="margin-bottom: 4px;">';
			echo '<span class="dashicons dashicons-clock" style="font-size: 14px; width: 14px; height: 14px;"></span> ';
			echo '<strong>' . esc_html( $label ) . '</strong><br>';
			echo '<small style="color: #666;">' . sprintf( __( "D'aquí a %s", 'until-wp' ), esc_html( $time_diff ) ) . '</small>';
			echo '</div>';
		}
		
		$total_count = $this->database->count_scheduled_changes( array(
			'post_id' => $post_id,
			'status' => 'pending'
		) );
		
		if ( $total_count > count( $changes ) ) {
			echo '<small><em>' . sprintf( __( '+ %d més', 'until-wp' ), $total_count - count( $changes ) ) . '</em></small>';
		}
		
		echo '</div>';
	}
	
	/**
	 * Obtenir l'etiqueta curta d'un canvi
	 *
	 * @param object $change Objecte del canvi
	 * @return string Etiqueta curta
	 */
	private function get_change_short_label( $change ) {
		switch ( $change->change_type ) {
			case 'post_status':
				$statuses = array(
					'publish' => __( 'Publicar', 'until-wp' ),
					'draft' => __( 'Esborrany', 'until-wp' ),
					'pending' => __( 'Pendent', 'until-wp' ),
					'private' => __( 'Privat', 'until-wp' )
				);
				return isset( $statuses[ $change->new_value ] ) ? $statuses[ $change->new_value ] : $change->new_value;
				
			case 'sticky':
				return ( $change->new_value === 'yes' ) ? __( 'Fixar', 'until-wp' ) : __( 'Desfixar', 'until-wp' );
				
			case 'custom_function':
				return sprintf( __( 'Funció: %s', 'until-wp' ), $change->new_value );
				
			default:
				return __( 'Canvi', 'until-wp' );
		}
	}
	
	/**
	 * Afegir widget al dashboard
	 */
	public function add_dashboard_widget() {
		// Només per usuaris amb permisos
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		
		wp_add_dashboard_widget(
			'until_wp_dashboard_widget',
			__( 'Canvis Programats - Until WP', 'until-wp' ),
			array( $this, 'render_dashboard_widget' )
		);
	}
	
	/**
	 * Renderitzar el widget del dashboard
	 */
	public function render_dashboard_widget() {
		// Obtenir els propers canvis
		$upcoming_changes = $this->database->get_scheduled_changes( array(
			'status' => 'pending',
			'limit' => 5,
			'orderby' => 'scheduled_time',
			'order' => 'ASC'
		) );
		
		// Obtenir l'historial recent
		$recent_history = $this->database->get_history( array(
			'limit' => 5,
			'orderby' => 'executed_time',
			'order' => 'DESC'
		) );
		
		?>
		<div class="until-wp-dashboard-widget">
			
			<!-- Propers canvis -->
			<div class="until-wp-widget-section">
				<h4 style="margin-top: 0;">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php _e( 'Propers Canvis', 'until-wp' ); ?>
				</h4>
				
				<?php if ( ! empty( $upcoming_changes ) ) : ?>
					<ul style="margin: 0;">
						<?php foreach ( $upcoming_changes as $change ) : ?>
							<li style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0;">
								<?php
								$post = get_post( $change->post_id );
								if ( $post ) {
									echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">';
									echo '<strong>' . esc_html( $post->post_title ) . '</strong>';
									echo '</a><br>';
								}
								
								echo '<span style="color: #666;">';
								echo esc_html( $this->get_change_description( $change ) );
								echo '</span><br>';
								
								$time_diff = human_time_diff( time(), strtotime( $change->scheduled_time ) );
								echo '<small style="color: #999;">';
								echo '<span class="dashicons dashicons-clock" style="font-size: 13px; width: 13px; height: 13px;"></span> ';
								echo sprintf( __( "D'aquí a %s", 'until-wp' ), esc_html( $time_diff ) );
								echo '</small>';
								?>
							</li>
						<?php endforeach; ?>
					</ul>
					
					<?php
					$total_pending = $this->database->count_scheduled_changes( array( 'status' => 'pending' ) );
					if ( $total_pending > 5 ) :
					?>
						<p style="margin-top: 10px;">
							<a href="<?php echo esc_url( admin_url( 'tools.php?page=until-wp-scheduled-changes' ) ); ?>" class="button button-small">
								<?php printf( __( 'Veure tots (%d)', 'until-wp' ), $total_pending ); ?>
							</a>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p style="color: #999; font-style: italic;">
						<?php _e( 'No hi ha canvis programats.', 'until-wp' ); ?>
					</p>
				<?php endif; ?>
			</div>
			
			<!-- Historial recent -->
			<?php if ( ! empty( $recent_history ) ) : ?>
				<div class="until-wp-widget-section" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
					<h4>
						<span class="dashicons dashicons-backup"></span>
						<?php _e( 'Executats Recentment', 'until-wp' ); ?>
					</h4>
					
					<ul style="margin: 0;">
						<?php foreach ( $recent_history as $item ) : ?>
							<li style="margin-bottom: 8px;">
								<?php
								$post = get_post( $item->post_id );
								if ( $post ) {
									echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">';
									echo esc_html( $post->post_title );
									echo '</a> - ';
								}
								
								echo '<span style="color: #666;">';
								echo esc_html( $this->get_history_description( $item ) );
								echo '</span><br>';
								
								$time_diff = human_time_diff( strtotime( $item->executed_time ), time() );
								echo '<small style="color: #999;">';
								echo sprintf( __( 'Fa %s', 'until-wp' ), esc_html( $time_diff ) );
								echo '</small>';
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			
		</div>
		<?php
	}
	
	/**
	 * Obtenir la descripció d'un canvi programat
	 *
	 * @param object $change Objecte del canvi
	 * @return string Descripció
	 */
	private function get_change_description( $change ) {
		switch ( $change->change_type ) {
			case 'post_status':
				$statuses = get_post_statuses();
				$status_name = isset( $statuses[ $change->new_value ] ) ? $statuses[ $change->new_value ] : $change->new_value;
				return sprintf( __( 'Canviar a %s', 'until-wp' ), $status_name );
				
			case 'sticky':
				return ( $change->new_value === 'yes' ) ? __( 'Fixar entrada', 'until-wp' ) : __( 'Desfixar entrada', 'until-wp' );
				
			case 'custom_function':
				return sprintf( __( 'Executar %s()', 'until-wp' ), $change->new_value );
				
			default:
				return __( 'Canvi desconegut', 'until-wp' );
		}
	}
	
	/**
	 * Obtenir la descripció d'un element de l'historial
	 *
	 * @param object $item Objecte de l'historial
	 * @return string Descripció
	 */
	private function get_history_description( $item ) {
		switch ( $item->change_type ) {
			case 'post_status':
				$statuses = get_post_statuses();
				$old_status = isset( $statuses[ $item->old_value ] ) ? $statuses[ $item->old_value ] : $item->old_value;
				$new_status = isset( $statuses[ $item->new_value ] ) ? $statuses[ $item->new_value ] : $item->new_value;
				return sprintf( __( '%s → %s', 'until-wp' ), $old_status, $new_status );
				
			case 'sticky':
				return ( $item->new_value === 'yes' ) ? __( 'Fixat', 'until-wp' ) : __( 'Desfixat', 'until-wp' );
				
			case 'custom_function':
				return sprintf( __( '%s() executat', 'until-wp' ), $item->new_value );
				
			default:
				return __( 'Canvi executat', 'until-wp' );
		}
	}
	
	/**
	 * Obtenir estadístiques de l'historial
	 *
	 * @param int $days Nombre de dies a analitzar
	 * @return array Estadístiques
	 */
	public function get_statistics( $days = 30 ) {
		global $wpdb;
		
		$history_table = $wpdb->prefix . 'until_wp_history';
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		
		// Total de canvis executats
		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$history_table} WHERE executed_time >= %s",
			$date_from
		) );
		
		// Per tipus de canvi
		$by_type = $wpdb->get_results( $wpdb->prepare(
			"SELECT change_type, COUNT(*) as count 
			FROM {$history_table} 
			WHERE executed_time >= %s 
			GROUP BY change_type",
			$date_from
		), OBJECT_K );
		
		// Posts més modificats
		$top_posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, COUNT(*) as count 
			FROM {$history_table} 
			WHERE executed_time >= %s 
			GROUP BY post_id 
			ORDER BY count DESC 
			LIMIT 5",
			$date_from
		) );
		
		return array(
			'total' => $total,
			'by_type' => $by_type,
			'top_posts' => $top_posts,
			'days' => $days
		);
	}
	
	/**
	 * Netejar l'historial antic (més de 90 dies)
	 */
	public function cleanup_old_history() {
		global $wpdb;
		
		$history_table = $wpdb->prefix . 'until_wp_history';
		$ninety_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
		
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$history_table} WHERE executed_time < %s",
			$ninety_days_ago
		) );
	}
}

