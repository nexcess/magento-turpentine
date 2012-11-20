# Nexcess.net Turpentine Extension for Magento

Turpentine is a Magento extension to improve Magento's compatibility with
[Varnish](https://www.varnish-cache.org/), a very-fast caching reverse-proxy. By
default, Varnish doesn't cache requests with cookies and Magento sends the
*frontend* cookie with every request causing a (near) zero hit-rate for Varnish's cache.
Turpentine provides Varnish configuration files (VCLs) to work with Magento and
modifies Magento's behaviour to significantly improve the cache hit rate.

Note that this extension is still in *beta* so use on a production site should
be considered carefully. There are already some sites using it in production,
but it is certainly not *stable* yet (ESI support brought significant changes
to how it works).

## Features

 - Full Magento Page Caching
 - Compatible with Varnish versions 2.1.4+ (including 3.x)
 - Requires very little configuration for impressive results
 - Able to apply new Varnish VCL (configurations) on the fly, without
 restarting/changing Varnish's config files
 - Cache purging by URL and content type
 - Exclude URL paths, request parameters (__SID, __store, etc)
 - Configure cache TTL by URL and individual block's TTL
 - Ability to force static asset (css, js, etc) caching
 - Supports multiple Varnish instances for clustered usage
 - Hole-punching via Varnish ESI support
 - Automatic cache clearing on actions (clearing product/catalog/cms page after saving)
 - Non-root Magento installs (i.e. putting Magento in /store/ instead of /)
 - Web crawler support for warming the cache

## Requirements

 - Magento Community Edition 1.6+ (earlier versions may work but have not been
 tested) or Magento Enterprise Edition 1.11+
 - Varnish 2.1+ (including 3.x versions)

## Installation

See the [Installation](/nexcess/magento-turpentine/wiki/Installation) page.

## How it works

The extension works in two parts, page caching and block (ESI) caching. A
simplified look at how they work:

For pages, Varnish first checks whether the visitor sent a ``frontend`` cookie.
If they didn't, then they are served a served a new page from the backend (Magento),
regardless of whether that page is already cached. This is so they get a new
session from Magento. If they already already have a ``frontend`` cookie, then
they get a (non-session-specific) page from the backend, with any session-specific
blocks (defined by the ESI policies) filled in via ESI.

For blocks, the extension listens for the ``core_block_abstract_to_html_before``
event in Magento. When this event is triggered, the extension looks at the block
attached to it and if an ESI policy has been defined for the block then the
block's template is replaced with a simple ESI template that tells Varnish to
pull the block content from a separate URL. The original block content is stored
in Magento's cache so that it can be returned when Varnish comes back to ask for
it.


## Future Plans

 - Get rid of the layout method for defining ESI blocks to cache, this should
 be configurable in the Magento system configuration
 - Use standard cache-control headers to tell Varnish about the block and page
 TTLs
 - Add support for caching in the admin section

## Known Issues

 - Logging and statistics will show all requests as coming from the same IP address
 (usually localhost/127.0.0.1). It should be possible to work around this using
 Apache's [mod_remoteip](http://httpd.apache.org/docs/trunk/mod/mod_remoteip.html)
 - Using memcached (or any other backend that doesn't support tags) is not likely
 to work well, the ESI blocks are stored and cleared by tags so cache tag support
 is needed.
 - The admin panel will not be cached at all. Attempts to inject ESI in the admin
 panel will cause a warning to be logged and then ignored.

## License

The code is licensed under GPLv2+, much of the ESI-specific code is taken from
Hughes Alary's (Magento-Varnish)[https://github.com/huguesalary/Magento-Varnish]
extension, which is licensed as GPLv3.
