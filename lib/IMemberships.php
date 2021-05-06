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


namespace OCA\Circles;


use OCA\Circles\Model\Member;
use OCA\Circles\Model\Membership;

/**
 * Interface IMemberships
 *
 * @package OCA\Circles
 */
interface IMemberships {

	/**
	 * @return string
	 */
	public function getSingleId(): string;

	/**
	 * @param Member[] $members
	 *
	 * @return $this
	 */
	public function setMembers(array $members): self;

	/**
	 * @return Member[]
	 */
	public function getMembers(): array;


	/**
	 * @param Member[] $members
	 *
	 * @return $this
	 */
	public function setInheritedMembers(array $members, bool $detailed): self;

	/**
	 * @return Member[]
	 */
	public function getInheritedMembers(): array;

	/**
	 * @param Membership[] $memberships
	 *
	 * @return $this
	 */
	public function setMemberships(array $memberships): self;

	/**
	 * @return Membership[]
	 */
	public function getMemberships(): array;

}
