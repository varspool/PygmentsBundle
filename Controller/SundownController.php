<?php

namespace Varspool\SundownBundle\Controller;

use Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Varspool\SundownBundle\Sundown\Render\ColorXHTML;

class SundownController extends Controller
{
    /**
     * @Route("/pygments/html/{style}.css", name="pygments_css")
     * @Method({"GET"})
     * @return Response
     */
    public function stylesAction($style = 'friendly')
    {
        $styles = ColorXHTML::getStyles('html', $style);

        $response = new Response($styles);
        $response->headers->set('Content-Type', 'text/css');
        $response->setCharset('utf-8');
        $response->setPublic();
        $response->setSharedMaxAge(9999999);
        $response->setMaxAge(9999999);

        return $response;
    }
}
