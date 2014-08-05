<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since	 1.0.0
 * @author	 Christopher Castro <chris@quickapps.es>
 * @link	 http://www.quickappscms.org
 * @license	 http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
namespace Node\Config;

use Cake\Core\Configure;
use Cake\Routing\Router;

if (!empty(Configure::read('QuickApps.node_types'))) {
	$nodeTypesPattern = implode('|', array_map('preg_quote', (array)Configure::read('QuickApps.node_types')));
	Router::connect('/:node_type_slug/:node_slug.html', [
		'plugin' => 'Node',
		'controller' => 'Serve',
		'action' => 'details'
	], [
		'node_type_slug' => $nodeTypesPattern,
		'node_slug' => '[a-z0-9\-]+',
		'pass' => ['node_type_slug', 'node_slug'],
		'_name' => 'node_details',
	]);
}

Router::connect('/find/:criteria', [
	'plugin' => 'Node',
	'controller' => 'Serve',
	'action' => 'search',
], [
	'pass' => ['criteria']
]);

Router::connect('/', [
	'plugin' => 'Node',
	'controller' => 'Serve',
	'action' => 'frontpage'
]);