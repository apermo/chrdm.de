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
 * The value is typed `mixed` rather than `array`: this is a filter callback,
 * so a misbehaving callback earlier in the chain could pass a non-array, and a
 * strict hint would then crash the very uploads this plugin exists to diagnose.
 *
 * @param mixed $metadata      Attachment metadata being generated.
 * @param int   $attachment_id Attachment post ID.
 * @return mixed Unmodified attachment metadata.
 */
function start( mixed $metadata, int $attachment_id ): mixed {
	$GLOBALS['_dut_start']              = \microtime( true );
	$GLOBALS['_dut_current_attachment'] = $attachment_id;
	$file                               = get_attached_file( $attachment_id );

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
	// memory exhaustion), NOT an external SIGKILL. Register once per request so
	// bulk (multi-attachment) requests don't log/alert the fatal more than once.
	static $registered = false;
	if ( ! $registered ) {
		\register_shutdown_function( __NAMESPACE__ . '\\report_fatal' );
		$registered = true;
	}

	return $metadata;
}

/**
 * Reports the last PHP fatal (if any) for the active attachment to file and Sentry.
 */
function report_fatal(): void {
	$error      = \error_get_last();
	$fatal_mask = [ \E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR ];

	if ( $error === null || ! \in_array( $error['type'], $fatal_mask, true ) ) {
		return;
	}

	$attachment_id = $GLOBALS['_dut_current_attachment'] ?? 0;
	$detail        = \sprintf( '%s @ %s:%d', $error['message'], $error['file'], $error['line'] );
	trace( 'FATAL ' . $detail );
	to_sentry( \sprintf( 'Upload sub-size FATAL (att %d): %s', $attachment_id, $detail ) );
}

/**
 * Records each intermediate size as it is written, revealing the cadence.
 *
 * Typed `mixed`: the filter can pass `false`/`null` when an earlier callback
 * skips or fails a size, so a strict `string` hint would crash the upload.
 *
 * @param mixed $path Absolute path to the generated intermediate file, or a non-string on skip/failure.
 * @return mixed Unmodified file path.
 */
function after_size( mixed $path ): mixed {
	if ( \is_string( $path ) ) {
		trace( 'SIZE done: ' . \basename( $path ) );
	} else {
		trace( 'SIZE failed or skipped (non-string path)' );
	}

	return $path;
}

/**
 * Marks a fully completed metadata generation and the resulting size count.
 *
 * @param mixed $metadata      Completed attachment metadata.
 * @param int   $attachment_id Attachment post ID.
 * @return mixed Unmodified attachment metadata.
 */
function finish( mixed $metadata, int $attachment_id ): mixed {
	$count = ( \is_array( $metadata ) && \is_array( $metadata['sizes'] ?? null ) ) ? \count( $metadata['sizes'] ) : 0;
	trace( \sprintf( 'FINISH att=%d sizes=%d', $attachment_id, $count ) );

	return $metadata;
}
