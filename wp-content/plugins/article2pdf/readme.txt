=== article2pdf ===
Contributors: Marc Schieferdecker, raufaser
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=m_schieferdecker%40hotmail%2ecom&item_name=article2pdf%20wp%20plugin&no_shipping=0&no_note=1&tax=0&currency_code=EUR&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: pdf, article, print, printing, post, page, export
Requires at least: 2.5.0
Tested up to: 2.8
Stable tag: 0.27

Article2pdf let your visitors download a post as PDF file: images, formats, lists, tables included. You can use a PDF as template file and much more.

== Description ==

Article2pdf will convert any post or page to a pdf file and send it on demand to the browser.

You can setup a pdf file, that is used as layout template for the generated pdf file and images, links, formatings, lists, tables and some more html elements are included as well. PDF Templates and PDF options can be configured with the plugin admin panel. Generated PDF files can be cached to reduce server load.

Hint: If you do SEO than configure that pdf files are not delivered to search engine spiders, because that possibly results in duplicate content.

Languages: English, Deutsch, Español, Română.

Please visit my other plugin [WP-Options-Manager](http://wordpress.org/extend/plugins/wp-options-manager/) if you are interested in keeping your WordPress options database table clean. If you play World of Warcraft, you should have a look at my [WoW Character Display](http://wordpress.org/extend/plugins/wow-character-display/) plugin.

== Installation ==

Install the plugin and activate it. After activation go to the plugin admin panel and change the configuration to your needs. _Important_: Choose correct paths for the template, cache and temp directory! The directorys MUST be writeable by the webserver, so set the correct owner and directory permissions to the dirs.
After that put the PDF creation link into your theme (or into a widget if you like), or you go to the configuration page and and enter a link text which will tell the plugin automatically add a pdf link to the bottom of a single post or page.

_Further informations on adding the PDF link to your theme:_

If you use non standard permalinks (e.g. "`/%postname%`"), add the link html code `<a href="?article2pdf=1">PDF Version</a>` to your theme. I put the html code into the "single.php" and the "page.php" of my theme and it works fine for me.<br/>
Example link: `http://my-blog.org/my-category/my-post/?article2pdf=1`

If you use the standard permalink structure (`?p=123`), add the following link to your theme: `<a href="<?php the_permalink(); ?>&amp;article2pdf=1">PDF Version</a>`<br/>
Example link: `http://my-blog.org/?p=123&article2pdf=1`

A demo can be found at <a href="http://www.das-motorrad-blog.de/meine-wordpress-plugins/">http://www.das-motorrad-blog.de/meine-wordpress-plugins/</a> - feel free to test the plugin by selecting a post or a page and then click "PDF" in the upper right corner.

Important: The link should be _inside_ the WordPress main loop. Search for something similar to that: `<?php if ($posts) : foreach ($posts as $post) : start_wp(); ?>`.
If you click your link on different posts and you always get the same pdf, then the link _is not_ inside the main loop.

== Frequently Asked Questions ==

= The link from the installation guide isn't working, why? =

The link to use depends on the WordPress permalink settings. If you use the standard permalinks (`?p=123`) you can't add a link like `?article2pdf=1` because you can't use the question mark twice in URL transmitted parameters, e.g.: `?p=123?article2pdf=1` will definitly not work! The link has to be `?p=123&article2pdf=1` because multiple URL transitted parameters are seperated by an ampersand. The question mark seperates the requested file from the transmitted options.

If you use not the standard permalink option, then the link has to be created with an question mark because your permalink is something like `/archive/2008/10/my-article/`, or simply `/my-article/`. If the link `/my-article/?article2pdf=1` is clicked, the plugin creates the pdf.

Another case is if you have an individual permalink structure and the structure set to `/%postname%`. A little detail: The trailing slash is missing. Depending on your URL rewrite parameters the pdf link doesen't work sometimes. If that is the case try a link like this: `./?article2pdf=1` or set your permalink structure to `/%postname%/` (add a trailing slash).

= The generated pdf files are empty or Acrobat shows an error message? =

In most cases the plugin produces an error and not a pdf file because you have activated caching but the cache directory is not writeable to the webserver. Set the correct user and directory permissons for the cache dir or deactivate caching.

= Some characters are converted to a "?" in the pdf, why? =

The FPDF library is not able to write UTF charsets. So I have to convert the UTF characters to ISO. If your char isn't available in the ISO charset... hm, no solution yet. But if it's replaceable by an ISO char please drop me an email and I will add that char to the replacement array.

= I edited a post, but the PDF is an old version? =

PDF files are cached. Please delete the cache file after editing a post. In the next version I will automate that.

= The plugin overwrites the pdf template files when I update the plugin to a new version? =

Didn't you read the warning message? That's the bold red text that is displayed when your pdf templates directory is inside of the article2pdf plugin directory. ;) The problem: On plugin update WordPress kills all files in the plugin directory and the reinstalls the plugin. If the pdf templates weren't stored outside the article2pdf directory they are deleted. Reconfigure the template path to something like "`/your/absolute/home/directory/wp-content/uploads/pdftemplates`" (`/your/absolute/home/directory/` is the absolute path on the server to your WordPress installation).

= What configuration options do I have? =

* You can set the right/left/top/bottom margins
* You can set the path where pdf templates are stored
* You can set a pdf file that is used as template for the generated pdf file
* You can set a pdf file that is used as template file for pages
* You can decide if pictures should be included into the pdf file (to large pictures will be resized to fit)
* You can configure if the publication date is included into the pdf file
* You can configure the outout format and locale of the publication date
* You can set the font family, the font size, and the line height
* You can activate caching of generated pdf files
* You can set the path for cache files
* You can set if search engine spiders were allowed to crawl your pdf content
* You can upload and delete pdf template files with the plugin configuration page
* You can setup pagination for your pdf files
* You can automatically let the pdf link add to your single posts and pages if you don't know hot to integrate it into your theme
* You can set much more things I have forgotten at the moment ;)

= Does article2pdf put my images into the pdf file? =

Yes, it does if you _configure_ that option in the plugins configuration page. To large images will be resized to fit on the page.

= Which HTML elements are included by the plugin? =

Currently (v0.20 or greater):

* table, tr, th, td
* img
* pre, code
* ol, ul, li
* blockquote
* strong, b
* em, i
* hr
* a href

= Sometimes links of a post weren't included into the pdf file, why? =

Check the html source of your post. If there is a line break in the html code of the link you have to fix that. The link html code has to be in one single line. I don't no why WordPress sometimes adds a line break to the link. I think its a result of the TinyMCE html editor.

= Where I have to store my pdf template files? =

You can use the upload option of the plugin configuration manager. If that fails you have to store the pdf template files in the directory you have set in the plugin admin panel. Upload the files with your favourite ftp client. After upload you can choose your pdf template on the plugin admin panel.

= What is the best value for the line height? =

Half of the font size always will be rewarded with good results.

= Will you improve this plugin? =

Yes, that's the plan. If you have feature requests drop me a mail or leave a comment in my weblog.

= I have further suggestions or problems? =

Please report in the comments of my weblog or drop me an email.

= How are the pdf files created? =

I use the great FPDF library (freeware). Have a look at <a href="http://www.fpdf.org">http://www.fpdf.org</a>.

= Why is your english so terrible? =

I'm from germany, sorry for my bad english.

== Screenshots ==

1. This screenshot shows some of the configuration options.
2. Here you see the cache file overview in the admin panel.
3. This is a generated pdf file from my personal weblog.
