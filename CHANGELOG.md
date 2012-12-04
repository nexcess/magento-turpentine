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
