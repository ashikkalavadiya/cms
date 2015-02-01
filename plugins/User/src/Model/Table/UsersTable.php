<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since    2.0.0
 * @author   Christopher Castro <chris@quickapps.es>
 * @link     http://www.quickappscms.org
 * @license  http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
namespace User\Model\Table;

use Cake\Auth\DefaultPasswordHasher;
use Cake\Error\FatalErrorException;
use Cake\Event\Event;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use User\Model\Entity\User;

/**
 * Represents "users" database table.
 *
 * @method    addSearchOperator(string $name, mixed $handler)
 */
class UsersTable extends Table
{

    /**
     * Initialize a table instance. Called after the constructor.
     *
     * @param array $config Configuration options passed to the constructor
     * @return void
     */
    public function initialize(array $config)
    {
        $this->belongsToMany('Roles', [
            'className' => 'User.Roles',
            'joinTable' => 'users_roles',
            'through' => 'UsersRoles',
            'propertyName' => 'roles',
        ]);
        $this->addBehavior('Timestamp', [
            'events' => [
                'Users.login' => [
                    'last_login' => 'always'
                ]
            ]
        ]);
        $this->addBehavior('Search.Searchable', [
            'fields' => function ($user) {
                $words = '';
                $words .= empty($user->name) ?: " {$user->name}";
                $words .= empty($user->username) ?: " {$user->username}";
                $words .= empty($user->email) ?: " {$user->email}";
                $words .= empty($user->web) ?: " {$user->web}";

                if (!empty($user->_fields)) {
                    foreach ($user->_fields as $vf) {
                        $words .= ' ' . trim($vf->value);
                    }
                }
                return $words;
            }
        ]);
        $this->addBehavior('Field.Fieldable');

        $this->addSearchOperator('created', 'operatorCreated');
        $this->addSearchOperator('limit', 'operatorLimit');
        $this->addSearchOperator('order', 'operatorOrder');
        $this->addSearchOperator('email', 'operatorEmail');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator object
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        return $validator
            ->requirePresence('name')
            ->notEmpty('name', __d('user', 'You must provide a name.'))
            ->requirePresence('username', 'create')
            ->add('username', [
                'characters' => [
                    'rule' => function ($value, $context) {
                        return preg_match('/^[a-zA-Z0-9\_]{3,}$/', $value) === 1;
                    },
                    'provider' => 'table',
                    'message' => __d('user', 'Invalid username. Only letters, numbers and "_" symbol, and at least three characters long.'),
                ],
                'unique' => [
                    'rule' => 'validateUnique',
                    'provider' => 'table',
                    'message' => __d('user', 'Username already in use.'),
                ],
            ])
            ->requirePresence('email')
            ->notEmpty('email', __d('user', 'e-Mail cannot be empty.'))
            ->add('email', [
                'unique' => [
                    'rule' => 'validateUnique',
                    'provider' => 'table',
                    'message' => __d('user', 'e-Mail already in use.'),
                ]
            ])
            ->add('username', [
                'unique' => [
                    'rule' => 'validateUnique',
                    'provider' => 'table',
                    'message' => __d('user', 'Username already in use.'),
                ]
            ])
            ->requirePresence('password', 'create')
            ->allowEmpty('password', 'update')
            ->add('password', [
                'compare' => [
                    'rule' => function ($value, $context) {
                        $value2 = isset($context['data']['password2']) ? $context['data']['password2'] : false;
                        return (new DefaultPasswordHasher)->check($value2, $value) || $value == $value2;
                    },
                    'message' => __d('user', 'Password mismatch.'),
                ],
                'length' => [
                    'rule' => function ($value, $context) {
                        $raw = isset($context['data']['password2']) ? $context['data']['password2'] : '';
                        return strlen($raw) >= 6;
                    },
                    'message' => __d('user', 'Password must be at least 6 characters long.'),
                ]
            ])
            ->allowEmpty('web')
            ->add('web', 'validUrl', [
                'rule' => 'url',
                'message' => __d('user', 'Invalid URL.'),
            ]);
    }

    /**
     * If not password is sent means user is not changing it.
     *
     * @param \Cake\Event\Event $event The event that was triggered
     * @param \User\Model\Entity\User $user User entity being saved
     * @return void
     */
    public function beforeSave(Event $event, User $user)
    {
        if (!$user->isNew() && $user->has('password') && empty($user->password)) {
            $user->unsetProperty('password');
            $user->dirty('password', false);
        }
    }

    /**
     * Generates a unique token for the given user. The generated token is
     * automatically persisted on DB.
     *
     * Tokens are unique and follows the pattern below:
     *
     *     <user_id>-<32-random-letters-and-numbers>
     *
     * @param \User\Model\Entity\User $user The user for which generate the token
     * @return \User\Model\Entity\User The user entity with a the new token property
     * @throws \Cake\Error\FatalErrorException When an invalid user entity was given
     */
    public function updateToken(User $user)
    {
        if (!$user->has('id')) {
            throw new FatalErrorException(__d('user', 'UsersTable::updateToken(), no ID was found for the given entity.'));
        }

        $user->set('token', $user->id . '-' . md5(uniqid($user->id, true)));
        $this->save($user, ['validate' => false]);
        return $user;
    }

    /**
     * Counts the number of administrators ins the system.
     *
     * @return int
     */
    public function countAdministrators()
    {
        return $this->find()
            ->matching('Roles', function ($q) {
                return $q->where(['Roles.id' => ROLE_ID_ADMINISTRATOR]);
            })
            ->count();
    }

    /**
     * Handles "created" search operator.
     *
     *     created:<date>
     *     created:<date1>..<date2>
     *
     * Dates must be in YEAR-MONTH-DATE format. e.g. `2014-12-30`
     *
     * @param \Cake\ORM\Query $query The query object
     * @param string $value Operator's arguments
     * @param bool $negate Whether this operator was negated or not
     * @param string $orAnd and|or
     * @return void
     */
    public function operatorCreated(Query $query, $value, $negate, $orAnd)
    {
        if (strpos($value, '..') !== false) {
            list($dateLeft, $dateRight) = explode('..', $value);
        } else {
            $dateLeft = $dateRight = $value;
        }

        $dateLeft = preg_replace('/[^0-9\-]/', '', $dateLeft);
        $dateRight = preg_replace('/[^0-9\-]/', '', $dateRight);
        $range = [$dateLeft, $dateRight];
        foreach ($range as &$date) {
            $parts = explode('-', $date);
            $year = !empty($parts[0]) ? intval($parts[0]) : date('Y');
            $month = !empty($parts[1]) ? intval($parts[1]) : 1;
            $day = !empty($parts[2]) ? intval($parts[2]) : 1;

            $year = (1 <= $year && $year <= 32767) ? $year : date('Y');
            $month = (1 <= $month && $month <= 12) ? $month : 1;
            $day = (1 <= $month && $month <= 31) ? $day : 1;

            $date = date('Y-m-d', strtotime("{$year}-{$month}-{$day}"));
        }

        list($dateLeft, $dateRight) = $range;
        if (strtotime($dateLeft) > strtotime($dateRight)) {
            $tmp = $dateLeft;
            $dateLeft = $dateRight;
            $dateRight = $tmp;
        }

        if ($dateLeft !== $dateRight) {
            $not = $negate ? ' NOT' : '';
            $conditions = [
                "AND{$not}" => [
                    'Users.created >=' => $dateLeft,
                    'Users.created <=' => $dateRight,
                ]
            ];
        } else {
            $cmp = $negate ? '<=' : '>=';
            $conditions = ["Users.created {$cmp}" => $dateLeft];
        }

        if ($orAnd === 'or') {
            $query->orWhere($conditions);
        } elseif ($orAnd === 'and') {
            $query->andWhere($conditions);
        } else {
            $query->where($conditions);
        }

        return $query;
    }

    /**
     * Handles "limit" search operator.
     *
     *     limit:<number>
     *
     * @param \Cake\ORM\Query $query The query object
     * @param string $value Operator's arguments
     * @param bool $negate Whether this operator was negated or not
     * @param string $orAnd and|or
     * @return void
     */
    public function operatorLimit(Query $query, $value, $negate, $orAnd)
    {
        if ($negate) {
            return $query;
        }

        $value = intval($value);

        if ($value > 0) {
            $query->limit($value);
        }

        return $query;
    }

    /**
     * Handles "order" search operator.
     *
     *     order:<field1>,<asc|desc>;<field2>,<asc,desc>; ...
     *
     * @param \Cake\ORM\Query $query The query object
     * @param string $value Operator's arguments
     * @param bool $negate Whether this operator was negated or not
     * @param string $orAnd and|or
     * @return void
     */
    public function operatorOrder(Query $query, $value, $negate, $orAnd)
    {
        if ($negate) {
            return $query;
        }

        $value = strtolower($value);
        $split = explode(';', $value);

        foreach ($split as $segment) {
            $parts = explode(',', $segment);
            if (count($parts) === 2 &&
                in_array($parts[1], ['asc', 'desc']) &&
                in_array($parts[0], ['slug', 'title', 'description', 'sticky', 'created', 'modified'])
            ) {
                $field = $parts[0];
                $dir = $parts[1];
                $query->order(["Users.{$field}" => $dir]);
            }
        }

        return $query;
    }

    /**
     * Handles "email" search operator.
     *
     *     email:<user1@demo.com>,<user2@demo.com>, ...
     *
     * @param \Cake\ORM\Query $query The query object
     * @param string $value Operator's arguments
     * @param bool $negate Whether this operator was negated or not
     * @param string $orAnd and|or
     * @return void
     */
    public function operatorEmail(Query $query, $value, $negate, $orAnd)
    {
        $value = explode(',', $value);

        if (!empty($value)) {
            $conjunction = $negate ? 'NOT IN' : 'IN';
            $conditions = ["Users.email {$conjunction}" => $value];

            if ($orAnd === 'or') {
                $query->orWhere($conditions);
            } elseif ($orAnd === 'and') {
                $query->andWhere($conditions);
            } else {
                $query->where($conditions);
            }
        }

        return $query;
    }
}
