<?php

/**
 * Nexcess.net Turpentine Extension for Magento
 * Copyright (C) 2012  Nexcess.net L.L.C.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class Nexcessnet_Turpentine_Model_Dummy_Request extends
    Mage_Core_Controller_Request_Http {

    public $GET         = null;
    public $POST        = null;
    public $SERVER      = null;
    public $ENV         = null;

    /**
     * Constructor
     *
     * If a $uri is passed, the object will attempt to populate itself using
     * that information.
     *
     * @param string|Zend_Uri $uri
     * @return void
     * @throws Zend_Controller_Request_Exception when invalid URI passed
     */
    public function __construct( $uri=null ) {
        $this->_initFakeSuperGlobals();
        $this->_fixupFakeSuperGlobals( $uri );
        try {
            parent::__construct( $uri );
        } catch( Exception $e ) {
            Mage::helper( 'turpentine/debug' )
                ->logError( 'Bad URI given to dummy request: ' . $uri );
            Mage::helper( 'turpentine/debug' )
                ->logBackTrace();
            Mage::logException( $e );
            if( Mage::helper( 'turpentine/esi' )->getEsiDebugEnabled() ) {
                throw $e;
            }
        }
    }

    /**
     * Access values contained in the superglobals as public members
     * Order of precedence: 1. GET, 2. POST, 3. COOKIE, 4. SERVER, 5. ENV
     *
     * @see http://msdn.microsoft.com/en-us/library/system.web.httprequest.item.aspx
     * @param string $key
     * @return mixed
     */
    public function __get( $key ) {
        switch( true ) {
            case isset( $this->_params[$key] ):
                return $this->_params[$key];
            case isset( $this->GET[$key] ):
                return $this->GET[$key];
            case isset( $this->POST[$key] ):
                return $this->POST[$key];
            case isset( $_COOKIE[$key] ):
                return $_COOKIE[$key];
            case ($key == 'REQUEST_URI'):
                return $this->getRequestUri();
            case ($key == 'PATH_INFO'):
                return $this->getPathInfo();
            case isset( $this->SERVER[$key] ):
                return $this->SERVER[$key];
            case isset( $this->ENV[$key] ):
                return $this->ENV[$key];
            default:
                return null;
        }
    }

    /**
     * Check to see if a property is set
     *
     * @param string $key
     * @return boolean
     */
    public function __isset( $key ) {
        switch (true) {
            case isset( $this->_params[$key] ):
                return true;
            case isset( $this->GET[$key] ):
                return true;
            case isset( $this->POST[$key] ):
                return true;
            case isset( $_COOKIE[$key] ):
                return true;
            case isset( $this->SERVER[$key] ):
                return true;
            case isset( $this->ENV[$key] ):
                return true;
            default:
                return false;
        }
    }

    /**
     * Set GET values
     *
     * @param  string|array $spec
     * @param  null|mixed $value
     * @return Zend_Controller_Request_Http
     */
    public function setQuery( $spec, $value=null ) {
        if ((null === $value) && !is_array($spec)) {
            #require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Invalid value passed to setQuery(); must be either array of values or key/value pair');
        }
        if ((null === $value) && is_array($spec)) {
            foreach ($spec as $key => $value) {
                $this->setQuery($key, $value);
            }
            return $this;
        }
        $this->GET[(string) $spec] = $value;
        return $this;
    }

    /**
     * Retrieve a member of the $_GET superglobal
     *
     * If no $key is passed, returns the entire $_GET array.
     *
     * @todo How to retrieve from nested arrays
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getQuery( $key=null, $default=null ) {
        if( null === $key ) {
            return $this->GET;
        }
        return ( isset( $this->GET[$key] ) ) ? $this->GET[$key] : $default;
    }

    /**
     * Retrieve a member of the $_POST superglobal
     *
     * If no $key is passed, returns the entire $_POST array.
     *
     * @todo How to retrieve from nested arrays
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getPost( $key=null, $default=null ) {
        if (null === $key) {
            return $this->POST;
        }

        return (isset($this->POST[$key])) ? $this->POST[$key] : $default;
    }

    /**
     * Retrieve a member of the $_SERVER superglobal
     *
     * If no $key is passed, returns the entire $_SERVER array.
     *
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getServer( $key=null, $default=null ) {
        if (null === $key) {
            return $this->SERVER;
        }

        return (isset($this->SERVER[$key])) ? $this->SERVER[$key] : $default;
    }

    /**
     * Retrieve a member of the $_ENV superglobal
     *
     * If no $key is passed, returns the entire $_ENV array.
     *
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getEnv( $key=null, $default=null ) {
        if (null === $key) {
            return $this->ENV;
        }

        return (isset($this->ENV[$key])) ? $this->ENV[$key] : $default;
    }

    /**
     * Return the value of the given HTTP header. Pass the header name as the
     * plain, HTTP-specified header name. Ex.: Ask for 'Accept' to get the
     * Accept header, 'Accept-Encoding' to get the Accept-Encoding header.
     *
     * @param string $header HTTP header name
     * @return string|false HTTP header value, or false if not found
     * @throws Zend_Controller_Request_Exception
     */
    public function getHeader( $header ) {
        if (empty($header)) {
            #require_once 'Zend/Controller/Request/Exception.php';
            throw new Zend_Controller_Request_Exception('An HTTP header name is required');
        }

        // Try to get it from the $_SERVER array first
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (isset($this->SERVER[$temp])) {
            return $this->SERVER[$temp];
        }

        // This seems to be the only way to get the Authorization header on
        // Apache
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers[$header])) {
                return $headers[$header];
            }
            $header = strtolower($header);
            foreach ($headers as $key => $value) {
                if (strtolower($key) == $header) {
                    return $value;
                }
            }
        }

        return false;
    }

    /**
     * Set the REQUEST_URI on which the instance operates
     *
     * If no request URI is passed, uses the value in $_SERVER['REQUEST_URI'],
     * $_SERVER['HTTP_X_REWRITE_URL'], or $_SERVER['ORIG_PATH_INFO'] + $_SERVER['QUERY_STRING'].
     *
     * @param string $requestUri
     * @return Zend_Controller_Request_Http
     */
    public function setRequestUri( $requestUri=null ) {
        if ($requestUri === null) {
            if (isset($this->SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
                $requestUri = $this->SERVER['HTTP_X_REWRITE_URL'];
            } elseif (
                // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
                isset($this->SERVER['IIS_WasUrlRewritten'])
                && $this->SERVER['IIS_WasUrlRewritten'] == '1'
                && isset($this->SERVER['UNENCODED_URL'])
                && $this->SERVER['UNENCODED_URL'] != ''
                ) {
                $requestUri = $this->SERVER['UNENCODED_URL'];
            } elseif (isset($this->SERVER['REQUEST_URI'])) {
                $requestUri = $this->SERVER['REQUEST_URI'];
                // Http proxy reqs setup request uri with scheme and host [and port] + the url path, only use url path
                $schemeAndHttpHost = $this->getScheme() . '://' . $this->getHttpHost();
                if (strpos($requestUri, $schemeAndHttpHost) === 0) {
                    $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
                }
            } elseif (isset($this->SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
                $requestUri = $this->SERVER['ORIG_PATH_INFO'];
                if (!empty($this->SERVER['QUERY_STRING'])) {
                    $requestUri .= '?' . $this->SERVER['QUERY_STRING'];
                }
            } else {
                return $this;
            }
        } elseif (!is_string($requestUri)) {
            return $this;
        } else {
            // Set GET items, if available
            if (false !== ($pos = strpos($requestUri, '?'))) {
                // Get key => value pairs and set $_GET
                $query = substr($requestUri, $pos + 1);
                parse_str($query, $vars);
                $this->setQuery($vars);
            }
        }

        $this->_requestUri = $requestUri;
        return $this;
    }

    /**
     * Set the base URL of the request; i.e., the segment leading to the script name
     *
     * E.g.:
     * - /admin
     * - /myapp
     * - /subdir/index.php
     *
     * Do not use the full URI when providing the base. The following are
     * examples of what not to use:
     * - http://example.com/admin (should be just /admin)
     * - http://example.com/subdir/index.php (should be just /subdir/index.php)
     *
     * If no $baseUrl is provided, attempts to determine the base URL from the
     * environment, using SCRIPT_FILENAME, SCRIPT_NAME, PHP_SELF, and
     * ORIG_SCRIPT_NAME in its determination.
     *
     * @param mixed $baseUrl
     * @return Zend_Controller_Request_Http
     */
    public function setBaseUrl( $baseUrl=null ) {
        if ((null !== $baseUrl) && !is_string($baseUrl)) {
            return $this;
        }

        if ($baseUrl === null) {
            $filename = (isset($this->SERVER['SCRIPT_FILENAME'])) ? basename($this->SERVER['SCRIPT_FILENAME']) : '';

            if (isset($this->SERVER['SCRIPT_NAME']) && basename($this->SERVER['SCRIPT_NAME']) === $filename) {
                $baseUrl = $this->SERVER['SCRIPT_NAME'];
            } elseif (isset($this->SERVER['PHP_SELF']) && basename($this->SERVER['PHP_SELF']) === $filename) {
                $baseUrl = $this->SERVER['PHP_SELF'];
            } elseif (isset($this->SERVER['ORIG_SCRIPT_NAME']) && basename($this->SERVER['ORIG_SCRIPT_NAME']) === $filename) {
                $baseUrl = $this->SERVER['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
            } else {
                // Backtrack up the script_filename to find the portion matching
                // php_self
                $path    = isset($this->SERVER['PHP_SELF']) ? $this->SERVER['PHP_SELF'] : '';
                $file    = isset($this->SERVER['SCRIPT_FILENAME']) ? $this->SERVER['SCRIPT_FILENAME'] : '';
                $segs    = explode('/', trim($file, '/'));
                $segs    = array_reverse($segs);
                $index   = 0;
                $last    = count($segs);
                $baseUrl = '';
                do {
                    $seg     = $segs[$index];
                    $baseUrl = '/' . $seg . $baseUrl;
                    ++$index;
                } while (($last > $index) && (false !== ($pos = strpos($path, $baseUrl))) && (0 != $pos));
            }

            // Does the baseUrl have anything in common with the request_uri?
            $requestUri = $this->getRequestUri();

            if (0 === strpos($requestUri, $baseUrl)) {
                // full $baseUrl matches
                $this->_baseUrl = $baseUrl;
                return $this;
            }

            if (0 === strpos($requestUri, dirname($baseUrl))) {
                // directory portion of $baseUrl matches
                $this->_baseUrl = rtrim(dirname($baseUrl), '/');
                return $this;
            }

            $truncatedRequestUri = $requestUri;
            if (($pos = strpos($requestUri, '?')) !== false) {
                $truncatedRequestUri = substr($requestUri, 0, $pos);
            }

            $basename = basename($baseUrl);
            if (empty($basename) || !strpos($truncatedRequestUri, $basename)) {
                // no match whatsoever; set it blank
                $this->_baseUrl = '';
                return $this;
            }

            // If using mod_rewrite or ISAPI_Rewrite strip the script filename
            // out of baseUrl. $pos !== 0 makes sure it is not matching a value
            // from PATH_INFO or QUERY_STRING
            if ((strlen($requestUri) >= strlen($baseUrl))
                && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0)))
            {
                $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
            }
        }

        $this->_baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    /**
     * Set the base path for the URL
     *
     * @param string|null $basePath
     * @return Zend_Controller_Request_Http
     */
    public function setBasePath( $basePath=null ) {
        if ($basePath === null) {
            $filename = (isset($this->SERVER['SCRIPT_FILENAME']))
                      ? basename($this->SERVER['SCRIPT_FILENAME'])
                      : '';

            $baseUrl = $this->getBaseUrl();
            if (empty($baseUrl)) {
                $this->_basePath = '';
                return $this;
            }

            if (basename($baseUrl) === $filename) {
                $basePath = dirname($baseUrl);
            } else {
                $basePath = $baseUrl;
            }
        }

        if (substr(PHP_OS, 0, 3) === 'WIN') {
            $basePath = str_replace('\\', '/', $basePath);
        }

        $this->_basePath = rtrim($basePath, '/');
        return $this;
    }

    /**
     * Retrieve a parameter
     *
     * Retrieves a parameter from the instance. Priority is in the order of
     * userland parameters (see {@link setParam()}), $_GET, $_POST. If a
     * parameter matching the $key is not found, null is returned.
     *
     * If the $key is an alias, the actual key aliased will be used.
     *
     * @param mixed $key
     * @param mixed $default Default value to use if key not found
     * @return mixed
     */
    public function getParam( $key, $default=null ) {
        $keyName = (null !== ($alias = $this->getAlias($key))) ? $alias : $key;

        $paramSources = $this->getParamSources();
        if (isset($this->_params[$keyName])) {
            return $this->_params[$keyName];
        } elseif (in_array('_GET', $paramSources) && (isset($this->GET[$keyName]))) {
            return $this->GET[$keyName];
        } elseif (in_array('_POST', $paramSources) && (isset($this->POST[$keyName]))) {
            return $this->POST[$keyName];
        }

        return $default;
    }

    /**
     * Retrieve an array of parameters
     *
     * Retrieves a merged array of parameters, with precedence of userland
     * params (see {@link setParam()}), $_GET, $_POST (i.e., values in the
     * userland params will take precedence over all others).
     *
     * @return array
     */
    public function getParams() {
        $return       = $this->_params;
        $paramSources = $this->getParamSources();
        if (in_array('_GET', $paramSources)
            && isset($this->GET)
            && is_array($this->GET)
        ) {
            $return += $this->GET;
        }
        if (in_array('_POST', $paramSources)
            && isset($this->POST)
            && is_array($this->POST)
        ) {
            $return += $this->POST;
        }
        return $return;
    }

    /**
     * Retrieve HTTP HOST
     *
     * @param bool $trimPort
     * @return string
     */
    public function getHttpHost( $trimPort=true ) {
        if (!isset($this->SERVER['HTTP_HOST'])) {
            return false;
        }
        if ($trimPort) {
            $host = explode(':', $this->SERVER['HTTP_HOST']);
            return $host[0];
        }
        return $this->SERVER['HTTP_HOST'];
    }

    /**
     * Set a member of the $_POST superglobal
     *
     * @param string|array $key
     * @param mixed $value
     *
     * @return Mage_Core_Controller_Request_Http
     */
    public function setPost( $key, $value=null ) {
        if (is_array($key)) {
            $this->POST = $key;
        }
        else {
            $this->POST[$key] = $value;
        }
        return $this;
    }

    /**
     * Init the fake superglobal vars
     *
     * @return null
     */
    protected function _initFakeSuperGlobals() {
        $this->GET = array();
        $this->POST = $_POST;
        $this->SERVER = $_SERVER;
        $this->ENV = $_ENV;
    }

    /**
     * Fix up the fake superglobal vars
     *
     * @param  string $uri
     * @return null
     */
    protected function _fixupFakeSuperGlobals( $uri ) {
        $parsedUrl = parse_url( $uri );

        if ( isset($parsedUrl['path']) ) {
            $this->SERVER['REQUEST_URI'] = $parsedUrl['path'];
        }
        if( isset( $parsedUrl['query'] ) && $parsedUrl['query'] ) {
            $this->SERVER['QUERY_STRING'] = $parsedUrl['query'];
            $this->SERVER['REQUEST_URI'] .= '?' . $this->SERVER['QUERY_STRING'];
        } else {
            $this->SERVER['QUERY_STRING'] = null;
        }
        parse_str( $this->SERVER['QUERY_STRING'], $this->GET );
        if( isset( $this->SERVER['SCRIPT_URI'] ) ) {
            $start = strpos( $this->SERVER['SCRIPT_URI'], '/', 9 );
            $sub = substr( $this->SERVER['SCRIPT_URI'], $start );
            $this->SERVER['SCRIPT_URI'] = substr(
                    $this->SERVER['SCRIPT_URI'], 0, $start ) .
                @str_replace(
                    $this->SERVER['SCRIPT_URL'], $parsedUrl['path'],
                    $sub, $c=1 );
        }
        if( isset( $this->SERVER['SCRIPT_URL'] ) ) {
            $this->SERVER['SCRIPT_URL'] = $parsedUrl['path'];
        }
    }

    /**
     * Check this request against the cms, standard, and default routers to fill
     * the module/controller/action/route fields.
     *
     * TODO: This whole thing is a gigantic hack. Would be nice to have a
     * better solution.
     *
     * @return null
     */
    public function fakeRouterDispatch() {
        if( $this->_cmsRouterMatch() ) {
            Mage::helper( 'turpentine/debug' )->logDebug( 'Matched router: cms' );
        } elseif( $this->_standardRouterMatch() ) {
            Mage::helper( 'turpentine/debug' )->logDebug( 'Matched router: standard' );
        } elseif( $this->_defaultRouterMatch() ) {
            Mage::helper( 'turpentine/debug' )->logDebug( 'Matched router: default' );
        } else {
            Mage::helper( 'turpentine/debug' )->logDebug( 'No router match' );
        }
    }

    /**
     *
     *
     * @return boolean
     */
    protected function _defaultRouterMatch() {
        $noRoute        = explode('/', Mage::app()->getStore()->getConfig('web/default/no_route'));
        $moduleName     = isset($noRoute[0]) ? $noRoute[0] : 'core';
        $controllerName = isset($noRoute[1]) ? $noRoute[1] : 'index';
        $actionName     = isset($noRoute[2]) ? $noRoute[2] : 'index';

        if (Mage::app()->getStore()->isAdmin()) {
            $adminFrontName = (string)Mage::getConfig()->getNode('admin/routers/adminhtml/args/frontName');
            if ($adminFrontName != $moduleName) {
                $moduleName     = 'core';
                $controllerName = 'index';
                $actionName     = 'noRoute';
                Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
            }
        }

        $this->setModuleName($moduleName)
            ->setControllerName($controllerName)
            ->setActionName($actionName);

        return true;
    }

    /**
     *
     *
     * @return bool
     */
    protected function _standardRouterMatch() {
        $router = Mage::app()->getFrontController()->getRouter( 'standard' );

        // $router->fetchDefault();

        $front = $router->getFront();
        $path = trim($this->getPathInfo(), '/');

        if ($path) {
            $p = explode('/', $path);
        } else {
            // was $router->_getDefaultPath()
            $p = explode('/', Mage::getStoreConfig('web/default/front'));
        }

        // get module name
        if ($this->getModuleName()) {
            $module = $this->getModuleName();
        } else {
            if (!empty($p[0])) {
                $module = $p[0];
            } else {
                $module = $router->getFront()->getDefault('module');
                $this->setAlias(Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS, '');
            }
        }
        if (!$module) {
            if (Mage::app()->getStore()->isAdmin()) {
                $module = 'admin';
            } else {
                return false;
            }
        }

        /**
         * Searching router args by module name from route using it as key
         */
        $modules = $router->getModuleByFrontName($module);

        if ($modules === false) {
            return false;
        }

        /**
         * Going through modules to find appropriate controller
         */
        $found = false;
        foreach ($modules as $realModule) {
            $this->setRouteName($router->getRouteByFrontName($module));

            // get controller name
            if ($this->getControllerName()) {
                $controller = $this->getControllerName();
            } else {
                if (!empty($p[1])) {
                    $controller = $p[1];
                } else {
                    $controller = $front->getDefault('controller');
                    $this->setAlias(
                        Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
                        ltrim($this->getOriginalPathInfo(), '/')
                    );
                }
            }

            // get action name
            if (empty($action)) {
                if ($this->getActionName()) {
                    $action = $this->getActionName();
                } else {
                    $action = !empty($p[2]) ? $p[2] : $front->getDefault('action');
                }
            }

            //checking if this place should be secure
            // $router->_checkShouldBeSecure($this, '/'.$module.'/'.$controller.'/'.$action);

            // $controllerClassName = $router->_validateControllerClassName($realModule, $controller);
            $controllerClassName = $router->getControllerClassName( $realModule, $controller );
            if (!$controllerClassName) {
                continue;
            } else {
                $controllerFileName = $router->getControllerFileName( $realModule, $controller );
                if( !$router->validateControllerFileName( $controllerFileName ) ) {
                    return false;
                }
                if (!class_exists($controllerClassName, false)) {
                    if (!file_exists($controllerFileName)) {
                        return false;
                    }
                    include_once $controllerFileName;

                    if (!class_exists($controllerClassName, false)) {
                        throw Mage::exception('Mage_Core', Mage::helper('core')->__('Controller file was loaded but class does not exist'));
                    }
                }
            }

            // instantiate controller class
            $controllerInstance = Mage::getControllerInstance($controllerClassName, $this, $front->getResponse());

            if (!$controllerInstance->hasAction($action)) {
                continue;
            }

            $found = true;
            break;
        }

        /**
         * if we did not found any suitable
         */
        if (!$found) {
            /*
            if ($router->_noRouteShouldBeApplied()) {
                $controller = 'index';
                $action = 'noroute';

                $controllerClassName = $router->_validateControllerClassName($realModule, $controller);
                if (!$controllerClassName) {
                    return false;
                }

                // instantiate controller class
                $controllerInstance = Mage::getControllerInstance($controllerClassName, $this,
                    $front->getResponse());

                if (!$controllerInstance->hasAction($action)) {
                    return false;
                }
            } else {
                return false;
            }
            */
            return false;
        }

        // set values only after all the checks are done
        $this->setModuleName($module);
        $this->setControllerName($controller);
        $this->setActionName($action);
        $this->setControllerModule($realModule);

        // set parameters from pathinfo
        for ($i = 3, $l = sizeof($p); $i < $l; $i += 2) {
            $this->setParam($p[$i], isset($p[$i+1]) ? urldecode($p[$i+1]) : '');
        }

        // dispatch action
        $this->setDispatched(true);
        // $controllerInstance->dispatch($action);

        return true;
    }

    /**
     *
     *
     * @return bool
     */
    protected function _cmsRouterMatch() {
        $router = Mage::app()->getFrontController()->getRouter( 'cms' );

        $identifier = trim($this->getPathInfo(), '/');

        $condition = new Varien_Object(array(
            'identifier' => $identifier,
            'continue'   => true
        ));
        Mage::dispatchEvent('cms_controller_router_match_before', array(
            'router'    => $router,
            'condition' => $condition
        ));
        $identifier = $condition->getIdentifier();

        if ($condition->getRedirectUrl()) {
            Mage::app()->getFrontController()->getResponse()
                ->setRedirect($condition->getRedirectUrl())
                ->sendResponse();
            $this->setDispatched(true);
            return true;
        }

        if (!$condition->getContinue()) {
            return false;
        }

        $page   = Mage::getModel('cms/page');
        $pageId = $page->checkIdentifier($identifier, Mage::app()->getStore()->getId());
        if (!$pageId) {
            return false;
        }

        $this->setModuleName('cms')
            ->setControllerName('page')
            ->setActionName('view')
            ->setParam('page_id', $pageId);
        $this->setAlias(
            Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
            $identifier
        );

        return true;
    }
}
