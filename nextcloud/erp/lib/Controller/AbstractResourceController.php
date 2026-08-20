<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Bündelt das in Phase 2/3/4 wiederholte Rechte-Gate-Muster
 * (getEffectivePermission + atLeast + OCSForbiddenException) für die neuen
 * Phase-5-Controller. Ältere Controller (Contacts/Calendar/Project/...)
 * bleiben unverändert, um stabilen, bereits getesteten Code nicht ohne Not
 * anzufassen — neue Controller nutzen ab hier diese Basis.
 */
abstract class AbstractResourceController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected PermissionService $permissionService,
		protected IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	abstract protected function resource(): ResourceType;

	/** @throws OCSForbiddenException */
	protected function requireUser(): IUser {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('No active user session');
		}
		return $user;
	}

	/** @throws OCSForbiddenException */
	protected function requireLevel(PermissionLevel $required): IUser {
		$user = $this->requireUser();
		$level = $this->permissionService->getEffectivePermission($user, $this->resource());
		if (!$level->atLeast($required)) {
			throw new OCSForbiddenException("Requires at least '{$required->value}' on '{$this->resource()->value}'");
		}
		return $user;
	}
}
