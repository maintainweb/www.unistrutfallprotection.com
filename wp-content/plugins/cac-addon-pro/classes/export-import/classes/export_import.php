<?php

/**
 * CAC_Export_Import Class
 *
 * @since 1.4.6.5
 *
 */
class CAC_Export_Import {

	private $cpac;

	/**
	 * Constructor
	 *
	 * @since 1.4.6.5
	 */
	function __construct( $cpac ) {

		$this->cpac = $cpac;

		// Add UI
		add_filter( 'cac/settings/groups', array( $this, 'settings_group' ) );
		add_action( 'cac/settings/groups/row=export', array( $this, 'display_export' ) );
		add_action( 'cac/settings/groups/row=import', array( $this, 'display_import' ) );

		// styling & scripts
		add_action( "admin_print_styles-settings_page_codepress-admin-columns", array( $this, 'scripts' ) );

		// Handle requests
		add_action( 'admin_init', array( $this, 'download_export' ) );
		add_action( 'admin_init', array( $this, 'handle_file_import' ) );
	}

	/**
	 * Add settings group to Admin Columns settings page
	 *
	 * @since 1.0
	 */
	public function settings_group( $groups ) {

		$groups['export'] =  array(
			'title'			=> __( 'Export Settings', CAC_EI_TEXTDOMAIN ),
			'description'	=> '
				<p>' . __( 'Pick the types for export from the left column. Click export to download your column settings.', CAC_EI_TEXTDOMAIN ) . '</p>
				<p><a href="javascript:;" class="cpac-pointer" rel="cpac-export-instructions-html" data-pos="right">' . __( 'Instructions', CAC_EI_TEXTDOMAIN ) . '</a></p>
				<div id="cpac-export-instructions-html" style="display:none;">
					<h3>' . __( 'Export Columns Types', CAC_EI_TEXTDOMAIN ) . '</h3>
					<p>' . __( 'Instructions', CAC_EI_TEXTDOMAIN ) . '</p>
					<ol>
						<li>' . __( 'Select one or more Column Types from the left section by clicking them.', CAC_EI_TEXTDOMAIN ) . '</li>
						<li>' . __( 'Click export.', CAC_EI_TEXTDOMAIN ) . '</li>
						<li>' . __( 'Save the export file when prompted.', CAC_EI_TEXTDOMAIN ) . '</li>
						<li>' . __( 'Upload and import your settings file through Import Settings.', CAC_EI_TEXTDOMAIN ) . '</li>
					</ol>
				</div>'
		);

		$groups['import'] =  array(
			'title'			=> __( 'Import Settings', CAC_EI_TEXTDOMAIN ),
			'description'	=> '
				<p>' . __( 'Copy and paste your import settings here.', CAC_EI_TEXTDOMAIN ) . '</p>
				<p><a href="javascript:;" class="cpac-pointer" rel="cpac-import-instructions-html" data-pos="right">' . __( 'Instructions', CAC_EI_TEXTDOMAIN ) . '</a></p>
				<div id="cpac-import-instructions-html" style="display:none;">
					<h3>' . __( 'Import Columns Types', CAC_EI_TEXTDOMAIN ) . '</h3>
					<p>' . __( 'Instructions', CAC_EI_TEXTDOMAIN ) . '</p>
					<ol>
						<li>' . __( 'Choose a Admin Columns Export file to upload.', CAC_EI_TEXTDOMAIN ) . '</li>
						<li>' . __( 'Click upload file and import.', CAC_EI_TEXTDOMAIN ) . '</li>
						<li>' . __( "That's it! You imported settings are now active.", CAC_EI_TEXTDOMAIN ) . '</li>
					</ol>
				</div>'
		);

		return $groups;
	}

	/**
	 * Display export
	 *
	 * @since 1.0
	 */
	function display_export() {
		?>
		<div class="cpac_export">

			<?php if ( $groups = $this->get_export_multiselect_options() ) : ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'download-export', '_cpac_nonce' ); ?>
				<select name="export_types[]" multiple="multiple" class="select" id="cpac_export_types">
					<?php
					$labels = array(
						'general'	=> __( 'General', CAC_EI_TEXTDOMAIN ),
						'posts'		=> __( 'Posts', CAC_EI_TEXTDOMAIN )
					);
					?>
					<?php foreach ( $groups as $group_key => $group ) : ?>
					<optgroup label="<?php echo $labels[$group_key];?>">
						<?php foreach ( $group as $storage_model ) : ?>
						<option value="<?php echo $storage_model->key; ?>"><?php echo $storage_model->label; ?></option>
						<?php endforeach; ?>
					</optgroup>
					<?php endforeach; ?>
				</select>
				<a id="export-select-all" class="export-select" href="javascript:;"><?php _e( 'select all', CAC_EI_TEXTDOMAIN ); ?></a>
				<input type="submit" id="cpac_export_submit" class="button-primary alignright" value="<?php _e( 'Export', CAC_EI_TEXTDOMAIN ); ?>">
			</form>

			<?php else : ?>
				<p><?php _e( 'No stored column settings are found.', CAC_EI_TEXTDOMAIN ); ?></p>
			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Display import
	 *
	 * @since 1.0
	 */
	function display_import() {
		?>
		<div id="cpac_import_input">
			<form method="post" action="" enctype="multipart/form-data">
				<input type="file" size="25" name="import" id="upload">
				<?php wp_nonce_field( 'file-import', '_cpac_nonce' ); ?>
				<input type="submit" value="<?php _e( 'Upload file and import', CAC_EI_TEXTDOMAIN ); ?>" class="button" id="import-submit" name="file-submit">
			</form>
		</div>
		<?php
	}

	/**
	 * Register scripts
	 *
	 * @since 1.0
	 */
	public function scripts() {

		wp_enqueue_style( 'cac-multi-select-css', CAC_EI_URL . 'assets/css/multi-select.css', array(), CAC_EI_VERSION, 'all' );
		wp_enqueue_style( 'cac-ei-css', CAC_EI_URL . 'assets/css/export-import.css', array(), CAC_EI_VERSION, 'all' );

		// js
		wp_enqueue_script( 'cac-ei-js', CAC_EI_URL . 'assets/js/export-import.js', array( 'jquery' ), CAC_EI_VERSION );
		wp_enqueue_script( 'cac-ei-multi-select-js', CAC_EI_URL . 'assets/js/jquery.multi-select.js', array( 'jquery' ), CAC_EI_VERSION );
	}

	/**
	 * Get export multiselect options
	 *
	 * Gets multi select options to use in a HTML select element
	 *
	 * @since 2.0.0
	 * @return array Multiselect options
	 */
	public function get_export_multiselect_options() {
		$options = array();

		foreach ( $this->cpac->storage_models as $storage_model ) {

			if ( ! $storage_model->get_stored_columns() )
				continue;

			// General group
			if ( in_array( $storage_model->key, array( 'wp-comments', 'wp-links', 'wp-users', 'wp-media' ) ) ) {
				$options['general'][] = $storage_model;
			}

			// Post(types) group
			else {
				$options['posts'][] = $storage_model;
			}
		}

		return $options;
	}

	/**
	 * Get export string
	 *
	 * @since 2.0.0
	 */
	function get_export_string( $types = array() ) {

		if ( empty( $types ) )
			return false;

		$columns = array();

		// get stored columns
		foreach ( $this->cpac->storage_models as $storage_model ) {

			if ( ! in_array( $storage_model->key, $types ) )
				continue;

			$columns[ $storage_model->key ] = $storage_model->get_stored_columns();
		}

		if ( empty( $columns ) )
			return false;

		return "<!-- START: Admin Columns export -->\n" . base64_encode( serialize( array_filter( $columns ) ) ) . "\n<!-- END: Admin Columns export -->";
	}

	/**
	 * Download Export
	 *
	 * @since 2.0.0
	 */
	function download_export() {
		if ( ! isset( $_REQUEST['_cpac_nonce'] ) || ! wp_verify_nonce( $_REQUEST['_cpac_nonce'], 'download-export' ) )
			return false;

		if ( empty( $_REQUEST['export_types'] ) ) {

			cpac_admin_message( __( 'Export field is empty. Please select your types from the left column.',  CAC_EI_TEXTDOMAIN ), 'error' );

			return false;
		}

		$single_type = '';
		if ( 1 == count( $_REQUEST['export_types'] ) ) {
			$single_type = '_' . $_REQUEST['export_types'][0];
		}

		$filename = 'admin-columns-export_' . date('Y-m-d', time() ) . $single_type;

		// generate text file
		header( "Content-disposition: attachment; filename={$filename}.txt" );
		header( 'Content-type: text/plain' );
		echo $this->get_export_string( $_REQUEST['export_types'] );
		exit;
	}

	/**
	 * Handle file import
	 *
	 * @uses wp_import_handle_upload()
	 * @since 2.0.0
	 */
	function handle_file_import() {
		if ( ! isset( $_REQUEST['_cpac_nonce'] ) || ! wp_verify_nonce( $_REQUEST['_cpac_nonce'], 'file-import' ) || empty( $_FILES['import'] ) )
			return false;

		// handles upload
		$file = wp_import_handle_upload();

		// any errors?
		$error = false;
		if ( isset( $file['error'] ) ) {
			$error = __( 'Sorry, there has been an error.', CAC_EI_TEXTDOMAIN ) . '<br />' . esc_html( $file['error'] );
		} else if ( ! file_exists( $file['file'] ) ) {
			$error = __( 'Sorry, there has been an error.', CAC_EI_TEXTDOMAIN ) . '<br />' . sprintf( __( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', CAC_EI_TEXTDOMAIN ), esc_html( $file['file'] ) );
		}

		if ( $error ) {
			cpac_admin_message( $error, 'error' );
			return false;
		}

		// read file contents and start the import
		$content = file_get_contents( $file['file'] );

		// cleanup
		wp_delete_attachment( $file['id'] );

		// decode file contents
		$columns = $this->get_decoded_settings( $content );

		if ( ! $columns ) {
			cpac_admin_message( __( 'Import failed. File does not contain Admin Column settings.',  CAC_EI_TEXTDOMAIN ), 'error' );
			return false;
		}

		// store settings
		foreach( $columns as $type => $settings ) {

			$storage_model = $this->cpac->get_storage_model( $type );

			if ( ! $storage_model ) {
				cpac_admin_message( sprintf( __( 'Screen %s does not exist.', CAC_EI_TEXTDOMAIN ), "<strong>{$type}</strong>" ), 'error' );
				continue;
			}

			$storage_model->store( $settings );
		}
	}

	/**
	 * Get decoded settings
	 *
	 * @since 2.0.0
	 *
	 * @param string $encoded_string
	 * @return array Columns
	 */
	function get_decoded_settings( $encoded_string = '' ) {
		if( ! $encoded_string || ! is_string( $encoded_string ) || strpos( $encoded_string, '<!-- START: Admin Columns export -->' ) === false )
			return false;

		// decode
		$encoded_string = str_replace( "<!-- START: Admin Columns export -->\n", "", $encoded_string );
		$encoded_string = str_replace( "\n<!-- END: Admin Columns export -->", "", $encoded_string);
		$decoded 	 	= maybe_unserialize( base64_decode( trim( $encoded_string ) ) );

		if ( empty( $decoded ) || ! is_array( $decoded ) )
			return false;

		return $decoded;
	}

	/**
	 * Update settings
	 *
	 * @since 2.0.0
	 *
	 * @param array $columns Columns
	 * @return bool
	 */
	function update_settings( $columns ) {
		$options = get_option( 'cpac_options' );

		// merge saved setting if they exist..
		if ( ! empty( $options['columns'] ) ) {
			$options['columns'] = array_merge( $options['columns'], $columns );
		}

		// .. if there are no setting yet use the import
		else {
			$options = array(
				'columns' => $columns
			);
		}

		return update_option( 'cpac_options', array_filter( $options ) );
	}
}
