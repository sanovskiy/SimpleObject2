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

/**
 * Class Model_User
 */
class Model_User extends Model_Base_User
{

    public function isActive() {
        return (empty($this->BanExpiration) || $this->BanExpiration < time()) && $this->IsActivated;
    }

    public function test()
    {
        echo 'I\'m simple ' . __CLASS__ . ' model!' . PHP_EOL;
    }

    public function save()
    {
        if (!$this->isValidMd5($this->Password)){
            $this->Password = md5($this->Password);
        }
        parent::save();
    }

    private function isValidMd5($md5 ='')
    {
        return preg_match('/^[a-f0-9]{32}$/', $md5);
    }

}