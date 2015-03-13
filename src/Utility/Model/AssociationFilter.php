<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Bake\Utility\Model;

use Cake\ORM\Table;
use Cake\Utility\Inflector;

/**
 * Utility class to filter Model Table associations
 *
 */
class AssociationFilter
{

    /**
     * Detect existing belongsToMany associations and cleanup the hasMany aliases based on existing
     * belongsToMany associations provided
     *
     * @param \Cake\ORM\Table $table
     * @param array $aliases
     * @return array $aliases
     */
    public static function filterHasManyAssociationsAliases(Table $table, array $aliases)
    {
        $belongsToManyJunctionsAliases = self::belongsToManyJunctionAliases($table);
        return array_values(array_diff($aliases, $belongsToManyJunctionsAliases));
    }

    /**
     * Get the array of junction aliases for all the BelongsToMany associations
     * @param Table $table
     * @return array junction aliases of all the BelongsToMany associations
     */
    public static function belongsToManyJunctionAliases(Table $table)
    {
        $extractor = function ($val) {
            return $val->junction()->alias();
        };
        return array_map($extractor, $table->associations()->type('BelongsToMany'));
    }

    /**
     * Returns filtered associations for controllers models. HasMany association are filtered if
     * already existing in BelongsToMany
     *
     * @param Table $model The model to build associations for.
     * @return array associations
     */
    public static function filterAssociations(Table $model)
    {
        $belongsToManyJunctionsAliases = self::belongsToManyJunctionAliases($model);
        $keys = ['BelongsTo', 'HasOne', 'HasMany', 'BelongsToMany'];
        $associations = [];

        foreach ($keys as $type) {
            foreach ($model->associations()->type($type) as $assoc) {
                $target = $assoc->target();
                $assocName = $assoc->name();
                $alias = $target->alias();
                //filter existing HasMany
                if ($type === 'HasMany' && in_array($alias, $belongsToManyJunctionsAliases)) {
                    continue;
                }
                $targetClass = get_class($target);
                list(, $className) = namespaceSplit($targetClass);

                $modelClass = get_class($model);
                if ($modelClass !== 'Cake\ORM\Table' && $targetClass === $modelClass) {
                    continue;
                }

                $className = preg_replace('/(.*)Table$/', '\1', $className);
                if ($className === '') {
                    $className = $alias;
                }

                $associations[$type][$assocName] = [
                    'property' => $assoc->property(),
                    'variable' => Inflector::variable($assocName),
                    'primaryKey' => (array)$target->primaryKey(),
                    'displayField' => $target->displayField(),
                    'foreignKey' => $assoc->foreignKey(),
                    'alias' => $alias,
                    'controller' => $className,
                    'fields' => $target->schema()->columns(),
                ];
            }
        }
        return $associations;
    }
}
