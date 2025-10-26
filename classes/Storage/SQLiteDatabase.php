<?php

namespace Shasoft\Rbac\Storage;

use Shasoft\Rbac\Role;
use Shasoft\Rbac\User;
use Shasoft\Rbac\Permission;
use Shasoft\Rbac\ItemContext;

class SQLiteDatabase implements IStorage
{
    protected int $num = 0;
    protected ?\PDO $pdo = null;
    protected array $pdoStatements = [];

    public function __construct(protected string $filepath)
    {
        $this->filepath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->filepath);
    }

    protected function pdo(): \PDO
    {
        if (is_null($this->pdo)) {
            $this->pdo = new \PDO('sqlite:' . $this->filepath);
        }
        return $this->pdo;
    }

    protected function getInValues(array $values, int $type): string
    {
        return '(' . implode(',', array_map(function (string $value) use ($type) {
            return $this->pdo()->quote($value, $type);
        }, $values)) . ')';
    }

    protected function dumpError(\Exception $e, string $sql, array $args): void
    {
        echo $e->getMessage() . PHP_EOL;
        echo $sql . PHP_EOL;
        print_r($args);
    }

    protected function runSqlQueries(array $sqlQueries): void
    {
        // то выполнить её
        foreach ($sqlQueries as $sql) {
            // Выполнить SQL
            $rc = $this->pdo()->query($sql);
            if ($rc !== false) {
                $rc->closeCursor();
            } else {
                throw new \Exception($sql);
            }
        }
    }

    protected function runQuery(string $sql, array $args = []): \PDOStatement
    {
        if (array_key_exists($sql, $this->pdoStatements)) {
            $ret = $this->pdoStatements[$sql];
        } else {
            try {
                $ret = $this->pdo()->prepare($sql);
            } catch (\PDOException $e) {
                $this->dumpError($e, $sql, $args);
                throw $e;
            }
            $this->pdoStatements[$sql] = $ret;
        }
        //-- Привязать параметры
        foreach ($args as $key => $value) {
            if (is_null($value)) {
                $ret->bindValue($key, $value, \PDO::PARAM_NULL);
            } else if (is_integer($value)) {
                $ret->bindValue($key, $value, \PDO::PARAM_INT);
            } else if (is_array($value)) {
                $ret->bindValue($key, json_encode($value), \PDO::PARAM_STR);
            } else if (is_object($value) && $value instanceof \DateTime) {
                $ret->bindValue($key, $value->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            } else {
                $ret->bindValue($key, $value, \PDO::PARAM_STR);
            }
        }
        try {
            $ret->execute();
        } catch (\PDOException $e) {
            $this->dumpError($e, $sql, $args);
            throw $e;
        }
        return $ret;
    }

    public function runQueryCb(string $sql, array $args, int $maxRecords, callable $cb): void
    {
        try {
            // Сохранить текущее значение            
            $MYSQL_ATTR_USE_BUFFERED_QUERY = $this->pdo->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
            // Отключить буферизированный режим работы для экономии памяти
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);
        } catch (\Throwable $th) {
            $MYSQL_ATTR_USE_BUFFERED_QUERY = null;
        }
        //
        $stmt = $this->runQuery($sql, $args);
        $rows = [];
        do {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row !== false) {
                $rows[] = $row;
            } else {
                if (!empty($rows)) {
                    call_user_func($cb, $rows);
                }
            }
            if (count($rows) == $maxRecords) {
                call_user_func($cb, $rows);
                $rows = [];
            }
        } while ($row !== false);
        // Вернуть сохраненное значение
        if (!is_null($MYSQL_ATTR_USE_BUFFERED_QUERY)) {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $MYSQL_ATTR_USE_BUFFERED_QUERY);
        }
    }

    protected function conversion_row(array $row): array
    {
        $ret = [];
        foreach ($row as $key => $value) {
            switch ($key) {
                case 'ban': {
                        if (!is_null($value)) {
                            $value = new \DateTime($value);
                        }
                    }
                    break;
                case 'permissions':
                case 'roles': {
                        $value = json_decode($value, true);
                    }
                    break;
            }
            $ret[$key] = $value;
        }
        return $ret;
    }

    public function create(): static
    {
        if (file_exists($this->filepath)) {
            $rc = unlink($this->filepath);
            if (!$rc) {
                throw new \Exception('Not unlink file ' . $this->filepath);
            }
        } else {
            $pathDir = dirname($this->filepath);
            if (!file_exists($pathDir)) {
                mkdir(directory: $pathDir, recursive: true);
            }
        }
        //
        $sqlCreateFiles = glob(__DIR__ . DIRECTORY_SEPARATOR . 'SQLiteDatabase' . DIRECTORY_SEPARATOR . '*.sql');
        sort($sqlCreateFiles);
        $this->runSqlQueries(
            array_map(
                function (string $filepath) {
                    return file_get_contents($filepath);
                },
                $sqlCreateFiles
            )
        );
        //
        return $this;
    }

    public function close(): void
    {
        $this->pdo = null;
    }

    private function save(array $items, string $sqlInsert, string $sqlUpdate): void
    {
        /** @var ItemContext $context */
        foreach ($items['insert'] as $_ => $context) {
            $this->runQuery(
                $sqlInsert,
                $context->row
            );
            $context->setInsertOk();
        }
        foreach ($items['update'] as $_ => $context) {
            $this->runQuery(
                $sqlUpdate,
                $context->row
            );
            $context->setUpdateOk();
        }
    }

    public function onFlush(array $actions): void
    {
        $this->save(
            $actions[Permission::class],
            'INSERT INTO `rbac.permission` (`name`,`description`,`exists`,`linkToBan`,`offsetValue`) VALUES (:name,:description,:exists,:linkToBan,:offsetValue)',
            'UPDATE `rbac.permission` SET `description` = :description,`exists` = :exists,`linkToBan` = :linkToBan,`offsetValue` = :offsetValue WHERE `name` = :name'
        );
        $this->save(
            $actions[Role::class],
            'INSERT INTO `rbac.role` (`name`,`description`,`roles`,`permissions`,`exists`) VALUES (:name,:description,:roles,:permissions,:exists)',
            'UPDATE `rbac.role` SET `description` = :description,`roles` = :roles,`permissions` = :permissions,`exists` = :exists WHERE `name` = :name'
        );
        $this->save(
            $actions[User::class],
            'INSERT INTO `rbac.user` (`id`,`roles`,`group`,`permissions`,`exists`,`ban`) VALUES (:id,:roles,:group,:permissions,:exists,:ban)',
            'UPDATE `rbac.user` SET `roles` = :roles,`group` = :group,`permissions` = :permissions,`exists` = :exists,`ban` = :ban WHERE `id` = :id'
        );
    }

    public function onReset(): void
    {
        $this->runQuery('DELETE FROM `rbac.permission`');
        $this->runQuery('DELETE FROM `rbac.role`');
        $this->runQuery('DELETE FROM `rbac.user`');
        $this->runQuery('DELETE FROM `rbac.cache`');
    }

    private function read(string $sql, array $contexts, int $type): void
    {
        /** @var array<ItemContext> $contexts */
        $ids = array_keys($contexts);
        $inArrayValues = $this->getInValues($ids, $type);
        $rows = $this->runQuery($sql . $inArrayValues, [])->fetchAll(\PDO::FETCH_ASSOC);
        $oks = [];
        $keyName = $contexts[$ids[0]]->key;
        foreach ($rows as $row) {
            $id = $row[$keyName];
            $contexts[$id]->setReadOk($this->conversion_row($row));
            $oks[$id] = 1;
        }
        // Проставить для тех которые не прочитали
        foreach ($contexts as $name => $context) {
            if (!array_key_exists($name, $oks)) {
                $context->setReadOk(null);
            }
        }
    }

    private function readAll(string $sql, int $maxRecords, callable $cb): void
    {
        $this->runQueryCb(
            $sql,
            [],
            $maxRecords,
            function (array $rows) use ($cb) {
                call_user_func($cb, array_map(function (array $row) {
                    return $this->conversion_row($row);
                }, $rows));
            }
        );
    }

    public function onReadPermission(array $contexts): void
    {
        $this->read(
            'SELECT `name`, `description`, `exists`, `linkToBan`, `offsetValue` FROM `rbac.permission`  WHERE `name` in ',
            $contexts,
            \PDO::PARAM_STR
        );
    }

    public function onReadAllPermission(int $maxRecords, callable $cb): void
    {
        $this->readAll(
            'SELECT `name`, `description`, `exists`, `linkToBan`, `offsetValue` FROM `rbac.permission`',
            $maxRecords,
            $cb
        );
    }

    public function onReadRole(
        array $contexts
    ): void {
        $this->read(
            'SELECT `name`, `description`, `roles`, `permissions`, `exists` FROM `rbac.role` WHERE `name` in ',
            $contexts,
            \PDO::PARAM_STR
        );
    }

    public function onReadAllRole(int $maxRecords, callable $cb): void
    {
        $this->readAll(
            'SELECT `name`, `description`, `roles`, `permissions`, `exists` FROM `rbac.role`',
            $maxRecords,
            $cb
        );
    }

    public function onReadUser(
        array $contexts
    ): void {
        $this->read(
            'SELECT `id`, `roles`, `group`, `permissions`, `exists`, `ban` FROM `rbac.user` WHERE `id` in ',
            $contexts,
            \PDO::PARAM_INT
        );
    }

    public function onReadAllUser(int $maxRecords, callable $cb): void
    {
        $this->readAll(
            'SELECT `id`, `roles`, `group`, `permissions`, `exists`, `ban` FROM `rbac.user`',
            $maxRecords,
            $cb
        );
    }

    public function onCacheRead(string $type, string $name): ?array
    {
        $rows = $this->runQuery(
            'SELECT `ref`, `state` FROM `rbac.cache` WHERE `type` = :type AND `name` = :name',
            [
                'type' => $type,
                'name' => $name
            ]
        )->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return null;
        }
        $refs = [];
        foreach ($rows as $row) {
            $row = $this->conversion_row($row);
            $refs[$row['ref']] = $row['state'];
        }
        return $refs;
    }

    public function onCacheWrite(string $type, string $name, array $refs): void
    {
        foreach ($refs as $ref => $state) {
            $this->runQuery(
                'INSERT INTO `rbac.cache` (`type`,`name`,`ref`,`state`) VALUES (:type,:name,:ref,:state)',
                [
                    'type' => $type,
                    'name' => $name,
                    'ref' => $ref,
                    'state' => $state
                ]
            );
        }
    }

    public function onCacheGets(string $type, array $refs, int $maxRecords, callable $cb): void
    {
        $this->runQueryCb(
            'SELECT DISTINCT `name` FROM `rbac.cache` WHERE `type` = :type AND `ref` in ' . $this->getInValues($refs, \PDO::PARAM_STR),
            ['type' => $type],
            $maxRecords,
            function (array $rows) use ($cb) {
                call_user_func($cb, array_map(function (array $row) {
                    return $row['name'];
                }, $rows));
            }
        );
    }

    public function onCacheRemove(string $type, array $names): void
    {
        $inArrayValues = $this->getInValues($names, \PDO::PARAM_STR);
        $this->runQuery(
            'DELETE FROM `rbac.cache` WHERE `type` = :type AND `name` in ' . $inArrayValues,
            ['type' => $type]
        );
    }
}
