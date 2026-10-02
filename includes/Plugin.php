<?php
/**
 * Main plugin orchestrator.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder;

use Uncoder\Builder\Controls\Controls;
use Uncoder\Builder\Core\Documents;
use Uncoder\Builder\Core\Elements;
use Uncoder\Builder\Core\Kit;
use Uncoder\Builder\Dynamic\Tags;

defined( 'ABSPATH' ) || exit;

/**
 * Boots every module and exposes the shared registries.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private ?Elements $elements  = null;
	private ?Controls $controls  = null;
	private ?Documents $documents = null;
	private ?Kit $kit             = null;
	private ?Tags $tags           = null;

	/** @var array<string, object> */
	private array $modules = array();

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 5 );
	}

	public function on_plugins_loaded(): void {
		Install::maybe_upgrade();

		$modules = array(
			'post_types' => Core\Post_Types::class,
			'assets'     => Core\Assets::class,
			'frontend'   => Frontend\Frontend::class,
			'seo'        => Core\Seo::class,
			'media'      => Core\Media::class,
			'editor'     => Editor\Editor::class,
			'preview'    => Editor\Preview::class,
			'draft_preview' => Editor\Draft_Preview::class,
			'rest'       => Rest\Rest::class,
			'admin'      => Admin\Admin::class,
			'admin_bar'  => Editor\Admin_Bar::class,
			'theme'      => Theme\Theme_Builder::class,
			'template_preview' => Theme\Template_Preview::class,
			'popups'     => Popups\Popups::class,
			'menus'      => Menus\Mega_Menu::class,
			'menu_extras' => Menus\Menu_Item_Extras::class,
			'forms'      => Forms\Forms::class,
			'patterns'   => Patterns\Patterns::class,
			'display'    => Site\Display_Conditions::class,
			'maintenance' => Site\Maintenance::class,
			'snippets'   => Site\Code_Snippets::class,
			'fonts'      => Site\Custom_Fonts::class,
			'transfer'   => Site\Transfer::class,
			'site_kit'   => Site\Site_Kit::class,
			'elementor_import' => Site\Elementor_Import::class,
			'live_search' => Site\Live_Search::class,
			'style_book'  => Site\Style_Book::class,
			'multilingual' => Site\Multilingual::class,
			'custom_icons' => Site\Custom_Icons::class,
			'adobe_fonts'  => Site\Adobe_Fonts::class,
			'support_tools' => Site\Support_Tools::class,
			'updater'      => Site\Updater::class,
			'ai_images'    => Site\Ai_Images::class,
			'find_replace' => Site\Find_Replace::class,
			'consent'    => Site\Consent::class,
			'lottie'     => Site\Lottie_Files::class,
			'page_effects' => Site\Page_Effects::class,
			'performance'  => Site\Performance::class,
			'schema'       => Site\Schema::class,
			'ai_writer'    => Site\Ai_Writer::class,
			'starters'     => Site\Starters::class,
			'mcp'        => Mcp\Mcp::class,
			'abilities'  => Mcp\Abilities_Bridge::class,
		);

		/**
		 * Filters the module class map before modules are instantiated.
		 *
		 * @param array<string,string> $modules Module key => class name.
		 */
		$modules = apply_filters( 'uncoder_wb/modules', $modules );

		foreach ( $modules as $key => $class ) {
			if ( class_exists( $class ) ) {
				$module = new $class();
				if ( method_exists( $module, 'register' ) ) {
					$module->register();
				}
				$this->modules[ $key ] = $module;
			}
		}

		do_action( 'uncoder_wb/loaded', $this );
	}

	/**
	 * @return object|null
	 */
	public function module( string $key ) {
		return $this->modules[ $key ] ?? null;
	}

	public function elements(): Elements {
		if ( null === $this->elements ) {
			$this->elements = new Elements();
		}
		return $this->elements;
	}

	public function controls(): Controls {
		if ( null === $this->controls ) {
			$this->controls = new Controls();
		}
		return $this->controls;
	}

	public function documents(): Documents {
		if ( null === $this->documents ) {
			$this->documents = new Documents();
		}
		return $this->documents;
	}

	public function kit(): Kit {
		if ( null === $this->kit ) {
			$this->kit = new Kit();
		}
		return $this->kit;
	}

	public function tags(): Tags {
		if ( null === $this->tags ) {
			$this->tags = new Tags();
		}
		return $this->tags;
	}
}
