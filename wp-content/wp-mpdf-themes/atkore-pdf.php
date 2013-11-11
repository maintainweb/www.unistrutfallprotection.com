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

  $attachment_id = get_field('color_logo', 'options');
  $size = "medium"; // (thumbnail, medium, large, full or custom size)
  $image = wp_get_attachment_image_src( $attachment_id, $size );
  // url = $image[0];
  // width = $image[1];
  // height = $image[2];

	//Set the Footer and the Header
	$pdf_header = '<img height="100px" src="http://unistrutfallprotection.local/assets/unistrutconstruction-logo-color-380x186.png" />';

	$pdf_footer = '<img height="100px" src="http://unistrutfallprotection.local/assets/unistrutconstruction-logo-color-380x186.png" />';

<?php
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

				$cat_links = "";
				foreach((get_the_category()) as $cat) {
					$cat_links .= '<a href="' . get_category_link($cat->term_id) . '" title="' . $cat->category_description . '">' . $cat->cat_name . '</a>, ';
				}
				$cat_links = substr($cat_links, 0, -2);


				$pdf_output .= '<bookmark content="'.the_title('','', false).'" level="1" /><tocentry content="'.the_title('','', false).'" level="1" />';
				$pdf_output .= '<div class="post">
				<h2><a href="' . get_permalink() . '" rel="bookmark" title="Permanent Link to ' . the_title('','', false) . '">' . the_title('','', false) . '</a></h2>';


				$pdf_output .= '<div class="entry">' .	'<div class="row">';

				$pdf_output .= '<div class="span3">';

        $pdf_output .= '<img src="http://unistrutfallprotection.local/assets/unistrutconstruction-logo-color-380x186.png" />';

        $pdf_output .= '</div>';

				$pdf_output .= '<div class="span9">' . wpautop($post->post_content, true) . '</div></div></div>';


				// the following is the extended metadata for a single page
				if(is_single()) {

				}


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
