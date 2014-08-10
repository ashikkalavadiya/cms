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
namespace Block\Model\Table;

use Cake\Database\Schema\Table as Schema;
use Cake\Event\Event;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Block\Model\Entity\Block;
use QuickApps\Utility\HookTrait;

/**
 * Represents "blocks" database table.
 *
 */
class BlocksTable extends Table {

	use HookTrait;

/**
 * Initialize method.
 *
 * @param array $config The configuration for the Table.
 * @return void
 */
	public function initialize(array $config) {
		$this->hasMany('BlockRegions', [
			'className' => 'Block.BlockRegions',
			'dependent' => true,
			'propertyName' => 'region',
		]);
		$this->belongsToMany('User.Roles', [
			'className' => 'User.Roles',
			'dependent' => false,
			'propertyName' => 'roles',
		]);
	}

/**
 * Alter the schema used by this table.
 *
 * @param \Cake\Database\Schema\Table $table The table definition fetched from database
 * @return \Cake\Database\Schema\Table the altered schema
 */
	protected function _initializeSchema(Schema $table) {
		$table->columnType('locale', 'serialized');
		$table->columnType('settings', 'serialized');
		return $table;
	}

/**
 * Default validation rules.
 *
 * @param \Cake\Validation\Validator $validator
 * @return \Cake\Validation\Validator
 */
	public function validationDefault(Validator $validator) {
		return $validator
			->validatePresence('title')
			->add('title', [
				'notEmpty' => [
					'rule' => 'notEmpty',
					'message' => __d('block', 'You need to provide a title.'),
				],
				'length' => [
					'rule' => ['minLength', 3],
					'message' => __d('block', 'Title need to be at least 3 characters long.'),
				],
			])
			->validatePresence('description')
			->add('description', [
				'notEmpty' => [
					'rule' => 'notEmpty',
					'message' => __d('block', 'You need to provide a description.'),
				],
				'length' => [
					'rule' => ['minLength', 3],
					'message' => __d('block', 'Description need to be at least 3 characters long.'),
				],
			])
			->add('visibility', 'validVisibility', [
				'rule' => function ($value, $context) {
					return in_array($value, ['except', 'only', 'php']);
				},
				'message' => __d('block', 'Invalid visibility.'),
			])
			->add('delta', [
				'unique' => [
					'rule' => ['validateUnique', ['scope' => 'handler']],
					'message' => __d('block', 'Invalid delta, there is already a block with the same [delta, handler] combination.'),
					'provider' => 'table',
				]
			])
			->validatePresence('handler', 'create')
			->add('handler', 'validHandler', [
				'rule' => 'notEmpty',
				'on' => 'create',
				'message' => __d('menu', 'Invalid menu handler'),
			]);
	}

/**
 * Validation rules for custom blocks.
 *
 * Plugins may define their own blocks, in these cases the "body" value is optional.
 * But blocks created by users (on the Blocks administration page) are required to have a valid "body".
 *
 * @param \Cake\Validation\Validator $validator
 * @return \Cake\Validation\Validator
 */
	public function validationCustom(Validator $validator) {
		return $this->validationDefault($validator)
			->validatePresence('body')
			->add('body', [
				'notEmpty' => [
					'rule' => 'notEmpty',
					'message' => __d('block', "You need to provide a content for block's body."),
				],
				'length' => [
					'rule' => ['minLength', 3],
					'message' => __d('block', "Block's body need to be at least 3 characters long."),
				],
			]);
	}

/**
 * Triggers the "Block.<handler>.beforeValidate" hook, so plugins may do any logic their require.
 *
 * @param \Cake\Event\Event $event
 * @param \Block\Model\Entity\Block $block
 * @param array $options
 * @return boolean False if save operation should not continue, true otherwise
 */
	public function beforeValidate(Event $event, Block $block, $options, Validator $validator) {
		$blockEvent = $this->invoke("Block.{$block->handler}.beforeValidate", $event->subject, $block, $options, $validator);
		if ($blockEvent->isStopped() || $blockEvent->result === false) {
			return false;
		}
		return true;
	}

/**
 * Triggers the "Block.<handler>.afterValidate" hook, so plugins may do any logic their require.
 *
 * @param \Cake\Event\Event $event
 * @param \Block\Model\Entity\Block $block
 * @param array $options
 * @return void
 */
	public function afterValidate(Event $event, Block $block, $options, Validator $validator) {
		$this->invoke("Block.{$block->handler}.afterValidate", $event->subject, $block, $options, $validator);
	}

/**
 * Triggers the "Block.<handler>.beforeSave" hook, so plugins may do any logic their require.
 *
 * @param \Cake\Event\Event $event
 * @param \Block\Model\Entity\Block $block
 * @param array $options
 * @return boolean False if save operation should not continue, true otherwise
 */
	public function beforeSave(Event $event, Block $block, $options = []) {
		$blockEvent = $this->invoke("Block.{$block->handler}.beforeSave", $event->subject, $block, $options);
		if ($blockEvent->isStopped() || $blockEvent->result === false) {
			return false;
		}
		return true;
	}

/**
 * Triggers the "Block.<handler>.afterSave" hook, so plugins may do any logic their require.
 *
 * @param \Cake\Event\Event $event
 * @param \Block\Model\Entity\Block $block
 * @param array $options
 * @return void
 */
	public function afterSave(Event $event, Block $block, $options = []) {
		$this->invoke("Block.{$block->handler}.afterSave", $event->subject, $block, $options);
	}

/**
 * Triggers the "Block.<handler>.beforeDelete" hook, so plugins may do any logic their require.
 *
 * @param \Cake\Event\Event $event
 * @param \Block\Model\Entity\Block $block
 * @param array $options
 * @return boolean False if delete operation should not continue, true otherwise
 */
	public function beforeDelete(Event $event, Block $block, $options = []) {
		$blockEvent = $this->invoke("Block.{$block->handler}.beforeDelete", $event->subject, $block, $options);
		if ($blockEvent->isStopped() || $blockEvent->result === false) {
			return false;
		}
		return true;
	}

/**
 * Triggers the "Block.<handler>.afterDelete" hook, so plugins may do any logic their require.
 *
 * @param \Cake\Event\Event $event
 * @param \Block\Model\Entity\Block $block
 * @param array $options
 * @return void
 */
	public function afterDelete(Event $event, Block $block, $options = []) {
		$this->invoke("Block.{$block->handler}.afterDelete", $event->subject, $block, $options);
	}

}
