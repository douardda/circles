<?php

declare(strict_types=1);


/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2021
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Circles\Service;


use daita\MySmallPhpTools\ActivityPub\Nextcloud\nc22\NC22Signature;
use daita\MySmallPhpTools\Exceptions\RequestNetworkException;
use daita\MySmallPhpTools\Model\Nextcloud\nc22\NC22Request;
use daita\MySmallPhpTools\Model\Request;
use daita\MySmallPhpTools\Traits\Nextcloud\nc22\TNC22Request;
use daita\MySmallPhpTools\Traits\TStringTools;
use OC;
use OCA\Circles\Db\EventWrapperRequest;
use OCA\Circles\Db\MemberRequest;
use OCA\Circles\Db\RemoteRequest;
use OCA\Circles\Db\ShareLockRequest;
use OCA\Circles\Exceptions\FederatedEventException;
use OCA\Circles\Exceptions\FederatedItemException;
use OCA\Circles\Exceptions\FederatedShareBelongingException;
use OCA\Circles\Exceptions\FederatedShareNotFoundException;
use OCA\Circles\Exceptions\InitiatorNotConfirmedException;
use OCA\Circles\Exceptions\JsonException;
use OCA\Circles\Exceptions\ModelException;
use OCA\Circles\Exceptions\OwnerNotFoundException;
use OCA\Circles\Exceptions\RemoteInstanceException;
use OCA\Circles\Exceptions\RemoteNotFoundException;
use OCA\Circles\Exceptions\RemoteResourceNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\UnknownRemoteException;
use OCA\Circles\IFederatedItem;
use OCA\Circles\IFederatedItemAsyncProcess;
use OCA\Circles\IFederatedItemCircleCheckNotRequired;
use OCA\Circles\IFederatedItemDataRequestOnly;
use OCA\Circles\IFederatedItemInitiatorCheckNotRequired;
use OCA\Circles\IFederatedItemInitiatorMembershipNotRequired;
use OCA\Circles\IFederatedItemLimitedToInstanceWithMembership;
use OCA\Circles\IFederatedItemMemberCheckNotRequired;
use OCA\Circles\IFederatedItemMemberEmpty;
use OCA\Circles\IFederatedItemMemberOptional;
use OCA\Circles\IFederatedItemMemberRequired;
use OCA\Circles\IFederatedItemMustBeInitializedLocally;
use OCA\Circles\IFederatedItemSharedItem;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Federated\EventWrapper;
use OCA\Circles\Model\Federated\FederatedEvent;
use OCA\Circles\Model\Federated\RemoteInstance;
use OCA\Circles\Model\Member;
use OCP\IL10N;
use ReflectionClass;
use ReflectionException;


/**
 * Class FederatedEventService
 *
 * @package OCA\Circles\Service
 */
class FederatedEventService extends NC22Signature {


	use TNC22Request;
	use TStringTools;


	/** @var IL10N */
	private $l10n;

	/** @var EventWrapperRequest */
	private $eventWrapperRequest;

	/** @var RemoteRequest */
	private $remoteRequest;

	/** @var ShareLockRequest */
	private $shareLockRequest;

	/** @var MemberRequest */
	private $memberRequest;

	/** @var RemoteUpstreamService */
	private $remoteUpstreamService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * FederatedEventService constructor.
	 *
	 * @param IL10N $l10n
	 * @param EventWrapperRequest $eventWrapperRequest
	 * @param RemoteRequest $remoteRequest
	 * @param MemberRequest $memberRequest
	 * @param ShareLockRequest $shareLockRequest
	 * @param RemoteUpstreamService $remoteUpstreamService
	 * @param ConfigService $configService
	 */
	public function __construct(
		IL10N $l10n, EventWrapperRequest $eventWrapperRequest, RemoteRequest $remoteRequest,
		MemberRequest $memberRequest, ShareLockRequest $shareLockRequest,
		RemoteUpstreamService $remoteUpstreamService,
		ConfigService $configService
	) {
		$this->l10n = $l10n;
		$this->eventWrapperRequest = $eventWrapperRequest;
		$this->remoteRequest = $remoteRequest;
		$this->shareLockRequest = $shareLockRequest;
		$this->memberRequest = $memberRequest;
		$this->remoteUpstreamService = $remoteUpstreamService;
		$this->configService = $configService;
	}


	/**
	 * Called when creating a new Event.
	 * This method will manage the event locally and upstream the payload if needed.
	 *
	 * @param FederatedEvent $event
	 *
	 * @return array
	 * @throws FederatedEventException
	 * @throws FederatedItemException
	 * @throws InitiatorNotConfirmedException
	 * @throws OwnerNotFoundException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws UnknownRemoteException
	 * @throws RemoteInstanceException
	 */
	public function newEvent(FederatedEvent $event): array {
		$event->setSource($this->configService->getFrontalInstance());

		$federatedItem = $this->getFederatedItem($event, false);

		$this->confirmInitiator($event, true);
		if ($this->configService->isLocalInstance($event->getCircle()->getInstance())) {
			$event->setIncomingOrigin($event->getCircle()->getInstance());

			$federatedItem->verify($event);

			if ($event->isDataRequestOnly()) {
				return $event->getOutcome();
			}

			if (!$event->isAsync()) {
				$federatedItem->manage($event);
			}

			$this->initBroadcast($event);
		} else {
			$this->remoteUpstreamService->confirmEvent($event);
			if ($event->isDataRequestOnly()) {
				return $event->getOutcome();
			}

//			if (!$event->isAsync()) {
//				$federatedItem->manage($event);
//			}
		}

		return $event->getOutcome();
	}


	/**
	 * This confirmation is optional, method is just here to avoid going too far away on the process
	 *
	 * @param FederatedEvent $event
	 * @param bool $local
	 *
	 * @throws InitiatorNotConfirmedException
	 */
	public function confirmInitiator(FederatedEvent $event, bool $local = false): void {
		if ($event->canBypass(FederatedEvent::BYPASS_INITIATORCHECK)) {
			return;
		}

		$circle = $event->getCircle();
		if (!$circle->hasInitiator()) {
			throw new InitiatorNotConfirmedException('Initiator does not exist');
		}

		if ($local) {
			if (!$this->configService->isLocalInstance($circle->getInitiator()->getInstance())) {
				throw new InitiatorNotConfirmedException(
					'Initiator is not from the instance at the origin of the request'
				);
			}
		} else {
			if ($circle->getInitiator()->getInstance() !== $event->getIncomingOrigin()) {
				throw new InitiatorNotConfirmedException(
					'Initiator must belong to the instance at the origin of the request'
				);
			}
		}

		if (!$event->canBypass(FederatedEvent::BYPASS_INITIATORMEMBERSHIP)
			&& $circle->getInitiator()->getLevel() < Member::LEVEL_MEMBER) {
			throw new InitiatorNotConfirmedException('Initiator must be a member of the Circle');
		}
	}


	/**
	 * @param FederatedEvent $event
	 * @param bool $checkLocalOnly
	 *
	 * @return IFederatedItem
	 * @throws FederatedEventException
	 */
	public function getFederatedItem(FederatedEvent $event, bool $checkLocalOnly = true): IFederatedItem {
		$class = $event->getClass();
		try {
			$test = new ReflectionClass($class);
		} catch (ReflectionException $e) {
			throw new FederatedEventException('ReflectionException with ' . $class . ': ' . $e->getMessage());
		}

		if (!in_array(IFederatedItem::class, $test->getInterfaceNames())) {
			throw new FederatedEventException($class . ' does not implements IFederatedItem');
		}

		$item = OC::$server->get($class);
		if (!($item instanceof IFederatedItem)) {
			throw new FederatedEventException($class . ' not an IFederatedItem');
		}

		$this->setFederatedEventBypass($event, $item);
		$this->confirmRequiredCondition($event, $item, $checkLocalOnly);
		$this->configureEvent($event, $item);

//		$this->confirmSharedItem($event, $item);

		return $item;
	}


	/**
	 * Some event might need to bypass some checks
	 *
	 * @param FederatedEvent $event
	 * @param IFederatedItem $item
	 */
	private function setFederatedEventBypass(FederatedEvent $event, IFederatedItem $item) {
		if ($item instanceof IFederatedItemCircleCheckNotRequired) {
			$event->bypass(FederatedEvent::BYPASS_LOCALCIRCLECHECK);
		}
		if ($item instanceof IFederatedItemMemberCheckNotRequired) {
			$event->bypass(FederatedEvent::BYPASS_LOCALMEMBERCHECK);
		}
		if ($item instanceof IFederatedItemInitiatorCheckNotRequired) {
			$event->bypass(FederatedEvent::BYPASS_INITIATORCHECK);
		}
		if ($item instanceof IFederatedItemInitiatorMembershipNotRequired) {
			$event->bypass(FederatedEvent::BYPASS_INITIATORMEMBERSHIP);
		}
	}

	/**
	 * Some event might require additional check
	 *
	 * @param FederatedEvent $event
	 * @param IFederatedItem $item
	 * @param bool $checkLocalOnly
	 *
	 * @throws FederatedEventException
	 */
	private function confirmRequiredCondition(
		FederatedEvent $event,
		IFederatedItem $item,
		bool $checkLocalOnly = true
	) {
		if (!$event->hasCircle()) {
			throw new FederatedEventException('FederatedEvent has no Circle linked');
		}

		// TODO: enforce IFederatedItemMemberEmpty if no member
		if ($item instanceof IFederatedItemMemberEmpty) {
			$event->setMember(null);
		} else if ($item instanceof IFederatedItemMemberRequired && !$event->hasMember()) {
			throw new FederatedEventException('FederatedEvent has no Member linked');
		}

		if ($event->hasMember()
			&& !($item instanceof IFederatedItemMemberRequired)
			&& !($item instanceof IFederatedItemMemberOptional)) {
			throw new FederatedEventException(
				get_class($item)
				. ' does not implements IFederatedItemMemberOptional nor IFederatedItemMemberRequired'
			);
		}

		if ($item instanceof IFederatedItemMustBeInitializedLocally && $checkLocalOnly) {
			throw new FederatedEventException('FederatedItem must be executed locally');
		}
	}


	/**
	 * @param FederatedEvent $event
	 * @param IFederatedItem $item
	 *
	 * @throws FederatedEventException
	 * @throws FederatedShareBelongingException
	 * @throws FederatedShareNotFoundException
	 * @throws OwnerNotFoundException
	 */
	private function confirmSharedItem(FederatedEvent $event, IFederatedItem $item): void {
		if (!$item instanceof IFederatedItemSharedItem) {
			return;
		}

		if ($event->getItemId() === '') {
			throw new FederatedEventException('FederatedItem must contains ItemId');
		}

		if ($this->configService->isLocalInstance($event->getCircle()->getInstance())) {
			$shareLock = $this->shareLockRequest->getShare($event->getItemId());
			if ($shareLock->getInstance() !== $event->getIncomingOrigin()) {
				throw new FederatedShareBelongingException('ShareLock belongs to another instance');
			}
		}
	}


	/**
	 * @param FederatedEvent $event
	 * @param IFederatedItem $item
	 */
	private function configureEvent(FederatedEvent $event, IFederatedItem $item) {
		if ($item instanceof IFederatedItemAsyncProcess) {
			$event->setAsync(true);
		}
		if ($item instanceof IFederatedItemLimitedToInstanceWithMembership) {
			$event->setLimitedToInstanceWithMember(true);
		}
		if ($item instanceof IFederatedItemDataRequestOnly) {
			$event->setDataRequestOnly(true);
		}
	}


	/**
	 * async the process, generate a local request that will be closed.
	 *
	 * @param FederatedEvent $event
	 * @param array $filter
	 */
	public function initBroadcast(FederatedEvent $event, array $filter = []): void {
		$instances = array_diff($this->getInstances($event), $filter);
		if (empty($instances)) {
			return;
		}

		$wrapper = new EventWrapper();
		$wrapper->setEvent($event);
		$wrapper->setToken($this->uuid());
		$wrapper->setCreation(time());
		$wrapper->setSeverity($event->getSeverity());

		$circle = $event->getCircle();
		foreach ($instances as $instance) {
			if ($circle->isConfig(Circle::CFG_LOCAL) && !$this->configService->isLocalInstance($instance)) {
				continue;
			}

			$wrapper->setInstance($instance);
			$this->eventWrapperRequest->create($wrapper);
		}

		$request = new NC22Request('', Request::TYPE_POST);
		$this->configService->configureRequest(
			$request, 'circles.EventWrapper.asyncBroadcast', ['token' => $wrapper->getToken()]
		);

		$event->setWrapperToken($wrapper->getToken());

		try {
			$this->doRequest($request);
		} catch (RequestNetworkException $e) {
			$this->e($e, ['wrapper' => $wrapper]);
		}
	}


	/**
	 * @param FederatedEvent $event
	 *
	 * @return array
	 * @throws RequestBuilderException
	 */
	public function getInstances(FederatedEvent $event): array {
		$local = $this->configService->getFrontalInstance();

		$circle = $event->getCircle();
		$instances = array_map(
			function(RemoteInstance $instance): string {
				return $instance->getInstance();
			},
			$this->remoteRequest->getOutgoingRecipient(
				$circle,
				$event->getData()->gBool('broadcastAsFederated')
			)
		);

		if ($event->isLimitedToInstanceWithMember()) {
			$instances =
				array_intersect(
					$instances, $this->memberRequest->getMemberInstances($circle->getSingleId())
				);
		}

		if ($event->hasMember()
			&& !$this->configService->isLocalInstance(($event->getMember()->getInstance()))
			&& !in_array($event->getMember()->getInstance(), $instances)) {
			// At that point, we know that the member belongs to a _known_ remote instance
			$instances[] = $event->getMember()->getInstance();
		}

		$instances = array_merge([$local], $instances);

		if ($event->isAsync()) {
			return $instances;
		}

		return array_values(
			array_diff($instances, array_merge($this->configService->getTrustedDomains(), [$local]))
		);
	}


	/**
	 * @param array $current
	 */
	private function updateGlobalScaleInstances(array $current): void {
//		$known = $this->remoteRequest->getFromType(RemoteInstance::TYPE_GLOBAL_SCALE);
	}

	/**
	 * @return array
	 */
	private function getRemoteInstances(): array {
		return [];
	}


	/**
	 * should be used to manage results from events, like sending mails on user creation
	 *
	 * @param string $token
	 */
	public function manageResults(string $token): void {
		try {
			$wrappers = $this->eventWrapperRequest->getByToken($token);
		} catch (JsonException | ModelException $e) {
			return;
		}

		$event = null;
		$results = [];
		foreach ($wrappers as $wrapper) {
			if ($wrapper->getStatus() !== EventWrapper::STATUS_DONE) {
				return;
			}

			if (is_null($event)) {
				$event = $wrapper->getEvent();
			}

			$results[$wrapper->getInstance()] = $wrapper->getResult();
		}

		if (is_null($event)) {
			return;
		}

		try {
			$gs = $this->getFederatedItem($event, false);
			$gs->result($event, $results);
		} catch (FederatedEventException $e) {
		}
	}

}
