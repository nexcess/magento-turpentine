# Change Log

### RELEASE-0.0.4

  * Initial Magento Connect release

### RELEASE-0.0.5


  * Add new VCL backend for Magento admin with longer timeout values

### RELEASE-0.0.6

  * Make backend timeouts and grace period configurable
  * Initial Magento Enterprise Edition compatible release on Magento Connect
  * Improve cache hit-rate in some cases

### RELEASE-0.1.0

  * Hole-punching support via ESI
  * Crawler support for cache warming
  * Most issues with Varnish socket communication will display flash errors
  instead of throwing exceptions
  * Many/most configuration options were changed or renamed

### RELEASE-0.1.1

  * Fixed EE ESI template and layout install location
  * Fix missing files in previous Magento Connect package

### RELEASE-0.1.2

  * Disable ESI injection if request does not come through Varnish to prevent
  broken output for SSL-enabled sites

### RELEASE-0.1.3

  * Fix ESI block rendering to be more accurate. This fixes the issue where some
  blocks didn't render exactly right (missing links in the header block)

### RELEASE-0.1.4

  * Added ESI block flushing on customer login/logout
  * Fixed syntax error under PHP 5.2
  * Added syntax check to build script
  * Made *footer* block global ESI cached by default
  * Removed *Varnish Management* admin page
    * VCL is now saved and applied on Varnish Options and Caching Options config
    saving
    * Cache clearing is now integrated into Cache Management page like other Magento
    cache types
    * Cache clearing by URL and content-type have been removed since they were
    not very useful in light of automatic cache clearing

### RELEASE-0.1.5

  * Fix VCL application and saving when the Magento compiler is enabled
  * Remove defunct debug option config dependancies
  * Added *right.poll* block to default ESI cache policy
  * Fix occasional "ESI processing not enabled" message (double ESI-include)
  * Fix *Varnish Pages* refresh not flushing Varnish cache

### RELEASE-0.1.6

  * Add support for identifing crawlers by User-Agent in addition to IP address,
  see the new *Crawler User Agents* setting (matches Googlebot, siege, ab, and
  [MageSpeedTest.com](http://www.magespeedtest.com/) by default)
  * Fix stale Login/Logout links
  * Add documentation for [SSL Support](https://github.com/nexcess/magento-turpentine/wiki/SSL_Support)

### RELEASE-0.2.0

  * Added AJAX injection for very short lived and/or frequently changed blocks
  (like the flash messages). This should resolve the issue with flash messages
  not displaying or displaying sporadically. To enable for other blocks, change
  the *setEsiOptions* method in the ``turpentine_esi.xml`` layout file to
  *setAjaxOptions*. Note that AJAX injected blocks are not cached at all
  * Added TTL extension for cache hits. Cached object's TTL will be extended by a
  small amount on cache hits to keep frequently used cache objects from expiring
  (unless the cache is flushed), allowing for lower TTL values to keep the cache
  from filling.
  * Prevented ESI injected blocks from being cached via Magento's block html
  caching while still allowing full block output to be cached. This is an
  improvement on the fix in RELEASE-0.1.5 for double ESI-includes
  * Fixed Magento using the ESI getBlock URL as the previously visited URL for
  visitor sessions
  * Fixed ESI injected blocks using the ESI getBlock URL as the current URL
  when rendering
  * Reduced size of generated VCL to reduce chance of hitting CLI buffer limit

### RELEASE-0.2.1

  * Changed the syntax for dummy blocks and registry keys in the ``setAjaxOptions``
  and ``setEsiOptions`` layout methods, see the wiki for the new syntax
  * Improved serialization of registry items in ESI/AJAX blocks
  * Fixed AJAX requests always being over HTTPS even if the main page was
  requested over HTTP
  * Changed the dummy URL to "/checkout/cart" instead of "/"
  * Fixed AJAX injection for *messages* block, which didn't use the standard
  layout method for block definitions. This also includes a new option to
  "Fix Flash Messages" which allows for disabling the new behavior for compatiblity
  with other AJAX extensions
  * Fixed POST request caching, which sometimes caused POST requests to fail the
  first time, and then succeed (bug introduced in RELEASE-0.2.0)
  * Fixed CE 1.6 + EE 1.11 incompatibility introduced in RELEASE-0.2.0

### RELEASE-0.3.0

The new site crawler functionality in this release adds a new User-Agent to the
default *Crawler User Agents* setting: "Nexcessnet_Turpentine/.*"
It is strongly recommended to manually add this new user agent string to the
*Crawler User Agents* setting after upgrading as Magento won't automatically
update the config setting with the new default value if the config has been
saved before.
This does not apply to new installs.

  * Added built-in crawler for automatically warming the cache. Also automatically
  warms pages that are flushed on save (product/category/CMS pages)
  * Removed hard-coded "messages" block AJAX options, the ESI/AJAX options for
  the "messages" block can now be configured in the ESI layout file like any
  other block
  * Made ESI client-cache ban (clear) events configurable. See the new "ESI
  Client Cache Purge Events" option
  * Enabling the *Normalize User-Agent* setting now also differentiates between
  desktop and mobile browsers, for caching different output for desktop and mobile
  browsers
  * Fixed *messages* block not correctly updating the layout if the layout cache
  was enabled
  * AJAX loaded blocks now (quickly) fade in instead of popping in
  * Enabled continuous-integration builds for PHP 5.2, 5.3, and 5.4 through
  [Travis-CI](https://travis-ci.org/nexcess/magento-turpentine). This is only
  relevant to developers and has no effect on users of the extension

### RELEASE-0.3.1

  * Fixed generated VCL to work with multi-store/multi-site setups with different
  URL paths

### RELEASE-0.3.2

  * Fixed Turpentine not correctly detecting and disabling ESI/AJAX on requests
  with HTTP Authorization
  * Fixed some requests being passed through Varnish instead of piped resulting
  in incorrect handling of response output
  * Removed cached object TTL extension (added in RELEASE-0.2.0) due to issues
  with cached object contention at high load
  * Improved warm-cache.sh script to handle different platforms better and
  take advantage of multiple processors/cores
  * Added advanced session validation. Varnish will now respect the Magento settings
  for validating the User-Agent, X-Forwarded-For, and Via headers, and the remote
  IP address with respect to sessions (under System > Configuration > General >
  Web > Session Validation Settings).

### RELEASE-0.4.0

There are changes to the ESI layout syntax in this release, any customizations
that have been done to the ESI layout will need to be updated

  * Improved serialization of ESI/AJAX data in URLs. ESI/AJAX URLs should now be
  50-75% as long as they previously were
  * Fixed rare cases of invalid characters in ESI/AJAX URLs, which would cause
  the block to not load or display an error message
  * Unified AJAX and ESI code paths. ESI and AJAX includes no longer use
  duplicated code which will make it easier to add other other inclusion methods
  in the future and reduces the potential for bugs
  * Selecting between AJAX and ESI inclusion is now done with the *method*
  parameter to ``setEsiOptions``, ``setAjaxOptions`` is no longer supported
  * Added ability to cache AJAX requests
  * The old *cacheType* parameter for ``setEsiOptions`` in the layout has been
  split into two new options: *scope* and *access*
    * *scope* = **global**: the cached object valid for the entire site
    * *scope* = **page**: the cached object is specific to a single page
    * *access* = **public**: the cached object is valid for any visitor
    * *access* = **private**: the cached object is valid for a single visitor's session
  * ESI block default TTL options have been removed. The new default for *public*
  ESI blocks is the same as the Varnish page TTL and *private* blocks use the
  session cookie lifetime as the TTL
  * Turpentine will now detect if there is a difference between how a block
  should be loaded vs how it is requested (ex. manually changing the *method*
  parameter for a AJAX block to ESI). If ESI debugging is turned off an error
  (403) will be returned
  * Client-side caching will now be used for static assets, in addition to
  Varnish's regular caching (via the Cache-Control header)
  * Improved page rendering performance. Pages that include ESI blocks will now
  render significantly faster (up to 50% in testing), dependant on how many
  ESI block includes were on the page
  * Fixed Turpentine ESI request entries in the Magento visitor log. This should
  result in a small speed-up for AJAX requests particularly as most of the time
  token for the request was spent doing the logging
  * Added custom VCL inclusion. Custom logic can be added to the VCL Turpentine
  generates by putting it in
  ``app/code/community/Nexcessnet/Turpentine/misc/custom_include.vcl``. If this
  file is found, Turpentine will automatically include it. Note that it is not
  Varnish version-specific like the other templates, users will need to make sure
  the VCL code used is compatible with the version of Varnish that they use

### RELEASE-0.5.0

There are changes to the ESI layout syntax (again) in this release, any
customizations that have been done to the ESI layout or added for custom blocks
will need to be updated and moved to the `local.xml` file. Additionally, the
Varnish cache will need to be fully flushed or cached pages that reference
previously cached ESI blocks will not load those blocks

  * Added support for event-based partial ESI cache flushing. ESI blocks will
  now only be flushed on the events specified for them in the layout rather than
  all of a client's ESI blocks being flushed on any event that triggered a flush
  * Removed the *purge_events* option as it was no longer needed with the new
  partial ESI flushing support
  * Removed the need for initial request pass through, Varnish will now generate
  a session token for new visitors and serve them pages from the cache on their
  first visit
  * Fixed CMS pages not being automatically flushed on update
  * Fixed piped requests in Varnish not having headers handled appropriately
  * Added ability to whitelist IP addresses for debug info from Varnish, even
  if Varnish debugging is disabled via Magento's developer IP setting
  * Added flushing on a product page, product review list, and individual review
  view on review saving (such as changing from 'Pending' to 'Approved')
  * Combined the Varnish and ESI debug options
  * Made the logging done by the extension consistent, all messages logged by
  Turpentine will be prefixed with `TURPENTINE:`
  * Added HMAC signing to the encrypted ESI data to prevent possible
  tampering with the ESI data in the request
  * The `turpentine_esi_custom.xml` file that was previously suggested to be
  used for custom ESI policies has been removed in favor of Magento's built-in
  facility for this (`local.xml`). Any ESI policies defined in `turpentine_esi_custom.xml`
  should be moved the `local.xml` file in the theme's layout directory to
  prevent issues with the order of defining blocks and setting ESI policies on
  them.

### RELEASE-0.5.1

This is the first release of Turpentine marked "stable" in Magento Connect

  * Fix PHP error when Block Logging is turned on
  * Saving a child product of a configurable or grouped product will now also
  cause the parent product(s) to be flushed. This makes saving consistent with
  the out-of-stock flushing behaviour
  * Varnish will now properly distinguish between HTTP and HTTPS responses. This
  should fix warnings in some browsers about mixing secure and insecure content
  when using Varnish+Turpentine behind an SSL proxy

### RELEASE-0.5.2

  * Added profiling calls for more debugging and performance-profiling options
  * Fixed layout XML caching logic to prevent writing to the cache on every
  page load, this should result in a small performance improvement on
  Varnish cache misses
  * Fixed multiple *frontend* cookies being generated when Magento was installed
  in the root of a domain (/)
  * Added support for translating the SID URL param into the *frontend* cookie
  for sharing sessions across different domains
  * Added utility script to dump Varnish's active VCL (util/dump-active-vcl.sh)
  for debugging
  * Improve ESI URL redirect interception, Turpentine should now prefer to
  redirect to the referring URL instead of defaulting to /checkout/cart/ all
  the time
  * Re-enabled visitor logging for ESI/AJAX requests, this fixes product
  comparison for non-logged-in visitors

### RELEASE-0.5.3

  * Added runtime model/helper/block rewriting. This negates the need to comment
  out the `core/messages` block rewrite to *really* disable the flash messages
  fix when using Turpentine in combination with certain other extensions
  * Add run-time detection of Varnish cli_buffer param to allow for longer VCLs
  after increasing the limit at Varnish start up
  * Added the new *Fix Product List Toolbar* option (under Varnish Options >
  General) to prevent caching of the products-per-page and product sorting options
  in the product list toolbar. Note that this option defaults to **Disabled**
  * Added the *store* and *currency* cookies to the request hash. This allows
  Varnish to differentiate between multiple stores with the same base URL and
  fixes currency handling.
  * Added a new option to disable the automatic application of the new VCL when
  making changes to the *Varnish Options* or *Caching Options* config sections
  * The *Flush Catalog Images Cache* and *Flush JavaScript/CSS Cache* no longer
  also flush the corresponding URLs in Varnish. This helps prevent broken images
  and script links after using those buttons.
  * Fixed Varnish ESI parsing failures that some users were experienced. This
  should prevent the "ESI processing not enabled" seen on some blocks
  * Removed the default ESI policy on the `checkout.cart` block as it was
  causing the shipping and tax quoting to not update correctly. It will be
  re-added in a future release once the quote issue is resolved
  * Added a `X-Turpentine-Block` header to ESI/AJAX responses containing the
  name of the block rendered
  * Disabled caching of the product comparison popup in the default ESI policy.
  This does not affect the product comparison *block*, only the popup window
  showing the actual comparison

### RELEASE-0.5.4

  * Fixed the Varnish-generated *frontend* cookie occasionally being set with
  the wrong path when Magento was located in a subdirectory instead of the
  root of the site
  * The built-in crawler should no longer try to crawl products that are not
  visible in the frontend
  * Missing blocks caused by layout changes between different handles should be
  handled more gracefully by Turpentine now
  * Parent products will also be banned when a child product goes out of stock
  or is saved
  * Fixed CMS URL generation for the built-in crawler
  * Fixed product stock change check to actually work
  * Improved the dummy request mock up. It should have all expected fields
  populated now and changes to it will not affect the global state or original
  request
  * Added a Magento shell script (util/varnishadm.php) that can be used like
  the real `varnishadm` command for users who aren't able to use real
  `varnishadm` command
  * Fixed duplicate flash messages experienced in some cases (thanks to
  @craigcarnell)
  * Made the `custom_include.vcl` file's location configurable, see the new
  *Custom VCL File Location* option
  * Added template parsing to the custom include VCL file, `{{variable}}`
  replacement will now also work in this file
  * Added a toggle-able Varnish Bypass to Cache Management page for bypassing
  the cache (including ESI) for a single *admin* session (thanks to @alexandre-jardin)
  * Varnish will now strip the Google-related parameters from the request to
  increase cache hit-rate

### RELEASE-0.5.5

  * Made VCL templates slightly shorter to help with running into the inline
  VCL character limit
  * The `warm-cache.sh` script now respects the `PROC` environment variable for
  the number of processes to use while warming the cache
  * [#253] Fixed VCL to correctly identify Chrome on OS X
  * [#281] Fixed unintentional flushing of full cache in some cases (thanks
  @jeroenvermeulen)
  * [#301] Fixed IE caching AJAX ESI includes (thanks @nickbock)
  * [#320] AJAX ESI should be compatible with themes that use jQuery instead of
  Prototype (thanks @steverice)
  * [#334] Bad URIs that cause the dummy request creation to throw an exception
  will now log the bad URI and hide the exception when debugging is disabled
  (thanks @ajardin)
  * [#337] Improve layout handle searching for ESI blocks in some cases (thanks
  @sanbornm)
  * [#356] Fix ESI blocks not rendering child blocks (thanks @magedev)
  * Changed Varnish-generated session ID format for compatibility with the
  SFC_Kount extension
  * Failures during advanced registry loading in ESI requests should be handled
  more gracefully

### RELEASE-0.6.0

This release *requires* a small addition to Varnish's configuration when used with
Magento CE 1.8+ or EE 1.13+, see [these instructions](https://github.com/nexcess/magento-turpentine/wiki/FAQ#wiki-i-upgraded-to-turpentine-06-and-are-the-add-to-cart-buttons-look-broken)

  * [#362] Fixed ESI detection for message block (thanks @eth8505)
  * [#380] Fixed ESI cache flushes not firing in some cases (thanks @gabrielsomoza)
  * [#391] Added Magento Composer Installer support (thanks @ajardin)
  * [#405] Fixed disabling Varnish bypass (thanks @ajardin)
  * [#438] Implemented ban lurker-friendly bans (thanks @jeroenvermeulen)
  * [#443] Fixed caching of static assets when the *Add Store Code to Urls* option
  is enabled (thanks @jeroenvermeulen)
  * [#345] Fixed compatibility with Magento CE 1.8 and EE 1.13
