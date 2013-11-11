<?php
	//Atkore Template

	global $post;
	global $pdf_output;
	global $pdf_header;
	global $pdf_footer;

	global $pdf_template_pdfpage;
	global $pdf_template_pdfpage_page;
	global $pdf_template_pdfdoc;

	global $pdf_html_header;
	global $pdf_html_footer;

	//Set a pdf template. if both are set the pdfdoc is used. (You didn't need a pdf template)
	$pdf_template_pdfpage 		= ''; //The filename off the pdf file (you need this for a page template)
	$pdf_template_pdfpage_page 	= 1;  //The page off this page (you need this for a page template)

	$pdf_template_pdfdoc  		= ''; //The filename off the complete pdf document (you need only this for a document template)

	$pdf_html_header 			= true; //If this is ture you can write instead of the array a html string on the var $pdf_header
	$pdf_html_footer 			= true; //If this is ture you can write instead of the array a html string on the var $pdf_footer

	//Set the Footer and the Header
	$pdf_header = array ();
	$pdf_footer = array ();


	$pdf_output = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
		<html xml:lang="en">

		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			<title>' . get_bloginfo() . '</title>
		</head>
		<body xml:lang="en">
			<bookmark content="'.htmlspecialchars(get_bloginfo('name'), ENT_QUOTES).'" level="0" /><tocentry content="'.htmlspecialchars(get_bloginfo('name'), ENT_QUOTES).'" level="0" />
			<div id="header"><div id="headerimg">
				<h1><a href="' . get_settings('home') . '/">' .  get_bloginfo('name') . '</a></h1>
				<div class="description">' .  get_bloginfo('description') . '</div>
			</div>
			</div>
			<div id="content" class="widecolumn">';

			if(have_posts()) :
				if(is_search()) $pdf_output .=  '<div class="post"><h2 class="pagetitle">Search Results</h2></div>';
			if(is_archive()) {
				global $wp_query;

				if(is_category()) {
					$pdf_output .= '<div class="post"><h2 class="pagetitle">Archive for the "' . single_cat_title('', false) . '" category</h2></div>';
				} elseif(is_year()) {
					$pdf_output .= '<div class="post"><h2 class="pagetitle">Archive for ' . get_the_time('Y') . '</h2></div>';
				} elseif(is_month()) {
					$pdf_output .= '<div class="post"><h2 class="pagetitle">Archive for ' . get_the_time('F, Y') . '</h2></div>';
				} elseif(is_day()) {
					$pdf_output .= '<div class="post"><h2 class="pagetitle">Archive for ' . get_the_time('F jS, Y') . '</h2></div>';
				} elseif(is_search()) {
					$pdf_output .= '<div class="post"><h2 class="pagetitle">Search Results</h2></div>';
				} elseif (is_author()) {
					$pdf_output .= '<div class="post"><h2 class="pagetitle">Author Archive</h2></div>';
				}
			}

			while (have_posts()) : the_post();

				$pdf_output .= '<bookmark content="'.the_title('','', false).'" level="1" /><tocentry content="'.the_title('','', false).'" level="1" />';
				$pdf_output .= '<div class="post">
				<h2 class="atkore-font"><a href="' . get_permalink() . '" rel="bookmark" title="Permanent Link to ' . the_title('','', false) . '">' . the_title('','', false) . '</a></h2>';

				$pdf_output .= '<div class="entry">' .	wpautop($post->post_content, true) . '</div>';

				$pdf_output .= '</div> <!-- post -->';
			endwhile;

		else :
			$pdf_output .= '<h2 class="center">Not Found</h2>
				<p class="center">Sorry, but you are looking for something that isn\'t here.</p>';
		endif;

		$pdf_output .= '</div> <!--content-->';


	$pdf_output .= '
		</body>
		</html>';
?>
