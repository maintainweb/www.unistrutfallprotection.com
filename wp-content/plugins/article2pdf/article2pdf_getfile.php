<?php
/**
 * Send a pdf file for download
 *
 * Because I don't want to use output buffering anymore, I'll now send the created pdf files with this extra file.
 * That gives me the possibility to use output buffering if the user selects HTTP redirection, or no output buffering
 * if the user selects JavaScript redirection. This should fit all needs and there should be no more conflicts with
 * other plugins that use output buffering.
 *
 */

if( !function_exists( 'sys_get_temp_dir' ) )
{
	function sys_get_temp_dir()
	{
		if( !empty( $_ENV[ 'TMP' ] ) )
			return realpath($_ENV['TMP']);
		if( !empty( $_ENV[ 'TMPDIR' ] ) )
			return realpath( $_ENV[ 'TMPDIR' ] );
		if( !empty( $_ENV[ 'TEMP' ] ) )
			return realpath( $_ENV[ 'TEMP' ] );
		$tempfile = tempnam( uniqid( rand(), TRUE ), '' );
		if( file_exists( $tempfile ) )
		{
			unlink( $tempfile );
			return realpath( dirname( $tempfile ) );
		}
	}
}

$post_name = str_replace( array( '../', '..\\', '..', '/', '\\' ), '', base64_decode( $_REQUEST[ 'p' ] ) );
$tmprand = str_replace( array( '../', '..\\', '..', '/', '\\' ), '', $_REQUEST[ 'r' ] );
$tmpdir = base64_decode( $_REQUEST[ 'd' ] );
if( !empty( $post_name ) )
{
	$tmpfile = $tmpdir . (substr( $tmpdir, -1 ) != '/' ? '/' : '') . 'a2p.tmp.' . $post_name . '.' . $tmprand . '.pdf';
	if( file_exists( $tmpfile ) )
	{
		if( $_GET[ 'debug_pdf_file' ] != '1' )
		{
			header( "Content-Type: application/pdf" );
			header( "Content-Disposition: attachment; filename=\"" . $post_name . ".pdf\"" );
		}
		readfile( $tmpfile );
		unlink( $tmpfile );
	}
	else	die( 'Sorry no pdf tempfile found. Please try again.' );
}
else	die( 'No post in this request.' );

?>