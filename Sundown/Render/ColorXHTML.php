<?php

namespace Varspool\SundownBundle\Sundown\Render;

use Sundown\Render\XHTML;
use \DomElement;
use \DomXPath;
use \DomDocument;
use \DomDocumentFragment;
use Symfony\Bridge\Monolog\Logger;

class ColorXHTML extends XHTML
{
    /**
     * Use your configuration management to ensure this exists. It's provided
     * by the python-pygments package in Debian/Ubuntu.
     */
    const PYGMENTIZE = '/usr/bin/pygmentize';

    /**
     * Languages that can be colorized
     *
     * @var array
     */
    protected $validLanguages;


    /**
     * A logger
     */
    protected $logger;

    /**
     * Styles
     */
    protected static $styles = array();

    /**
     * Constructor
     *
     * Don't worry, just DI :-)
     */
    public function __construct($renderFlags = array(), Logger $logger)
    {
        parent::__construct($renderFlags);
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

            if (!is_executable(self::PYGMENTIZE)) {
                $this->logger->err('Disabling colorization: pygmentize not +x');
                return array();
            }

            $command = sprintf(
                '%s -L lexers',
                self::PYGMENTIZE
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

    public static function getStyles($formatter, $style)
    {
        if (isset(self::$styles[$formatter][$style])) {
            return self::$styles[$formatter][$style];
        }

        $styles = null;

        $command = sprintf(
            '%s -S %s -f %s',
            self::PYGMENTIZE,
            $style,
            $formatter
        );

        $styles = `$command`;

        self::$styles[$formatter][$style] = $styles;
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

    protected function getLexerArguments($language)
    {
        switch ($language) {
            case 'php':
                return array(
                    'startinline' => 'True'
                );
                break;
            default:
                return array();
        }
    }

    /**
     * @param string $language
     * @param string $content
     * @return string
     */
    protected function colorize($language, $content)
    {
        $arguments = $this->getLexerArguments($language);

        $argstring = '';
        foreach ($arguments as $argument => $value) {
            $argstring .= ' -P ' . escapeshellarg("$argument=$value");
        }

        $command = sprintf(
            '%s -l %s -f html %s',
            self::PYGMENTIZE,
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
            $this->logger->warn($e);
            return $content;
        }

        if ($return_value != 0 || $err) {
            $this->logger->warn('Bad pygments run: ' . $err);
        }

        return $output;
    }

    /**
     * @param string $string
     * @return string
     */
    public function postProcess($string)
    {
        $document = new DomDocument('1.0', 'utf-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        $document->loadHTML($string);

        $xpath = new DomXPath($document);

        $result = $xpath->query('//pre/code');
        foreach ($result as $element) {
            if ($language = $this->getLanguage($element)) {
                $fragment = $document->createDocumentFragment();
                $fragment->appendXML($this->colorize($language, $element->nodeValue));

                $parent = $element->parentNode;
                $parent->replaceChild($fragment, $element);
            }
        }

        $result = $xpath->query('//pre/div[@class="highlight"]/pre');
        foreach ($result as $element) {
            $new = $document->createElement('code');
            $new->setAttribute('class', 'highlight ' . $language);

            foreach ($element->childNodes as $node) {
                $new->appendChild(clone $node);
            }

            $element->parentNode->parentNode->replaceChild($new,
                $element->parentNode);
        }

        $body = $document->saveHTML($document->documentElement->firstChild);
        $body = substr($body, 7, -8);

        return $body;
    }
}
