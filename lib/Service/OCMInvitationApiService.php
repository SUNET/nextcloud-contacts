<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Service;

use OCA\CloudFederationAPI\OCMInvitation;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\ContactsManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Contacts\IManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;


class OCMInvitationApiService
{
  private $invitation_table_name = "federated_invites";
  public function __construct(
    private CardDavBackend $davBackend,
    private IClientService $clientService,
    private IConfig $config,
    private IDBConnection $dbConnection,
    private IL10N $l10n,
    private IMailer $mailer,
    private IManager $manager,
    private ITimeFactory $timeFactory,
    private IURLGenerator $urlGen,
    private IUserManager $userManager,
    private LoggerInterface $logger,
  ) {}

  /**
   * Retrieves and initiates all addressbooks from a user
   *
   * @param {string} userId the user to query
   * @param {IManager} the contact manager to load
   */
  protected function registerAddressbooks($userId, IManager $manager)
  {
    $coma = new ContactsManager($this->davBackend, $this->l10n);
    $coma->setupContactsProvider($manager, $userId, $this->urlGen);
    $this->manager = $manager;
  }

  /**
   * Gets the addressbook of an addressbookId
   *
   * @param {String} addressbookId the identifier of the addressbook
   * @param {IManager} manager optional a ContactManager to use
   *
   * @returns {IAddressBook} the corresponding addressbook or null
   */
  protected function getAddressBook(string $userId): ?IAddressBook
  {
    $localUser = $this->userManager->get($invitation->user_id);
    $addressBook = $this->davBackend->getAddressBooksForUser("principals/users/$userId");;
    return $addressBook;
  }

  public function createInvitation(OCMInvitation $invitation): void
  {
    /**
     * @var IQueryBuilder $query
     */
    $query = $this->dbConnection->getQueryBuilder();
    $num_bytes = 16;
    $token = bin2hex(openssl_random_pseudo_bytes($num_bytes));
    $query->insert($this->invitation_table_name)
      ->values([
        'user_id' => $invitation->user_id,
        'token' => $token,
        'recipient_email' => $invitation->recipient_email,
        'createdAt' => $invitation->createdAt,
        'expiresAt' => $invitation->expiresAt,
      ])->executeStatement();
    $this->sendInvitationEMail($invitation);
  }

  public function acceptInvitation(OCMInvitation $invitation): void
  {
    $this->registerAddressbooks($invitation->user_id, $this->manager);
    $addressBook = $this->getAddressBook($invitation->user_id);
    $properties = array( 
      "UID" => $invitation['recpient_user_id'],
      "FN" => $invitation['recipient_name'],
      "EMAIL" => $invitation['recipient_email'],
    );
    $this->manager->createOrUpdate($properties, $addressBook->getResourceId());
  }

  public function sendInvitationEMail($invitation)

  {
    $localUser = $this->userManager->get($invitation->user_id);
    $displayName = $localUser->getDisplayName();
    $email = $localUser->getSystemEMailAddress();
    $toUserEmail = $invitation->recipient_email;

    if (!$toUserEmail || !filter_var($toUserEmail, FILTER_VALIDATE_EMAIL)) {
      throw new \Exception('Invalid email address (' . $toUserEmail . ')');
    }

    $emailTemplate = $this->mailer->createEMailTemplate($email, [
      'owner' => $displayName,
      'title' => 'Invitation to connect with' . $displayName . '<' . $localUser->getCloudId() . '>'
    ]);

    $emailTemplate->setSubject('Invitation to connect with' . $localUser->getCloudId());
    $emailTemplate->addHeader();
    $emailTemplate->addHeading('OCM Invitation', false);
    $emailTemplate->addBodyText('This is an ocm invite.');
    $emailTemplate->addBodyText('It was sent by: ' . $localUser->getCloudId());
    $emailTemplate->addBodyText('The token you need to accept the invite is: <pre>' . $invitation->token . '</pre>');


    try {
      $message = $this->mailer->createMessage();
      $message->setTo([$toUserEmail => $displayName]);
      $message->useTemplate($emailTemplate);
      $this->mailer->send($message);

      return 1;
    } catch (\Exception $e) {
      $this->logger->logException($e);
      throw $e;
    }
  }
}
