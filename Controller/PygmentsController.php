<?php

namespace Varspool\PygmentsBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

use Varspool\PygmentsBundle\Sundown\Render\ColorXHTML;

class PygmentsController extends ContainerAware
{
    /**
     * @Route("/pygments/html/{style}.css", name="varspool_pygments_css")
     * @Method({"GET"})
     * @return Response
     */
    public function stylesAction($style = 'friendly')
    {
        $pygments_formatter = $this->container->get('varspool_pygments');
        $styles = $pygments_formatter->getStyles($style);

        $response = new Response($styles);
        $response->headers->set('Content-Type', 'text/css');
        $response->setCharset('utf-8');
        $response->setPublic();
        $response->setSharedMaxAge(9999999);
        $response->setMaxAge(9999999);

        return $response;
    }
}
