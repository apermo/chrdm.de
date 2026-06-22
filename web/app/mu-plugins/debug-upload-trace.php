<?php
/**
 * Plugin Name: Debug Upload Trace (TEMPORARY)
 * Description: Traces image upload sub-size generation to a kill-proof file and reports PHP fatals to Sentry, to diagnose silent large-image upload failures. Remove once the upload issue is resolved.
 */

namespace Apermo\DebugUploadTrace;

use function Sentry\captureMessage;

add_filter( 'wp_generate_attachment_metadata', __NAMESPACE__ . '\\start', 1, 2 );
add_filter( 'image_make_intermediate_size', __NAMESPACE__ . '\\after_size', 9999, 1 );
add_filter( 'wp_generate_attachment_metadata', __NAMESPACE__ . '\\finish', 9999, 2 );

/**
 * Returns the trace log file path inside the uploads directory.
 *
 * @return string Absolute path to the trace log file.
 */
function log_file(): string {
	$uploads = wp_upload_dir();

	return $uploads['basedir'] . '/upload-trace.log';
}

/**
 * Writes one immediately-flushed trace line.
 *
 * Uses an append-and-close write per call so the line survives even a hard
 * external SIGKILL (e.g. an lsapi/LVE request kill), where a shutdown handler
 * never runs.
 *
 * @param string $message Trace message to append.
 */
function trace( string $message ): void {
	$start = $GLOBALS['_dut_start'] ?? \microtime( true );
	$line  = \sprintf(
		"[%s] +%6.2fs mem=%4dM peak=%4dM  %s\n",
		\gmdate( 'H:i:s' ),
		\microtime( true ) - $start,
		\memory_get_usage( true ) / 1048576,
		\memory_get_peak_usage( true ) / 1048576,
		$message,
	);

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct write is intentional: the line must survive a hard request kill, which the WP_Filesystem FTP transport cannot guarantee.
	\file_put_contents( log_file(), $line, \FILE_APPEND | \LOCK_EX );
}

/**
 * Reports a message to Sentry when the wp-sentry SDK is loaded.
 *
 * @param string $message Message to send to Sentry.
 */
function to_sentry( string $message ): void {
	if ( \function_exists( 'Sentry\\captureMessage' ) ) {
		captureMessage( $message );
	}
}

/**
 * Marks the start of metadata generation and arms the fatal-error reporter.
 *
 * @param array $metadata      Attachment metadata being generated.
 * @param int   $attachment_id Attachment post ID.
 * @return array Unmodified attachment metadata.
 */
function start( array $metadata, int $attachment_id ): array {
	$GLOBALS['_dut_start'] = \microtime( true );
	$file                  = get_attached_file( $attachment_id );

	trace(
		\sprintf(
			'START att=%d file=%s sapi=%s max_exec=%s mem_limit=%s',
			$attachment_id,
			\basename( (string) $file ),
			\php_sapi_name(),
			\ini_get( 'max_execution_time' ),
			\ini_get( 'memory_limit' ),
		),
	);

	// Fires only on a normal PHP shutdown — i.e. a PHP-level fatal (timeout or
	// memory exhaustion), NOT an external SIGKILL.
	\register_shutdown_function( __NAMESPACE__ . '\\report_fatal', $attachment_id );

	return $metadata;
}

/**
 * Reports the last PHP fatal (if any) for the given attachment to file and Sentry.
 *
 * @param int $attachment_id Attachment post ID.
 */
function report_fatal( int $attachment_id ): void {
	$error      = \error_get_last();
	$fatal_mask = [ \E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR ];

	if ( $error === null || ! \in_array( $error['type'], $fatal_mask, true ) ) {
		return;
	}

	$detail = \sprintf( '%s @ %s:%d', $error['message'], $error['file'], $error['line'] );
	trace( 'FATAL ' . $detail );
	to_sentry( \sprintf( 'Upload sub-size FATAL (att %d): %s', $attachment_id, $detail ) );
}

/**
 * Records each intermediate size as it is written, revealing the cadence.
 *
 * @param string $path Absolute path to the generated intermediate file.
 * @return string Unmodified file path.
 */
function after_size( string $path ): string {
	trace( 'SIZE done: ' . \basename( (string) $path ) );

	return $path;
}

/**
 * Marks a fully completed metadata generation and the resulting size count.
 *
 * @param array $metadata      Completed attachment metadata.
 * @param int   $attachment_id Attachment post ID.
 * @return array Unmodified attachment metadata.
 */
function finish( array $metadata, int $attachment_id ): array {
	$count = \is_array( $metadata['sizes'] ?? null ) ? \count( $metadata['sizes'] ) : 0;
	trace( \sprintf( 'FINISH att=%d sizes=%d', $attachment_id, $count ) );

	return $metadata;
}
