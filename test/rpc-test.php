#!/usr/bin/php
<?php
/**
 * @package Horde_RPC
 */

define('HORDE_BASE', dirname(dirname(dirname(dirname(__FILE__)))));
define('AUTH_HANDLER', true);
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
require_once HORDE_BASE . '/lib/base.php';
require_once 'Horde/RPC.php';

if (!isset($argv) || count($argv) < 2) {
    die("Can't read arguments.\n");
}

array_shift($argv);
$testno = array_shift($argv);
$user   = @array_shift($argv);
$pass   = @array_shift($argv);

switch ($testno) {
case 0:
    $response = Horde_RPC::request('xmlrpc', Horde::url('rpc.php', true, -1),
                                   'system.listMethods', null,
                                   array('user' => $user, 'pass' => $pass));
    break;

case 1:
    $response = Horde_RPC::request('xmlrpc', Horde::url('rpc.php', true, -1),
                                   'system.describeMethods', array('tasks.list'),
                                   array('user' => $user, 'pass' => $pass));
    break;

case 2:
    $response = Horde_RPC::request('xmlrpc', Horde::url('rpc.php', true, -1),
                                   'tasks.list', array(0),
                                   array('user' => $user, 'pass' => $pass));
    break;

case 3:
    $response = Horde_RPC::request('xmlrpc', 'http://dev.horde.org/horde/rpc.php',
                                   'system.listMethods', null,
                                   array('user' => $user, 'pass' => $pass));
    break;

case 4:
    $response = Horde_RPC::request('xmlrpc', 'http://pear.php.net/xmlrpc.php',
                                   'package.listAll');
    break;

case 5:
    $response = Horde_RPC::request('soap', 'http://api.google.com/search/beta2',
                                   'doGoogleSearch',
                                   array('key' => '5a/mF/FQFHKTD4vgNxfFeODwtLdifPPq',
                                         'q' => 'Horde IMP',
                                         'start' => 0,
                                         'maxResults' => 10,
                                         'filter' => true,
                                         'restrict' => '',
                                         'safeSearch' => false,
                                         'lr' => '',
                                         'ie' => 'iso-8859-1',
                                         'oe' => 'iso-8859-1'),
                                   array('namespace' => 'urn:GoogleSearch'));
    break;

case 6:
    $response = Horde_RPC::request('soap', Horde::url('rpc.php', true, -1),
                                   'tasks.list', array(),
                                   array('namespace' => 'urn:horde',
                                         'user' => $user,
                                         'pass' => $pass));
    break;

case 7:
    $response = Horde_RPC::request('soap', Horde::url('rpc.php', true, -1),
                                   array_shift($argv), $argv,
                                   array('namespace' => 'urn:horde',
                                         'user' => $user,
                                         'pass' => $pass));
    break;

case 8:
    $response = Horde_RPC::request('xmlrpc', Horde::url('rpc.php', true, -1),
                                   array_shift($argv), $argv,
                                   array('user' => $user, 'pass' => $pass));
    break;

case 9:
    $response = Horde_RPC::request('jsonrpc', Horde::url('rpc.php', true, -1),
                                   array_shift($argv), $argv,
                                   array('user' => $user, 'pass' => $pass));
    break;

}

if (is_a($response, 'PEAR_Error')) {
    echo "===error======\n";
    echo $response->getMessage();
    echo "\n";
    $info = $response->getUserInfo();
    if (is_string($info)) {
        echo strtr($info, array_flip(get_html_translation_table(HTML_ENTITIES)));
    } else {
        var_dump($info);
    }
    echo "\n==============\n";
} else {
    echo "===value======\n";
    var_dump($response);
    echo "==============\n";
}
