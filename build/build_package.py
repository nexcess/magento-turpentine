#!/usr/bin/env python2.7
# -*- coding: utf-8 -*-

# Nexcess.net Turpentine Extension for Magento
# Copyright (C) 2012  Nexcess.net L.L.C.

# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.

# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

"""Script to generate Magento Extension package files

Run as: build_package.py <package_description.xml>

You can override the xmllint and PHP binaries used for syntax checks with the
TURPENTINE_BIN_PHP and TURPENTINE_BIN_XMLLINT environment variables. Useful for
checking with a non-default version of PHP.
"""

__title__       = 'build_package.py'
__version__     = '0.0.3'
__author__      = 'Alex Headley <aheadley@nexcess.net>'
__license__     = 'GPLv2'
__copyright__   = 'Copyright (C) 2012  Nexcess.net L.L.C.'

import os
import xml.etree.ElementTree as ElementTree
import logging
import datetime
import hashlib
import re
import tarfile
import subprocess

class Magento_Packager(object):
    BIN_PHP = os.environ.get('TURPENTINE_BIN_PHP', 'php')
    BIN_XMLLINT = os.environ.get('TURPENTINE_BIN_XMLLINT', 'xmllint')
    BIN_BASH = os.environ.get('TURPENTINE_BIN_BASH', 'bash')
    BIN_GCC = os.environ.get('TURPENTINE_BIN_GCC', 'gcc')

    TARGET_DIRS = {
        'magelocal':        'app/code/local',
        'magecommunity':    'app/code/community',
        'magecore':         'app/code/core',
        'magedesign':       'app/design',
        'mageetc':          'app/etc',
    }
    MAGE_PKG_XML_FILENAME   = 'package.xml'

    def __init__(self, base_dir, debug=False):
        self._base_dir = base_dir
        self._logger = logging.getLogger(self.__class__.__name__)
        if debug:
            self._logger.setLevel(logging.DEBUG)
        else:
            self._logger.setLevel(logging.INFO)
        self._file_list = []
        self._logger.debug('Packager init with base dir: %s', self._base_dir)
        self._logger.debug('Using PHP binary: %s', self.BIN_PHP)
        self._logger.debug('Using xmllint binary: %s', self.BIN_XMLLINT)

    def do_syntax_check(self):
        self._logger.info('Running syntax check on %d files', len(self._file_list))
        result = True
        syntax_map = {
            '.php':     self._php_syntax_check,
            '.phtml':   self._php_syntax_check,
            '.xml':     self._xml_syntax_check,
            '.sh':      self._bash_syntax_check,
            '.bash':    self._bash_syntax_check,
            '.c':       self._gcc_syntax_check,
        }
        def unsupported_syntax_check(filename):
            self._logger.debug('Skipping syntax check for unsupported file: %s',
                filename)
            return True

        for filename in self._file_list:
            syntax_check = syntax_map.get(os.path.splitext(filename)[1].lower(),
                unsupported_syntax_check)
            if not syntax_check(filename):
                self._logger.warning('Syntax check failed for file: %s', filename)
                result = False
        return result

    def build_package_xml(self, connect_file):
        self._logger.info('Building package from connect file: %s', connect_file)
        connect_dom = ElementTree.parse(connect_file)
        ext_name = connect_dom.find('name').text
        self._logger.debug('Using "%s" as extension name', ext_name)
        config_dom = self._get_config_dom(ext_name, connect_dom.find('channel').text)
        module_dom = self._get_module_dom(ext_name)

        self._logger.info('Building extension %s version %s', ext_name,
            config_dom.find('modules/%s/version' % ext_name).text)

        if connect_dom.find('channel').text != \
                module_dom.find('modules/%s/codePool' % ext_name).text:
            self._logger.warning('Connect file code pool (%s) does not match module code pool (%s)',
                connect_dom.find('channel').text,
                module_dom.find('modules/%s/codePool' % ext_name).text)

        pkg_dom = self._build_package_dom(ElementTree.Element('package'),
            connect_dom, config_dom, module_dom)

        self._logger.info('Finished building extension package XML')

        return pkg_dom

    def build_tarball(self, pkg_xml, tarball_name=None, keep_pkg_xml=False):
        manifest_filename = '%s/build/manifest-%s.xml' % \
            (self._base_dir, pkg_xml.findtext('./version'))
        if tarball_name is None:
            tarball_name = '%s/build/%s-%s.tgz' % (self._base_dir,
                pkg_xml.findtext('./name'), pkg_xml.findtext('./version'))
        self._logger.info('Writing tarball to: %s', tarball_name)
        cdir = os.getcwd()
        os.chdir(self._base_dir)
        with open(manifest_filename, 'w') as xml_file:
            ElementTree.ElementTree(pkg_xml).write(xml_file, 'utf-8', True)
        self._logger.debug('Wrote package XML')
        with tarfile.open(tarball_name, 'w:gz') as tarball:
            for filename in self._file_list:
                alt_filename = filename.replace(self._base_dir + '/', '')
                self._logger.debug('Adding file to tarball: %s', alt_filename)
                tarball.add(filename, alt_filename)
            self._logger.debug('Adding file to tarball: %s',
                self.MAGE_PKG_XML_FILENAME)
            tarball.add(manifest_filename, self.MAGE_PKG_XML_FILENAME)
        self._logger.info('Finished writing tarball')
        if not keep_pkg_xml:
            os.unlink(manifest_filename)
        os.chdir(cdir)
        return tarball_name

    def _build_package_dom(self, pkg_dom, connect_dom, config_dom, module_dom):
        ext_name = connect_dom.find('name').text
        now = datetime.datetime.now()
        commit_hash = self._get_git_hash()
        self._logger.debug('Using commit hash: %s', commit_hash)
        extension = {
            'name': ext_name,
            'version': config_dom.find('modules/%s/version' % ext_name).text,
            'stability': connect_dom.find('stability').text,
            'license': connect_dom.find('license').text,
            'channel': connect_dom.find('channel').text,
            'extends': None,
            'summary': connect_dom.find('summary').text,
            'description': connect_dom.find('description').text,
            'notes': connect_dom.find('notes').text,
            'authors': None,
            'date': now.date().isoformat(),
            'time': now.time().strftime('%H:%M:%S'),
            'contents': None,
            'compatibile': None,
            'dependencies': None,
            '__packager': '%s v%s' % (__title__, __version__),
            '__commit_hash': commit_hash,
        }
        for key, value in extension.iteritems():
            tag = ElementTree.SubElement(pkg_dom, key)
            if value:
                tag.text = value
            self._logger.debug('Added package element <%s> = "%s"', key, value)

        pkg_dom.find('license').set('uri', connect_dom.find('license_uri').text)
        self._build_authors_tag(pkg_dom.find('authors'), connect_dom)
        self._build_contents_tag(pkg_dom.find('contents'), connect_dom)
        self._build_dependencies_tag(pkg_dom.find('dependencies'), connect_dom)
        return pkg_dom

    def _build_authors_tag(self, authors_tag, connect_dom):
        for i, _ in enumerate(connect_dom.findall('authors/name/name')):
            author_tag = ElementTree.SubElement(authors_tag, 'author')
            name_tag = ElementTree.SubElement(author_tag, 'name')
            name_tag.text = list(connect_dom.findall('authors/name/name'))[i].text
            user_tag = ElementTree.SubElement(author_tag, 'user')
            user_tag.text = list(connect_dom.findall('authors/user/user'))[i].text
            email_tag = ElementTree.SubElement(author_tag, 'email')
            email_tag.text = list(connect_dom.findall('authors/email/email'))[i].text
            self._logger.info('Added author %s (%s) <%s>', name_tag.text,
                user_tag.text, email_tag.text)
        return authors_tag

    def _build_contents_tag(self, contents_tag, connect_dom):
        used_target_paths = list(set(el.text for el in connect_dom.findall('contents/target/target')))
        targets = list(self._iterate_targets(connect_dom))
        for target_path_name in used_target_paths:
            target_tag = ElementTree.SubElement(contents_tag, 'target')
            target_tag.set('name', target_path_name)
            self._logger.debug('Adding objects for target: %s', target_path_name)
            for target in (t for t in targets if t['target'] == target_path_name):
                if target['type'] == 'dir':
                    self._logger.info('Recursively adding dir: %s::%s',
                        target['target'], target['path'])
                    for obj_path, obj_name, obj_hash in self._walk_path(os.path.join(
                                self._base_dir, self.TARGET_DIRS[target['target']], target['path']),
                            target['include'], target['ignore']):
                        parent_tag = self._make_parent_tags(target_tag, obj_path.replace(
                            os.path.join(self._base_dir, self.TARGET_DIRS[target['target']]), '').strip('/'))
                        if obj_hash is None:
                            obj_tag = ElementTree.SubElement(parent_tag, 'dir')
                            obj_tag.set('name', obj_name)
                            self._logger.debug('Added directory: %s', obj_name)
                        else:
                            obj_tag = ElementTree.SubElement(parent_tag, 'file')
                            obj_tag.set('name', obj_name)
                            obj_tag.set('hash', obj_hash)
                            self._file_list.append(os.path.join(obj_path, obj_name))
                            self._logger.debug('Added file: %s (%s)', obj_name, obj_hash)
                else:
                    parent_tag = self._make_parent_tags(target_tag, os.path.dirname(target['path']))
                    obj_name = os.path.basename(target['path'])
                    obj_hash = self._get_file_hash(os.path.join(
                        self._base_dir, self.TARGET_DIRS[target['target']],
                        target['path']))
                    obj_tag = ElementTree.SubElement(parent_tag, 'file')
                    obj_tag.set('name', obj_name)
                    obj_tag.set('hash', obj_hash)
                    self._file_list.append(os.path.join(self._base_dir,
                        self.TARGET_DIRS[target['target']], target['path']))
                    self._logger.info('Added single file: %s::%s (%s)',
                        target['target'], target['path'], obj_hash)
        self._logger.debug('Finished adding targets')
        return contents_tag

    def _make_parent_tags(self, target_tag, tag_path):
        if tag_path:
            parts = tag_path.split('/')
            current_node = target_tag
            for part in parts:
                new_node = current_node.find('dir[@name=\'%s\']' % part)
                if new_node is None:
                    new_node = ElementTree.SubElement(current_node, 'dir')
                    new_node.set('name', part)
                current_node = new_node
            return current_node
        else:
            return target_tag

    def _iterate_targets(self, connect_dom):
        for i, el in enumerate(connect_dom.findall('contents/target/target')):
            yield {
                'target':   connect_dom.find('contents/target').getchildren()[i].text,
                'path':     connect_dom.find('contents/path').getchildren()[i].text,
                'type':     connect_dom.find('contents/type').getchildren()[i].text,
                'include':  connect_dom.find('contents/include').getchildren()[i].text,
                'ignore':   connect_dom.find('contents/ignore').getchildren()[i].text,
            }

    def _get_file_hash(self, filename):
        with open(filename, 'rb') as f:
            return hashlib.md5(f.read()).hexdigest()

    def _walk_path(self, path, include, ignore):
        for dirpath, dirnames, filenames in os.walk(path):
            for filename in filenames:
                if (include and re.match(include[1:-1], filename) and not \
                        (ignore and re.match(ignore[1:-1], filename))):
                    yield dirpath, filename, self._get_file_hash(os.path.join(dirpath, filename))
            for dirname in dirnames:
                if (include and re.match(include[1:-1], dirname) and not \
                        (ignore and re.match(ignore[1:-1], dirname))):
                    yield dirpath, dirname, None

    def _build_dependencies_tag(self, dependencies_tag, connect_dom):
        req_tag = ElementTree.SubElement(dependencies_tag, 'required')
        php_tag = ElementTree.SubElement(req_tag, 'php')
        min_tag = ElementTree.SubElement(php_tag, 'min')
        min_tag.text = connect_dom.findtext('depends_php_min')
        max_tag = ElementTree.SubElement(php_tag, 'max')
        max_tag.text = connect_dom.findtext('depends_php_max')
        self._logger.debug('Finished adding dependencies')
        return dependencies_tag

    def _get_module_dom(self, ext_name):
        fn = os.path.join(self._base_dir, 'app/etc/modules', ext_name + '.xml')
        self._logger.debug('Using extension config file: %s', fn)
        return ElementTree.parse(fn)

    def _get_config_dom(self, ext_name, codepool):
        ns, ext = ext_name.split('_', 2)
        fn = os.path.join(self._base_dir, 'app/code', codepool, ns, ext, 'etc', 'config.xml')
        self._logger.debug('Using extension module file: %s', fn)
        return ElementTree.parse(fn)

    def _get_git_hash(self):
        """Get the current git commit hash

        Blatently stolen from:
        https://github.com/overviewer/Minecraft-Overviewer/blob/master/overviewer_core/util.py#L40
        """
        try:
            with open(os.path.join(self._base_dir, '.git', 'HEAD'), 'r') as head_file:
                ref = head_file.read().strip()
            if ref[:5] == 'ref: ':
                with open(os.path.join(self._base_dir, '.git', ref[5:]), 'r') as commit_file:
                    return commit_file.read().strip()
            else:
                return ref[5:]
        except Exception as err:
            self._logger.warning('Couldnt read the git commit hash: %s :: %s',
                err.__class__.__name__, err)
            return 'UNKNOWN'

    def _php_syntax_check(self, filename):
        self._logger.debug('Checking PHP syntax for file: %s', filename)
        return self._run_quiet(self.BIN_PHP, '-l', filename)

    def _xml_syntax_check(self, filename):
        self._logger.debug('Checking XML syntax for file: %s', filename)
        return self._run_quiet(self.BIN_XMLLINT, '--format', filename)

    def _bash_syntax_check(self, filename):
        self._logger.debug('Checking Bash syntax for file: %s', filename)
        return self._run_quiet(self.BIN_BASH, '-n', filename)

    def _gcc_syntax_check(self, filename):
        self._logger.debug('Checking C syntax for file: %s', filename)
        return self._run_quiet(self.BIN_GCC, '-fsyntax-only', filename)

    def _run_quiet(self, *pargs):
        with open('/dev/null', 'w') as dev_null:
            return not bool(subprocess.call(pargs, stdin=None, stdout=dev_null,
                stderr=dev_null))

def main(base_path, pkg_desc_file, skip_tarball=False, tarball=None, keep_package_xml=False,
        debug=False, skip_syntax_check=False, **kwargs):
    pkgr = Magento_Packager(base_path, debug=debug)
    pkg_xml = pkgr.build_package_xml(pkg_desc_file)
    if not skip_syntax_check:
        if not pkgr.do_syntax_check():
            raise SystemExit('Syntax check failed!')
    if not skip_tarball:
        pkgr.build_tarball(pkg_xml, tarball_name=tarball,
            keep_pkg_xml=keep_package_xml)

if __name__ == '__main__':
    import sys
    import optparse
    logging.basicConfig()
    parser = optparse.OptionParser()
    parser.add_option('-d', '--debug', action='store_true',
        default=os.environ.get('MPKG_DEV', False))
    parser.add_option('-p', '--keep-package-xml', action='store_true', default=False)
    parser.add_option('-t', '--tarball', action='store', default=None)
    parser.add_option('-T', '--skip-tarball', action='store_true', default=False)
    parser.add_option('-S', '--skip-syntax-check', action='store_true', default=False)
    opts, args = parser.parse_args()
    base_path = os.path.dirname(os.path.dirname(os.path.abspath(sys.argv[0])))
    if len(args):
        main(base_path, args[0], **vars(opts))
    else:
        print 'Missing package definition file argument (mage-package.xml)!'
