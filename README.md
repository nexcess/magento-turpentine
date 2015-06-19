# [Nexcess.net](https://www.nexcess.net/) Turpentine Extension for Magento
[![Build Status](https://travis-ci.org/nexcess/magento-turpentine.png?branch=master,devel)](https://travis-ci.org/nexcess/magento-turpentine)

Turpentine is a full page cache extension for [Magento](https://www.magentocommerce.com/)
that works with [Varnish](https://www.varnish-cache.org/), a very fast caching reverse-proxy. By
default, Varnish doesn't cache requests with cookies and Magento sends the
*frontend* cookie with every request causing a (near) zero hit-rate for Varnish's cache.
Turpentine configures Varnish to work with Magento and modifies Magento's
behaviour to significantly improve the cache hit rate.

Note that while this extension is now considered *stable*, it is strongly
recommended that it be tested on a development/staging site before deploying
on a production site due to the potential need to add custom ESI policies
for blocks added by other extensions.

## Features

  * Full Page Caching, with hole-punching via Varnish ESI and/or AJAX, even for
  logged in visitors
  * Configurable via standard Magento methods (Admin system configuration and
  layout XML), no manual editing of Varnish config required for most cases
  * Able to generate and apply new Varnish VCLs (configurations) on the fly,
  without restarting/changing Varnish's config files or flushing the cache
  * Blacklist requests from caching by URL or parameters (SID, store, etc)
  * Configure cache TTL by URL and individual block's TTL
  * Supports multiple Varnish instances for clustered usage
  * Automatic cache clearing on actions (clearing product/catalog/cms page after saving)
  * Supports non-root Magento installs (i.e. putting Magento in /store/ instead
  of /) and multi-store/multi-site setups
  * Support for site-crawlers for cache warming, and includes a (basic)
  built-in site-crawler
  * [SSL support](https://github.com/nexcess/magento-turpentine/wiki/SSL_Support)
  through [Pound](http://www.apsis.ch/pound) or [Nginx](http://nginx.org/)

## Requirements

  * Magento Community Edition 1.6+ or Magento Enterprise Edition 1.11+
  * Varnish 2.1+ (including 3.0+)

## Installation & Usage

See the [Installation](https://github.com/nexcess/magento-turpentine/wiki/Installation)
and [Usage](https://github.com/nexcess/magento-turpentine/wiki/Usage) pages.

## Support

If you have an issue, please read the [FAQ](https://github.com/nexcess/magento-turpentine/wiki/FAQ)
then if you still need help, open a bug report in GitHub's
[issue tracker](https://github.com/nexcess/magento-turpentine/issues).

Please do not use Magento Connect's Reviews or (especially) the Q&A for support.
There isn't a way for me to reply to reviews and the Q&A moderation is very slow.

## Contributing

If you have a fix or feature for Turpentine, submit a pull request through GitHub
to the **devel** branch. The *master* branch is only for stable releases. Please
make sure the new code follows the same style and conventions as already written
code.

## How it works

The extension works in two parts, page caching and block (ESI/AJAX) caching. A
simplified look at how they work:

For pages, Varnish first checks whether the visitor sent a ``frontend`` cookie.
If they didn't, then Varnish will generate a new session token for them. The page
is then served from cache (or fetched from the backend if it's not already in
the cache), with any blocks with ESI polices filled in via ESI. Note that the
cookie checking is bypassed for clients identified as crawlers (see the
``Crawler IP Addresses`` and ``Crawler User Agents`` settings).

For blocks, the extension listens for the ``core_block_abstract_to_html_before``
event in Magento. When this event is triggered, the extension looks at the block
attached to it and if an [ESI policy](https://github.com/nexcess/magento-turpentine/wiki/ESI_Cache_Policy)
has been defined for the block then the
block's template is replaced with a simple ESI (or AJAX) template that tells Varnish to
pull the block content from a separate URL. Varnish then does another request to
that URL to get the content for that block, which can be cached separately from
the page and may differ between different visitors/clients.

## Notes and Caveats

  * Turpentine will **not** help (directly) with the speed of "actions" like adding things
  to the cart or checking out. It only caches, so it can only speed up page load
  speed for site browsing. It will remove a lot of load on the backend though so
  for heavily loaded sites it can free up enough backend resources to have a
  noticeable effect on "actions".
  * There are some technical limitations when using Varnish 2.1.x: External ESI
  requests are not blocked, and per-block TTLs in ESI policies are not honored
  (all blocks use the default TTL)
  * The core parts of Turpentine (caching and ESI/AJAX injection) work under Magento CE 1.5, but a significant
  portion of the auxiliary functionality doesn't work due to changes to event names. That
  said, it would be possible to use Turpentine with Magento CE 1.5 with an understanding
  that it is not supported and what actions need to be taken manually. Both
  *cache flushing* (both automatic an manual) and *cache warming* (due to
  missing events that trigger the cache flushing) do not work.
  * Anonymous blocks are not able to be hole-punched. For CMS pages, it is
  recommended that you include the block in the page's layout updates XML and
  give it a name, then it can have an ESI policy like normal

## Known Issues

  * Logging and statistics will show all requests as coming from the same IP address
  (usually localhost/127.0.0.1). It should be possible to work around this using
  Apache's [mod_remoteip](http://httpd.apache.org/docs/trunk/mod/mod_remoteip.html)
  or [mod_rpaf](http://www.stderr.net/apache/rpaf/).

## Demo

See the [Demo Sites](https://github.com/nexcess/magento-turpentine/wiki/Demo-Sites)
wiki page.

If you use Turpentine (on a production site), feel free to add your site to the
list!

## License

The code is licensed under GPLv2+, much of the ESI-specific code is taken from
Hugues Alary's [Magento-Varnish](https://github.com/huguesalary/Magento-Varnish)
extension, which is licensed as GPLv3.
