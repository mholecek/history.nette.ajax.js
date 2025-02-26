<?php

namespace VojtechDobes\NetteAjax;

use Nette\Application\Responses\ForwardResponse;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\IRouter;
use Nette\Http;
use Nette\Reflection\Property;


/**
 * Automatically adds 'redirect' to payload when forward happens
 * to simplify userland code in presenters.
 *
 * Also bypasses 'redirect()' calls with 'forward()' calls.
 *
 * Sets 'Vary: X-Requested-With' header to disable payload caching.
 *
 * @author Vojtěch Dobeš
 */
class OnResponseHandler
{

	/** @var Http\IRequest */
	private $httpRequest;

	/** @var IRouter */
	private $router;

	/** @var bool */
	private $forwardHasHappened = FALSE;

	/** @var string */
	private $fragment = '';



	/**
	 * @param  Http\IRequest
	 * @param  IRouter
	 */
	public function __construct(Http\IRequest $httpRequest, IRouter $router)
	{
		$this->httpRequest = $httpRequest;
		$this->router = $router;
	}



	/**
	 * Stores information about ocurring forward() call
	 */
	public function markForward()
	{
		$this->forwardHasHappened = TRUE;
	}



	public function __invoke($application, $response)
	{
		if ($response instanceof JsonResponse && ($payload = $response->getPayload()) instanceof \stdClass) {
			if (!$this->forwardHasHappened && isset($payload->redirect)) {
				if (($fragmentPos = strpos($payload->redirect, '#')) !== FALSE) {
					$this->fragment = substr($payload->redirect, $fragmentPos);
				}
				$url = new Http\UrlScript($payload->redirect, $this->httpRequest->url->scriptPath);
			
				$httpRequest = new Http\Request($url);

				if ($this->router->match($httpRequest) !== NULL) {
					$prop = new \ReflectionProperty(\Nette\Application\Application::class, 'httpRequest');
					$prop->setAccessible(TRUE);
					$prop->setValue($application, $httpRequest);

					$application->run();
					exit;
				}
			} elseif ($this->forwardHasHappened && !isset($payload->redirect)) {
				$payload->redirect = $application->getPresenter()->link('this', $application->getPresenter()->getParameters()) . $this->fragment;
				$this->fragment = '';
			}
		}
	}

}
