<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since	 2.0.0
 * @author	 Christopher Castro <chris@quickapps.es>
 * @link	 http://www.quickappscms.org
 * @license	 http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
namespace User\Model\Table;

use Cake\ORM\Table;
use User\Model\Entity\User;

/**
 * Represents "permissions" database table.
 *
 */
class PermissionsTable extends Table {

/**
 * Initialize a table instance. Called after the constructor.
 *
 * @param array $config Configuration options passed to the constructor
 * @return void
 */
	public function initialize(array $config) {
		$this->belongsTo('Acos', [
			'className' => 'User.Acos',
			'propertyName' => 'aco',
		]);
	}

/**
 * Checks if the given $aro has access to action $action in $aco
 *
 * @return bool true if user has access to action in ACO, false otherwise
 */
	public function check(User $user, $path) {
		$acoPath = $this->Acos->node($path);

		if (!$acoPath) {
			return false;
		}

		if (!$user->roles) {
			return false;
		}

		$acoIDs = $acoPath->extract('id');
		foreach ($user->roles as $role_id) {
			$permission = $this->find()
				->where([
					'role_id' => $role_id,
					'aco_id IN' => $acoIDs,
				]);
			if ($permission) {
				return true;
			}
		}

		return false;
	}

}
