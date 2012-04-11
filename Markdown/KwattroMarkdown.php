<?php

namespace Varspool\PygmentsBundle\Markdown;

use Sundown\Render\Base as SundownRenderer;
use Kwattro\MarkdownBundle\Parser\Parser;
use Kwattro\MarkdownBundle\Markdown\KwattroMarkdown as BaseKwattroMarkdown;

/**
 * Markdown object
 *
 * I had to override more than I wanted to here. Most of this class is directly
 * copied from Kwattro\MarkdownBundle\Markdown\KwattroMarkdown, but that class
 * defines its members as private.
 *
 * The main modification is to allow the renderer object to be specified as an
 * instance, rather than a class name string. This allows the renderer to be
 * dependency-injected. I'll send a PR upstream when I get a chance.
 *
 * @author Dominic Scheirlinck <dominic@varspool.com>
 */
class KwattroMarkdown extends BaseKwattroMarkdown
{
    /**
     * Whether the renderer instance has been explicitly set by a call to setRenderer
     * rather than instantiated in the configure() method
     *
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::$explicitRenderer
     * @var Sundown\Render\Base
     */
    protected $explicitRenderer;

    /**
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::$renderer
     * @var mixed Actutally string, then renderer instance in parent class
     */
    protected $renderer;

    /**
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::$parser
     */
    protected $parser;

    /**
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::$extensions
     * @var array
     */
    protected $extensions = array(
        'no_intra_emphasis' => false,
        'tables' => true,
        'fenced_code_blocks' => true,
        'autolink' => true,
        'strikethrough' => true,
        'lax_html_blocks' => false,
        'space_after_headers' => true,
        'superscript' => false,
    );

    /**
     * @var array
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::$renderers
     */
    protected $renderers = array(
        'base' => '\Sundown\Render\Base',
        'html' => '\Sundown\Render\HTML',
        'xhtml' => '\Sundown\Render\XHTML',
        'custom' => '',
    );

    /**
     * @var array
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::$flags
     */
    protected $flags = array(
        'filter_html' => false,
        'no_images' => false,
        'no_links' => false,
        'no_styles' => false,
        'safe_links_only' => false,
        'with_toc_data' => false,
        'hard_wrap' => true,
        'xhtml' => true,
    );

    /**
     * Constructor
     *
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::__construct
     * @param array $extensions_config
     * @param array $flags_config
     * @param string $renderer
     * @param string $render_class
     * @param SundownRenderer $explicit_renderer
     */
    public function __construct(array $extensions_config, array $flags_config, $renderer, $render_class = null, SundownRenderer $explicit_renderer = null)
    {
        $this->configure($extensions_config, $flags_config, $renderer, $render_class, $explicit_renderer);
    }

    /**
     * Set up the Markdown instance if it does not exist
     *
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::setUpMarkdown
     */
    public function setUpMarkdown()
    {
        $this->parser = new Parser($this->renderer, $this->extensions);
    }

    /**
     * Allow the renderer to be set directly
     *
     * For example, from dependency injection. This allows us to set ColorXHTML
     * as the renderer but still control the lifecycle of the renderer object
     * and inject services into it (for example, it's quite nice to have a
     * renderer with access to logging).
     *
     * See also the $explicit_renderer parameter to __construct()
     *
     * @param  $renderer
     */
    public function setExplicitRenderer(SundownRenderer $renderer)
    {
        $this->renderer = $renderer;
        $this->explicitRenderer = $renderer;
    }

    /**
     * Configures the Markdown with extensions and renderer sepcified
     *
     * @param array $extensions
     * @param array $flags
     * @param string $renderer
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::configure
     */
    public function configure(array $extensions = array(), array $flags = array(), $renderer = null, $render_class = null, SundownRenderer $explicit_renderer = null)
    {
        if(!empty($extensions)) {
            foreach($extensions as $key => $value) {
                $this->checkIfValidExtension($key);
            }
            $this->extensions = array_merge($this->extensions, $extensions);
        }

        if(!empty($flags)) {
            foreach($flags as $key => $value) {
                $this->checkIfValidFlag($key);
            }
            $this->flags = array_merge($this->flags, $flags);
        }

        if (isset($explicit_renderer)) {
            $this->setExplicitRenderer($explicit_renderer);
            $explicit_renderer->setRenderFlags($this->flags);
        } else {
            if(!empty($renderer) && $this->isValidRenderer($renderer, $render_class)) {
                $this->renderer = new $this->renderers[$renderer]($this->flags);
            } else {
                $this->renderer = new $this->renderers['html']($this->flags);
            }
        }

        $this->setUpMarkdown();
    }

    /**
     * Parse the given string with the Sundown Parser
     *
     * @param string $text The text to transform
     * @param array $extensions The extensions configuration
     * @param array $flags The flags configuration
     * @param string $renderer The desired renderer
     * @return string The transformed text
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::render
     */
    public function render($text, array $extensions = array(), array $flags = array(), $renderer = null)
    {
        if ((!empty($renderer) && $renderer != 'custom') || empty($this->explicitRenderer)) {
            return parent::render($text, $extensions, $flags, $renderer);
        } else {
            $this->configure($extensions, $flags, $renderer, null, $this->explicitRenderer);
            return $this->transform($text);
        }
    }

    /**
     * Transforms the text into a markdown style
     *
     * @param string $text
     * @return string $transform
     * @see Kwattro\MarkdownBundle\Markdown\KwattroMarkdown::transform
     */
    public function transform($text)
    {
        return $this->parser->render($text);
    }
}