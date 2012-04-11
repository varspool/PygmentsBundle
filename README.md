<!-- vim: set ft=markdown tw=79 sw=4 ts=4 et : -->
# VarspoolPygmentsBundle

Provides (X)HTML rendering of Markdown similar to GFM (Github flavored markdown),
including syntax-highted fenced code blocks. Code coloring is provided by
the Python Pygments library (interfaces with pygmentize using proc_open for now).

VarspoolPygmentsBundle doesn't reinvent the wheel: it uses the Sundown support
in KwattroMarkdownBundle to do the initial Markdown rendering.

## Installation

### Install the Sundown PHP extension

The [Sundown extension](http://pecl.php.net/package/sundown) is available via
PECL in beta state, so installing it should be something like:

```sh
sudo pecl install sundown-beta
```

You'll be able to tell you're successful when the Sundown extension is shown in
the output of `php\_info()`:

```sh
php -i | grep 'Sundown Support'
```

### Install pygments

This is usually available via your package manager, as the `python-pygments`
package or similar.

``` sh
sudo apt-get install python-pygments
```

The key thing, however, is just that you have the pygmentize script available
to execute. It's usually at `/usr/bin/pygmentize`, but if not, you can
configure its location (see app/config.yml below).

### deps file

If you're using bin/vendors to configure your dependencies, add the following
lines to your `deps` file:

```ini
[KwattroMarkdownBundle]
    git=http://github.com/kwattro/KwattroMarkdownBundle.git
    target=/bundles/Kwattro/MarkdownBundle

[VarspoolPygmentsBundle]
    git=git://github.com/dominics/VarspoolPygmentsBundle.git
    target=/bundles/Varspool/PygmentsBundle
    version=origin/master
```

Or, fork to your own repository first so you can send in pull requests and 
improve the bundles! Once you've done this you can use `bin/vendors` to obtain
the bundles:

```
$ bin/vendors update

[...]
  > Installing/Updating KwattroMarkdownBundle
  > Installing/Updating VarspoolPygmentsBundle
```

### app/autoload.php

Register the Varspool namespace in your autoloader:

```php
# app/autoload.php
$loader->registerNamespaces(array(
    // [...]
    'Varspool' => __DIR__.'/../vendor/bundles',
    'Kwattro'  => __DIR__.'/../vendor/bundles'
));
```

### app/AppKernel.php

Register the `KwattroMarkdownBundle` and `VarspoolPygmentsBundle`:

```php
# app/AppKernel.php
public function registerBundles()
{
    $bundles = array(
        //...
        new Kwattro\MarkdownBundle\KwattroMarkdownBundle(),
        new Varspool\PygmentsBundle\VarspoolPygmentsBundle(),
    );
}
```

### app/config.yml

Next, configure the default Markdown renderer for the `kwattro_markdown` service,
so that it'll stop complaining.

```yaml
kwattro_markdown:
    renderer:     xhtml
```

You can optionally configure where to find the `pygmentize` script. The default
is `/usr/bin/pygmentize`:

```yaml
varspool_pygments:
    bin:     /usr/local/bin/pygmentize
```

## Usage

### Services

#### kwattro_markdown

KwattroMarkdownBundle usually provides the `kwattro_markdown` service. This 
won't change when you set up VarspoolPygmentsBundle: the service will continue
to provide a Markdown rendering without syntax highlighting. This service is
usually a `Kwattro\MarkdownBundle\Markdown\KwattroMarkdown` object.

```php
$xhtml = $this->get('kwattro_markdown')->render($markdown_source);
```

#### varspool_markdown

Once you've installed VarspoolPygmentsBundle, you'll have a second service 
available: `vaspool_markdown`. This service will extend
`Kwattro\MarkdownBundle\Markdown\KwattroMarkdown`, so you should just be able
to swap it in as a replacement quite easily. It'll colorize fenced code blocks
in the markdown. This service is usually a 
`Varspool\PygmentsBundle\Markdown\KwattroMarkdown` object.

```php
$colorized_xhtml = $this->get('varspool_markdown')->render($markdown_source);
```

#### varspool_pygments

This service is the Sundown renderer instance responsible for coloring the 
output. It's usually an instance of `Varspool\PygmentsBundle\Sundown\Render\ColorXHTML`.

### Stylesheets

The Pygments renderer marks up parts of the output with `div` tags and classes.
You'll then need to assign stlying to these tags.

#### SCSS/Compass

If you're already using Compass or SASS, there's an example Pygments stylesheet
in Resources/public/css/_pygments.scss. The default implementation uses the 
[Solarized](http://ethanschoonover.com/solarized) color scheme. You should be
able to @import this stylesheet from one of your own.

#### Dynamic Styles

Pygments can provide one of several stylesheets to automatically color the 
output. A controller is provided that will output styles by calling
`pygmentize -S <style>`. To use the controller, reference it from your routing:

```yaml
# app/config/routing.yml
varspool_pygments:
  resource: '@VarspoolPygmentsBundle/Controller/PygmentsController.php'
  type: annotation
``` 

Then include a CSS file in your page via the URL `/pygments/<pygments_formatter>/<pygments_style>.css`.
(e.g. /pygments/html/friendly.css).

Alternatively, you can get the styles as a string from the varspool_pygments service:

```php
$pygments_formatter = $this->container->get('varspool_pygments');
$styles = $pygments_formatter->getStyles('friendly');
```