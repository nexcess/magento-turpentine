# Release Guide

  1. Switch to *devel* branch
  2. Bump version in `app/code/community/Nexcessnet/Turpentine/etc/config.xml`
  under `config/modules/Nexcessnet_Turpentine/version`
  3. Commit
  4. Merge *devel* into *master*
  5. Tag *master* branch with `release-<version>`
  6. Push *master* and *devel* branches to GitHub
  7. Run make: `make all`
    * The `all` is important, running bare `make` does not build the package
  8. Upload new package (from `build/`) to Magento Connect, use only the notes
  from the latest section of the release notes from
  `build/magento-connect-changelog-<version>.html` in the release notes box
  9. Update the description box with contents of
  `build/magento-connect-desc-<version>.html`
  10. ???
  11. Profit
