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
require_once __DIR__ . '/../../SimpleConsole/SimpleConsole.php';

$settings = [
    'path_models' => __DIR__ . DIRECTORY_SEPARATOR . 'Models',
    'dbcon' => [
        'host' => 'localhost',
        'user' => 'simpleobject',
        'password' => 'kog46zkVcnYBtFu0',
        'database' => 'simpleobject'
    ]
];

require_once __DIR__ . '/../SimpleObject.php';
SimpleObject::init($settings);

SimpleObject::reverseEngineerModels();

SimpleConsole::getInstance()->showDump(SimpleObject::getConnection()->getUsageInfo());