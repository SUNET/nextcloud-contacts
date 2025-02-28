<?php

namespace OCA\Contacts\Controller;

use GuzzleHttp\Client;
use OCA\Contacts\AppInfo\Application;
use OCA\CloudFederationAPI\OCMInvitation;
use OCA\Contacts\Service\SocialApiService;
use OCA\Contacts\Service\OCMInvitationApiService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\OCM\IOCMProvider;
use Psr\Log\LoggerInterface;

class OCMInvitationApiController extends ApiController
{
  protected $appName;

  public function __construct(
    IRequest $request,
    private Client $client,
    private IConfig $config,
    private ITimeFactory $timeFactory,
    private IUserSession $userSession,
    private LoggerInterface $logger,
    private OCMInvitationApiService $invitationService,
    private IOCMProvider $ocmProvider,
    private SocialApiService $socialApiService
  ) {
    parent::__construct(Application::APP_ID, $request);

    $this->appName = Application::APP_ID;
  }


  /**
   * @NoAdminRequired
   *
   * create invitation (user option)
   *
   * @returns {JSONResponse} an empty JSONResponse with respective http status code
   */
  public function create(string $name, string $email, ?\Datetime $expiresAt)
  {
    $invitation = new OCMInvitation();
    if ($email == null || trim($email) == '') {
      return new JSONResponse([], Http::STATUS_BAD_REQUEST);
    }
    $invitation->recipient_name = $name;
    $invitation->recipient_email = $email;
    $invitation->expiresAt = $expiresAt;
    $invitation->createdAt = $this->timeFactory->getDateTime('now');
    $invitation->user_id = $this->userSession->getUser()->getUID();
    $this->invitationService->createInvitation($invitation);
    return new JSONResponse([], Http::STATUS_OK);
  }

  /**
   * @NoAdminRequired
   *
   * accept invitation (user option)
   *
   * @returns {JSONResponse} an empty JSONResponse with respective http status code
   */
  public function accept(string $ocmProviderAddress, string $token)
  {
    $url = parse_url($ocmProviderAddress);
    if (is_null($url['scheme'])) {
      $url['scheme'] = 'https';
    }
    # /ocm-provider <= 1.1.0 /.well-known/ocm < 1.1.0
    if (! in_array($url['path'], ['/ocm-provider', '/.well-known/ocm'])){
      # Use the one that allways works
      $url['path'] = '/ocm-provider';
    }
    $dicoveryEndpoint = $url['scheme'] . '://' . $url['host'] . $url['path'];
    if (isset($url['port'])) {
      $dicoveryEndpoint = $url['scheme'] . '://' . $url['host'] . ':' . $url['port'] . $url['path'];
    }
    $this->logger->debug("discovery endpoint: " . $dicoveryEndpoint);
    try {
      $response = $this->client->get($dicoveryEndpoint);
    } catch (\Exception $e) {
      return new JSONResponse([], Http::STATUS_PRECONDITION_FAILED);
    }
    $body = $response->getBody();
    $string_body = "";
    $endPoint = "";
    $capabilities = [];
    if ($body) {
      $string_body = $body->getContents();
      $array_body = json_decode($string_body);
      if (!$array_body->error) {
        $capabilities = $array_body['capabilities'];
        if ($capabilities && in_array('/invite-accepted',$capabilities) ){
          $endPoint = $array_body['endPoint'];
        }
      } else {
        $this->logger->error($array_body->message);
        $error_response["message"] = $array_body->message;
        return new JSONResponse(json_encode($error_response));
      }
    } else {
      $this->logger->error("No response body");
      $error_response["message"] = "No response body";
      return new JSONResponse(json_encode($error_response));
    }
    $user = $this->userSession->getUser();
    if (is_null($user)) {
      return new JSONResponse([], Http::STATUS_PRECONDITION_FAILED);
    }
    if ($endPoint) {
      $localUser = $this->userSession->getUser();
      $body = [
        "userId" => $localUser->getCloudId(),
        "token" => $token,
        "email" => $localUser->getPrimaryEMailAddress(),
        "name" => $localUser->getDisplayName()
      ];
      try {
        $this->client->post($endPoint, $body);
      } catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        $error_response["message"] = $e->getMessage();
        return new JSONResponse(json_encode($error_response));
      }
    }

    return new JSONResponse([], Http::STATUS_OK);
  }
}
