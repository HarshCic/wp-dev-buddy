<?php
/*
Plugin Name: DevBuddy Twitter Feed Plugin
Description: A Twitter (v1.1) feed plugin for the developers. It's flexible, supports multiple feeds, custom styling, and aims to make your dev process swift.
Author: Eji Osigwe
Version: 1.0.0
Author URI: http://www.eji-osigwe.co.uk/

========================================================================
Copyright 2013  Eji Osigwe  (email : web@eji-osigwe.co.uk)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
========================================================================
*/


define('DBTF_PATH', plugin_dir_path(__FILE__));
define('DBTF_URL', plugins_url(null, __FILE__));

require_once DBTF_PATH.'/lib/functions.php';
$dbtf = new DB_Twitter_Feed;
require_once DBTF_PATH.'/admin/options.php';
require_once DBTF_PATH.'/lib/twitter-api-exchange.php';
require_once DBTF_PATH.'/lib/twitter-feed.php';
?>