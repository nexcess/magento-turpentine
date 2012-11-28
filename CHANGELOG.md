Change Log
==========

RELEASE-0.0.4
-------------

  * Initial Magento Connect release

RELEASE-0.0.5
-------------

  * Add new VCL backend for Magento admin with longer timeout values

RELEASE-0.0.6
-------------

  * Make backend timeouts and grace period configurable
  * Initial Magento Enterprise Edition compatible release on Magento Connect
  * Improve cache hit-rate in some cases

RELEASE-0.1.0
-------------

  * Hole-punching support via ESI
  * Crawler support for cache warming
  * Most issues with Varnish socket communication will display flash errors
  instead of throwing exceptions
  * Many/most configuration options were changed or renamed

RELEASE-0.1.1
-------------

  * Fixed EE ESI template and layout install location
  * Fix missing files in previous Magento Connect package

RELEASE-0.1.2
-------------

  * Disable ESI injection if request does not come through Varnish to prevent
  broken output for SSL-enabled sites
