<?php

namespace Varspool\PygmentsBundle\Sundown\Render;

use Sundown\Render\XHTML;
use \DomElement;
use \DomXPath;
use \DomDocument;
use \DomDocumentFragment;
use Symfony\Bridge\Monolog\Logger;

class ColorXHTML extends XHTML
{

    /**
     * Languages that can be colorized
     *
     * @var array
     */
    protected $validLanguages;

    /**
     * A logger
     *
     * @var Monolog\Logger
     */
    protected $logger;

    /**
     * Styles
     *
     * @var array
     */
    protected static $styles = array();

    /**
     * Use your configuration management to ensure this exists. It's provided
     * by the python-pygments package in Debian/Ubuntu.
     *
     * @var string
     */
    protected $pygmentize;

    /**
     * Used for DomDocument workaround
     *
     * @var string
     */
    protected $documentTemplate = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>%s</body></html>';

    /**
     *
     * @param unknown_type $pygmentize
     * @param Logger $logger
     */
    public function __construct(Logger $logger, $pygmentize = '/usr/bin/pygmentize')
    {
        parent::__construct();

        $this->logger = $logger;
        $this->pygmentize = $pygmentize;
    }

    /**
     * DI'd
     *
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Gets the valid languages that can be colorized
     *
     * @return array of string
     */
    protected function getValidLanguages()
    {
        if (empty($this->validLanguages)) {
            $this->validLanguages = array();

            if (!is_executable($this->pygmentize)) {
                $this->logger->err('Disabling colorization: pygmentize not +x');
                return array();
            }

            $command = sprintf(
                '%s -L lexers',
                $this->pygmentize
            );

            $output = `$command`;

            if (preg_match_all('#\* (.+):#', $output, $matches)) {
                if (is_array($matches[1])) {
                    $matches = $matches[1];
                    foreach ($matches as $match) {
                        foreach (explode(', ', $match) as $language) {
                            $this->validLanguages[] = trim($language);
                        }
                    }
                }
            }
        }

        return $this->validLanguages;
    }

    /**
     * Gets CSS rules for the given Pygments style
     *
     * @param string $style
     * @param string $formatter Pygments formatter
     * @return string
     */
    public function getStyles($style, $formatter = 'html')
    {
        if (isset(self::$styles[$formatter][$style])) {
            return self::$styles[$formatter][$style];
        }

        $styles = null;

        $command = sprintf(
            '%s -S %s -f %s',
            escapeshellcmd($this->pygmentize),
            escapeshellarg($style),
            escapeshellarg($formatter)
        );

        $styles = `$command`;

        if ($styles) {
            self::$styles[$formatter][$style] = $styles;
        }

        return $styles;
    }


    /**
     * Whether the given language can be colorized
     *
     * @param string $language
     * @return boolean
     */
    protected function isValidLanguage($language)
    {
        return in_array($language, $this->getValidLanguages());
    }

    /**
     * Gets the language of the given code element
     *
     * @param DomElement $element
     * @return string or false
     */
    protected function getLanguage(DomElement $element)
    {
        if (($language = trim($element->getAttribute('language')))) {
            if ($this->isValidLanguage($language)) {
                return $language;
            }
        } elseif (($classes = $element->getAttribute('class'))) {
            $classes = explode(' ', $classes);
            foreach ($classes as $class) {
                $class = trim($class);
                if ($this->isValidLanguage($class)) {
                    return $class;
                }
            }
        }

        return false;
    }

    protected function getLexerArguments($language, $content)
    {
        $defaultArgs = array(
            'linenos' => 'table',
        );
        
        switch ($language) {
            case 'php':
                $args = array(
                    'startinline' => (strpos($content, '<?php') !== false ? 'False' : 'True')
                );
                break;
            default:
                $args = array();
                break;
        }
        
        return array_merge($args, $defaultArgs);
    }

    /**
     * Colorizes the given content for the given language
     *
     * @param string $language
     * @param string $content
     * @return string
     */
    protected function colorize($language, $content)
    {
        $this->logger->notice('Colorizing block for language: ' . $language);

        $arguments = $this->getLexerArguments($language, $content);

        $argstring = '';
        foreach ($arguments as $argument => $value) {
            $argstring .= ' -P ' . escapeshellarg("$argument=$value");
        }

        $command = sprintf(
            '%s -l %s -f html %s',
            $this->pygmentize,
            $language,
            $argstring
        );

        $handle = null;
        try {
            $handle = proc_open(
                $command,
                array(
                    0 => array('pipe', 'r'),
                    1 => array('pipe', 'w'),
                    2 => array('pipe', 'w')
                ),
                $pipes,
                '/tmp',
                array()
            );

            $stdin = $pipes[0];
            $stdout = $pipes[1];
            $stderr = $pipes[2];

            fwrite($stdin, $content);
            fwrite($stdin, "\n");
            fclose($stdin);

            $output = '';
            while (!feof($stdout)) {
                $output .= fread($stdout, 8192);
            }
            fclose($stdout);

            $err = '';
            while (!feof($stderr)) {
                $err .= fread($stderr, 8192);
            }
            fclose($stderr);

            $return_value = proc_close($handle);

            unset($stdin);
            unset($stdout);
            unset($stderr);
        } catch (\Exception $e) {
            if ($handle !== null) {
                proc_close($handle);
            }
            throw $e;
        }

        if ($return_value != 0 || $err) {
            throw new \Exception('Bad pygments run: ' . $err);
        }

        return trim($output);
    }

    public function blockCode($code, $language)
    {
        try {
            $colorized = preg_replace(array(
                    '#<pre>#',
                    '#</pre>#',
                    '#class="highlight"#',
                ), array(
                    '<pre><code>',
                    '</code></pre>',
                    'class="highlight '.$language.'"'
                ), 
                $this->colorize($language, $code));
            
            return $colorized;
        } catch (\Exception $e) {
            $this->logger->warn($e);
            return sprintf('<pre><code>%s</code></pre>', $code);
        }
    }
}
