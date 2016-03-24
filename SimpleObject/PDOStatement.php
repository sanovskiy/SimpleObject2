<?php

/**
 * Copyright 2010-2016 Pavel Terentyev <pavel.terentyev@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
class SimpleObject_PDOStatement extends PDOStatement
{

    /**
     * @var SimpleObject_PDO
     */
    protected $pdo;

    /**
     * SimpleObject_PDOStatement constructor.
     * @param SimpleObject_PDO $pdo
     */
    protected function __construct(SimpleObject_PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array|null $input_parameters
     * @return bool
     */
    public function execute(array $input_parameters = null)
    {
        $start = $this->pdo->getMicro();
        $result = parent::execute($input_parameters);
        $end = $this->pdo->getMicro();
        $this->pdo->registerTime($start, $end, $this->queryString);
        return $result;
    }

}