<?php
/**
 * This file is part of EspoCRM and/or TreoPIM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2018 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * TreoPIM is EspoCRM-based Open Source Product Information Management application.
 * Copyright (C) 2017-2018 Zinit Solutions GmbH
 * Website: http://www.treopim.com
 *
 * TreoPIM as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TreoPIM as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "TreoPIM" word.
 */

namespace Espo\Modules\TreoCore\Listeners;

use Espo\Core\Utils\Json;
use Espo\Core\Exceptions\BadRequest;

/**
 * Settings listener
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Settings extends AbstractListener
{
    /**
     * @param array $data
     *
     * @return array
     */
    public function afterActionUpdate(array $data): array
    {
        if (isset($data['data']->allowUnstable)) {
            if (!empty($this->getConfig()->get('allowUnstableBlocked'))) {
                $message = $this
                    ->getLanguage()
                    ->translate('allowUnstableParamBlocked', 'messages');

                throw new BadRequest($message);
            }

            $this->setMinimumStability((!empty($data['data']->allowUnstable)) ? 'RC' : 'stable');

            // clear cache
            $this->getContainer()->get('serviceFactory')->create('Packagist')->clearCache();

            $data['result']['allowUnstable'] = !empty($data['data']->allowUnstable);
        }

        return $data;
    }

    /**
     * Set minimum-stability to composer.json
     *
     * @param string $minimumStability
     */
    protected function setMinimumStability(string $minimumStability): void
    {
        // prepare path
        $path = 'composer.json';

        if (file_exists($path)) {
            // prepare data
            $data = Json::decode(file_get_contents($path), true);
            $data['minimum-stability'] = $minimumStability;

            // delete old file
            unlink($path);

            // create new file
            $file = fopen($path, "w");
            fwrite($file, Json::encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fclose($file);
        }
    }
}
