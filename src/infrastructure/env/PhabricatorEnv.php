<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @task uri URI Validation
 */
final class PhabricatorEnv {
  private static $env;

  public static function setEnvConfig(array $config) {
    self::$env = $config;
  }

  public static function getEnvConfig($key, $default = null) {
    return idx(self::$env, $key, $default);
  }

  public static function newObjectFromConfig($key, $args = array()) {
    $class = self::getEnvConfig($key);
    $object = newv($class, $args);
    $instanceof = idx(self::getRequiredClasses(), $key);
    if (!($object instanceof $instanceof)) {
      throw new Exception("Config setting '$key' must be an instance of ".
        "'$instanceof', is '".get_class($object)."'.");
    }
    return $object;
  }

  public static function getRequiredClasses() {
    return array(
      'metamta.mail-adapter' => 'PhabricatorMailImplementationAdapter',
      'metamta.maniphest.reply-handler' => 'PhabricatorMailReplyHandler',
      'metamta.differential.reply-handler' => 'PhabricatorMailReplyHandler',
      'metamta.diffusion.reply-handler' => 'PhabricatorMailReplyHandler',
      'storage.engine-selector' => 'PhabricatorFileStorageEngineSelector',
      'search.engine-selector' => 'PhabricatorSearchEngineSelector',
      'differential.field-selector' => 'DifferentialFieldSelector',
      'maniphest.custom-task-extensions-class' => 'ManiphestTaskExtensions',
      'aphront.default-application-configuration-class' =>
        'AphrontApplicationConfiguration',
      'controller.oauth-registration' =>
        'PhabricatorOAuthRegistrationController',
      'mysql.implementation' => 'AphrontMySQLDatabaseConnectionBase',
      'differential.attach-task-class' => 'DifferentialTasksAttacher',
      'mysql.configuration-provider' => 'DatabaseConfigurationProvider',
    );
  }

  public static function envConfigExists($key) {
    return array_key_exists($key, self::$env);
  }

  public static function getURI($path) {
    return rtrim(self::getEnvConfig('phabricator.base-uri'), '/').$path;
  }

  public static function getProductionURI($path) {
    // If we're passed a URI which already has a domain, simply return it
    // unmodified. In particular, files may have URIs which point to a CDN
    // domain.
    $uri = new PhutilURI($path);
    if ($uri->getDomain()) {
      return $path;
    }

    $production_domain = self::getEnvConfig('phabricator.production-uri');
    if (!$production_domain) {
      $production_domain = self::getEnvConfig('phabricator.base-uri');
    }
    return rtrim($production_domain, '/').$path;
  }

  public static function getCDNURI($path) {
    $alt = self::getEnvConfig('security.alternate-file-domain');
    if (!$alt) {
      $alt = self::getEnvConfig('phabricator.base-uri');
    }
    $uri = new PhutilURI($alt);
    $uri->setPath($path);
    return (string)$uri;
  }

  public static function getAllConfigKeys() {
    return self::$env;
  }

  public static function getDoclink($resource) {
    return 'http://www.phabricator.com/docs/phabricator/'.$resource;
  }


/* -(  URI Validation  )----------------------------------------------------- */


  /**
   * Detect if a URI satisfies either @{method:isValidLocalWebResource} or
   * @{method:isValidRemoteWebResource}, i.e. is a page on this server or the
   * URI of some other resource which has a valid protocol. This rejects
   * garbage URIs and URIs with protocols which do not appear in the
   * ##uri.allowed-protocols## configuration, notably 'javascript:' URIs.
   *
   * NOTE: This method is generally intended to reject URIs which it may be
   * unsafe to put in an "href" link attribute.
   *
   * @param string URI to test.
   * @return bool True if the URI identifies a web resource.
   * @task uri
   */
  public static function isValidWebResource($uri) {
    return self::isValidLocalWebResource($uri) ||
           self::isValidRemoteWebResource($uri);
  }


  /**
   * Detect if a URI identifies some page on this server.
   *
   * NOTE: This method is generally intended to reject URIs which it may be
   * unsafe to issue a "Location:" redirect to.
   *
   * @param string URI to test.
   * @return bool True if the URI identifies a local page.
   * @task uri
   */
  public static function isValidLocalWebResource($uri) {
    $uri = (string)$uri;

    if (!strlen($uri)) {
      return false;
    }

    if (preg_match('/\s/', $uri)) {
      // PHP hasn't been vulnerable to header injection attacks for a bunch of
      // years, but we can safely reject these anyway since they're never valid.
      return false;
    }

    // Valid URIs must begin with '/', followed by the end of the string or some
    // other non-'/' character. This rejects protocol-relative URIs like
    // "//evil.com/evil_stuff/".
    return (bool)preg_match('@^/([^/]|$)@', $uri);
  }


  /**
   * Detect if a URI identifies some valid remote resource.
   *
   * @param string URI to test.
   * @return bool True if a URI idenfies a remote resource with an allowed
   *              protocol.
   * @task uri
   */
  public static function isValidRemoteWebResource($uri) {
    $uri = (string)$uri;

    $proto = id(new PhutilURI($uri))->getProtocol();
    if (!$proto) {
      return false;
    }

    $allowed = self::getEnvConfig('uri.allowed-protocols');
    if (empty($allowed[$proto])) {
      return false;
    }

    return true;
  }

}
