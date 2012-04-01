<?php

namespace Varspool\SundownBundle\Services;

use Sundown\Markdown;
use Varspool\SundownBundle\Sundown\Render\ColorXHTML;
use Symfony\Bridge\Monolog\Logger;

class MarkdownFactory
{
    protected static $cached = null;

    public static function get(Logger $logger)
    {
        if (self::$cached == null || true) {
            self::$cached = new Markdown(
                new ColorXHTML(array(
                    'filter_html'         => true,
                    'no_styles'           => true,
                    'safe_links_only'     => true,
                    'with_toc_data'       => false,
                    'hard_wrap'           => false,
                ), $logger),
                array(
                    'no_intra_emphasis'   => true,
                    'tables'              => false,
                    'fenced_code_blocks'  => true,
                    'autolink'            => false,
                    'strikethrough'       => true,
                    'lax_html_blocks'     => false,
                    'space_after_headers' => true,
                    'superscript'         => true,
                )
            );
        }
        return self::$cached;
    }
}
