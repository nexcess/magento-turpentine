# Webandpeople CustomMenu extension

## Patch #0

Fixes compatibility issues. Normally the template is set by a block that isn't
included in the ESI rendering context, patch forces the template to be set when
rendering.
