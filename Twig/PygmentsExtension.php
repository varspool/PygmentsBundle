<?php

namespace Varspool\PygmentsBundle\Twig;


/**
 * This class contains the following Twig filters:
 *  * pygmentize($text, $language). Exemple usage {{ some_code|pygmentize('php') }}
 *
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
class PygmentsExtension extends \Twig_Extension
{
    protected $pygments_renderer;


    public function __construct($pygments_renderer)
    {
        $this->pygments_renderer = $pygments_renderer;
    }

    public function getFilters()
    {
        return array(
            'pygmentize' => new \Twig_Filter_Method($this, 'pygmentize'),
        );
    }

    public function pygmentize($text, $language)
    {
        return $this->pygments_renderer->blockCode($text, $language);
    }

    public function getName()
    {
        return 'pygments_extension';
    }
}

