<?php
/**
 * Plain-language descriptions of the widgets, for people: the ⓘ tooltip in Insert and the help next to the
 * element's name in the inspector. AI clients keep reading each widget's own description() (MCP), which is written
 * for building with it; nothing here reaches them.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Editor;

defined( 'ABSPATH' ) || exit;

final class Widget_Tips {

	/**
	 * @return array<string,string> Widget name => one or two short sentences.
	 */
	public static function all(): array {
		return array(
			'container'            => __( 'A box that holds other elements. Use it for sections, rows, columns and cards.', 'uncoder' ),
			'accordion'            => __( 'Questions or topics that open and close when clicked. Great for FAQs.', 'uncoder' ),
			'alert'                => __( 'A coloured notice box for a tip, a success message, a warning or an error.', 'uncoder' ),
			'animated-headline'    => __( 'A heading with a moving effect, like an underline that draws itself or words that change.', 'uncoder' ),
			'archive-description'  => __( 'The description of the category, tag or author being viewed. For archive templates.', 'uncoder' ),
			'archive-title'        => __( 'The name of the category, tag, author or search being viewed. For archive templates.', 'uncoder' ),
			'author-box'           => __( 'The writer of the post, with photo, name and short bio.', 'uncoder' ),
			'blockquote'           => __( 'A quote that stands out, with the name of the person who said it.', 'uncoder' ),
			'breadcrumbs'          => __( 'Shows where visitors are on your site, like Home › Blog › This post.', 'uncoder' ),
			'button'               => __( 'A button that links somewhere, like “Get a quote” or “Book now”.', 'uncoder' ),
			'call-to-action'       => __( 'A promo box with an image, a short message and one or two buttons.', 'uncoder' ),
			'carousel'             => __( 'Slides that visitors can swipe through. Each slide can hold anything.', 'uncoder' ),
			'code-highlight'       => __( 'Shows a piece of code nicely formatted, with a copy button.', 'uncoder' ),
			'countdown'            => __( 'A timer counting down to a date, like the end of a sale.', 'uncoder' ),
			'counter'              => __( 'A number that counts up when it comes into view, like “500+ happy clients”.', 'uncoder' ),
			'divider'              => __( 'A line to separate content, with an optional word or icon in the middle.', 'uncoder' ),
			'facebook-embed'       => __( 'Shows a Facebook page, post or video on your site.', 'uncoder' ),
			'featured-image'       => __( 'The main image of the post being viewed. For post templates.', 'uncoder' ),
			'flip-box'             => __( 'A card that flips over on hover to show more on the back.', 'uncoder' ),
			'form'                 => __( 'A contact or sign-up form. Messages are saved and emailed to you.', 'uncoder' ),
			'google-maps'          => __( 'A Google map showing your address or any place.', 'uncoder' ),
			'heading'              => __( 'A title or subtitle. Use one main heading (h1) per page.', 'uncoder' ),
			'hotspot'              => __( 'An image with clickable dots that show a little note.', 'uncoder' ),
			'html'                 => __( 'Paste your own HTML code, like an embed from another service.', 'uncoder' ),
			'icon'                 => __( 'A single icon, from a large icon library or your own SVG.', 'uncoder' ),
			'icon-box'             => __( 'An icon with a title and a short text. Good for features and services.', 'uncoder' ),
			'icon-list'            => __( 'A list where each line starts with an icon, like a checklist or contact details.', 'uncoder' ),
			'image'                => __( 'A picture from your media library, with an optional caption and link.', 'uncoder' ),
			'image-box'            => __( 'A picture with a title and a short text. Good for services, team or articles.', 'uncoder' ),
			'image-carousel'       => __( 'A row of pictures visitors can swipe through.', 'uncoder' ),
			'image-compare'        => __( 'Two pictures with a slider to compare them, like before and after.', 'uncoder' ),
			'image-gallery'        => __( 'A grid of pictures that open larger when clicked.', 'uncoder' ),
			'language-switcher'    => __( 'Lets visitors switch to another language of your site (WPML or Polylang).', 'uncoder' ),
			'link-in-bio'          => __( 'A complete “link in bio” page: your photo, a short bio and big link buttons.', 'uncoder' ),
			'login'                => __( 'A login form for people with an account on your site.', 'uncoder' ),
			'logo-grid'            => __( 'The logos of your clients or partners, in a grid or a scrolling row.', 'uncoder' ),
			'loop-carousel'        => __( 'Your posts as cards in a carousel, using a card design you make once.', 'uncoder' ),
			'loop-filter'          => __( 'Buttons or a search box that filter a post grid on the same page.', 'uncoder' ),
			'loop-grid'            => __( 'Your posts as cards in a grid, using a card design you make once.', 'uncoder' ),
			'lottie'               => __( 'A smooth animation from a Lottie file.', 'uncoder' ),
			'marquee'              => __( 'Text, icons or images that scroll past endlessly.', 'uncoder' ),
			'menu-anchor'          => __( 'An invisible marker that menu links can jump to on the same page.', 'uncoder' ),
			'nav-menu'             => __( 'Your site menu, with dropdowns and a mobile menu button.', 'uncoder' ),
			'off-canvas'           => __( 'A button that slides in a side panel, for a menu, a form or anything else.', 'uncoder' ),
			'post-comments'        => __( 'The comments of the post being viewed, and the form to add one.', 'uncoder' ),
			'post-content'         => __( 'The text of the post or page being viewed. For post and page templates.', 'uncoder' ),
			'post-excerpt'         => __( 'A short summary of the post being viewed.', 'uncoder' ),
			'post-info'            => __( 'Details of the post being viewed, like author, date and categories.', 'uncoder' ),
			'post-navigation'      => __( 'Links to the previous and next post.', 'uncoder' ),
			'post-title'           => __( 'The title of the post or page being viewed. For templates.', 'uncoder' ),
			'posts'                => __( 'A list or grid of your latest posts, with image, title and summary.', 'uncoder' ),
			'price-list'           => __( 'A menu-style price list, like dishes or services with their prices.', 'uncoder' ),
			'price-table'          => __( 'A pricing plan card with price, features and a button.', 'uncoder' ),
			'progress-bar'         => __( 'A bar that fills to a percentage, for skills or goals.', 'uncoder' ),
			'reading-progress'     => __( 'A thin bar at the top that fills as visitors scroll down.', 'uncoder' ),
			'scheme-switch'        => __( 'A button that lets visitors switch between light and dark mode.', 'uncoder' ),
			'search-form'          => __( 'A search box for your site, or a search icon that opens one.', 'uncoder' ),
			'share-buttons'        => __( 'Buttons to share the page on Facebook, X, LinkedIn, WhatsApp and more.', 'uncoder' ),
			'shortcode'            => __( 'Shows a shortcode from another plugin, like a booking form.', 'uncoder' ),
			'site-logo'            => __( 'Your site logo, linked to the home page. Changes when you change your logo.', 'uncoder' ),
			'site-tagline'         => __( 'Your site’s tagline, from the WordPress settings.', 'uncoder' ),
			'site-title'           => __( 'Your site’s name, linked to the home page.', 'uncoder' ),
			'sitemap'              => __( 'A list of all your pages and posts, for a sitemap page.', 'uncoder' ),
			'slides'               => __( 'A big slideshow with a picture, text and buttons on each slide.', 'uncoder' ),
			'social-icons'         => __( 'Icons that link to your social media profiles.', 'uncoder' ),
			'soundcloud'           => __( 'A SoundCloud player for a track or playlist.', 'uncoder' ),
			'spacer'               => __( 'Empty space between elements.', 'uncoder' ),
			'star-rating'          => __( 'A rating shown as stars, like 4.5 out of 5.', 'uncoder' ),
			'steps'                => __( 'A numbered “how it works” process, step by step.', 'uncoder' ),
			'table'                => __( 'A table with rows and columns, for data or comparisons.', 'uncoder' ),
			'table-of-contents'    => __( 'A clickable list of the headings on the page.', 'uncoder' ),
			'tabs'                 => __( 'Content split into tabs that visitors click between.', 'uncoder' ),
			'team-member'          => __( 'A card for a person: photo, name, role and social links.', 'uncoder' ),
			'template'             => __( 'Shows a saved section. Edit it once and it updates everywhere.', 'uncoder' ),
			'testimonial'          => __( 'A quote from a happy customer, with their name and photo.', 'uncoder' ),
			'testimonial-carousel' => __( 'Several customer quotes in a carousel.', 'uncoder' ),
			'text-editor'          => __( 'Paragraphs of text, with bold, links and lists.', 'uncoder' ),
			'text-path'            => __( 'Text that follows a curve or circle, like a round badge.', 'uncoder' ),
			'timeline'             => __( 'Events in order along a line, like your company history.', 'uncoder' ),
			'video'                => __( 'A YouTube, Vimeo or uploaded video.', 'uncoder' ),
			'video-playlist'       => __( 'A video player with a list of videos next to it.', 'uncoder' ),
		);
	}

	/**
	 * Adds each widget's tip to the editor's schemas (a widget without one keeps its own description).
	 *
	 * @param array<string, array<string,mixed>> $schemas Element schemas by name.
	 * @return array<string, array<string,mixed>>
	 */
	public static function add( array $schemas ): array {
		$tips = self::all();
		foreach ( $schemas as $name => $schema ) {
			$schemas[ $name ]['tip'] = $tips[ $name ] ?? (string) ( $schema['description'] ?? '' );
		}
		return $schemas;
	}
}
