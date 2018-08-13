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

declare(strict_types=1);

namespace Espo\Modules\TreoCore\Core\Portal;

use Espo\Core\Utils\Json;
use Espo\Core\Portal\Application as EspoApplication;

/**
 * Portal Application class
 *
 */
class Application extends EspoApplication
{
    const CONFIG_PATH = 'data/portals.json';

    /**
     * @var null|array
     */
    protected static $urls = null;

    /**
     * Is portal calling now
     *
     * @return string
     */
    public static function getPortalCallingId(): string
    {
        // prepare result
        $result = '';

        // prepare protocol
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";

        // prepare url
        $url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        if (in_array($url, self::getUrlFileData())) {
            $result = array_search($url, self::getUrlFileData());
        }

        return $result;
    }

    /**
     * Get url config file data
     *
     * @return array
     */
    public static function getUrlFileData(): array
    {
        if (is_null(self::$urls)) {
            // prepare result
            self::$urls = [];

            if (file_exists(self::CONFIG_PATH)) {
                $json = file_get_contents(self::CONFIG_PATH);
                if (!empty($json)) {
                    self::$urls = Json::decode($json, true);
                }
            }
        }

        return self::$urls;
    }


    /**
     * Set data to url config file
     *
     * @param array $data
     */
    public static function saveUrlFile(array $data): void
    {
        $file = fopen(self::CONFIG_PATH, "w");
        fwrite($file, Json::encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fclose($file);
    }

    /**
     * Run client
     */
    public function runClient()
    {
        $this->getContainer()->get('clientManager')->display(
            null,
            'html/treo-portal.html',
            [
                'portalId'        => $this->getPortal()->id,
                'classReplaceMap' => json_encode($this->getMetadata()->get(['app', 'clientClassReplaceMap'], [])),
                'year'            => date('Y'),
                'version'         => $this->getContainer()->get('config')->get('version')
            ]
        );
    }
}
