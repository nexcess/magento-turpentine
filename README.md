# Nexcess.net Turpentine Extension for Magento

Turpentine is a Magento extension to improve Magento's compatibility with
[Varnish](https://www.varnish-cache.org/), a very-fast caching reverse-proxy. By
default, Varnish doesn't cache requests with cookies and Magento sends the
*frontend* cookie with every request causing a (near) zero hit-rate for Varnish's cache.
Turpentine provides Varnish configuration files (VCLs) to work with Magento and
modifies Magento's behaviour to significantly improve the cache hit rate.

Note that this extension is still in **beta** so use on a production site should
be considered carefully. There are already some sites using it in production,
but it is certainly not *stable* yet (ESI support brought significant changes
to how it works).

## Features

 - Full Magento Page Caching
 - Requires very little configuration for impressive results
 - Able to apply new Varnish VCLs (configurations) on the fly, without
 restarting/changing Varnish's config files or flushing the cache
 - Cache purging by URL and content type
 - Exclude URL paths, request parameters (__SID, __store, etc) from caching
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
 - Varnish 3.0+

## Installation & Usage

See the [Installation](/nexcess/magento-turpentine/wiki/Installation) and
[Usage](/nexcess/magento-turpentine/wiki/Usage) pages.

## Support

If you have an issue, please read the [FAQ](/nexcess/magento-turpentine/wiki/FAQ)
then if you still need help, open a bug report in GitHub's
[issue tracker](/nexcess/magento-turpentine/issues).

## How it works

The extension works in two parts, page caching and block (ESI) caching. A
simplified look at how they work:

For pages, Varnish first checks whether the visitor sent a ``frontend`` cookie.
If they didn't, then they are served a served a new page from the backend (Magento),
regardless of whether that page is already cached. This is so they get a new
session from Magento. If they already already have a ``frontend`` cookie, then
they get a (non-session-specific) page from the backend, with any session-specific
blocks (defined by the ESI policies) filled in via ESI. Note that this is bypassed
for client IPs in the ``Crawler IPs`` setting.

For blocks, the extension listens for the ``core_block_abstract_to_html_before``
event in Magento. When this event is triggered, the extension looks at the block
attached to it and if an [ESI policy](/nexcess/magento-turpentine/wiki/ESI_Cache_Policy)
has been defined for the block then the
block's template is replaced with a simple ESI template that tells Varnish to
pull the block content from a separate URL. Varnish then does another request to
that URL to get the content for that block, which can be cached separately from
the page and may differ between different visitors/clients.

## Notes

 - This extension is currently in **beta**. There are some sites using it in
 production but you should carefully test it on your own dev site before pushing
 to production.
 - Turpentine will **not** help with the speed of "actions" like adding things
 to the cart or checking out. It only caches, so it can only speed up page load
 speed for site browsing.

## Future Plans

 - Get rid of the layout method for defining ESI blocks to cache, this should
 be configurable in the Magento system configuration
 - Use standard cache-control headers to tell Varnish about the block and page
 TTLs
 - Add support for caching in the admin section
 - Re-add Varnish 2.1.x support
 - SSL support through [Pound](http://www.apsis.ch/pound)

## Known Issues

 - Logging and statistics will show all requests as coming from the same IP address
 (usually localhost/127.0.0.1). It should be possible to work around this using
 Apache's [mod_remoteip](http://httpd.apache.org/docs/trunk/mod/mod_remoteip.html)
 - The admin panel will not be cached at all. Attempts to inject ESI in the admin
 panel will cause a warning to be logged and then ignored.
 - Flash messages usually do not display, or display sporadically

## License

The code is licensed under GPLv2+, much of the ESI-specific code is taken from
Hugues Alary's [Magento-Varnish](https://github.com/huguesalary/Magento-Varnish)
extension, which is licensed as GPLv3.
