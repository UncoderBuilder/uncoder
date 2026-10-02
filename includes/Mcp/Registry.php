<?php
/**
 * MCP tool registry.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Holds tool definitions. Each definition:
 *   name, title, description, scope (read|content|design|site), input (JSON Schema object),
 *   annotations (readOnlyHint, destructiveHint, idempotentHint, openWorldHint), callback( array $args, Call $call ).
 */
final class Registry {

	/** @var array<string, array<string,mixed>> */
	private array $tools = array();

	private bool $loaded = false;

	private static ?Registry $instance = null;

	public static function instance(): Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @param array<string,mixed> $tool Definition.
	 */
	public function add( array $tool ): void {
		$tool['input'] = $tool['input'] ?? array( 'type' => 'object', 'properties' => new \stdClass() );
		if ( ! isset( $tool['input']['properties'] ) || array() === $tool['input']['properties'] ) {
			$tool['input']['properties'] = new \stdClass();
		}
		$this->tools[ $tool['name'] ] = $tool;
	}

	private function load(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		$providers    = array(
			new Tools\Site_Tools(),
			new Tools\Guide_Tools(),
			new Tools\Design_Tools(),
			new Tools\Page_Tools(),
			new Tools\Template_Tools(),
			new Tools\Menu_Tools(),
			new Tools\Media_Tools(),
			new Tools\Content_Tools(),
		);
		foreach ( $providers as $provider ) {
			$provider->register( $this );
		}
		/**
		 * Register extra MCP tools: $registry->add( [ 'name' => …, 'callback' => … ] ).
		 *
		 * @param Registry $registry Registry.
		 */
		do_action( 'uncoder_wb/mcp/tools', $this );
	}

	/**
	 * @return array<string, array<string,mixed>>
	 */
	public function all(): array {
		$this->load();
		return $this->tools;
	}

	public function get( string $name ): ?array {
		$this->load();
		return $this->tools[ $name ] ?? null;
	}

	/**
	 * Tool list for tools/list, filtered by the caller's scopes.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function describe( ?Context $ctx ): array {
		$out = array();
		foreach ( $this->all() as $tool ) {
			if ( $ctx && ! $ctx->has_scope( (string) ( $tool['scope'] ?? 'read' ) ) ) {
				continue;
			}
			$annotations = array_merge( array( 'title' => $tool['title'] ?? $tool['name'] ), (array) ( $tool['annotations'] ?? array() ) );
			$out[]       = array(
				'name'        => $tool['name'],
				'title'       => $tool['title'] ?? $tool['name'],
				'description' => $tool['description'] ?? '',
				'inputSchema' => $tool['input'],
				'annotations' => $annotations,
			);
		}
		return $out;
	}
}
