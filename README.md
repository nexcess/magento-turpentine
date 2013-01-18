# Nexcess.net Turpentine Extension for Magento
[![Build Status](https://travis-ci.org/nexcess/magento-turpentine.png)](https://travis-ci.org/nexcess/magento-turpentine)

Turpentine is a Magento extension to improve Magento's compatibility with
[Varnish](https://www.varnish-cache.org/), a very-fast caching reverse-proxy. By
default, Varnish doesn't cache requests with cookies and Magento sends the
*frontend* cookie with every request causing a (near) zero hit-rate for Varnish's cache.
Turpentine provides Varnish configuration files (VCLs) to work with Magento and
modifies Magento's behaviour to significantly improve the cache hit rate.

Note that this extension is still in **beta** so use on a production site should
be considered carefully. There are already some sites using it in production,
but it is certainly not *stable* yet (ESI support brought significant changes
to how it works) so it should be carefully tested on a development site before
being deployed on a production site.

## Features

 - Full Magento Page Caching
 - Requires very little configuration for impressive results
 - Able to apply new Varnish VCLs (configurations) on the fly, without
 restarting/changing Varnish's config files or flushing the cache
 - Exclude URL paths, request parameters (SID, store, etc) from caching
 - Configure cache TTL by URL and individual block's TTL
 - Ability to force static asset (css, js, etc) caching
 - Supports multiple Varnish instances for clustered usage
 - Hole-punching via Varnish ESI support and/or AJAX
 - Automatic cache clearing on actions (clearing product/catalog/cms page after saving)
 - Non-root Magento installs (i.e. putting Magento in /store/ instead of /)
 - External web crawler support for warming the cache
 - Builtin crawler for automatically warming flushed cache pages
 - Multi-store/multi-site support
 - [SSL support](https://github.com/nexcess/magento-turpentine/wiki/SSL_Support) through
 [Pound](http://www.apsis.ch/pound) or [Nginx](http://nginx.org/)

## Requirements

 - Magento Community Edition 1.6+ (earlier versions may work but have not been
 tested) or Magento Enterprise Edition 1.11+
 - Varnish 2.1+ (including 3.0+)

## Installation & Usage

See the [Installation](https://github.com/nexcess/magento-turpentine/wiki/Installation)
and [Usage](https://github.com/nexcess/magento-turpentine/wiki/Usage) pages.

## Support

If you have an issue, please read the [FAQ](https://github.com/nexcess/magento-turpentine/wiki/FAQ)
then if you still need help, open a bug report in GitHub's
[issue tracker](https://github.com/nexcess/magento-turpentine/issues).

## How it works

The extension works in two parts, page caching and block (ESI) caching. A
simplified look at how they work:

For pages, Varnish first checks whether the visitor sent a ``frontend`` cookie.
If they didn't, then they are served a served a new page from the backend (Magento),
regardless of whether that page is already cached. This is so they get a new
session from Magento. If they already already have a ``frontend`` cookie, then
they get a (non-session-specific) page from the backend, with any session-specific
blocks (defined by the ESI policies) filled in via ESI. Note that this is bypassed
for clients identified as crawlers (see the ``Crawler IP Addresses`` and
``Crawler User Agents`` settings).

For blocks, the extension listens for the ``core_block_abstract_to_html_before``
event in Magento. When this event is triggered, the extension looks at the block
attached to it and if an [ESI policy](https://github.com/nexcess/magento-turpentine/wiki/ESI_Cache_Policy)
has been defined for the block then the
block's template is replaced with a simple ESI template that tells Varnish to
pull the block content from a separate URL. Varnish then does another request to
that URL to get the content for that block, which can be cached separately from
the page and may differ between different visitors/clients.

## Notes and Caveats

 - Turpentine will **not** help (directly) with the speed of "actions" like adding things
 to the cart or checking out. It only caches, so it can only speed up page load
 speed for site browsing. It will remove a lot of load on the backend though so
 for heavily loaded sites it can free up enough backend resources to have a
 noticeable effect on "actions".
 - By design, the first request from a new visitor will not serve them a cached
 page (it will be passed through to the backend so they get a unique session),
 so the page load speed will the same as normal (without Varnish/Turpentine).
 The second request will also
 be somewhat faster than normal, but not full cached-page speed as the ESI blocks
 will need to be regenerated and cached for the visitor's new session. Any further
 requests will be at full cached-page speed (assuming the pages are already in
 the cache).
 - Multi-store/multi-site setups that use the same *URL path and domain combo*
 will not work. Specifically they will always use the default site/store and
 changing via the dropdown menu will not do anything. Examples:
    - example.com/store/en/ and example.com/store/de/ works (same domain, different paths)
    - example.com/store/ and example.com/store/substore/ works (same domain, different paths)
    - en.example.com/store/ and de.example.com/store/ works (different domain, same paths)
    - example.com/store/ for both EN and DE **does not** work (same domain and paths)
 - **Varnish 2.1**: Due to technical limitations, some features are not
 available when using Varnish 2.1:
    - TTL extension on cache hits does not work
    - External ESI requests are not blocked
    - Per-block TTLs are not honored, all ESI blocks use their default TTL

## Known Issues

 - Logging and statistics will show all requests as coming from the same IP address
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
