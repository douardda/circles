<?php
/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
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

namespace OCA\Circles\Handlers;


use daita\MySmallPhpTools\Traits\TArrayTools;
use OC\URLGenerator;
use OCA\Circles\AppInfo\Application;
use OCA\Circles\Service\ConfigService;
use OCP\Http\WellKnown\IHandler;
use OCP\Http\WellKnown\IRequestContext;
use OCP\Http\WellKnown\IResponse;
use OCP\Http\WellKnown\JrdResponse;
use OCP\IURLGenerator;


/**
 * Class WebfingerHandler
 *
 * @package OCA\Circles\Handlers
 */
class WebfingerHandler implements IHandler {


	use TArrayTools;


	/** @var URLGenerator */
	private $urlGenerator;

	/** @var ConfigService */
	private $configService;


	/**
	 * WebfingerHandler constructor.
	 *
	 * @param IURLGenerator $urlGenerator
	 * @param ConfigService $configService
	 */
	public function __construct(IURLGenerator $urlGenerator, ConfigService $configService) {
		$this->urlGenerator = $urlGenerator;
		$this->configService = $configService;
	}


	/**
	 * @param string $service
	 * @param IRequestContext $context
	 * @param IResponse|null $response
	 *
	 * @return IResponse|null
	 */
	public function handle(string $service, IRequestContext $context, ?IResponse $response): ?IResponse {
		if ($service !== 'webfinger') {
			return $response;
		}

		$request = $context->getHttpRequest();
		$params = $request->getParams();
		unset($params['service'], $params['_route']);
		if (empty($params)) {
			parse_str(parse_url($request->getRequestUri(), PHP_URL_QUERY), $params);
		}

		$subject = $this->get('resource', $params);
		if ($subject !== Application::APP_SUBJECT) {
			return $response;
		}

//		if (!($response instanceof JrdResponse)) {
		$response = new JrdResponse($subject);
//		}

		$href = $this->configService->getRemotePath('circles.Navigation.navigate');

		return $response
			->addLink(
				Application::APP_REL, 'application/json', $href, [],
				[
					'app'     => Application::APP_ID,
					'name'    => Application::APP_NAME,
					'version' => $this->configService->getAppValue('installed_version'),
					'api'     => Application::APP_API
				]
			);
	}

}

