<?php
/*
Plugin Name: article2pdf
Plugin URI: http://www.das-motorrad-blog.de/meine-wordpress-plugins/
Version: 0.27
Author: Marc Schieferdecker
Author URI: http://www.das-motorrad-blog.de
Description: This plugin let your visitors download an article as PDF file - images, formats and tables included. You can specifiy a PDF file to use as template for the generated file. Installation: Activate plugin and add the link &lt;a href="?article2pdf=1"&gt;PDF version&lt;/a&gt; somewhere in the single.php (or post.php) of your theme. Have fun! Configure the plugin options via the <a href="options-general.php?page=article2pdf.php">configuration page</a>. Bug reports and feature requests welcome!
License: GPL
*/

// Include PDF/PDI functions (have a look at http://www.fpdf.org - nice library and freeware!)
define( 'FPDF_FONTPATH', dirname(__FILE__) . "/contributed/pdffonts/" );
define( 'FPDF_TEMPLATEPATH', dirname(__FILE__) . "/contributed/pdftemplates/" );
define( 'FPDF_INCLUDEPATH', dirname(__FILE__) . "/contributed/fpdf/" );
define( 'FPDI_INCLUDEPATH', dirname(__FILE__) . "/contributed/fpdi/" );
define( 'A2P_CACHEPATH', str_replace( 'plugins/article2pdf/../../', '', dirname(__FILE__) . "/../../uploads/" ) );
require_once( FPDF_INCLUDEPATH . 'fpdf.php' );
require_once( FPDF_INCLUDEPATH . 'fpdf_alpha.php' );
require_once( FPDI_INCLUDEPATH . 'fpdi.php' );

/**
 * Replacement for PHP 5 function sys_get_temp_dir
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
		$tempfile = @tempnam( uniqid( rand(), TRUE ), '' );
		if( file_exists( $tempfile ) )
		{
			@unlink( $tempfile );
			return realpath( dirname( $tempfile ) );
		}
	}
}

/**
 * The main plugin class
 */
class article2pdf
{
	var $a2p_AdminOptionsName;
	var $a2p_AdminOptions;
	var $_page_counter;

	// Construct
	function __construct()
	{
		$this -> a2p_AdminOptionsName	= 'a2pPlugin_AdminOptions';
		$this -> a2p_AdminOptions	= $this -> a2p_GetAdminOptions();
		$this -> _page_counter		= 0;
	}
	// PHP4 compatibe construct (please update to PHP 5 soon! ;) )
	function article2pdf() { $this -> __construct(); }

	// Get options for this plugin
	function a2p_GetAdminOptions()
	{
		// Set default options
		$a2pOptions = array(	'PDFTemplateFile' => '',
					'PDFTemplateFilePages' => '',
					'PDFTemplatePath' => FPDF_TEMPLATEPATH,
					'PDFTemplateMarginLeft' => 25,
					'PDFTemplateMarginRight' => 25,
					'PDFTemplateMarginTop' => 25,
					'PDFTemplateMarginBottom' => 25,
					'PDFOptionIncludePics' => 'true',
					'PDFOptionIncludeDate' => 'true',
					'PDFOptionIncludeDateOnPages' => 'false',
					'PDFOptionDateformat' => '%x -',
					'PDFOptionDateLocale' => 'en_EN',
					'PDFOptionFont' => 'Arial',
					'PDFOptionFontSize' => 12,
					'PDFOptionLineHeight' => 6,
					'PDFOptionCacheTime' => 3600,
					'PDFOptionCachePath' => A2P_CACHEPATH,
					'PDFPageCountPosX' => 10,
					'PDFPageCountPosY' => 10,
					'PDFPageCountString' => 'Page %%page%% of %%pagestotal%%',
					'PDFPageCountFontSize' => '8',
					'PDFOptionDenySearchengines' => 'false',
					'PDFOptionRedirectMethod' => 'HTTP',
					'PDFOptionTmpPath' => @sys_get_temp_dir(),
					'PDFOptionAutoAddLinkText' => ''
				);
		// Load existing options
		$_a2pOptions = get_option( $this -> a2p_AdminOptionsName );
		// Overwrite defaults
		$update = false;
		if( count( $_a2pOptions ) )
		{
			foreach( $_a2pOptions AS $oKey => $oVal )
			{
				if( $oKey == 'PDFTemplatePath' && empty( $oVal ) ) {
					$oVal = FPDF_TEMPLATEPATH;
					$update = true;
				}
				if( $oKey == 'PDFOptionCachePath' && empty( $oVal ) ) {
					$oVal = A2P_CACHEPATH;
					$update = true;
				}
				if( $oKey == 'PDFOptionTmpPath' && empty( $oVal ) ) {
					$oVal = @sys_get_temp_dir();
					$update = true;
				}
				$a2pOptions[ $oKey ] = $oVal;
			}
		}
		// Set default options to wp db if no existing options or new options are found
		if( !count( $_a2pOptions ) || count( $_a2pOptions ) != count( $a2pOptions ) || $update )
		{
			update_option( $this -> a2p_AdminOptionsName, $a2pOptions );
		}
		// Return options
		return $a2pOptions;
	}

	// Create pdf
	function a2p_CreatePdf( $content_html )
	{
		// Get post
		global $post;
		// Save content html to new var
		$content = $content_html;
		$pdfdata = '';

		// Set base url
		$base_url = get_option( 'siteurl' );

		// Search engine spider check (works only with redirect method HTTP!)
		if( $this -> a2p_AdminOptions[ 'PDFOptionDenySearchengines' ] == 'true' && $this -> a2p_AdminOptions[ 'PDFOptionRedirectMethod' ] != 'JS' && $this -> _is_bot() )
		{
			// Drop all output buffers
			$this -> _ob_end_clean();
			// Send a 410 gone to stop spidering and tell to remove already spidered content
			header( 'HTTP/1.1 410 Gone' );
			header( 'Status: 410 Gone' );
			die();
		}

		// On debug never deliver from cache!
		if( strpos( $_SERVER[ 'REQUEST_URI' ], 'debug_pdf_file=1' ) || $_POST[ 'debug_pdf_file' ] == '1' )
		{
			$this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ] = 0;
			$debug_pdf_file = 1;
		}
		else	$debug_pdf_file = 0;

		if( !empty( $post -> ID ) )
		{
			// Get File from cache?
			$cachefile = $this -> _cache_get_filename( $post );
			if( $this -> _cache_recreate( $cachefile ) )
			{
				// Check if this is a page or a post
				$page_ids = get_all_page_ids();
				$is_page = in_array( $post -> ID, $page_ids );
				unset( $page_ids );

				$pdf =& new FPDI();
				$pdf -> SetAuthor( "WordPress article2pdf plugin" );
				$pdf -> SetCreator( "WordPress article2pdf plugin" );
				$pdf -> SetTitle( $this -> _decode_utf( strip_tags( $post -> post_title ) ) );
				if( !empty( $this -> a2p_AdminOptions[ 'PDFTemplateFile' ] ) && empty( $this -> a2p_AdminOptions[ 'PDFTemplateFilePages' ] ) )
					$this -> a2p_AdminOptions[ 'PDFTemplateFilePages' ] = $this -> a2p_AdminOptions[ 'PDFTemplateFile' ];
				if( !empty( $this -> a2p_AdminOptions[ 'PDFTemplateFile' ] ) && !$is_page )
				{
					$pdf -> setSourceFile( $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] . $this -> a2p_AdminOptions[ 'PDFTemplateFile' ] );
					$pdf -> tplIdx = $pdf -> importPage( 1 );
				}
				else
				if( !empty( $this -> a2p_AdminOptions[ 'PDFTemplateFilePages' ] ) && $is_page )
				{
					$pdf -> setSourceFile( $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] . $this -> a2p_AdminOptions[ 'PDFTemplateFilePages' ] );
					$pdf -> tplIdx = $pdf -> importPage( 1 );
				}
				$this -> _add_page( $pdf );
				if( (!empty( $this -> a2p_AdminOptions[ 'PDFTemplateFile' ] ) && !$is_page) || (!empty( $this -> a2p_AdminOptions[ 'PDFTemplateFilePages' ] ) && $is_page) )
					$pdf -> useTemplate( $pdf -> tplIdx );
				$pdf -> SetLineWidth( 0.2 );
				$pdf -> SetLeftMargin( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] );
				$pdf -> SetRightMargin( $this -> a2p_AdminOptions[ 'PDFTemplateMarginRight' ] );
				$pdf -> SetTopMargin( $this -> a2p_AdminOptions[ 'PDFTemplateMarginTop' ] );
				$pdf -> SetAutoPageBreak( true, $this -> a2p_AdminOptions[ 'PDFTemplateMarginBottom' ] );
				$pdf -> SetXY( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ], $this -> a2p_AdminOptions[ 'PDFTemplateMarginTop' ] );
				$pdf -> SetFont( $this -> a2p_AdminOptions[ 'PDFOptionFont' ], '', $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] );
				$pdf -> AliasNbPages( '%%pagestotal%%' );

				// Write title
				if( $this -> a2p_AdminOptions[ 'PDFOptionIncludeDate' ] == 'true' )
				{
					if( ($is_page && $this -> a2p_AdminOptions[ 'PDFOptionIncludeDateOnPages' ] == 'true') || !$is_page )
					{
						$date_timestamp = strtotime( $post -> post_date );
						setlocale( LC_TIME, $this -> a2p_AdminOptions[ 'PDFOptionDateLocale' ] . '.UTF8' );
						$date_str = strftime( $this -> a2p_AdminOptions[ 'PDFOptionDateformat' ], $date_timestamp );
						$post -> post_title = $date_str . ' ' . $post -> post_title;
					}
				}
				$this -> _font_bold( $pdf );
				$pdf -> Cell( 0, $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ] * ceil( $pdf -> GetStringWidth( $this -> _decode_utf( html_entity_decode( strip_tags( $post -> post_title ), ENT_QUOTES, 'UTF-8' ) ) ) / ($pdf -> w - $pdf -> lMargin - $pdf -> rMargin) ), $this -> _decode_utf( html_entity_decode( strip_tags( $post -> post_title ), ENT_QUOTES, 'UTF-8' ) ), 1, 1, 'C' );
				$this -> _font_normal( $pdf );
				$pdf -> Ln();

				// Write body
				// Convert html to natural tags
				$convertRegEx = array(	'/<div.*>/iU' => '',
							'/<\/div>/iU' => '',
							'/<\/li(.*)><li(.*)>/iU' => '</li\1>' . "\n" . '<li\2>',
							'/<script.*>.*<\/script>/siU' => '',
							'/<style.*>.*<\/style>/siU' => '',
							'/<link.*>/iU' => '',
							'/<span.*>/iU' => '',
							'/<\/span>/iU' => '',
							'/<br.*>/iU' => '<br/>',
							"/<br\/>\n/siU" => '<br/>',
							'/<ul.*>/iU' => '<ul>',
							'/<ol.*>/iU' => '<ol>',
							'/<li.*>/iU' => '<li>',
							'/<blockquote.*>/iU' => '<blockquote>',
							'/<pre.*>/iU' => '<pre>',
							'/<code.*>/iU' => '<code>',
							'/<i[^m]+.*>/iU' => '<i>',
							'/<em.*>/iU' => '<em>',
							'/<u[^l]+.*>/iU' => '<u>',
							'/<b[^rl]+.*>/iU' => '<b>',
							'/<strong.*>/iU' => '<strong>',
							'/<hr.*>/iU' => '<hr>',
							'/<h([1-9]).*>/iU' => '<h\1>'
							/*'/<.*>/iU' => '<>',*/
						);
				// Remove windows shit
				$content = str_replace( "\r", '', $content );
				// Convert tags to cleaned html
				$content = preg_replace( array_keys( $convertRegEx ), $convertRegEx, $content );
				// Prepare tables to be in a single paragraph (easier to parse)
				preg_match_all( '#.*(<table.*>.*</table>).*#siU', $content, $tables );
				if( count( $tables[ 1 ] ) )
				{
					foreach( $tables[ 1 ] AS $tkey => $tbl )
					{
						$tbl = str_replace( "\n", '', $tbl );
						$content = str_replace( $tables[ 1 ][ $tkey ], $tbl, $content );
					}
				}
				// Prepare code and pre for easyer parsing
				preg_match_all( '#.*(<(pre|code)>.*</(pre|code)>).*#siU', $content, $codes );
				if( count( $codes[ 1 ] ) )
				{
					foreach( $codes[ 1 ] AS $ckey => $code )
					{
						$code = str_replace( "\n", array( '|BR|', '' ), $code );
						$content = str_replace( $codes[ 1 ][ $ckey ], $code, $content );
					}
				}
				// Explore by paragraph
				$paragraphs_array = explode( "\n", $content );
				$isFirstParagraph = true;
				$link_href = '';
				$ol_counter = 0;
				foreach( $paragraphs_array AS $pkey => $p )
				{
					$page_before = $pdf -> PageNo();
					$p = trim( $p );
					if( !empty( $p ) )
					{
						// Parse br
						$p = str_replace( '<br/>', "\n", $p );

						// Convert tables
						$p = $this -> _convert_tables( $p );

						// Convert code
						$p = $this -> _convert_code( $p );

						// Convert links
						$p = $this -> _convert_links( $p );

						// Convert bold
						$p = $this -> _convert_bold( $p );

						// Convert underline
						$p = $this -> _convert_underline( $p );

						// Convert italic
						$p = $this -> _convert_italic( $p );

						// Convert blockquote
						$p = $this -> _convert_blockquote( $p );

						// Convert lists and items
						$p = $this -> _convert_listitem( $p );

						// Convert headings
						$p = $this -> _convert_heading( $p );

						// Convert horizontal lines
						$p = $this -> _convert_lines( $p );

						// Write text
						$breaks_after = 2;
						$parts = explode( '|-====-|', $p );
						foreach( $parts AS $pkey => $part )
						{
							// Parse style of part
							// ...beginnings
							if( substr( $part, 0, 5 ) == 'LINK|' )
							{
								$this -> _font_underline( $pdf );
								$part = substr( $part, 5 );
								$part_arr = explode( '|', $part );
								$link_href = $part_arr[ 0 ];
								unset( $part_arr[ 0 ] );
								$part = implode( $part_arr );
								unset( $part_arr );
							}
							if( substr( $part, 0, 2 ) == 'U|' )
							{
								$this -> _font_underline( $pdf );
								$part = substr( $part, 2 );
							}
							if( substr( $part, 0, 2 ) == 'B|' )
							{
								$this -> _font_bold( $pdf );
								$part = substr( $part, 2 );
							}
							if( substr( $part, 0, 2 ) == 'I|' )
							{
								$this -> _font_italic( $pdf );
								$part = substr( $part, 2 );
							}
							if( substr( $part, 0, 1 ) == 'H' && substr( $part, 2, 1 ) == '|' )
							{
								$hlevel = substr( $part, 1, 1 );
								$this -> _font_heading( $pdf, $hlevel );
								$part = substr( $part, 3 );
							}
							if( substr( $part, 0, 2 ) == 'Q|' )
							{
								$this -> _margin_quote( $pdf );
								$part = substr( $part, 2 );
							}
							if( substr( $part, 0, 5 ) == 'CODE|' )
							{
								$this -> _font_code( $pdf );
								$part = str_replace( '|BR|', "\n", substr( $part, 5 ) );
								$pdf -> MultiCell( 0, $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ], $this -> _decode_utf( html_entity_decode( $part, ENT_QUOTES, 'UTF-8' ) ), 1, 'L' );
								$part = '';
							}
							if( substr( $part, 0, 3 ) == 'UL|' || substr( $part, 0, 3 ) == 'OL|' )
							{
								if( substr( $part, 0, 3 ) == 'OL|' )
									$ol_counter = 1;
								$this -> _margin_quote( $pdf );
								$part = substr( $part, 3 );
								$breaks_after = 1;
							}
							if( substr( $part, 0, 3 ) == 'LI|' )
							{
								$part = substr( $part, 3 );
								if( $ol_counter == 0 )
								{
									$part = ' ' . $part;
								}
								else
								{
									$part = "$ol_counter. " . $part;
									$ol_counter++;
								}
							}
							if( substr( $part, 0, 3 ) == 'TR|' )
							{
								if( !isset( $trheight ) )
								{
									$trheight = $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ];
									$pdf -> SetX( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] );
								}
								else
								{
									$pdf -> SetXY( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ], $y + $trheight );
									$trheight = $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ];
								}
								$ccount = intval( substr( $part, 3 ) );
								$cwidth = floor( ($pdf -> w - $pdf -> lMargin - $pdf -> rMargin) / $ccount );
								$part = '';
								$tdcounter = 1;
							}
							if( substr( $part, 0, 3 ) == 'TH|' )
							{
								$this -> _font_bold( $pdf );
								$cpart = $this -> _decode_utf( html_entity_decode( strip_tags( trim( substr( $part, 3 ) ) ), ENT_QUOTES, 'UTF-8' ) );
								$pdf -> SetFillColor( 0xCC, 0xCC, 0xCC );
								$y = $pdf -> GetY();
								$x = $pdf -> GetX();
								$pdf -> MultiCell( $cwidth, $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ], $cpart, 'LTR', 'C', true );
								if( $pdf -> GetY() < $y )
								{
									$pdf -> useTemplate( $pdf -> tplIdx );
									$trheight = $pdf -> GetY() - $this -> a2p_AdminOptions[ 'PDFTemplateMarginTop' ];
									$pdf -> SetXY( $x + $cwidth, $this -> a2p_AdminOptions[ 'PDFTemplateMarginTop' ] );
								}
								else
								{
									$trheight = (($pdf -> GetY() - $y) > $trheight ? $pdf -> GetY() - $y : $trheight);
									if( $tdcounter == $ccount )
									{
										for( $lc = 0; $lc <= $ccount; $lc++ )
											$pdf -> Line( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] + ($cwidth * $lc), $y, $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] + ($cwidth * $lc), $y + $trheight );
									}
									$pdf -> SetXY( $x + $cwidth, $y );
								}
								$part = '';
								$tdcounter++;
							}
							if( substr( $part, 0, 3 ) == 'TD|' )
							{
								$pdf -> SetFontSize( floor( $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] * 0.80 ) );
								$cpart = $this -> _decode_utf( html_entity_decode( strip_tags( trim( substr( $part, 3 ) ) ), ENT_QUOTES, 'UTF-8' ) );
								$y = $pdf -> GetY();
								$x = $pdf -> GetX();
								$pdf -> MultiCell( $cwidth, $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ], $cpart, 'LTR', 'L', false );
								if( $pdf -> GetY() < $y )
								{
									$pdf -> useTemplate( $pdf -> tplIdx );
									$trheight = $pdf -> GetY() - $this -> a2p_AdminOptions[ 'PDFTemplateMarginTop' ];
									$pdf -> SetXY( $x + $cwidth, $this -> a2p_AdminOptions[ 'PDFTemplateMarginTop' ] );
									$y = $this -> a2p_AdminOptions[ 'PDFTemplateMarginTop' ];
								}
								else
								{
									$trheight = (($pdf -> GetY() - $y) > $trheight ? $pdf -> GetY() - $y : $trheight);
									if( $tdcounter == $ccount )
									{
										for( $lc = 0; $lc <= $ccount; $lc++ )
											$pdf -> Line( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] + ($cwidth * $lc), $y, $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] + ($cwidth * $lc), $y + $trheight );
									}
									$pdf -> SetXY( $x + $cwidth, $y );
								}
								$part = '';
								$tdcounter++;
								$pdf -> SetFontSize( $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] );
							}
							if( substr( $part, 0, 3 ) == '__|' )
							{
								$part = '';
								$y = $pdf -> GetY();
								$pdf -> SetLineWidth( 0.4 );
								$pdf -> Line( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ], $y, $pdf -> w - $this -> a2p_AdminOptions[ 'PDFTemplateMarginRight' ], $y );
								$pdf -> SetLineWidth( 0.2 );
								$breaks_after = 1;
							}

							// Get Images from text
							if( $this -> a2p_AdminOptions[ 'PDFOptionIncludePics' ] == 'true' )
							{
								if( preg_match_all( '#<img.*src="(.*)".*>#iU', $part, $matches ) )
								{
									foreach( $matches[ 1 ] AS $ikey => $img )
									{
										// Convert to local img path, if current base url found in img src
										if( strpos( $img, $base_url ) === 0 )
										{
											$local_img_path = str_replace( $base_url, '', $img );
											$img = ABSPATH . (substr( $local_img_path, 0, 1 ) === '/' ? substr( $local_img_path, 1 ) : $local_img_path);
										}
										else
										if( substr( $img, 0, 1 ) === '/' )
										{
											$img = ABSPATH . $img;
										}
										// Get dimensions
										$imgsize = GetImageSize( $img );
										// Set size to points at 72 dpi
										$imgsize[ 0 ] = $imgsize[ 0 ] / $pdf -> k;
										$imgsize[ 1 ] = $imgsize[ 1 ] / $pdf -> k;
										// Recalculate image width if image to large
										$w = 0;
										$h = 0;
										if( $imgsize[ 0 ] > ($pdf -> w - $pdf -> lMargin - $pdf -> rMargin) )
										{
											$w_f = $imgsize[ 0 ] / ($pdf -> w - $pdf -> lMargin - $pdf -> rMargin);
											$w = ($pdf -> w - $pdf -> lMargin - $pdf -> rMargin);
											$h = $imgsize[ 1 ] / $w_f;
											$imgsize[ 0 ] = $w;
											$imgsize[ 1 ] = $h;
										}
										// Begin new page if image has too much height
										if( $isFirstParagraph == false )
										{
											if( ($pdf -> GetY() + $imgsize[ 1 ]) > ($pdf -> h - $pdf -> bMargin) )
												$this -> _add_page( $pdf );
										}
										// Centered image
										$pdf -> Image( $img, ($pdf -> w / 2) - ($imgsize[ 0 ] / 2), $pdf -> GetY(), $w, $h, '', $link_href );
										// Set next X,Y position of following text
										$pdf -> SetXY( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ], $pdf -> GetY() + $imgsize[ 1 ] + $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ] );
										// Remove img from paragraph
										$part = str_replace( $matches[ 0 ][ $ikey ], '', $part );
									}
									$breaks_after = 0;
								}
							}

							// ...endings
							if( substr( $part, 0, 3 ) == '/U|' || substr( $part, 0, 3 ) == '/B|' || substr( $part, 0, 3 ) == '/I|' )
							{
								$this -> _font_normal( $pdf );
								$part = substr( $part, 3 );
							}
							if( substr( $part, 0, 6 ) == '/CODE|' )
							{
								$this -> _font_normal( $pdf );
								$part = substr( $part, 6 );
							}
							if( substr( $part, 0, 6 ) == '/LINK|' )
							{
								$this -> _font_normal( $pdf );
								$part = substr( $part, 6 );
								$link_href = '';
							}
							if( substr( $part, 0, 4 ) == '/UL|' || substr( $part, 0, 4 ) == '/OL|' )
							{
								if( substr( $part, 0, 4 ) == '/OL|' )
									$ol_counter = 0;
								$this -> _margin_normal( $pdf );
								$part = substr( $part, 4 );
								$breaks_after = 2;
							}
							if( substr( $part, 0, 4 ) == '/LI|' )
							{
								$part = substr( $part, 4 );
								$breaks_after = 1;
							}
							if( substr( $part, 0, 2 ) == '/H' )
							{
								$this -> _font_normal( $pdf );
								$part = substr( $part, 4 );
								$breaks_after = 2;
							}
							if( substr( $part, 0, 4 ) == '/TD|' || substr( $part, 0, 4 ) == '/TH|' || substr( $part, 0, 4 ) == '/TR|' )
							{
								if( substr( $part, 0, 4 ) == '/TR|' )
									$pdf -> Line( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ], $y + $trheight, $pdf -> w - $this -> a2p_AdminOptions[ 'PDFTemplateMarginRight' ], $y + $trheight );
								$this -> _font_normal( $pdf );
								$part = substr( $part, 4 );
							}
							if( substr( $part, 0, 3 ) == '/Q|' )
							{
								$this -> _margin_normal( $pdf );
								$part = substr( $part, 3 );
							}

							// Write part
							$part_content = $this -> _decode_utf( html_entity_decode( strip_tags( $part ), ENT_QUOTES, 'UTF-8' ) );
							if( !empty( $part_content ) )
								$pdf -> Write( $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ], $part_content, $link_href );
						}
						// Begin new paragraph
						for( $b = 0; $b < $breaks_after; $b++ )
							$pdf -> Write( $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ], "\n" );

						// Set first paragraph is printed
						$isFirstParagraph = false;
					}

					// On page break use pdf template
					if( $page_before != $pdf -> PageNo() )
					{
						if( (!empty( $this -> a2p_AdminOptions[ 'PDFTemplateFile' ] ) && !$is_page) || (!empty( $this -> a2p_AdminOptions[ 'PDFTemplateFilePages' ] ) && $is_page) )
							$pdf -> useTemplate( $pdf -> tplIdx );
						$this -> _add_page_count( $pdf );
					}
				}
				// Get pdfdata and store in cache, copy it to tmp dir
				$pdfdata = $pdf -> Output( '', 'S' );
				if( !empty( $this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ] ) && is_writable( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
					file_put_contents( $cachefile, $pdfdata );
			}
			// Copy cache file to tmp file or write tmp file from pdf data
			$tmprand = substr( md5( rand( 10000000, 99999999 ) . $_SERVER[ 'REMOTE_ADDR' ] ), 0, 6 );
			$tmpdir = $this -> a2p_AdminOptions[ 'PDFOptionTmpPath' ];
			$tmpfile = $tmpdir . (substr( $tmpdir, -1 ) != '/' ? '/' : '') . 'a2p.tmp.' . $post -> post_name . '.' . $tmprand . '.pdf';
			if( file_exists( $cachefile ) )
				copy( $cachefile, $tmpfile );
			else
			if( !empty( $pdfdata ) )
				file_put_contents( $tmpfile, $pdfdata );
			else
				die( 'article2pdf plugin: No pdf data was created' );
			// Redirect method HTTP
			if( $this -> a2p_AdminOptions[ 'PDFOptionRedirectMethod' ] == 'HTTP' )
			{
				// Drop all output buffers
				$this -> _ob_end_clean();
				// Redirect to download the tmp file
				if( $debug_pdf_file )
					header( "Location: $base_url" . (substr( $base_url, -1 ) != '/' ? '/' : '') . "wp-content/plugins/article2pdf/article2pdf_getfile.php?p=" . base64_encode( $post -> post_name ) . "&r=$tmprand&d=" . base64_encode( $tmpdir ) . "&debug_pdf_file=1" );
				else
					header( "Location: $base_url" . (substr( $base_url, -1 ) != '/' ? '/' : '') . "wp-content/plugins/article2pdf/article2pdf_getfile.php?p=" . base64_encode( $post -> post_name ) . "&r=$tmprand&d=" . base64_encode( $tmpdir ) );
				die();
			}
			// Redirect method JavaScript
			else
			{
				if( $debug_pdf_file )
					$content_html .= "<script type=\"text/javascript\">setTimeout( \"window.location.href = '$base_url" . (substr( $base_url, -1 ) != '/' ? '/' : '') . "wp-content/plugins/article2pdf/article2pdf_getfile.php?p=" . base64_encode( $post -> post_name ) . "&r=$tmprand&d=" . base64_encode( $tmpdir ) . "&debug_pdf_file=1'\", 3000 );</script>";
				else
					$content_html .= "<script type=\"text/javascript\">setTimeout( \"window.location.href = '$base_url" . (substr( $base_url, -1 ) != '/' ? '/' : '') . "wp-content/plugins/article2pdf/article2pdf_getfile.php?p=" . base64_encode( $post -> post_name ) . "&r=$tmprand&d=" . base64_encode( $tmpdir ) . "'\", 3000 );</script>";
				return $content_html;
			}
		}
		else	echo 'article2pdf plugin: No post ID found!';
	}

	// Adminpage
	function a2p_AdminPage()
	{
		if( function_exists( 'load_plugin_textdomain' ) ) {
			load_plugin_textdomain( 'article2pdf', 'wp-content/plugins/article2pdf' );
		}
		$open = 0;

		// Create a test pdf file
		if( $_POST[ 'a2p_admin_action' ] == 'create_test_pdf' )
		{
			$this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ] = 0;
			$this -> a2p_AdminOptions[ 'PDFOptionRedirectMethod' ] = 'HTTP';
			global $post;
			$post = new stdClass;
			$post -> ID		= rand( 10000, 10000000 );
			$post -> post_title	= 'TEST PDF';
			$post -> post_date	= 'now';
			$post -> post_name	= 'test-pdf-' . substr( md5( rand() ), 0, 5 );
			$this -> a2p_CreatePdf( stripslashes( $_POST[ 'testhtml' ] ) );
			$open = 5;
		}

		// Delete single file from cache
		if( $_GET[ 'a2p_admin_action' ] == 'deletecachefile' )
		{
			$cachefile = str_replace( '..', '', urldecode( $_GET[ 'cachefile' ] ) );
			if( file_exists( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cachefile ) )
				unlink( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cachefile );
			$open = 4;
		}

		// Get file from cache
		if( $_GET[ 'a2p_admin_action' ] == 'getcachefile' )
		{
			$cachefile = str_replace( '..', '', urldecode( $_GET[ 'cachefile' ] ) );
			if( file_exists( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cachefile ) )
			{
				// Drop all output buffers
				$this -> _ob_end_clean();
				header( "Content-Type: application/pdf" );
				header( "Content-Disposition: attachment; filename=\"" . $cachefile . "\"" );
				echo file_get_contents( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cachefile );
				die();
			}
			$open = 4;
		}

		// Delete expired cache files
		if( $_GET[ 'a2p_admin_action' ] == 'cache_delete_expired' )
		{
			if( is_writeable( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
			{
				if( $d = opendir( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
				{
					while( false !== ($cf = readdir( $d ) ) )
					{
						$fSuffix_Arr = explode( ".", $cf );
						if( end( $fSuffix_Arr ) == 'pdf' && $fSuffix_Arr[ 0 ] == 'a2p' && $fSuffix_Arr[ 1 ] == 'cache' )
						{
							if( filemtime( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cf ) < (time() - $this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ]) )
								unlink( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cf );
						}
					}
				}
				print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "All expired cache files deleted.", "article2pdf" ) . "</strong></p></div>";
				$open = 4;
			}
			else	print __( "Sorry, the cache directory is not writeable. Please chmod the directory to a permission that allows the webserver to write into that directory.", "article2pdf" );
		}

		// Delete cache
		if( $_POST[ 'a2p_admin_action' ] == 'cache_delete' )
		{
			if( is_writeable( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
			{
				if( $d = opendir( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
				{
					while( false !== ($cf = readdir( $d ) ) )
					{
						$fSuffix_Arr = explode( ".", $cf );
						if( end( $fSuffix_Arr ) == 'pdf' && $fSuffix_Arr[ 0 ] == 'a2p' && $fSuffix_Arr[ 1 ] == 'cache' )
						{
							unlink( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cf );
						}
					}
				}
				print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "All cache files deleted.", "article2pdf" ) . "</strong></p></div>";
				$open = 4;
			}
			else	print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "Sorry, the cache directory is not writeable. Please chmod the directory to a permission that allows the webserver to write into that directory.", "article2pdf" ) . "</strong></p></div>";
		}

		// Delete template file
		if( $_POST[ 'a2p_admin_action' ] == 'delete' )
		{
			if( !empty( $_POST[ 'PDFTemplateFile' ] ) )
			{
				if( $_POST[ 'PDFTemplateFile' ] != $this -> a2p_AdminOptions[ 'PDFTemplateFile' ] )
				{
					if( unlink( $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] . $_POST[ 'PDFTemplateFile' ] ) )
					{
						print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "The file was successfully deleted.", "article2pdf" ) . "</strong></p></div>";
					}
					else
					{
						print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "Sorry, the deletion of the file failed. Maybe the directory is not writeable from the webserver?", "article2pdf" ) . "</strong></p></div>";
					}
				}
				else
				{
					print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "Sorry, we can't delete the file that is currently set as template file.", "article2pdf" ) . "</strong></p></div>";
				}
			}
			else
			{
				print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "No file selected, no file deleted. That was easy...", "article2pdf" ) . "</strong></p></div>";
			}
			$open = 3;
		}

		// Upload template file
		if( $_POST[ 'a2p_admin_action' ] == 'upload' )
		{
			if( is_uploaded_file( $_FILES[ 'templatefile' ][ 'tmp_name' ] ) )
			{
				if( is_writeable( $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] ) )
				{
					$suffix_arr = explode( '.', $_FILES[ 'templatefile' ][ 'name' ] );
					if( strtolower( end( $suffix_arr ) ) == 'pdf' )
					{
						$local_filename = preg_replace( '/[^a-z0-9\.]/i', '', $_FILES[ 'templatefile' ][ 'name' ] );
						if( move_uploaded_file( $_FILES[ 'templatefile' ][ 'tmp_name' ], $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] . $local_filename ) )
						{
							print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "Upload successful. Now you can use the pdf file as template. Just configure that in the article2pdf options.", "article2pdf" ) . "</strong></p></div>";
							$open = 1;
						}
						else
						{
							print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "Sorry, the file could not moved to the target directory. Maybe the directory is not writeable from the webserver?", "article2pdf" ) . "</strong></p></div>";
							$open = 2;
						}
					}
					else
					{
						print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "This is not a pdf file. Upload failed.", "article2pdf" ) . "</strong></p></div>";
						$open = 2;
					}
				}
				else
				{
					print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "Sorry, the directory is not writeable by the webserver. Please set the correct owner.", "article2pdf" ) . "</strong></p></div>";
					$open = 2;
				}
			}
			else
			{
				print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "No upload file? Sorry, dave...", "article2pdf" ) . "</strong></p></div>";
				$open = 2;
			}
		}

		// Set options
		if( $_POST[ 'a2p_admin_action' ] == 'set' )
		{
			$a2pOptionsNew = array();
			foreach( array_keys( $this -> a2p_AdminOptions ) AS $oKey )
			{
				$a2pOptionsNew[ $oKey ] = (!eregi( "[^0-9,.]", $_POST[ $oKey ] ) ? str_replace( ',', '.', $_POST[ $oKey ] ) : $_POST[ $oKey ]);
				if( $oKey == 'PDFOptionCachePath' )
				{
					if( !empty( $_POST[ $oKey ] ) )
						$a2pOptionsNew[ $oKey ] = (substr( $_POST[ $oKey ], -1 ) != '/' ? $_POST[ $oKey ] . '/' : $_POST[ $oKey ]);
					else
						$a2pOptionsNew[ $oKey ] = A2P_CACHEPATH;
				}
				if( $oKey == 'PDFTemplatePath' )
				{
					if( !empty( $_POST[ $oKey ] ) )
						$a2pOptionsNew[ $oKey ] = (substr( $_POST[ $oKey ], -1 ) != '/' ? $_POST[ $oKey ] . '/' : $_POST[ $oKey ]);
					else
						$a2pOptionsNew[ $oKey ] = FPDF_TEMPLATEPATH;
				}
				if( $oKey == 'PDFOptionTmpPath' && empty( $_POST[ $oKey ] ) )
						$a2pOptionsNew[ $oKey ] = @sys_get_temp_dir();
			}
			update_option( $this -> a2p_AdminOptionsName, $a2pOptionsNew );
			$this -> a2p_AdminOptions = $a2pOptionsNew;
			if( $_POST[ 'createdir' ] == 'yes' )
			{
				if( !file_exists( $_POST[ 'PDFTemplatePath' ] ) )
					mkdir( $_POST[ 'PDFTemplatePath' ] );
			}
			print "<div id=\"message\" class=\"updated fade\"><p><strong>" . __( "Options updated.", "article2pdf" ) . "</strong></p></div>";
		}

		// Container
		print "<div class=\"wrap\">\n";
		print "<h2>" . __( "article2pdf plugin admin page", "article2pdf" ) . "</h2>\n";
		print "<br class=\"clear\"/>";

		// Output setup form
		print "<div id=\"poststuff\">\n";
		print "<div class=\"postbox\">\n";
		print "<form name=\"a2pAdminPage\" method=\"POST\" action=\"" . $_SERVER[ "REQUEST_URI" ] . "\" enctype=\"multipart/form-data\">\n";
		print "<input type=\"hidden\" name=\"a2p_admin_action\" value=\"set\"/>\n";
		print "<h3>" . __( "article2pdf options", "article2pdf" ) . "</h3>\n";
		print "<div class=\"inside\">\n";
		print "<h4>" . __( "PDF template file", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Enter the absolute path to the directory where the pdf template files were stored. Important: The directory MUST be writeable by the webserver. The last character MUST be a slash.<br/>Hint: After changing this you have to upload a new pdf template file to that directory.", "article2pdf" );
		print "</td><td><input type=\"text\" name=\"PDFTemplatePath\" size=\"50\" maxlength=\"400\" value=\"" . $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] . "\"/></td></tr>\n";
		if( !is_writeable( $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] ) )
		{
			print "<tr valign=\"top\"><td colspan=\"2\" style=\"color:red\">";
			print __( "Sorry, the template directory is not writeable. Please chmod the directory to a permission that allows the webserver to write into that directory or upload your templates with FTP.", "article2pdf" );
			print "</td></tr>\n";
		}
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Try to create this directory on change? (e.g. if your enter something like '[...]/wp-content/uploads/pdftemplates' what is always a good choice!)", "article2pdf" );
		print "</td><td>";
		print "<input type=\"checkbox\" name=\"createdir\" value=\"yes\"/> " . __( "Yes, do that!", "article2pdf" ) . "\n";
		print "</td></tr>\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print str_replace( '%%templatepath%%', $this -> a2p_AdminOptions[ 'PDFTemplatePath' ], __( "To use a pdf file as template for your generated pdf files, just place the template file in %%templatepath%% and choose the file from the select box.", "article2pdf" ) ) . "\n";
		print "</td><td>";
		$sTpl  = "<select name=\"PDFTemplateFile\">\n";
		$sTpl .= "<option value=\"\">" . __( "none", "article2pdf" ) . "</option>\n";
		// PDFTemplateFile: Select a file
		if( $d = opendir( $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] ) )
		{
			while( false !== ($tf = readdir( $d ) ) )
			{
				$fSuffix_Arr = explode( ".", $tf );
				$fSuffix = end( $fSuffix_Arr );
				if( strtolower( $fSuffix ) == 'pdf' )
					$sTpl .= "<option value=\"$tf\"" . ($this -> a2p_AdminOptions[ 'PDFTemplateFile' ] == $tf ? "selected=\"selected\"" : "") . ">$tf</option>\n";
			}
		}
		$sTpl .= "</select>\n";
		print $sTpl;
		print "</td></tr>\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Select different template file to use for converting pages into the pdf format. If you select nothing, the standard template file will be used.", "article2pdf" ) . "\n";
		print "</td><td>";
		$sTpl2  = "<select name=\"PDFTemplateFilePages\">\n";
		$sTpl2 .= "<option value=\"\">" . __( "none", "article2pdf" ) . "</option>\n";
		// PDFTemplateFilePages: Select a file
		if( $d = opendir( $this -> a2p_AdminOptions[ 'PDFTemplatePath' ] ) )
		{
			while( false !== ($tf = readdir( $d ) ) )
			{
				$fSuffix_Arr = explode( ".", $tf );
				$fSuffix = end( $fSuffix_Arr );
				if( strtolower( $fSuffix ) == 'pdf' )
					$sTpl2 .= "<option value=\"$tf\"" . ($this -> a2p_AdminOptions[ 'PDFTemplateFilePages' ] == $tf ? "selected=\"selected\"" : "") . ">$tf</option>\n";
			}
		}
		$sTpl2 .= "</select>\n";
		print $sTpl2;
		print "</td></tr>\n";
		if( strpos( $this -> a2p_AdminOptions[ 'PDFTemplatePath' ], 'article2pdf/contributed/pdftemplates' ) )
		{
			print "<tr valign=\"top\"><td colspan=\"2\" style=\"font-weight:bold;color:red;\">";
			print str_replace( '%%suggestion%%', A2P_CACHEPATH . 'pdftemplates/', __( "Warning! The template path you have selected is not safe because the templates are stored under the plugin directory. If you update a plugin with the WordPress built in automatic plugin update feature ALL FILES in the article2pdf plugin directory were deleted - including your pdf template files. So if you don't want to upload the pdf template file again after every update, here a suggestion of a good template path: %%suggestion%% (be sure to activate 'Try to create this directory on change').", "article2pdf" ) ) . "\n";
			print "</td></tr>";
		}
		print "</table>\n";
		// PDFTemplateMarginLeft, PDFTemplateMargin...
		print "<style type=\"text/css\">.table-noborders td { border:none; background-color:#FFF }</style>\n";
		print "<h4>" . __( "PDF Page Margins (unit is millimeters)", "article2pdf" ) . "</h4>\n";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Set your page margins here...", "article2pdf" );
		print "</td><td>";
		print "<table class=\"table-noborders\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">";
		print "<tr><td>&nbsp;</td><td><input type=\"text\" name=\"PDFTemplateMarginTop\" size=\"6\" maxlength=\"4\" value=\"" . $this -> a2p_AdminOptions[ 'PDFTemplateMarginTop' ] . "\"/></td><td>&nbsp;</td></tr>";
		print "<tr><td><input type=\"text\" name=\"PDFTemplateMarginLeft\" size=\"6\" maxlength=\"4\" value=\"" . $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] . "\"/></td><td>&nbsp;</td><td><input type=\"text\" name=\"PDFTemplateMarginRight\" size=\"6\" maxlength=\"4\" value=\"" . $this -> a2p_AdminOptions[ 'PDFTemplateMarginRight' ] . "\"/></td></tr>";
		print "<tr><td>&nbsp;</td><td><input type=\"text\" name=\"PDFTemplateMarginBottom\" size=\"6\" maxlength=\"4\" value=\"" . $this -> a2p_AdminOptions[ 'PDFTemplateMarginBottom' ] . "\"/></td><td>&nbsp;</td></tr>";
		print "</table>";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionIncludePics: select true or false
		print "<h4>" . __( "Include pictures into the pdf file?", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Set if pictures in the article should be included into the generated pdf file.", "article2pdf" );
		print "</td><td>";
		print "<select name=\"PDFOptionIncludePics\">\n";
			print "<option value=\"false\"" . ($this -> a2p_AdminOptions[ 'PDFOptionIncludePics' ] == 'false' ? "selected=\"selected\"" : "") . ">" . __( "No, please not", "article2pdf" ) . "</option>\n";
			print "<option value=\"true\"" . ($this -> a2p_AdminOptions[ 'PDFOptionIncludePics' ] == 'true' ? "selected=\"selected\"" : "") . ">" . __( "Yes, do that!", "article2pdf" ) . "</option>\n";
		print "</select>\n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionIncludeDate: select true or false
		print "<h4>" . __( "Output publication date before the post title?", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Set if the publication date should be printed at the beginning of the post title.", "article2pdf" );
		print "</td><td>";
		print "<select name=\"PDFOptionIncludeDate\">\n";
			print "<option value=\"false\"" . ($this -> a2p_AdminOptions[ 'PDFOptionIncludeDate' ] == 'false' ? "selected=\"selected\"" : "") . ">" . __( "No, please not", "article2pdf" ) . "</option>\n";
			print "<option value=\"true\"" . ($this -> a2p_AdminOptions[ 'PDFOptionIncludeDate' ] == 'true' ? "selected=\"selected\"" : "") . ">" . __( "Yes, do that!", "article2pdf" ) . "</option>\n";
		print "</select>\n";
		print "</td></tr>\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Output the publication date on pages also?", "article2pdf" );
		print "</td><td>";
		print "<select name=\"PDFOptionIncludeDateOnPages\">\n";
			print "<option value=\"false\"" . ($this -> a2p_AdminOptions[ 'PDFOptionIncludeDateOnPages' ] == 'false' ? "selected=\"selected\"" : "") . ">" . __( "No, only posts get a date!", "article2pdf" ) . "</option>\n";
			print "<option value=\"true\"" . ($this -> a2p_AdminOptions[ 'PDFOptionIncludeDateOnPages' ] == 'true' ? "selected=\"selected\"" : "") . ">" . __( "Yes, pages get a date also!", "article2pdf" ) . "</option>\n";
		print "</select>\n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionDateformat: set date format
		print "<h4>" . __( "Use date format", "article2pdf" ) . "</h4>\n";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Define the date format used for printing out the date. Read http://php.net/strftime if you are uncertain what to enter.", "article2pdf" );
		print "</td><td>";
		print "<input type=\"text\" name=\"PDFOptionDateformat\" size=\"30\" maxlength=\"40\" value=\"" . $this -> a2p_AdminOptions[ 'PDFOptionDateformat' ] . "\"/> \n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionDateLocale: set date locale
		print "<h4>" . __( "Use date locale", "article2pdf" ) . "</h4>\n";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Define the date format locale information, eg: de_DE for Germany, nl_NL for netherlands, en_US, for the States and so on.", "article2pdf" );
		print "</td><td>";
		print "<input type=\"text\" name=\"PDFOptionDateLocale\" size=\"8\" maxlength=\"10\" value=\"" . $this -> a2p_AdminOptions[ 'PDFOptionDateLocale' ] . "\"/> \n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionFont: set font to use
		print "<h4>" . __( "Use font", "article2pdf" ) . "</h4>\n";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Define the font that is used to generate text in the pdf file.", "article2pdf" );
		print "</td><td>";
		$fonts_arr = array( 'Arial', 'Courier', 'Helvetica', 'Symbol', 'Times', 'ZapfDingBats' );
		print "<select name=\"PDFOptionFont\">\n";
		foreach( $fonts_arr AS $font )
			print "<option value=\"$font\"" . ($this -> a2p_AdminOptions[ 'PDFOptionFont' ] == $font ? " selected" : "") . ">$font</option>\n";
		print "</select>\n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionFontSize: set font size
		print "<h4>" . __( "Use font size", "article2pdf" ) . "</h4>\n";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Enter the font size that should be used when writing text.", "article2pdf" );
		print "</td><td>";
		print "<input type=\"text\" name=\"PDFOptionFontSize\" size=\"3\" maxlength=\"3\" value=\"" . $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] . "\"/> \n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionLineHeight: set line height
		print "<h4>" . __( "Line height", "article2pdf" ) . "</h4>\n";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Use the given line heigth. Hint: Set it to half of the fontsize and you will get good results.", "article2pdf" );
		print "</td><td>";
		print "<input type=\"text\" name=\"PDFOptionLineHeight\" size=\"3\" maxlength=\"3\" value=\"" . $this -> a2p_AdminOptions[ 'PDFOptionLineHeight' ] . "\"/> \n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionPageCountX/Y/string
		print "<h4>" . __( "Display page count", "article2pdf" ) . "</h4>\n";
		print "<table class=\"form-table\">\n";
		print "<tr><td colspan=\"2\"><em>" . __( "If you don't want to use this feature just enter nothing and save.", "article2pdf" ) . "</em></td></tr>";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Enter the X position where the page count will be inserted (in mm, from left)", "article2pdf" );
		print "</td><td>";
		print "<input type=\"text\" name=\"PDFPageCountPosX\" size=\"5\" maxlength=\"8\" value=\"" . $this -> a2p_AdminOptions[ 'PDFPageCountPosX' ] . "\"/> \n";
		print "</td></tr>\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Enter the Y position where the page count will be inserted (in mm, from top)", "article2pdf" );
		print "</td><td>";
		print "<input type=\"text\" name=\"PDFPageCountPosY\" size=\"5\" maxlength=\"8\" value=\"" . $this -> a2p_AdminOptions[ 'PDFPageCountPosY' ] . "\"/> \n";
		print "</td></tr>\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Enter the string to be printed (%%page%% is replaced by the actual page number and %%pagestotal%% by the total count of pages, e.g.: Page %%page%% of %%pagestotal%%)", "article2pdf" );
		print "</td><td>";
		print "<input type=\"text\" name=\"PDFPageCountString\" size=\"20\" maxlength=\"128\" value=\"" . $this -> a2p_AdminOptions[ 'PDFPageCountString' ] . "\"/> \n";
		print "</td></tr>\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Enter the font size of the string", "article2pdf" );
		print "</td><td>";
		print "<input type=\"text\" name=\"PDFPageCountFontSize\" size=\"3\" maxlength=\"3\" value=\"" . $this -> a2p_AdminOptions[ 'PDFPageCountFontSize' ] . "\"/> \n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionCacheTime: set time to cache pdf file before recreation, or zero too turn off
		print "<h4>" . __( "Cache options", "article2pdf" ) . "</h4>\n";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Enter the absolute path to the directory where the cache files were stored. Only modify if you got problems with the default value (try '/tmp/' if nothing works). Important: The directory MUST be writeable by the webserver. The last character MUST be a slash.", "article2pdf" );
		print "</td><td><input type=\"text\" name=\"PDFOptionCachePath\" size=\"50\" maxlength=\"400\" value=\"" . $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . "\"/></td></tr>\n";
		if( is_writeable( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
		{
			print "<tr valign=\"top\"><td width=\"50%\">";
			print __( "Enter the time in seconds that pdf files are delivered from cache before they are recreated. Enter 0 to deactivate caching.", "article2pdf" );
			print "</td><td>";
			print "<input type=\"text\" name=\"PDFOptionCacheTime\" size=\"8\" maxlength=\"16\" value=\"" . $this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ] . "\"/><br/>\n";
			print __( "Popular values: 3600 = 1 hour, 21600 = 6 hours, 86400 = 1 day", "article2pdf" );
			print "</td></tr>\n";
		}
		else
		{
			print "<tr valign=\"top\"><td colspan=\"2\" style=\"color:red\">";
			print __( "Sorry, the cache directory is not writeable. Please chmod the directory to a permission that allows the webserver to write into that directory.", "article2pdf" );
			print "</td></tr>\n";
			print "<input type=\"hidden\" name=\"PDFOptionCacheTime\" value=\"0\"/>\n";
		}
		print "</table>\n";
		// PDFOptionDenySearchengines: select true or false
		print "<h4>" . __( "Deny generated pdf files to search enging spiders", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "If you don't want search engine spiders to crawl your pdf documents activate the following option. The plugin will send a '410 GONE' response to the crawlers.", "article2pdf" );
		print "</td><td>";
		print "<select name=\"PDFOptionDenySearchengines\">\n";
			print "<option value=\"false\"" . ($this -> a2p_AdminOptions[ 'PDFOptionDenySearchengines' ] == 'false' ? "selected=\"selected\"" : "") . ">" . __( "No, please not", "article2pdf" ) . "</option>\n";
			print "<option value=\"true\"" . ($this -> a2p_AdminOptions[ 'PDFOptionDenySearchengines' ] == 'true' ? "selected=\"selected\"" : "") . ">" . __( "Yes, do that!", "article2pdf" ) . "</option>\n";
		print "</select>\n";
		print "</td></tr>\n";
		print "</table>\n";
		// PDFOptionRedirectMethod: select HTTP or JS
		print "<h4>" . __( "PDF download redirect method", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td width=\"50%\">";
		print __( "Please select how the user get redirected to the plugin file that sends the PDF as download to the browser. 99% can select HTTP here, but if you got problems select JavaScript.", "article2pdf" );
		print "</td><td>";
		print "<select name=\"PDFOptionRedirectMethod\">\n";
			print "<option value=\"HTTP\"" . ($this -> a2p_AdminOptions[ 'PDFOptionRedirectMethod' ] == 'HTTP' ? "selected=\"selected\"" : "") . ">" . __( "HTTP", "article2pdf" ) . "</option>\n";
			print "<option value=\"JS\"" . ($this -> a2p_AdminOptions[ 'PDFOptionRedirectMethod' ] == 'JS' ? "selected=\"selected\"" : "") . ">" . __( "JavaScript", "article2pdf" ) . "</option>\n";
		print "</select>\n";
		print "</td></tr>\n";
		// PDFOptionTmpPath: select path for tempfile creation
		print "<tr><td width=\"50%\">";
		print __( "This path is used to temporarily store the pdf file before sending it to the user. If there are problems set the same path as the cache path here. Or set any path that is writeable to the webserver.", "article2pdf" );
		print "</td><td><input type=\"text\" name=\"PDFOptionTmpPath\" size=\"50\" maxlength=\"400\" value=\"" . $this -> a2p_AdminOptions[ 'PDFOptionTmpPath' ] . "\"/></td></tr>\n";
		if( !is_writeable( $this -> a2p_AdminOptions[ 'PDFOptionTmpPath' ] ) )
		{
			print "<tr valign=\"top\"><td colspan=\"2\" style=\"color:red\">";
			print __( "Sorry, the temp file directory is not writeable. Please chmod the directory to a permission that allows the webserver to write into that directory or use another directory.", "article2pdf" );
			print "</td></tr>\n";
		}
		print "</table>\n";
		// PDFOptionAutoAddLinkText: automatically add link to post/page bottom
		print "<h4>" . __( "PDF link theme integration", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr><td width=\"50%\">";
		print __( "If you want that a link for downloading a page or post as a pdf file ist automatically added at the bottom of the content, please enter the link text here (e.g.: PDF version). If you want to integrate the link manually in your theme (like described in the installation notes and the FAQ) leave this blank.", "article2pdf" );
		print "</td><td><input type=\"text\" name=\"PDFOptionAutoAddLinkText\" size=\"30\" maxlength=\"200\" value=\"" . $this -> a2p_AdminOptions[ 'PDFOptionAutoAddLinkText' ] . "\"/></td></tr>\n";
		print "</table>\n";
		// Submit options
		print "<div class=\"submit\"><input type=\"submit\" name=\"update_a2pAdminOptions\" value=\"" . __( "Update options", "article2pdf" ) . "\"/></div>\n";
		print "</form>\n";
		print "</div>\n";
		print "</div>\n";
		print "</div>\n";

		// Output template uploadform
		print "<div id=\"poststuff\">\n";
		print "<div class=\"postbox\">\n";
		print "<form name=\"a2pAdminPage\" method=\"POST\" action=\"" . $_SERVER[ "REQUEST_URI" ] . "\" enctype=\"multipart/form-data\">\n";
		print "<input type=\"hidden\" name=\"a2p_admin_action\" value=\"upload\"/>\n";
		print "<h3>" . __( "Upload a new pdf template file", "article2pdf" ) . "</h3>\n";
		print "<div class=\"inside\">\n";
		print "<h4>" . __( "Choose pdf template file from your local disk", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td>";
		print "<input type=\"FILE\" name=\"templatefile\" size=\"50\"/>";
		print "</td></tr>\n";
		print "</table>\n";
		print str_replace( '/wp-content/plugins/article2pdf/contributed/pdftemplates', $this -> a2p_AdminOptions[ 'PDFTemplatePath' ], "<p>" . __( "Important: The directory /wp-content/plugins/article2pdf/contributed/pdftemplates must be writeable for your webserver, or the upload will fail. If you have problems, set the owner and group of the directory to the user and group of your webserver (chown www-user.www-group for example).", "article2pdf" ) ) . "</p>";
		print "<div class=\"submit\"><input type=\"submit\" name=\"update_a2pAdminOptions\" value=\"" . __( "Upload file", "article2pdf" ) . "\"/></div>\n";
		print "</form>\n";
		print "</div>\n";
		print "</div>\n";
		print "</div>\n";

		// Delete template form
		print "<div id=\"poststuff\">\n";
		print "<div class=\"postbox\">\n";
		print "<form name=\"a2pAdminPage\" method=\"POST\" action=\"" . $_SERVER[ "REQUEST_URI" ] . "\">\n";
		print "<input type=\"hidden\" name=\"a2p_admin_action\" value=\"delete\"/>\n";
		print "<h3>" . __( "Delete a pdf template file", "article2pdf" ) . "</h3>\n";
		print "<div class=\"inside\">\n";
		print "<h4>" . __( "Choose template file and submit the form", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td>";
		print str_replace( 'selected="selected"', '', $sTpl );
		print "</td></tr>\n";
		print "</table>\n";
		print "<p>" . __( "Important: Do not choose the template file that is currently in use. The file will not be deleted if you do.", "article2pdf" ) . "</p>";
		print "<div class=\"submit\"><input type=\"submit\" name=\"update_a2pAdminOptions\" value=\"" . __( "Delete file", "article2pdf" ) . "\"/></div>\n";
		print "</form>\n";
		print "</div>\n";
		print "</div>\n";
		print "</div>\n";

		// Cache status
		if( !empty( $_GET[ 'cache_sort_by' ] ) )
			$open = 4;
		print "<div id=\"poststuff\">\n";
		print "<div class=\"postbox\">\n";
		print "<form name=\"a2pAdminPage\" method=\"POST\" action=\"" . $_SERVER[ "REQUEST_URI" ] . "\">\n";
		print "<input type=\"hidden\" name=\"a2p_admin_action\" value=\"cache_delete\"/>\n";
		print "<h3>" . __( "Cache status", "article2pdf" ) . "</h3>\n";
		print "<div class=\"inside\">\n";
		print "<h4>" . __( "List of cached files (red: expired, green: pdf is delivered from cache)", "article2pdf" ) . "</h4>";
		print "<table class=\"form-table\">\n";
		print "<tr valign=\"top\"><td>";
		print __( "Sort by:", "article2pdf" ) . " [<a href=\"" . $_SERVER[ "REQUEST_URI" ] . "&cache_sort_by=state\">" . __( "state", "article2pdf" ) . "</a>] [<a href=\"" . $_SERVER[ "REQUEST_URI" ] . "&cache_sort_by=name\">" . __( "name", "article2pdf" ) . "</a>]<br/><br/>";
		// Display cache files
		$cache_counter_green = 0;
		$cache_counter_red = 0;
		$cache_counter = 0;
		if( !empty( $this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ] ) )
		{
			if( is_writeable( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
			{
				if( $d = opendir( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
				{
					$cache_content = array();
					while( false !== ($cf = readdir( $d ) ) )
					{
						$fSuffix_Arr = explode( ".", $cf );
						if( end( $fSuffix_Arr ) == 'pdf' && $fSuffix_Arr[ 0 ] == 'a2p' && $fSuffix_Arr[ 1 ] == 'cache' )
						{
							if( filemtime( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cf ) < (time() - $this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ]) )
							{
								$cache_counter_red++;
								$col = 'red';
							}
							else
							{
								$cache_counter_green++;
								$col = 'green';
							}
							$cache_content[ $cf ] = $col;
						}
					}
					$cache_counter = $cache_counter_green + $cache_counter_red;
					if( $_GET[ 'cache_sort_by' ] == 'name' || empty( $_GET[ 'cache_sort_by' ] ) )
						ksort( $cache_content );
					else
						arsort( $cache_content );
					foreach( $cache_content AS $cf => $col )
					{
						echo "<a style=\"color:$col\" href=\"" . $_SERVER[ "REQUEST_URI" ] . "&a2p_admin_action=getcachefile&cachefile=$cf\">$cf</a> (" . date( "d.m.y H:i:s", filemtime( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $cf ) ) . ") [<a style=\"color:$col\" href=\"" . $_SERVER[ "REQUEST_URI" ] . "&a2p_admin_action=deletecachefile&cachefile=$cf\" onclick=\"return confirm( '" . __( "Really delete?", "article2pdf" ) . "');\">" . __( "delete", "article2pdf" ) . "</a>]<br/>\n";
					}
				}
				if( !$cache_counter )
					echo __( "Sorry, no files are cached at the moment.", "article2pdf" );
			}
			else	print __( "Sorry, the cache directory is not writeable. Please chmod the directory to a permission that allows the webserver to write into that directory.", "article2pdf" );
		}
		else	echo __( "Caching is disabled.", "article2pdf" );
		print "</td></tr>\n";
		print "</table>\n";
		if( $cache_counter )
		{
			echo "<strong>";
			echo "<span style=\"color:green\">$cache_counter_green</span> " . __( "files are cached.", "article2pdf" ) . " ";
			echo "<span style=\"color:red\">$cache_counter_red</span> " . __( "files are cached and outdated.", "article2pdf" ) . "<br/><br/>";
			echo "$cache_counter " . __( "files are cached total.", "article2pdf" );
			echo "</strong>";
		}
		print "<div class=\"submit\"><input type=\"submit\" name=\"update_a2pAdminOptions\" value=\"" . __( "Delete all cache files and force recreation", "article2pdf" ) . "\"/> <input type=\"button\" onclick=\"window.document.location.href='" . $_SERVER[ "REQUEST_URI" ] . "&a2p_admin_action=cache_delete_expired';\" value=\"" . __( "Delete all expired cache files", "article2pdf" ) . "\"/></div>\n";
		print "</form>\n";
		print "</div>\n";
		print "</div>\n";
		print "</div>\n";

		// Test PDF creation
		print "<div id=\"poststuff\">\n";
		print "<div class=\"postbox\">\n";
		print "<form name=\"a2pAdminPage\" method=\"POST\" action=\"" . $_SERVER[ "REQUEST_URI" ] . "\">\n";
		print "<input type=\"hidden\" name=\"a2p_admin_action\" value=\"create_test_pdf\"/>\n";
		print "<h3>" . __( "PDF creation testsuite", "article2pdf" ) . "</h3>\n";
		print "<div class=\"inside\" style=\"line-height:160%;font-size:1em;\">\n";
		print "<p>" . __( "Copy and paste the post html from your blog into the field and submit the form.", "article2pdf" ) . "</p>";
		print "<p><textarea name=\"testhtml\" style=\"width:95%;height:480px\" wrap=\"off\">" . stripslashes( $_POST[ 'testhtml' ] ) . "</textarea>";
		print "<p><input type=\"checkbox\" name=\"debug_pdf_file\" value=\"1\"/> " . __( "Don't send PDF for download. Just display the pdf source and possible failures for debugging.", "article2pdf" ) . "</p>";
		print "<div class=\"submit\"><input type=\"submit\" name=\"testpdf\" value=\"" . __( "Create test pdf file", "article2pdf" ) . "\"/></div>\n";
		print "</form>\n";
		print "</div>";
		print "</div>\n";
		print "</div>\n";

		// Donate link and support informations
		print "<div id=\"poststuff\">\n";
		print "<div class=\"postbox\">\n";
		print "<h3>" . __( "Donate &amp; support", "article2pdf" ) . "</h3>\n";
		print "<div class=\"inside\" style=\"line-height:160%;font-size:1em;\">\n";
		print __( "Please", "article2pdf" ) . " <a href=\"https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=m_schieferdecker%40hotmail%2ecom&item_name=article2pdf%20wp%20plugin&no_shipping=0&no_note=1&tax=0&currency_code=EUR&bn=PP%2dDonationsBF&charset=UTF%2d8\">" . __( "DONATE", "article2pdf" ) . "</a> " . __( "if you like this plugin.", "article2pdf" ) . "<br/>";
		print __( "Many thanks to Oliver for the great", "article2pdf" ) . " <a href=\"http://www.fpdf.org\">" . __( "FPDF library", "article2pdf" ) . "</a>.<br/>";
		print "<br/>" . __( "If you need support, want to report bugs or suggestions, drop me an ", "article2pdf" ) . " <a href=\"mailto:m_schieferdecker@hotmail.com\">" . __( "email", "article2pdf" ) . "</a> " . __( "or visit the", "article2pdf" ) . " <a href=\"http://www.das-motorrad-blog.de/meine-wordpress-plugins\">" . __( "plugin homepage", "article2pdf" ) . "</a>.<br/>";
		print "<br/>" . __( "Translations: ", "article2pdf" ) . " Alejandro Urrutia Daglio (Español), Marc Schieferdecker (Deutsch), Robert @ egt-design.com (Română)<br/>";
		print "<br/>" . __( "And this persons I thank for a donation:", "article2pdf" ) . " Marcus Hochstadt, Frank Becker<br/>";
		print "<br/>" . __( "Final statements: Code is poetry. Motorcycles are cooler than cars.", "article2pdf" );
		print "</div>";
		print "</div>\n";
		print "</div>\n";

		// Close container
		print "</div>\n";

		// Nice display
		if( version_compare( substr($wp_version, 0, 3), '2.6', '<' ) )
		{
?>
		<script type="text/javascript">
		//<!--
			var a2p_openPanel = <?php print $open; ?>;
			var a2p_PanelCounter = 1;
			jQuery('.postbox h3').prepend('<a class="togbox">+</a> ');
			jQuery('.postbox h3').click( function() { jQuery(jQuery(this).parent().get(0)).toggleClass('closed'); } );
			jQuery('.postbox h3').each(function() {
				if( (a2p_PanelCounter++) != a2p_openPanel )
					jQuery(jQuery(this).parent().get(0)).toggleClass('closed');
			});
		//-->
		</script>
		<style type="text/css">
			h4 {
				margin-bottom:0em;
			}
		</style>
<?php
		}
	}

	/*
	 * Misc functions
	 */
	// Decode utf8 chars and replace special utf-8 chars to iso-8859-1 representation
	function _decode_utf( $str )
	{
		$str = str_replace( array( '“', '”', '„' ), '"', $str );
		$str = str_replace( array( "’", "‚" ), "'", $str );
		$str = str_replace( '…', '...', $str );
		$str = str_replace( '€', 'EUR', $str );
		$str = str_replace( '↩', '<-', $str );
		return utf8_decode( $str );
	}

	// Check user-agent for crawlers
	function _is_bot()
	{
		$bot_array = array(	'abachobot', 'abcdatos_botlink', 'spider', 'ah-ha.com', 'ia_archiver', 'scooter', 'mercator', 'roach.smo.av.com',
					'merc_resh', 'altavista', 'crawler', 'wget', 'acoon robot ', 'antibot', 'atomz', 'axmorobot', 'buscaplus robi',
					'canseek', 'clushbot', 'daadle.com', 'rabot ', 'deepindex ', 'dittospyder', 'jack', 'earthcom', 'euripbot',
					'arachnoidea ', 'ezresult', 'fast data search', 'fireball', 'fybersearch ', 'galaxybot', 'geckobot', 'geonabot ',
					'getrax', 'googlebot', 'moget', 'aranha', 'slurp', 'toutatis', 'hubater ', 'iltrovatore-setaccio ', 'incywincy',
					'ultraseek', 'infoseek', 'mole2', 'mp3bot', 'ipselonbot ', 'knowledge.com', 'kuloko-bot', 'lapozzbot',
					'linknzbot', 'lookbot ', 'mantraagent', 'netresearchserver', 'joocerbot', 'henrythemiragorobot', 'mojeekbot', 'mozdex',
					'msnbot', 'gulliver', 'objectssearch', 'onetszukaj', 'picosearch', 'diibot', 'privacyfinder', 'nttdirectory_robot',
					'griffon', 'gazz', 'mousebot', 'dloader', 'dumrobo', 'noxtrumbot', 'openfind', 'openbot', 'psbot', '.ip3000.com',
					'qweerybot', 'alkalinebot', 'stackrambler', 'seznambot', 'search-10 ', 'scrubby', 'asterias', 'speedfind', 'kototoi',
					'searchbyusa', 'sightquestbot', 'teoma_agent1 ', 'teradex_mapper ', 'vivante link checker ', 'appie', 'nazilla ',
					'wombat', 'marvin', 'muscatferret', 'whizbang', 'zyborg', 'webrefiner', 'wscbot ', 'yandex', 'ybsbot',
					'libwww-perl', 'iron33' );
		$ua = strtolower( $_SERVER[ 'HTTP_USER_AGENT' ] );
		$is_bot = false;
		if( !empty( $ua ) )
		{
			foreach( $bot_array AS $chk_ua )
			{
				$is_bot = (strpos( $ua, $chk_ua ) !== false ? true : false);
				if( $is_bot )
					break;
			}
		}
		unset( $bot_array );	// Remove from memory, no longer needed
		return $is_bot;
	}

	/*
	 * PDF function helpers
	 */
	// Parse tables
	function _convert_tables( $p )
	{
		preg_match_all( '#(<table.*>)(.*)(</table>)#siU', $p, $tables );
		if( count( $tables[ 2 ] ) )
		foreach( $tables[ 2 ] AS $tkey => $tcontent )
		{
			$tcontent = str_replace( "\n", '', $tcontent );
			$tcontent = preg_replace( '#(<thead.*>)|(<tbody.*>)|(</thead.*>)|(</tbody.*>)#siU', '', $tcontent );
			$p_new = '';
			preg_match_all( '#(<tr.*>)(.*)(</tr>)#siU', $tcontent, $rows );
			foreach( $rows[ 2 ] AS $rkey => $row )
			{
				preg_match_all( '#(<th.*>|<td.*>)(.*)(</th>|</td>)#siU', $row, $cells );
				$p_new .= '|-====-|TR|' . count( $cells[ 2 ] );
				foreach( $cells[ 2 ] AS $ckey => $ccontent )
				{
					$cstart = $cells[ 1 ][ $ckey ];
					$cend = $cells[ 3 ][ $ckey ];
					if( strpos( strtolower( $cstart ), '<th' ) !== false )
						$p_new .= '|-====-|TH|' . strip_tags( $ccontent ) . '|-====-|/TH|';
					else
						$p_new .= '|-====-|TD|' . strip_tags( $ccontent ) . '|-====-|/TD|';
				}
				$p_new .= '|-====-|/TR|';
			}
			$p = str_replace( $tables[ 0 ], $p_new, $p );
		}
		return $p;
	}

	// Parse code and pre tags
	function _convert_code( $p )
	{
		return str_replace( array( "<code>", "<pre>", "</code>", "</pre>" ), array( "|-====-|CODE|", "|-====-|CODE|", "|-====-|/CODE|", "|-====-|/CODE|" ), $p );
	}

	// Parse links
	function _convert_links( $p )
	{
		preg_match_all( '#<a.*href="(.*)".*>(.*)</a>#siU', $p, $matches );
		$is_converted = false;
		$p_new = '';
		if( count( $matches[ 1 ] ) )
		foreach( $matches[ 1 ] AS $mkey => $href )
		{
			$linkhtml = $matches[ 0 ][ $mkey ];
			$linkcontent = $matches[ 2 ][ $mkey ];
			if( strpos( $href, '#' ) !== 0 && !empty( $linkcontent ) )
			{
				$p_first = substr( $p, 0, strpos( $p, $linkhtml ) );
				$p_last = substr( $p, (strpos( $p, $linkhtml ) + strlen( $linkhtml )) );

				$p_new .= $p_first;
				$p_new .= "|-====-|LINK|$href|$linkcontent|-====-|/LINK|";
				$p_new .= $p_middle2;

				$p = preg_replace( '#^.*' . preg_quote( $linkhtml, '#' ) . '#sU', '', $p );
				$is_converted = true;
			}
		}
		if( $is_converted )
		{
			$p_new .= $p_last;
			return $p_new;
		}
		else	return $p;
	}

	// Parse bold
	function _convert_bold( $p )
	{
		return str_replace( array( "<b>", "<strong>", "</b>", "</strong>" ), array( "|-====-|B|", "|-====-|B|", "|-====-|/B|", "|-====-|/B|" ), $p );
	}

	// Parse underline
	function _convert_underline( $p )
	{
		return str_replace( array( "<u>", "</u>" ), array( "|-====-|U|", "|-====-|/U|" ), $p );
	}

	// Parse italic
	function _convert_italic( $p )
	{
		return str_replace( array( "<i>", "<em>", "</i>", "</em>" ), array( "|-====-|I|", "|-====-|I|", "|-====-|/I|", "|-====-|/I|" ), $p );
	}

	// Parse blockquote
	function _convert_blockquote( $p )
	{
		return str_replace( array( "<blockquote>", "</blockquote>" ), array( "|-====-|Q|", "|-====-|/Q|" ), $p );
	}

	// Parse list items
	function _convert_listitem( $p )
	{
		$p = str_replace( array( "<li>", "</li>", "<ul>", "</ul>", "<ol>", "</ol>" ), array( "|-====-|LI|", "|-====-|/LI|", "|-====-|UL|", "|-====-|/UL|", "|-====-|OL|", "|-====-|/OL|" ), $p );
		return $p;
	}

	// Parse headings
	function _convert_heading( $p )
	{
		preg_match_all( '/<h([1-9])>/si', $p, $matches );
		foreach( $matches[ 1 ] AS $h )
			$p = str_replace( array( "<h$h>", "</h$h>" ), array( "|-====-|H$h|", "|-====-|/H$h|" ), $p );
		return $p;
	}

	// Parse horizontal lines
	function _convert_lines( $p )
	{
		return preg_replace( '#<hr.*>#iU', '|-====-|__|', $p );
	}

	// Set font style
	function _font_normal( &$pdf )
	{
		$pdf -> SetFont( $this -> a2p_AdminOptions[ 'PDFOptionFont' ], '', $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] );
	}

	function _font_underline( &$pdf )
	{
		$pdf -> SetFont( $this -> a2p_AdminOptions[ 'PDFOptionFont' ], 'U', $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] );
	}

	function _font_bold( &$pdf )
	{
		$pdf -> SetFont( $this -> a2p_AdminOptions[ 'PDFOptionFont' ], 'B', $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] );
	}

	function _font_italic( &$pdf )
	{
		$pdf -> SetFont( $this -> a2p_AdminOptions[ 'PDFOptionFont' ], 'I', $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] );
	}

	function _font_code( &$pdf )
	{
		$pdf -> SetFont( 'Courier', '', $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] );
	}

	function _font_heading( &$pdf, $hlevel = 2 )
	{
		$heading_font_size = $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] + (($this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] / 2) / $hlevel);
		$pdf -> SetFont( $this -> a2p_AdminOptions[ 'PDFOptionFont' ], 'B', $heading_font_size );
	}

	// Set margins
	function _margin_normal( &$pdf )
	{
		$pdf -> SetLeftMargin( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] );
	}

	function _margin_quote( &$pdf )
	{
		$pdf -> SetLeftMargin( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] * 2 );
		$pdf -> SetX( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ] * 2 );
	}

	// Add page to pdf
	function _add_page( &$pdf )
	{
		$pdf -> AddPage();
		$this -> _add_page_count( $pdf );
		// Set to start of
		$pdf -> SetXY( $this -> a2p_AdminOptions[ 'PDFTemplateMarginLeft' ], $this -> a2p_AdminOptions[ 'PDFTemplateMarginRight' ] );
	}

	// Write page count
	function _add_page_count( &$pdf )
	{
		if( !empty( $this -> a2p_AdminOptions[ 'PDFPageCountString' ] ) && !empty( $this -> a2p_AdminOptions[ 'PDFPageCountPosX' ] ) && !empty( $this -> a2p_AdminOptions[ 'PDFPageCountPosY' ] ) && !empty( $this -> a2p_AdminOptions[ 'PDFPageCountFontSize' ] ) )
		{
			$this -> _page_counter++;
			$page_str = str_replace( '%%page%%', $this -> _page_counter, $this -> a2p_AdminOptions[ 'PDFPageCountString' ] );
			$x = $pdf -> GetX();
			$y = $pdf -> GetY();
			$lMargin = $pdf -> lMargin;
			$tMargin = $pdf -> tMargin;
			$pdf -> SetLeftMargin( 0 );
			$pdf -> SetTopMargin( 0 );
			$pdf -> SetAutoPageBreak( false, 0 );
			$pdf -> SetXY( $this -> a2p_AdminOptions[ 'PDFPageCountPosX' ], $this -> a2p_AdminOptions[ 'PDFPageCountPosY' ] );
			$pdf -> SetFont( $this -> a2p_AdminOptions[ 'PDFOptionFont' ], '', $this -> a2p_AdminOptions[ 'PDFPageCountFontSize' ] );
			$pdf -> Write( 0, $page_str );
			$pdf -> SetLeftMargin( $lMargin );
			$pdf -> SetTopMargin( $tMargin );
			$pdf -> SetAutoPageBreak( true, $this -> a2p_AdminOptions[ 'PDFTemplateMarginBottom' ] );
			$pdf -> SetFont( $this -> a2p_AdminOptions[ 'PDFOptionFont' ], '', $this -> a2p_AdminOptions[ 'PDFOptionFontSize' ] );
			$pdf -> SetXY( $x, $y );
		}
	}

	/*
	 * Cache functions
	 */
	// Cache: Get cachefile path
	function _cache_get_filename( &$post )
	{
		$fname = 'a2p.cache.' . $post -> post_name . '.pdf';
		return $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . $fname;
	}

	// Cache: recreate pdf or deliver cached version
	function _cache_recreate( $cachefile )
	{
		if( file_exists( $cachefile ) && !empty( $this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ] ) && is_writeable( $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] ) )
		{
			if( filemtime( $cachefile ) < (time() - $this -> a2p_AdminOptions[ 'PDFOptionCacheTime' ]) )
				return true;
			else
				return false;
		}
		else	return true;
	}

	// Cache: remove by post ID
	function _cache_remove_by_postid( $post_or_page_ID )
	{
		$page_ids = get_all_page_ids();
		if( !in_array( $post_or_page_ID, $page_ids ) )
			$pdfpost = get_post( $post_or_page_ID );
		else
			$pdfpost = get_page( $post_or_page_ID );
		unset( $page_ids );
		$fname = $this -> a2p_AdminOptions[ 'PDFOptionCachePath' ] . 'a2p.cache.' . $pdfpost -> post_name . '.pdf';
		if( file_exists( $fname ) )
			unlink( $fname );
	}

	/*
	 * OB functions
	 */
	// Kill ALL Buffers!
	function _ob_end_clean()
	{
		$ob_level = ob_get_level();
		while( $ob_level > 0 )
		{
			ob_end_clean();
			$ob_level--;
		}
	}
}

// Create class instance
$a2p_Plugin = new article2pdf();

/**
 * Misc hooks
 */
// Add action: Install - create default options
add_action( 'activate_article2pdf/article2pdf.php', array( &$a2p_Plugin, 'a2p_GetAdminOptions' ) );


/**
 * Admin hooks
 */
if( strpos( $_SERVER[ 'REQUEST_URI' ], 'wp-admin' ) )
{
	// Admin wrapper
	function wrapper_a2p_AdminPage()
	{
		global $a2p_Plugin;
		add_options_page( 'article2pdf Options', 'article2pdf', 9, basename(__FILE__), array( &$a2p_Plugin, 'a2p_AdminPage' ) );
	}
	// Add action: Admin page
	add_action( 'admin_menu', 'wrapper_a2p_AdminPage' );
}


/**
 * Init hooks
 */
// Store page content in buffer if plugin is active, because of header redirection
if( $a2p_Plugin -> a2p_AdminOptions[ 'PDFOptionRedirectMethod' ] == 'HTTP' || strpos( $_SERVER[ 'REQUEST_URI' ], 'a2p_admin_action=getcachefile' ) || $_POST[ 'a2p_admin_action' ] == 'create_test_pdf' )
{
	function a2p_init()
	{
		ob_start();
	}
	// With this I drop rendered content (ob_end_clean is called by a2p_CreatePdf)
	if( strpos( $_SERVER[ 'REQUEST_URI' ], 'article2pdf=' ) || strpos( $_SERVER[ 'REQUEST_URI' ], 'a2p_admin_action=getcachefile' ) || $_POST[ 'a2p_admin_action' ] == 'create_test_pdf' )
		add_action( 'plugins_loaded', 'a2p_init' );
}


/**
 * PDF creation hooks
 */
// PDF creation wrapper
function wrapper_a2p_CreatePdf( $content )
{
	global $a2p_Plugin;
	return $a2p_Plugin -> a2p_CreatePdf( $content );
}
// Generate PDF from content after all shortcodes and filters are done
if( strpos( $_SERVER[ 'REQUEST_URI' ], 'article2pdf=' ) )
	add_filter( 'the_content', 'wrapper_a2p_CreatePdf', 9999 );
// Auto add pdf link if configured and we are on a single post or page
if( !empty( $a2p_Plugin -> a2p_AdminOptions[ 'PDFOptionAutoAddLinkText' ] ) )
{
	function a2p_auto_add_pdf_link( $the_content )
	{
		if( is_single() || is_page() )
		{
			global $a2p_Plugin;
			$posturl = get_permalink();
			if( strpos( $post_url, '?' ) !== false )
				$pdflink = $posturl . '&article2pdf=1';
			else
				$pdflink = $posturl . (substr( $_SERVER[ 'REQUEST_URI' ], -1 ) == '/' ? '?article2pdf=1' : '/?article2pdf=1');
			$the_content .= "<div class=\"article2pdf_link\"><a href=\"$pdflink\">" . $a2p_Plugin -> a2p_AdminOptions[ 'PDFOptionAutoAddLinkText' ] . "</a></div>";
		}
		return $the_content;
	}
	add_filter( 'the_content', 'a2p_auto_add_pdf_link', 10000 );
}

/**
 * Force recaching hooks
 */
if( !empty( $a2p_Plugin -> a2p_AdminOptions[ 'PDFOptionCacheTime' ] ) )
{
	// Post/Page hooks
	add_action( 'edit_post', array( &$a2p_Plugin, '_cache_remove_by_postid' ) );
	add_action( 'delete_post', array( &$a2p_Plugin, '_cache_remove_by_postid' ) );
}

?>