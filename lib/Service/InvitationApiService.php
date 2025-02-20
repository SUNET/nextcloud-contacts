<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Service;

use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Service\Social\CompositeSocialProvider;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\ContactsManager;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Contacts\IManager;
use OCP\Http\Client\IClientService;
use OCP\IAddressBook;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;


class InvitationApiService {
	private $appName;

	public function __construct(
		private IDBConnection $dbConnection,
		private IManager $manager,
		private IConfig $config,
		private IClientService $clientService,
		private IL10N $l10n,
		private IURLGenerator $urlGen,
		private CardDavBackend $davBackend,
		private ITimeFactory $timeFactory,
		private ImageResizer $imageResizer,
	) {
		$this->appName = Application::APP_ID;
	}


}