<?php
/**
 * File upload field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

use Glixform\Support\Uploads;

defined( 'ABSPATH' ) || exit;

/**
 * Accepts one or more files. Checks: upload errors, count, size, extension
 * against the field's list, real file type (wp_check_filetype_and_ext) and
 * WordPress's allowed MIME types. Files are moved into protected storage only
 * after the whole form is valid.
 */
class FileUpload extends AbstractField {

	const DEFAULT_EXTENSIONS = 'jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, ppt, pptx, odt, txt, csv, zip';

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'file';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'File Upload', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-upload';
	}

	/**
	 * Palette group.
	 *
	 * @return string
	 */
	public function category() {
		return 'fancy';
	}

	/**
	 * Value is a list of files.
	 *
	 * @return bool
	 */
	public function is_multiple() {
		return true;
	}

	/**
	 * Supported options.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'allowed_extensions', 'max_size', 'max_files', 'css_class' );
	}

	/**
	 * Upload limits.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'allowed_extensions' => array(
				'type'    => 'text',
				'label'   => __( 'Allowed file types', 'glixform' ),
				'help'    => __( 'Comma-separated extensions. Types WordPress does not allow (such as .php or .exe) are always blocked.', 'glixform' ),
				'default' => self::DEFAULT_EXTENSIONS,
			),
			'max_size'           => array(
				'type'    => 'number',
				'label'   => __( 'Maximum file size (MB)', 'glixform' ),
				'help'    => __( 'Cannot exceed the server upload limit.', 'glixform' ),
				'default' => 5,
				'min'     => 1,
				'max'     => 1024,
				'step'    => 1,
			),
			'max_files'          => array(
				'type'    => 'number',
				'label'   => __( 'Maximum number of files', 'glixform' ),
				'default' => 1,
				'min'     => 1,
				'max'     => 20,
				'step'    => 1,
			),
		);
	}

	/**
	 * Normalized list of allowed extensions.
	 *
	 * @param array $field Field config.
	 * @return string[]
	 */
	public function allowed_extensions( array $field ) {
		$list = strtolower( (string) ( $field['allowed_extensions'] ?? self::DEFAULT_EXTENSIONS ) );
		$exts = array_filter( array_map( 'trim', explode( ',', str_replace( '.', '', $list ) ) ) );
		return array_values(
			array_unique(
				array_filter(
					$exts,
					static function ( $ext ) {
						return (bool) preg_match( '/^[a-z0-9]{1,10}$/', $ext );
					}
				)
			)
		);
	}

	/**
	 * Effective size limit in bytes.
	 *
	 * @param array $field Field config.
	 * @return int
	 */
	public function max_bytes( array $field ) {
		$mb = max( 1, (int) ( $field['max_size'] ?? 5 ) );
		return (int) min( $mb * MB_IN_BYTES, wp_max_upload_size() );
	}

	/**
	 * Request key for this field's files.
	 *
	 * @param int $field_id Field ID.
	 * @return string
	 */
	public static function input_name( $field_id ) {
		return 'glixform_file_' . absint( $field_id );
	}

	/**
	 * Render the file input.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Ignored (browsers cannot pre-fill file inputs).
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		$max_files = max( 1, (int) ( $field['max_files'] ?? 1 ) );
		$accept    = implode(
			',',
			array_map(
				static function ( $ext ) {
					return '.' . $ext;
				},
				$this->allowed_extensions( $field )
			)
		);

		$attrs['name'] = self::input_name( $field['id'] ) . '[]';
		$field_attrs   = $field;
		unset( $field_attrs['placeholder'] );

		/* translators: 1: maximum file size, e.g. "5 MB". 2: number of files. */
		$hint = sprintf( _n( 'Up to %2$d file, %1$s max.', 'Up to %2$d files, %1$s each max.', $max_files, 'glixform' ), size_format( $this->max_bytes( $field ) ), $max_files );

		return sprintf(
			'<div class="glixform-file" data-max-files="%1$d" data-max-bytes="%2$d" data-extensions="%3$s"><input type="file" class="glixform-file-input"%4$s accept="%5$s"%6$s><div class="glixform-file-hint">%7$s</div></div>',
			$max_files,
			$this->max_bytes( $field ),
			esc_attr( implode( ',', $this->allowed_extensions( $field ) ) ),
			$this->common_attributes( $field_attrs, $attrs ),
			esc_attr( $accept ),
			$max_files > 1 ? ' multiple' : '',
			esc_html( $hint )
		);
	}

	/**
	 * Keep well-formed upload records; drop empty slots.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Normalized $_FILES list for this field.
	 * @return array
	 */
	public function sanitize_value( array $field, $raw ) {
		$files = array();
		foreach ( (array) $raw as $file ) {
			if ( ! is_array( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
				continue;
			}
			$files[] = array(
				'name'     => sanitize_file_name( (string) ( $file['name'] ?? '' ) ),
				'tmp_name' => (string) ( $file['tmp_name'] ?? '' ),
				'size'     => (int) ( $file['size'] ?? 0 ),
				'error'    => (int) $file['error'],
			);
		}
		return $files;
	}

	/**
	 * Check every file.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Upload records.
	 * @return string
	 */
	public function validate( array $field, $value ) {
		$files = is_array( $value ) ? $value : array();

		if ( ! $files ) {
			return empty( $field['required'] ) ? '' : __( 'Please choose a file.', 'glixform' );
		}

		$max_files = max( 1, (int) ( $field['max_files'] ?? 1 ) );
		if ( count( $files ) > $max_files ) {
			/* translators: %d: maximum number of files. */
			return sprintf( _n( 'You can upload %d file.', 'You can upload up to %d files.', $max_files, 'glixform' ), $max_files );
		}

		$allowed = $this->allowed_extensions( $field );
		$max     = $this->max_bytes( $field );

		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== $file['error'] ) {
				return in_array( $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
					/* translators: %s: maximum file size. */
					? sprintf( __( 'Files must be smaller than %s.', 'glixform' ), size_format( $max ) )
					: __( 'The file could not be uploaded. Please try again.', 'glixform' );
			}
			if ( $file['size'] > $max ) {
				/* translators: %s: maximum file size. */
				return sprintf( __( 'Files must be smaller than %s.', 'glixform' ), size_format( $max ) );
			}
			if ( ! $this->is_valid_upload( $file, $allowed ) ) {
				/* translators: %s: list of allowed file extensions. */
				return sprintf( __( 'This file type is not allowed. Allowed types: %s.', 'glixform' ), implode( ', ', $allowed ) );
			}
		}

		return '';
	}

	/**
	 * Real uploaded file with an allowed extension and matching content type.
	 *
	 * @param array    $file    Upload record.
	 * @param string[] $allowed Allowed extensions.
	 * @return bool
	 */
	protected function is_valid_upload( array $file, array $allowed ) {
		if ( ! $this->is_uploaded( $file['tmp_name'] ) ) {
			return false;
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$ext   = strtolower( (string) $check['ext'] );
		if ( '' === $ext || empty( $check['type'] ) || ! in_array( $ext, $allowed, true ) ) {
			return false;
		}
		// Must also be a type WordPress itself allows.
		foreach ( array_keys( get_allowed_mime_types() ) as $pattern ) {
			if ( in_array( $ext, explode( '|', $pattern ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Wrapper so tests can replace the upload check.
	 *
	 * @param string $tmp_name Temporary path.
	 * @return bool
	 */
	protected function is_uploaded( $tmp_name ) {
		return '' !== $tmp_name && is_uploaded_file( $tmp_name );
	}

	/**
	 * Move files into protected storage.
	 *
	 * @param array        $field   Field config.
	 * @param string|array $value   Upload records.
	 * @param int          $form_id Form ID.
	 * @return array|\WP_Error Stored files: [ name, file (relative path), size, type ].
	 */
	public function finalize_value( array $field, $value, $form_id ) {
		$stored = array();
		if ( ! $value ) {
			return $stored;
		}

		$dir = Uploads::form_dir( $form_id );
		if ( ! $dir ) {
			return new \WP_Error( 'glixform_upload_dir', __( 'The file could not be saved. Please contact the site owner.', 'glixform' ) );
		}

		foreach ( (array) $value as $file ) {
			$check  = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			$base   = pathinfo( $file['name'], PATHINFO_FILENAME );
			$target = wp_unique_filename( $dir, wp_generate_password( 16, false ) . '-' . sanitize_file_name( $base ) . '.' . strtolower( $check['ext'] ) );

			if ( ! $this->move( $file['tmp_name'], $dir . '/' . $target ) ) {
				return new \WP_Error( 'glixform_upload_move', __( 'The file could not be saved. Please try again.', 'glixform' ) );
			}
			chmod( $dir . '/' . $target, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

			$stored[] = array(
				'name' => $file['name'],
				'file' => absint( $form_id ) . '/' . $target,
				'size' => (int) $file['size'],
				'type' => (string) $check['type'],
			);
		}

		return $stored;
	}

	/**
	 * Wrapper so tests can replace the move.
	 *
	 * @param string $from Temporary path.
	 * @param string $to   Destination.
	 * @return bool
	 */
	protected function move( $from, $to ) {
		return move_uploaded_file( $from, $to );
	}

	/**
	 * File names.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Stored files.
	 * @return string
	 */
	public function format_value( array $field, $value ) {
		$names = array();
		foreach ( (array) $value as $file ) {
			if ( is_array( $file ) && ! empty( $file['name'] ) ) {
				$names[] = $file['name'];
			}
		}
		return implode( ', ', $names );
	}

	/**
	 * Logic sees the file names.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function logic_value( array $field, $value ) {
		return $this->format_value( $field, $value );
	}

	/**
	 * Download links on the entry screen.
	 *
	 * @param array $item    Snapshot item.
	 * @param array $context entry_id.
	 * @return string
	 */
	public function entry_html( array $item, array $context = array() ) {
		$links = array();
		foreach ( (array) ( $item['value'] ?? array() ) as $index => $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$url     = wp_nonce_url(
				add_query_arg(
					array(
						'action'   => 'glixform_download',
						'entry_id' => absint( $context['entry_id'] ?? 0 ),
						'field_id' => absint( $item['id'] ),
						'index'    => absint( $index ),
					),
					admin_url( 'admin-post.php' )
				),
				'glixform_download_' . absint( $context['entry_id'] ?? 0 )
			);
			$links[] = sprintf(
				'<a class="glixform-file-link" href="%s"><span class="dashicons dashicons-media-default" aria-hidden="true"></span> %s <span class="glixform-file-size">%s</span></a>',
				esc_url( $url ),
				esc_html( $file['name'] ?? '' ),
				esc_html( size_format( (int) ( $file['size'] ?? 0 ) ) )
			);
		}
		return implode( '<br>', $links );
	}
}
