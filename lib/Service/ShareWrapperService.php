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


use OCA\Circles\Db\ShareWrapperRequest;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\ShareWrapperNotFoundException;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\ShareWrapper;
use OCP\Files\NotFoundException;
use OCP\Share\IShare;


/**
 * Class ShareWrapperService
 *
 * @package OCA\Circles\Service
 */
class ShareWrapperService {


	/** @var ShareWrapperRequest */
	private $shareWrapperRequest;


	/**
	 * ShareWrapperService constructor.
	 *
	 * @param ShareWrapperRequest $shareWrapperRequest
	 */
	public function __construct(ShareWrapperRequest $shareWrapperRequest) {
		$this->shareWrapperRequest = $shareWrapperRequest;
	}


	/**
	 * @param $singleId
	 * @param $nodeId
	 *
	 * @return ShareWrapper
	 * @throws ShareWrapperNotFoundException
	 */
	public function searchShare(string $singleId, int $nodeId): ShareWrapper {
		return $this->shareWrapperRequest->searchShare($singleId, $nodeId);
	}


	/**
	 * @param IShare $share
	 *
	 * @throws NotFoundException
	 */
	public function save(IShare $share): void {
		$this->shareWrapperRequest->save($share);
	}


	/**
	 * @param ShareWrapper $shareWrapper
	 */
	public function update(ShareWrapper $shareWrapper): void {
		$this->shareWrapperRequest->update($shareWrapper);
	}


	/**
	 * @param int $shareId
	 * @param FederatedUser|null $federatedUser
	 *
	 * @return ShareWrapper
	 * @throws ShareWrapperNotFoundException
	 */
	public function getShareById(int $shareId, ?FederatedUser $federatedUser = null): ShareWrapper {
		return $this->shareWrapperRequest->getShareById($shareId, $federatedUser);
	}


	/**
	 * @param int $fileId
	 *
	 * @return ShareWrapper[]
	 */
	public function getSharesByFileId(int $fileId): array {
		return $this->shareWrapperRequest->getSharesByFileId($fileId);
	}


	/**
	 * @param FederatedUser $federatedUser
	 * @param int $nodeId
	 * @param int $offset
	 * @param int $limit
	 * @param bool $getData
	 *
	 * @return ShareWrapper[]
	 * @throws RequestBuilderException
	 */
	public function getSharedWith(
		FederatedUser $federatedUser,
		int $nodeId,
		int $offset,
		int $limit,
		bool $getData = false
	): array {
		return $this->shareWrapperRequest->getSharedWith($federatedUser, $nodeId, $offset, $limit, $getData);
	}


	/**
	 * @param FederatedUser $federatedUser
	 * @param int $nodeId
	 * @param bool $reshares
	 * @param int $offset
	 * @param int $limit
	 * @param bool $getData
	 *
	 * @return ShareWrapper[]
	 * @throws RequestBuilderException
	 */
	public function getSharesBy(
		FederatedUser $federatedUser,
		int $nodeId,
		bool $reshares,
		int $offset,
		int $limit,
		bool $getData = false
	): array {
		return $this->shareWrapperRequest->getSharesBy(
			$federatedUser, $nodeId, $reshares, $offset, $limit, $getData
		);
	}


	/**
	 * @param FederatedUser $federatedUser
	 * @param int $nodeId
	 * @param bool $reshares
	 *
	 * @return ShareWrapper[]
	 * @throws RequestBuilderException
	 */
	public function getSharesInFolder(FederatedUser $federatedUser, int $nodeId, bool $reshares): array {
		return $this->shareWrapperRequest->getSharesInFolder($federatedUser, $nodeId, $reshares);
	}


	/**
	 * @param FederatedUser $federatedUser
	 * @param IShare $share
	 *
	 * @return ShareWrapper
	 * @throws NotFoundException
	 * @throws ShareWrapperNotFoundException
	 */
	public function getChild(IShare $share, FederatedUser $federatedUser): ShareWrapper {
		try {
			return $this->shareWrapperRequest->getChild($federatedUser, (int)$share->getId());
		} catch (ShareWrapperNotFoundException $e) {
		}

		return $this->createChild($share, $federatedUser);
	}


	/**
	 * @param FederatedUser $federatedUser
	 * @param IShare $share
	 *
	 * @return ShareWrapper
	 * @throws ShareWrapperNotFoundException
	 * @throws NotFoundException
	 */
	private function createChild(IShare $share, FederatedUser $federatedUser): ShareWrapper {
		$share->setSharedWith($federatedUser->getSingleId());
		$childId = $this->shareWrapperRequest->save($share, (int)$share->getId());

		return $this->getShareById($childId, $federatedUser);
	}

}

