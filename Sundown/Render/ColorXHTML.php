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

    protected $options;

    /**
     * Used for DomDocument workaround
     *
     * @var string
     */
    protected $documentTemplate = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>%s</body></html>';

    /**
     * Constructor
     *
     * @param array $options
     *     Formerly string $pygmentize (BC).
     *     Supported options:
     *       - bin             => string, path to pygmentize
     *       - lexer_arguments => array, default lexer arguments
     * @param Logger $logger
     */
    public function __construct(Logger $logger, $options = array())
    {
        parent::__construct();

        $this->logger = $logger;

        // BC for $pygmentize param, will be removed in future
        if (is_string($options)) {
            $options = array('bin' => $options);
        }

        $this->configure($options);
    }

    /**
     * Configures the instance based on the options, setting default options
     *
     * @param array<string => mixed> $options
     */
    protected function configure(array $options)
    {
        $options = array_merge(array(
            // Use your configuration management to ensure this path exists,
            // or override it by passing in an alternative path as an option.
            'bin'      => '/usr/bin/pygmentize',

            'lexer_arguments' => array()
        ), $options);

        $this->options = $options;
    }

    /**
     * Sets an option
     *
     * @param string $name
     * @param mixed $value
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
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

            if (!is_executable($this->options['bin'])) {
                $this->logger->err('Disabling colorization: pygmentize not +x');
                return array();
            }

            $command = sprintf(
                '%s -L lexers',
                $this->options['bin']
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
            escapeshellcmd($this->options['bin']),
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
        if (!$language) {
            return false;
        }
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

    /**
     * Gets default lexer arguments, by language and content
     *
     * The basic default lexer arguments can be provided by the lexer_arguments
     * option.
     *
     * @param string $language
     * @param string $content
     * @return array
     */
    protected function getLexerArguments($language, $content)
    {
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

        return array_merge($args, $this->options['lexer_arguments']);
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
            $this->options['bin'],
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
        if (!$this->isValidLanguage($language)) {
            return parent::blockCode($code, $language);
        }

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
            return parent::blockCode($code, $language);
        }
    }
}
